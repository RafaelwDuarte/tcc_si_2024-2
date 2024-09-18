<?php
session_start();

// Removendo a verificação de autenticação para permitir o acesso direto à página

// Parâmetros de conexão ao banco de dados
$servername = getenv('RDS_PROXY_HOST');  // Definido nas variáveis de ambiente do ECS
$username = getenv('MYSQL_USER');
$password = getenv('MYSQL_PASSWORD');
$dbname = getenv('MYSQL_DATABASE');

// Crie a conexão com o MySQL
$conn = new mysqli($servername, $username, $password, $dbname);

// Verifique se a conexão foi bem-sucedida
if ($conn->connect_error) {
    die('<div class="alert alert-danger" role="alert">
            Conexão falhou: ' . $conn->connect_error . '
         </div>');
}

// Variável de controle para exibir os resultados
$showUsers = isset($_POST['show_users']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refact App PHP - MySQL Data Viewer</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4">MySQL Data Viewer</h1>

        <div class="card">
            <div class="card-header">
                <h2>Status da Conexão</h2>
            </div>
            <div class="card-body">
                <div class="alert alert-success" role="alert">
                    Conectado com sucesso ao MySQL via Proxy RDS!
                </div>
            </div>
        </div>

        <div class="mt-4">
            <form method="POST">
                <button type="submit" name="show_users" class="btn btn-primary btn-lg btn-block">Mostrar Usuários</button>
            </form>
        </div>

        <?php if ($showUsers): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h2>Lista de Usuários</h2>
                </div>
                <div class="card-body">
                    <?php
                    // SQL para obter os usuários
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
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Feche a conexão com o banco de dados
$conn->close();
?>
