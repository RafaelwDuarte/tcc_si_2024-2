<?php
require 'vendor/autoload.php';

use Aws\SecretsManager\SecretsManagerClient;
use Aws\Exception\AwsException;
use GuzzleHttp\Client;

session_start();

// Variáveis de ambiente para Cognito
$cognitoDomain = getenv('COGNITO_DOMAIN');
$clientId = getenv('COGNITO_CLIENT_ID');
$clientSecret = getenv('COGNITO_CLIENT_SECRET');
$redirectUri = getenv('COGNITO_REDIRECT_URI');
$msgError = "Ocorreu um erro de configuração. Por favor, contate o administrador do sistema.";

// Verifica se as variáveis de ambiente estão definidas
if (!$cognitoDomain || !$clientId || !$clientSecret || !$redirectUri) {
    error_log('Erro: Variáveis de ambiente para o Cognito não estão definidas corretamente.');
    echo $msgError;
    exit();
}

// Função para obter o segredo do AWS Secrets Manager
function getSecret() {
    global $msgError;
    $secretName = getenv('AWS_SECRET_ARN');
    $region = getenv('AWS_REGION');

    if (!$secretName || !$region) {
        error_log("Erro: As variáveis de ambiente AWS_SECRET_ARN ou AWS_REGION não estão definidas.");
        echo $msgError;
        return null;
    }

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
        error_log('Erro ao obter o segredo: ' . $e->getMessage());
        echo "Não foi possível recuperar as credenciais do Secrets Manager.";
        return null;
    }
}

// Verificar se o usuário já está autenticado
if (!isset($_SESSION['id_token'])) {
    // Iniciar o processo de autenticação com Cognito se não estiver logado
    if (!isset($_GET['code'])) {
        // Verifica se o $cognitoDomain já começa com 'http'
        if (strpos($cognitoDomain, 'http') !== 0) {
            $cognitoDomain = 'https://' . $cognitoDomain;
        }
        // Remove a barra final, se houver
        $cognitoDomain = rtrim($cognitoDomain, '/');

        // Redirecionar para o login do Cognito
        $loginUrl = "{$cognitoDomain}/oauth2/authorize?response_type=code&client_id={$clientId}&redirect_uri={$redirectUri}&scope=openid";
        header("Location: $loginUrl");
        exit();
    } else {
        // Trocar o código de autorização pelo token
        $code = $_GET['code'];

        // Verifica se o $cognitoDomain já começa com 'http'
        if (strpos($cognitoDomain, 'http') !== 0) {
            $cognitoDomain = 'https://' . $cognitoDomain;
        }
        // Remove a barra final, se houver
        $cognitoDomain = rtrim($cognitoDomain, '/');

        $tokenUrl = "{$cognitoDomain}/oauth2/token";
        $client = new Client();

        try {
            $response = $client->post($tokenUrl, [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => $redirectUri,
                    'code' => $code,
                ]
            ]);

            $body = json_decode($response->getBody(), true);

            if (isset($body['id_token'])) {
                $_SESSION['id_token'] = $body['id_token'];

                // Redirecionar de volta para a página principal
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit();
            } else {
                // Erro ao obter o id_token
                error_log('Erro: id_token não encontrado na resposta do Cognito.');
                echo 'Erro durante a autenticação. Por favor, tente novamente.';
                exit();
            }
        } catch (\Exception $e) {
            error_log('Erro durante a autenticação: ' . $e->getMessage());
            echo 'Erro durante a autenticação. Por favor, tente novamente.';
            exit();
        }
    }
}

// Usuário autenticado, continuar com o restante da aplicação
$credentials = getSecret();

if ($credentials) {
    $servername = getenv('RDS_PROXY_HOST');
    $username = $credentials['username'];
    $password = $credentials['password'];
    $dbname = 'appref';

    if (!$servername) {
        error_log("Erro: A variável de ambiente RDS_PROXY_HOST não está definida.");
        echo $msgError;
        exit();
    }

    // Criar conexão
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Início do layout HTML
    echo '<!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>App_Refact</title>
        <style>/* ... estilos de layout ... */</style>
    </head>
    <body>
    <div class="container">';

    // Verificar a conexão
    if ($conn->connect_error) {
        echo '<div class="alert alert-danger" role="alert">
                Conexão falhou: ' . htmlspecialchars($conn->connect_error) . '
              </div>';
    } else {
        echo '<div class="alert alert-success" role="alert">
                Conectado com sucesso ao MySQL via Proxy RDS!
              </div>';

        // Badge do SonarCloud
        echo '<div class="badge-container">
                <img src="https://sonarcloud.io/api/project_badges/quality_gate?project=RafaelwDuarte_tcc_si_2024-2" alt="Quality Gate Status" />
              </div>';

        // Botão para ver usuários
        echo '<form method="post">
                <button type="submit" name="ver_usuarios" class="btn">Ver Usuários</button>
              </form>';

        // Caixa de pesquisa por email
        echo '<form method="post">
                <input type="text" name="search_email" placeholder="Pesquisar por email" />
                <button type="submit" class="btn">Buscar</button>
              </form>';

        // Exibir os dados da tabela apenas se o botão for clicado
        if (isset($_POST['ver_usuarios'])) {
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

        // Exibir os resultados da pesquisa de email
        if (isset($_POST['search_email']) && !empty($_POST['search_email'])) {
            $email = $conn->real_escape_string($_POST['search_email']);
            $sql = "SELECT id, name, email FROM users WHERE email = '$email'";
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
                        Nenhum dado encontrado para o email: ' . htmlspecialchars($email) . '
                      </div>';
            }
        }
    }

    echo '</div></body></html>';

    $conn->close();
} else {
    echo '<div class="alert alert-danger" role="alert">
            Não foi possível recuperar as credenciais do Secrets Manager.
          </div>';
}
?>
