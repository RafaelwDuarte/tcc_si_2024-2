<?php
require 'vendor/autoload.php'; // Carregar o autoload do AWS SDK for PHP

use Aws\SecretsManager\SecretsManagerClient; 
use Aws\Exception\AwsException;

function getSecret() {
    $secretName = getenv('AWS_SECRET_ARN'); // Obtém o ARN do segredo da variável de ambiente
    $region = getenv('AWS_REGION'); // Obtém a região da variável de ambiente

    // Verifica se as variáveis de ambiente estão definidas
    if (!$secretName || !$region) {
        echo "Erro: As variáveis de ambiente AWS_SECRET_ARN ou AWS_REGION não estão definidas.";
        return null;
    }

    // Cria o cliente do Secrets Manager
    $client = new SecretsManagerClient([
        'version' => 'latest',
        'region' => $region
    ]);

    try {
        $result = $client->getSecretValue([
            'SecretId' => $secretName,
        ]);

        if (isset($result['SecretString'])) {
            $secret = $result['SecretString'];
        } else {
            $secret = base64_decode($result['SecretBinary']);
        }

        return json_decode($secret, true);

    } catch (AwsException $e) {
        // Exibir mensagem de erro em caso de falha
        echo $e->getMessage();
        return null;
    }
}

// Obter o segredo
$credentials = getSecret();

if ($credentials) {
    $servername = getenv('RDS_PROXY_HOST'); // Obtém o host do RDS Proxy da variável de ambiente
    $username = $credentials['username'];  // Pega o username do segredo
    $password = $credentials['password'];  // Pega o password do segredo
    $dbname = 'appref';  // Nome do banco de dados

    // Verifica se a variável de ambiente RDS_PROXY_HOST está definida
    if (!$servername) {
        echo "Erro: A variável de ambiente RDS_PROXY_HOST não está definida.";
        exit;
    }

    // Criar conexão
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Verificar a conexão
    if ($conn->connect_error) {
        echo '<div class="alert alert-danger" role="alert">
                Conexão falhou: ' . $conn->connect_error . '
              </div>';
    } else {
        echo '<div class="alert alert-success" role="alert">
                Conectado com sucesso ao MySQL via Proxy RDS!
              </div>';

        // Exibir os dados da tabela (assumindo que existe uma tabela 'users')
        $sql = "SELECT id, name, email FROM users";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            echo '<table class="table table-striped mt-3">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>';
            while ($row = $result->fetch_assoc()) {
                echo '<tr>
                        <td>' . htmlspecialchars($row["id"]) . '</td>
                        <td>' . htmlspecialchars($row["name"]) . '</td>
                        <td>' . htmlspecialchars($row["email"]) . '</td>
                      </tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="alert alert-info" role="alert">
                    Nenhum dado encontrado.
                  </div>';
        }
    }

    $conn->close();
} else {
    echo '<div class="alert alert-danger" role="alert">
            Não foi possível recuperar as credenciais do Secrets Manager.
          </div>';
}
?>
