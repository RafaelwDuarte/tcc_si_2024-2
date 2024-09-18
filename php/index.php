<?php
session_start();

// Check if the user is authenticated via ALB
if (!isset($_SERVER['HTTP_X_AMZN_OIDC_DATA'])) {
    // User is not authenticated, redirect to an error page or show an error message
    echo '<div class="alert alert-danger" role="alert">
            Acesso negado. Usuário não autenticado.
          </div>';
    exit();
}

// Decode the JWT token from the ALB header
$idToken = $_SERVER['HTTP_X_AMZN_OIDC_DATA'];
$tokenPayload = explode('.', $idToken)[1];
$tokenPayload = json_decode(base64_decode(str_replace('_', '/', str_replace('-','+', $tokenPayload))), true);

// Check if the user belongs to the required Cognito group
if (!isset($tokenPayload['cognito:groups']) || !in_array('acesso_nivel1', $tokenPayload['cognito:groups'])) {
    echo '<div class="alert alert-danger" role="alert">
            Acesso negado. Você não pertence ao grupo "acesso_nivel1".
          </div>';
    exit();
}

// User is authenticated and belongs to the required group
// Proceed with database connection and data retrieval

// Database connection parameters
$servername = getenv('RDS_PROXY_HOST');
$username = getenv('MYSQL_USER');
$password = getenv('MYSQL_PASSWORD');
$dbname = getenv('MYSQL_DATABASE');

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die('<div class="alert alert-danger" role="alert">
            Conexão falhou: ' . $conn->connect_error . '
         </div>');
}

// Variable to control the display of results
$showUsers = isset($_POST['show_users']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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
                    // SQL query to fetch data
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
$conn->close();
?>
