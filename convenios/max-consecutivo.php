<?php

include_once("../../db.php");

function handleCORS() {
    header('Access-Control-Allow-Origin: http://localhost:3000');
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

header('Content-Type: application/json'); 

try {
    $result = $conn->query("SELECT MAX(consecutivo_numerico) AS maxConsecutivo FROM convenios");

    if ($result) {
        $maxConsecutivo = $result->fetch_assoc()['maxConsecutivo'] ?? 0;
        echo json_encode(['maxConsecutivo' => $maxConsecutivo]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Error al obtener el máximo consecutivo"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

?>
