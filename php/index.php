<?php

require 'vendor/autoload.php';

use Aws\SecretsManager\SecretsManagerClient; 
use Aws\Exception\AwsException;

function getSecret() {
    $secretName = 'arn:aws:secretsmanager:us-east-1:999859181284:secret:acesso_rds-bcd8J0';
    $region = 'us-east-1';

    // Crie o cliente do Secrets Manager
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
        // Output error message if fails
        echo $e->getMessage();
        return null;
    }
}

// Obter o segredo
$credentials = getSecret();

if ($credentials) {
    $servername = 'app-refac.proxy-cze86866e6m1.us-east-1.rds.amazonaws.com';
    $username = $credentials['username'];  // Pega o username do segredo
    $password = $credentials['password'];  // Pega o password do segredo
    $dbname = 'app_database';

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

        if ($result->num_rows > 0) {
            echo '<table class="table table-striped mt-3">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>';
            while ($row = $result->fetch_assoc()) {
                echo '<tr>
                        <td>' . $row["id"] . '</td>
                        <td>' . $row["name"] . '</td>
                        <td>' . $row["email"] . '</td>
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