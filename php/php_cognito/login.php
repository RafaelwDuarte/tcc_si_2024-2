<?php
require 'vendor/autoload.php'; // Carrega o autoload do AWS SDK for PHP

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\Exception\AwsException;

session_start();

// Configurações do AWS Cognito
$clientId = 'SEU_APP_CLIENT_ID';
$clientSecret = 'SEU_APP_CLIENT_SECRET'; // Se aplicável
$region = 'SUA_REGIÃO_AWS';

// Se o formulário for enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $client = new CognitoIdentityProviderClient([
        'version' => 'latest',
        'region'  => $region,
    ]);

    try {
        $params = [
            'ClientId' => $clientId,
            'AuthFlow' => 'USER_PASSWORD_AUTH',
            'AuthParameters' => [
                'USERNAME' => $username,
                'PASSWORD' => $password,
            ],
        ];

        // Se estiver usando Client Secret
        // $params['ClientSecret'] = $clientSecret;

        $result = $client->initiateAuth($params);

        // Armazena o token de acesso na sessão
        $_SESSION['access_token'] = $result['AuthenticationResult']['AccessToken'];
        $_SESSION['username'] = $username;

        // Redireciona para index.php
        header('Location: index.php');
        exit;

    } catch (AwsException $e) {
        // Autenticação falhou
        $error = $e->getAwsErrorMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login - Minha Aplicação</title>
    <style>
        body {
            background-color: #f0f0f0; /* Cor de fundo */
            font-family: Arial, sans-serif;
        }
        .container {
            width: 30%;
            margin: 0 auto;
            background-color: #fff; /* Fundo branco para o conteúdo */
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-top: 100px;
            text-align: center;
        }
        input[type="text"], input[type="password"] {
            width: 80%;
            padding: 10px;
            margin-top: 10px;
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
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            padding: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Login</h2>
    <?php if (isset($error)): ?>
        <div class="alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="post">
        <input type="text" name="username" placeholder="Usuário" required><br>
        <input type="password" name="password" placeholder="Senha" required><br>
        <button type="submit" class="btn">Entrar</button>
    </form>
</div>
</body>
</html>
