<?php
require 'vendor/autoload.php'; // Carregar o autoload do AWS SDK for PHP
use Aws\SecretsManager\SecretsManagerClient;

session_start();

// Variáveis de ambiente para Cognito
$cognitoDomain = getenv('COGNITO_DOMAIN');
$clientId = getenv('COGNITO_CLIENT_ID');
$redirectUri = getenv('COGNITO_REDIRECT_URI');
$authorizationUrl = "$cognitoDomain/oauth2/authorize?response_type=code&client_id=$clientId&redirect_uri=$redirectUri&scope=openid email";

// Verificar se o usuário já está autenticado
if (!isset($_SESSION['user_logged_in'])) {
    // Se não estiver autenticado, redirecionar para o Cognito
    header("Location: $authorizationUrl");
    exit();
}

// Função para obter o segredo do AWS Secrets Manager
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

// Função para trocar o código de autorização por um token
function getTokens($code) {
    global $clientId, $clientSecret, $redirectUri, $cognitoDomain;

    $tokenUrl = "$cognitoDomain/oauth2/token";
    $postData = [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'code' => $code,
        'redirect_uri' => $redirectUri,
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode("$clientId:$clientSecret"),
        'Content-Type: application/x-www-form-urlencoded',
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// Se o código de autorização foi retornado, troque-o por tokens
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $tokenData = getTokens($code);

    if (isset($tokenData['id_token'])) {
        // Salvar o token na sessão
        $_SESSION['id_token'] = $tokenData['id_token'];
        $_SESSION['user_logged_in'] = true;

        // Redirecionar de volta para a página inicial
        header("Location: /");
        exit();
    } else {
        echo "Erro ao obter o token.";
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

    // Início do layout HTML
    echo '<!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <title>App_Refact</title>
        <style>
            body {
                background-color: #f0f0f0; /* Cor de fundo */
                font-family: Arial, sans-serif;
            }
            .container {
                width: 80%;
                margin: 0 auto;
                background-color: #fff; /* Fundo branco para o conteúdo */
                padding: 20px;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
                margin-top: 50px;
            }
            .btn {
                display: inline-block;
                background-color: #007bff; /* Azul */
                color: #fff;
                padding: 10px 20px;
                text-decoration: none;
                border-radius: 4px;
                margin-top: 20px;
                border: none;
                cursor: pointer;
            }
            .btn:hover {
                background-color: #0056b3; /* Azul mais escuro */
            }
            /* Estilos para a tabela */
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            table, th, td {
                border: 1px solid #ddd;
            }
            th, td {
                padding: 8px;
                text-align: left;
            }
            th {
                background-color: #f2f2f2;
            }
            .alert {
                padding: 15px;
                margin-top: 20px;
                border: 1px solid transparent;
                border-radius: 4px;
            }
            .alert-success {
                color: #155724;
                background-color: #d4edda;
                border-color: #c3e6cb;
            }
            .alert-danger {
                color: #721c24;
                background-color: #f8d7da;
                border-color: #f5c6cb;
            }
            .alert-info {
                color: #0c5460;
                background-color: #d1ecf1;
                border-color: #bee5eb;
            }
            /* Estilos para o badge */
            .badge-container {
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
    <div class="container">';

    // Verificar a conexão
    if ($conn->connect_error) {
        echo '<div class="alert alert-danger" role="alert">
                Conexão falhou: ' . $conn->connect_error . '
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
