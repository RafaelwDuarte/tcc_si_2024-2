<?php
require 'vendor/autoload.php'; // Carregar o autoload do AWS SDK for PHP

use Aws\SecretsManager\SecretsManagerClient; 
use Aws\Exception\AwsException;

// Função para obter o segredo do AWS Secrets Manager
function getSecret() {
    $secretName = getenv('SECRET_ARN');
    $region = getenv('AWS_REGION');

    // Criar o cliente do Secrets Manager
    $client = new SecretsManagerClient([
        'version' => 'latest',
        'region' => $region,
    ]);

    try {
        $result = $client->getSecretValue([
            'SecretId' => $secretName,
        ]);

        return json_decode($result['SecretString'], true);

    } catch (AwsException $e) {
        echo "Erro ao obter o segredo: ", $e->getMessage();
    }
}

// Obter as credenciais do Secret
$secret = getSecret();
$servername = getenv('RDS_PROXY_HOST'); 
$username = $secret['username'];
$password = $secret['password'];
$dbname = "appref";

// Criar conexão
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexão
if ($conn->connect_error) {
    echo '<div class="alert alert-danger" role="alert">
            Conexão falhou: ' . $conn->connect_error . '
          </div>';
} else {
    echo '<div class="alert alert-success" role="alert">
            Conectado com sucesso ao MySQL!
          </div>';

    // Exibir dados da tabela
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
        while($row = $result->fetch_assoc()) {
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
?>