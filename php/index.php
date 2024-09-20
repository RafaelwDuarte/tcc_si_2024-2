<?php
// Configurações iniciais: carregar variáveis de ambiente para Cognito e banco
$region = getenv('AWS_REGION');
$cognitoClientId = getenv('COGNITO_CLIENT_ID');
$cognitoUserPoolId = getenv('COGNITO_USER_POOL_ID');
$cognitoRegion = getenv('COGNITO_REGION');
$secretArn = getenv('AWS_SECRET_ARN'); // Para banco de dados
$rdsProxyEndpoint = getenv('RDS_PROXY_ENDPOINT');

// Autenticação com Cognito
require 'vendor/autoload.php'; // Carregar dependências via Composer (incluindo AWS SDK)

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\SecretsManager\SecretsManagerClient;
use Aws\Exception\AwsException;

// Iniciar cliente do Cognito com as variáveis de ambiente
$cognitoClient = new CognitoIdentityProviderClient([
    'region' => $cognitoRegion,
    'version' => '2016-04-18',
]);

// Verificar login (simplificado)
session_start();
if (!isset($_SESSION['user_logged_in'])) {
    // Exibir tela de login (HTML básico)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
        // Autenticação com AWS Cognito
        try {
            $result = $cognitoClient->adminInitiateAuth([
                'AuthFlow' => 'ADMIN_NO_SRP_AUTH',
                'ClientId' => $cognitoClientId,
                'UserPoolId' => $cognitoUserPoolId,
                'AuthParameters' => [
                    'USERNAME' => $_POST['username'],
                    'PASSWORD' => $_POST['password'],
                ],
            ]);
            $_SESSION['user_logged_in'] = true;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (AwsException $e) {
            echo "Erro de autenticação: " . $e->getMessage();
        }
    } else {
        // Exibir formulário de login
        echo '<form method="POST">';
        echo 'Usuário: <input type="text" name="username" required><br>';
        echo 'Senha: <input type="password" name="password" required><br>';
        echo '<input type="submit" value="Login">';
        echo '</form>';
        exit();
    }
}

// Após login bem-sucedido, exibir interface
echo '<h1>Bem-vindo!</h1>';
echo '<form method="GET" action="' . $_SERVER['PHP_SELF'] . '">';
echo '<button type="submit" name="action" value="list_users">Listar Usuários</button>';
echo '<button type="submit" name="action" value="search_email">Pesquisar por E-mail</button>';
echo '</form>';

// Conectar ao MySQL via RDS Proxy usando Secrets Manager
$secretsClient = new SecretsManagerClient([
    'region' => $region,
    'version' => '2017-10-17',
]);

try {
    // Recuperar segredos do RDS Proxy
    $secret = $secretsClient->getSecretValue([
        'SecretId' => $secretArn, // Usar o ARN para recuperar o segredo
    ]);
    $secretData = json_decode($secret['SecretString'], true);

    // Conectar ao banco de dados com as credenciais recuperadas
    $pdo = new PDO(
        "mysql:host={$rdsProxyEndpoint};dbname=your_database_name;charset=utf8",
        $secretData['username'],
        $secretData['password']
    );
    echo '<p>Conectado ao banco de dados RDS.</p>';

    // Operações com o banco de dados
    if (isset($_GET['action'])) {
        if ($_GET['action'] == 'list_users') {
            // Listar usuários
            $stmt = $pdo->query("SELECT name, email FROM users");
            echo '<table border="1">';
            echo '<tr><th>Nome</th><th>Email</th></tr>';
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo '<tr><td>' . htmlspecialchars($row['name']) . '</td><td>' . htmlspecialchars($row['email']) . '</td></tr>';
            }
            echo '</table>';
        } elseif ($_GET['action'] == 'search_email' && isset($_GET['email'])) {
            // Pesquisar por email
            $stmt = $pdo->prepare("SELECT name, email FROM users WHERE email = :email");
            $stmt->execute(['email' => $_GET['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                echo '<p>Usuário encontrado: ' . htmlspecialchars($user['name']) . ' (' . htmlspecialchars($user['email']) . ')</p>';
            } else {
                echo '<p>Usuário não encontrado.</p>';
            }
        }
    }
} catch (AwsException $e) {
    echo '<p>Erro ao conectar ao banco de dados RDS: ' . $e->getMessage() . '</p>';
}

// Badge do SonarCloud
echo '<div class="badge-container" style="position:fixed;bottom:0;right:0;">
        <img src="https://sonarcloud.io/api/project_badges/quality_gate?project=RafaelwDuarte_tcc_si_2024-2" alt="Quality Gate Status" />
      </div>';

?>
