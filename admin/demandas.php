<?php
// Gerenciar demandas (admin)
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
    <title>Gerenciar Demandas - Juntas Goiás</title>
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
        <h1 class="mb-4">Gerenciar Demandas</h1>
        <p>Conteúdo para gerenciar demandas.</p>
        <!-- Aqui você pode adicionar tabelas, formulários para editar/excluir demandas, etc. -->
        <a href="dashboard.html" class="btn btn-primary">Voltar ao Dashboard</a>
    </div>
</body>
</html>
