<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once(__DIR__ . "/../../db.php");

function handleCORS() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');
    header('Access-Control-Max-Age: 1728000');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Content-Length: 0');
        header('Content-Type: text/plain');
        exit();
    }
}

handleCORS();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === "GET") {
    $registro_proceso_id = $_GET["registro_proceso_id"] ?? null;

    if (!$registro_proceso_id) {
        http_response_code(400);
        echo json_encode(["error" => "registro_proceso_id es requerido"]);
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT id, evento, fecha FROM historial_registro_procesos WHERE registro_proceso_id = ? ORDER BY fecha ASC");
        $stmt->bind_param("i", $registro_proceso_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $eventos = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode($eventos);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al obtener historial: " . $e->getMessage()]);
    }
    exit;
}

if ($method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    $registro_proceso_id = $data["registro_proceso_id"] ?? null;
    $evento = $data["evento"] ?? null;
    $fecha = $data["fecha"] ?? null;

    if (!$registro_proceso_id || !$evento || !$fecha) {
        http_response_code(400);
        echo json_encode(["error" => "Faltan datos obligatorios"]);
        exit;
    }

    try {
        $stmt = $conn->prepare("INSERT INTO historial_registro_procesos (registro_proceso_id, evento, fecha) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $registro_proceso_id, $evento, $fecha);
        $stmt->execute();
        echo json_encode(["message" => "Evento agregado correctamente"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al insertar evento: " . $e->getMessage()]);
    }
    exit;
}

if ($method === "DELETE") {
    $id = $_GET["id"] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "ID es requerido"]);
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT id FROM historial_registro_procesos WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(["error" => "Evento no encontrado"]);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM historial_registro_procesos WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        echo json_encode(["message" => "Evento eliminado correctamente"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al eliminar evento: " . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(["error" => "Método no permitido"]);
