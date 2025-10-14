<?php
// API de áreas de risco
require_once '../config/cors.php'; // Inclui as configurações CORS
require_once '../config/database.php'; // Inclui o script de conexão e a classe JsonResponse

$database = new Database();
$conn = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Lógica para buscar áreas de risco
    // Exemplo:
    // $sql = "SELECT * FROM areas_risco";
    // try {
    //     $stmt = $conn->prepare($sql);
    //     $stmt->execute();
    //     $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //     JsonResponse::success($areas);
    // } catch (PDOException $e) {
    //     JsonResponse::error("Erro ao buscar áreas de risco: " . $e->getMessage(), 500);
    // }
    JsonResponse::success([], "API de áreas de risco (GET) - Em desenvolvimento.");

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lógica para criar uma nova área de risco
    // Exemplo:
    // $json_data = file_get_contents("php://input");
    // $data = json_decode($json_data, true);
    // if (empty($data['nome']) || empty($data['latitude']) || empty($data['longitude'])) {
    //     JsonResponse::error("Nome, latitude e longitude são obrigatórios.", 400);
    // }
    // $nome = $data['nome'];
    // $latitude = $data['latitude'];
    // $longitude = $data['longitude'];
    // $sql = "INSERT INTO areas_risco (nome, latitude, longitude) VALUES (:nome, :latitude, :longitude)";
    // try {
    //     $stmt = $conn->prepare($sql);
    //     $stmt->bindParam(':nome', $nome);
    //     $stmt->bindParam(':latitude', $latitude);
    //     $stmt->bindParam(':longitude', $longitude);
    //     $stmt->execute();
    //     JsonResponse::success([], "Área de risco criada com sucesso!", 201);
    // } catch (PDOException $e) {
    //     JsonResponse::error("Erro ao criar área de risco: " . $e->getMessage(), 500);
    // }
    JsonResponse::success([], "API de áreas de risco (POST) - Em desenvolvimento.", 201);

} else {
    JsonResponse::error("Método de requisição não permitido.", 405);
}

$database->closeConnection();
?>
