<?php
// Monitorar alertas (admin)
// Exemplo:
// session_start();
// if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
//     header('Location: login.php');
//     exit();
// }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitorar Alertas - Juntas Goiás</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            padding-top: 20px;
        }
        .container {
            max-width: 960px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Monitorar Alertas</h1>
        <p>Conteúdo para monitorar alertas.</p>
        <!-- Aqui você pode adicionar tabelas, filtros, etc. para alertas -->
        <a href="dashboard.html" class="btn btn-primary">Voltar ao Dashboard</a>
    </div>
</body>
</html>
