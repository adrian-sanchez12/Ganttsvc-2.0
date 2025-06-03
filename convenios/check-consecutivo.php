<?php
function handleCORS() {
    header('Access-Control-Allow-Origin: *'); 
    header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');
    header('Access-Control-Max-Age: 1728000'); // Cache de 1 día

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Content-Length: 0');
        header('Content-Type: text/plain');
        exit();
    }
}

handleCORS();

include_once("../../db.php");

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === "GET") {
    $consecutivo = $_GET['consecutivo'] ?? null;

    if (!$consecutivo) {
        echo json_encode(["error" => "Falta el número de consecutivo"]);
        http_response_code(400);
        exit;
    }

    $stmt = $conn->prepare("SELECT id FROM convenios WHERE consecutivo_numerico = ? LIMIT 1");
    $stmt->bind_param("s", $consecutivo);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(["exists" => true]);
    } else {
        echo json_encode(["exists" => false]);
    }

    exit;
}

http_response_code(405); // Método no permitido
echo json_encode(["error" => "Método no permitido"]);
?>
