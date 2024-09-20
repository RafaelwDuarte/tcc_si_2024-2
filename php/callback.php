<?php

include 'vendor/autoload.php'; // Carregar o autoload do AWS SDK for PHP
require 'autoload.php'

session_start();

// Variáveis de ambiente para Cognito
$cognitoDomain = getenv('COGNITO_DOMAIN');
$clientId = getenv('COGNITO_CLIENT_ID');
$clientSecret = getenv('COGNITO_CLIENT_SECRET');
$redirectUri = getenv('COGNITO_REDIRECT_URI');

// Verifica se o código foi retornado como parte da URL de redirecionamento
if (isset($_GET['code'])) {
    $code = $_GET['code'];

    // Trocar o código de autorização por um token
    $tokenUrl = "$cognitoDomain/oauth2/token";
    $postData = [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'code' => $code,
        'redirect_uri' => $redirectUri,
    ];

    // Enviar a requisição para trocar o código pelo token
    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode("$clientId:$clientSecret"),
        'Content-Type: application/x-www-form-urlencoded',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Obtém o código de resposta HTTP
    $curlError = curl_error($ch); // Verifica se houve erros no cURL
    curl_close($ch);

    $tokenData = json_decode($response, true);

    // Se o token for recebido com sucesso, salvar na sessão
    if ($httpCode == 200 && isset($tokenData['id_token'])) {
        $_SESSION['id_token'] = $tokenData['id_token'];
        $_SESSION['user_logged_in'] = true;

        // Redirecionar para a página inicial ou outra página protegida
        header("Location: /");
        exit();
    } else {
        // Exibir informações detalhadas de erro
        echo "Erro ao obter o token.<br>";
        echo "Código HTTP: " . $httpCode . "<br>";

        if ($curlError) {
            echo "Erro cURL: " . $curlError . "<br>";
        }

        // Exibir a resposta completa do Cognito para debug
        echo "Resposta completa do servidor: <br>";
        echo "<pre>";
        print_r($tokenData);
        echo "</pre>";
    }
} else {
    echo "Código de autorização não encontrado.";
}
?>
