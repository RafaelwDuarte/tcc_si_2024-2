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
        <!-- Importando o Bootstrap CSS -->
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
        <style>
            body {
                background-color: #f8f9fa;
            }
            .container {
                margin-top: 50px;
            }
            .badge-container {
                text-align: center;
                margin-bottom: 20px;
            }
            .badge-container img {
                max-width: 100%;
                height: auto;
            }
            .btn-custom {
                margin-right: 10px;
            }
            .table-responsive {
                margin-top: 20px;
            }
        </style>
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

        // Formulários
        echo '<form method="post" class="form-inline">
                <button type="submit" name="ver_usuarios" class="btn btn-primary btn-custom">Ver Usuários</button>
                <input type="text" name="search_email" class="form-control mb-2 mr-sm-2" placeholder="Pesquisar por email" />
                <button type="submit" class="btn btn-success mb-2">Buscar</button>
              </form>';

        // Exibir os dados da tabela apenas se o botão for clicado
        if (isset($_POST['ver_usuarios'])) {
            $sql = "SELECT id, name, email FROM users";
            $result = $conn->query($sql);

            if ($result && $result->num_rows > 0) {
                echo '<div class="table-responsive">
                        <table class="table table-striped mt-3">
                            <thead class="thead-dark">
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
                echo '</tbody></table></div>';
            } else {
                echo '<div class="alert alert-info" role="alert">
                        Nenhum dado encontrado.
                      </div>';
            }
        }

        // Exibir os resultados da pesquisa de email
        if (isset($_POST['search_email']) && !empty($_POST['search_email'])) {
            $email = $_POST['search_email'];

            // Preparar declaração SQL para evitar SQL Injection
            $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                echo '<div class="table-responsive">
                        <table class="table table-striped mt-3">
                            <thead class="thead-dark">
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
                echo '</tbody></table></div>';
            } else {
                echo '<div class="alert alert-info" role="alert">
                        Nenhum dado encontrado para o email: ' . htmlspecialchars($email) . '
                      </div>';
            }
            $stmt->close();
        }
    }

    echo '</div>
    <!-- Importando o Bootstrap JS e dependências -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    </body>
    </html>';

    $conn->close();
} else {
    echo '<div class="alert alert-danger" role="alert">
            Não foi possível recuperar as credenciais do Secrets Manager.
          </div>';
}
?>
