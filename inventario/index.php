<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');
header('Access-Control-Max-Age: 1728000');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once(__DIR__ . "/../../db.php");
$method = $_SERVER['REQUEST_METHOD'];

header('Content-Type: application/json');

if ($method === "GET") {
    try {
        $result = $conn->query("SELECT * FROM inventario");
        $data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode($data);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al obtener inventario: " . $e->getMessage()]);
    }
    exit;
}

if ($method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    // Limpiar y extraer campos
    $nombre_convenio = trim($data["nombre_convenio"] ?? "");
    $objeto_convenio = trim($data["objeto_convenio"] ?? "");
    $tipo_instrumento = trim($data["tipo_instrumento"] ?? "");
    $presupuesto = isset($data["presupuesto"]) ? floatval($data["presupuesto"]) : 0;
    $instancias_tecnicas = trim($data["instancias_tecnicas"] ?? "");
    $informe = trim($data["informe"] ?? "");
    $fecha_rige = trim($data["fecha_rige"] ?? "");
    $fecha_vencimiento = trim($data["fecha_vencimiento"] ?? "");
    $cooperante = trim($data["cooperante"] ?? "");
    $contraparte_externa = trim($data["contraparte_externa"] ?? "");

    // Validar campos obligatorios
    if (!$nombre_convenio || !$fecha_rige || !$fecha_vencimiento) {
        http_response_code(400);
        echo json_encode(["error" => "Faltan campos obligatorios como nombre_convenio, fecha_rige o fecha_vencimiento"]);
        exit;
    }

    try {
        $stmt = $conn->prepare("
            INSERT INTO inventario (
                nombre_convenio, objeto_convenio, tipo_instrumento, presupuesto,
                instancias_tecnicas, informe, fecha_rige, fecha_vencimiento,
                cooperante, contraparte_externa
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "sssdsdssss",
            $nombre_convenio,
            $objeto_convenio,
            $tipo_instrumento,
            $presupuesto,
            $instancias_tecnicas,
            $informe,
            $fecha_rige,
            $fecha_vencimiento,
            $cooperante,
            $contraparte_externa
        );

        $stmt->execute();

        echo json_encode(["message" => "Inventario creado correctamente"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al guardar el inventario: " . $e->getMessage()]);
    }

    exit;
}


if ($method === "PUT") {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data["id"] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Falta el campo 'id'"]);
        exit;
    }

    $campos_permitidos = [
        "nombre_convenio", "objeto_convenio", "tipo_instrumento", "presupuesto",
        "instancias_tecnicas", "informe", "fecha_rige", "fecha_vencimiento",
        "documento_pdf", "cooperante", "contraparte_externa" // 👈 Agregados aquí
    ];

    $updates = [];
    $values = [];

    foreach ($campos_permitidos as $campo) {
        if (isset($data[$campo])) {
            $updates[] = "$campo = ?";
            $values[] = $data[$campo];
        }
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(["error" => "No se enviaron campos para actualizar"]);
        exit;
    }

    $values[] = $id;
    $sql = "UPDATE inventario SET " . implode(", ", $updates) . " WHERE id = ?";

    try {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(str_repeat("s", count($values) - 1) . "i", ...$values);
        $stmt->execute();

        echo json_encode(["message" => "Inventario actualizado correctamente"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error interno del servidor: " . $e->getMessage()]);
    }
    exit;
}


if ($method === "DELETE") {
    $id = $_GET["id"] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Falta el parámetro 'id'"]);
        exit;
    }

    try {
        // Verificar si el registro existe
        $stmt = $conn->prepare("SELECT id FROM inventario WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            http_response_code(404);
            echo json_encode(["error" => "Registro no encontrado"]);
            exit;
        }

        // Eliminar
        $stmt = $conn->prepare("DELETE FROM inventario WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        echo json_encode(["message" => "Inventario eliminado correctamente"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al eliminar el inventario: " . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(["error" => "Método no permitido"]);
?>
