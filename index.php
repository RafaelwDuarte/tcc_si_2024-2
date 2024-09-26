<?php
require 'vendor/autoload.php';

use Aws\SecretsManager\SecretsManagerClient;
use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\Exception\AwsException;
use GuzzleHttp\Client;

session_start();

// Variáveis de ambiente para Cognito
$cognitoDomain = getenv('COGNITO_DOMAIN');
$clientId = getenv('COGNITO_CLIENT_ID');
$clientSecret = getenv('COGNITO_CLIENT_SECRET');
$redirectUri = getenv('COGNITO_REDIRECT_URI');
$userPoolId = getenv('COGNITO_USER_POOL_ID'); // Adicione esta variável de ambiente
$region = getenv('AWS_REGION'); // Certifique-se de que a região está definida

$msgError = "Ocorreu um erro de configuração. Por favor, contate o administrador do sistema.";

// Verifica se as variáveis de ambiente estão definidas
if (!$cognitoDomain || !$clientId || !$clientSecret || !$redirectUri || !$userPoolId || !$region) {
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

// Função para encerrar a sessão e redirecionar para o logout do Cognito
function logout() {
    global $cognitoDomain, $clientId, $redirectUri;
    session_unset();
    session_destroy();

    $logoutUrl = "{$cognitoDomain}/logout?client_id={$clientId}&logout_uri={$redirectUri}";
    header("Location: $logoutUrl");
    exit();
}

// Verificar se o botão de logout foi clicado
if (isset($_POST['logout'])) {
    logout();
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

                // Extrair o nome de usuário do id_token
                $parts = explode('.', $_SESSION['id_token']);
                $payload = json_decode(base64_decode($parts[1]), true);
                $_SESSION['username'] = $payload['cognito:username'];

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

    // Criar conexão com o banco de dados
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Criar cliente do Cognito para administrar usuários
    $cognitoClient = new CognitoIdentityProviderClient([
        'version' => 'latest',
        'region' => $region,
    ]);

    // Processamento do formulário de edição de usuário
    if (isset($_POST['editar_usuario'])) {
        $userToEdit = $_POST['edit_username'];
        $newEmail = $_POST['edit_email'];
        $newPassword = $_POST['edit_password'];
        $disableUser = isset($_POST['disable_user']) ? true : false;

        try {
            // Atualizar atributos do usuário
            if (!empty($newEmail)) {
                $cognitoClient->adminUpdateUserAttributes([
                    'UserPoolId' => $userPoolId,
                    'Username' => $userToEdit,
                    'UserAttributes' => [
                        [
                            'Name' => 'email',
                            'Value' => $newEmail,
                        ],
                    ],
                ]);
            }

            // Atualizar senha do usuário
            if (!empty($newPassword)) {
                $cognitoClient->adminSetUserPassword([
                    'UserPoolId' => $userPoolId,
                    'Username' => $userToEdit,
                    'Password' => $newPassword,
                    'Permanent' => true,
                ]);
            }

            // Desabilitar ou habilitar usuário
            if ($disableUser) {
                $cognitoClient->adminDisableUser([
                    'UserPoolId' => $userPoolId,
                    'Username' => $userToEdit,
                ]);
            } else {
                $cognitoClient->adminEnableUser([
                    'UserPoolId' => $userPoolId,
                    'Username' => $userToEdit,
                ]);
            }

            echo '<div class="alert alert-success" role="alert">
                    Usuário atualizado com sucesso!
                  </div>';
        } catch (AwsException $e) {
            echo '<div class="alert alert-danger" role="alert">
                    Erro ao atualizar o usuário: ' . htmlspecialchars($e->getAwsErrorMessage()) . '
                  </div>';
        }
    }

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
                position: fixed;
                bottom: 10px;
                right: 10px;
            }
            .badge-container img {
                max-width: 150px;
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

        // Botões de ação
        echo '<form method="post" class="form-inline">
                <button type="submit" name="ver_usuarios" class="btn btn-primary btn-custom">Ver Usuários</button>
                <input type="text" name="search_email" class="form-control mb-2 mr-sm-2" placeholder="Pesquisar por email" />
                <button type="submit" class="btn btn-success mb-2">Buscar</button>
                <button type="submit" name="gerenciar_usuarios" class="btn btn-warning btn-custom">Gerenciar Usuários</button>
                <button type="submit" name="logout" class="btn btn-danger mb-2 ml-auto">Logout</button>
              </form>';

        // Gerenciar Usuários
        if (isset($_POST['gerenciar_usuarios'])) {
            try {
                $users = [];
                $result = $cognitoClient->listUsers([
                    'UserPoolId' => $userPoolId,
                ]);

                foreach ($result['Users'] as $user) {
                    $attributes = [];
                    foreach ($user['Attributes'] as $attribute) {
                        $attributes[$attribute['Name']] = $attribute['Value'];
                    }
                    $users[] = [
                        'Username' => $user['Username'],
                        'Email' => isset($attributes['email']) ? $attributes['email'] : '',
                        'Enabled' => $user['Enabled'],
                    ];
                }

                // Exibir tabela de usuários
                echo '<h3>Gerenciar Usuários</h3>
                      <div class="table-responsive">
                        <table class="table table-striped mt-3">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Nome de Usuário</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>';
                foreach ($users as $user) {
                    echo '<tr>
                            <td>' . htmlspecialchars($user['Username']) . '</td>
                            <td>' . htmlspecialchars($user['Email']) . '</td>
                            <td>' . ($user['Enabled'] ? 'Ativo' : 'Desabilitado') . '</td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="user_to_edit" value="' . htmlspecialchars($user['Username']) . '"/>
                                    <button type="submit" name="editar_usuario_form" class="btn btn-sm btn-primary">Editar</button>
                                </form>
                            </td>
                          </tr>';
                }
                echo '</tbody></table></div>';

            } catch (AwsException $e) {
                echo '<div class="alert alert-danger" role="alert">
                        Erro ao listar os usuários: ' . htmlspecialchars($e->getAwsErrorMessage()) . '
                      </div>';
            }
        }

        // Exibir o formulário de edição de usuário
        if (isset($_POST['editar_usuario_form'])) {
            $userToEdit = $_POST['user_to_edit'];

            try {
                $result = $cognitoClient->adminGetUser([
                    'UserPoolId' => $userPoolId,
                    'Username' => $userToEdit,
                ]);

                $attributes = [];
                foreach ($result['UserAttributes'] as $attribute) {
                    $attributes[$attribute['Name']] = $attribute['Value'];
                }

                $email = isset($attributes['email']) ? $attributes['email'] : '';
                $isEnabled = $result['Enabled'];

                echo '<h3>Editar Usuário: ' . htmlspecialchars($userToEdit) . '</h3>
                      <form method="post">
                        <input type="hidden" name="edit_username" value="' . htmlspecialchars($userToEdit) . '"/>
                        <div class="form-group">
                            <label for="edit_email">Email:</label>
                            <input type="email" name="edit_email" class="form-control" value="' . htmlspecialchars($email) . '" />
                        </div>
                        <div class="form-group">
                            <label for="edit_password">Nova Senha (deixe em branco para manter a atual):</label>
                            <input type="password" name="edit_password" class="form-control" />
                        </div>
                        <div class="form-group form-check">
                            <input type="checkbox" name="disable_user" class="form-check-input" ' . (!$isEnabled ? 'checked' : '') . '>
                            <label class="form-check-label" for="disable_user">Desabilitar Usuário</label>
                        </div>
                        <button type="submit" name="editar_usuario" class="btn btn-primary">Salvar Alterações</button>
                      </form>';

            } catch (AwsException $e) {
                echo '<div class="alert alert-danger" role="alert">
                        Erro ao obter informações do usuário: ' . htmlspecialchars($e->getAwsErrorMessage()) . '
                      </div>';
            }
        }

        // Resto do seu código existente para inserir e visualizar usuários no banco de dados
        // ...

        // Formulário para inserir usuário
        echo '<form method="post" class="form-inline">
                <input type="text" name="insert_name" class="form-control mb-2 mr-sm-2" placeholder="Nome" required />
                <input type="email" name="insert_email" class="form-control mb-2 mr-sm-2" placeholder="Email" required />
                <button type="submit" name="inserir_usuario" class="btn btn-secondary mb-2">Inserir Usuário</button>
              </form>';

        // Inserir usuário na tabela
        if (isset($_POST['inserir_usuario'])) {
            $name = $_POST['insert_name'];
            $email = $_POST['insert_email'];

            // Validar os dados de entrada
            if (!empty($name) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // Preparar declaração SQL para evitar SQL Injection
                $stmt = $conn->prepare("INSERT INTO users (name, email) VALUES (?, ?)");
                $stmt->bind_param("ss", $name, $email);

                if ($stmt->execute()) {
                    echo '<div class="alert alert-success" role="alert">
                            Usuário inserido com sucesso!
                          </div>';
                } else {
                    echo '<div class="alert alert-danger" role="alert">
                            Erro ao inserir o usuário: ' . htmlspecialchars($stmt->error) . '
                          </div>';
                }
                $stmt->close();
            } else {
                echo '<div class="alert alert-warning" role="alert">
                        Por favor, insira um nome válido e um email válido.
                      </div>';
            }
        }

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
    <!-- Badge do SonarCloud -->
    <div class="badge-container">
        <img src="https://sonarcloud.io/api/project_badges/quality_gate?project=RafaelwDuarte_tcc_si_2024-2" alt="Quality Gate Status" />
    </div>
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
