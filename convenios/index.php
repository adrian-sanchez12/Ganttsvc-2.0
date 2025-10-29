<?php

function handleCORS() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');
    header('Access-Control-Max-Age: 1728000');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Content-Length: 0');
        header('Content-Type: text/plain');
        http_response_code(200);
        exit();
    }
}

handleCORS();

include_once("../../db.php");

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === "GET") {
    $convenios = $conn->query("SELECT * FROM convenios")->fetch_all(MYSQLI_ASSOC);
    $registros = $conn->query("SELECT convenio_id, MAX(fase_registro) as fase_actual FROM registro_procesos GROUP BY convenio_id")->fetch_all(MYSQLI_ASSOC);
    $totalConvenios = $conn->query("SELECT COUNT(*) AS total FROM convenios")->fetch_assoc()['total'];
    $totalCooperantes = $conn->query("SELECT COUNT(DISTINCT cooperante) AS total FROM convenios")->fetch_assoc()['total'];

    foreach ($convenios as &$c) {
        $c['fase_actual'] = "Negociación"; // Por defecto
        foreach ($registros as $r) {
            if ($r['convenio_id'] == $c['id']) {
                $c['fase_actual'] = $r['fase_actual'];
                break;
            }
        }
    }

    echo json_encode([
        "totalConvenios" => (int)$totalConvenios,
        "totalCooperantes" => (int)$totalCooperantes,
        "convenios" => $convenios
    ]);
    exit;
}

if ($method === "POST") {
    $body = json_decode(file_get_contents("php://input"), true);

    $cooperante = $body["cooperante"] ?? null;
    $nombre = $body["nombre"] ?? null;
    $sector = $body["sector"] ?? null;
    $fase_actual = $body["fase_actual"] ?? "Negociación";
    $firmado = isset($body["firmado"]) && $body["firmado"] ? 1 : 0;
    $consecutivo = $body["consecutivo_numerico"] ?? null;
    $fecha_inicio = $body["fecha_inicio"] ?? null;

    if (!$cooperante || !$nombre || $sector === null || $consecutivo === null) {
        http_response_code(400);
        echo json_encode(["error" => "Faltan campos obligatorios"]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO convenios (cooperante, nombre, sector, fase_actual, firmado, consecutivo_numerico, fecha_inicio) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssis", $cooperante, $nombre, $sector, $fase_actual, $firmado, $consecutivo, $fecha_inicio);

    if ($stmt->execute()) {
        echo json_encode(["message" => "Convenio agregado correctamente"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Error en la base de datos: " . $stmt->error]);
    }

    $stmt->close();
    exit;
}

if ($method === "DELETE") {
    parse_str($_SERVER["QUERY_STRING"], $query);
    $id = $query["id"] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "ID del convenio es obligatorio"]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM convenios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    echo json_encode(["message" => "Convenio eliminado correctamente"]);
    exit;
}

http_response_code(405); // Método no permitido
echo json_encode(["error" => "Método no permitido"]);