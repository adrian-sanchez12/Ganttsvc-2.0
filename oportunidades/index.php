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

//*Consigue los datos de la tabla
if ($method === "GET") {
    try {
        $result = $conn->query("SELECT * FROM oportunidades");
        $oportunidades = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode($oportunidades);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al obtener oportunidades: " . $e->getMessage()]);
    }
    exit;
}

//*Insertar
if ($method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    // Limpia y extrae campos
    $nombre_oportunidad = trim($data["nombre_oportunidad"] ?? "");
    $objetivo = trim($data["objetivo"] ?? "");
    $modalidad = trim($data["modalidad"] ?? "");
    $tipo_oportunidad = trim($data["tipo_oportunidad"] ?? "");
    $socio = trim($data["socio"] ?? "");
    $sector = trim($data["sector"] ?? "");
    $tema = trim($data["tema"] ?? "");
    $poblacion_meta = trim($data["poblacion_meta"] ?? "");
    $despacho = trim($data["despacho"] ?? "");
    $direccion_envio = trim($data["direccion_envio"] ?? "");
    $fecha_inicio = trim($data["fecha_inicio"] ?? "");
    $fecha_fin = trim($data["fecha_fin"] ?? "");
    $funcionario = trim($data["funcionario"] ?? "");

    // Valida campos obligatorios
    if (!$nombre_oportunidad || !$fecha_inicio || !$socio || !$objetivo) {
        http_response_code(400);
        echo json_encode(["error" => "Faltan campos obligatorios como nombre_oportunidad, fecha, socio u objetivo"]);
        exit;
    }

    try {
        $stmt = $conn->prepare(query: "
            INSERT INTO oportunidades (
                nombre_oportunidad, objetivo, modalidad, tipo_oportunidad, socio,
                sector, tema, poblacion_meta, despacho, direccion_envio, 
                fecha_inicio, fecha_fin, funcionario
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "sssssssssssss",
            $nombre_oportunidad,
            $objetivo,
            $modalidad,
            $tipo_oportunidad,
            $socio,
            $sector,
            $tema,
            $poblacion_meta,
            $despacho,
            $direccion_envio,
            $fecha_inicio,
            $fecha_fin,
            $funcionario
        );

        $stmt->execute();

        echo json_encode(["message" => "Oportunidad creada correctamente"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al guardar la oportunidad: " . $e->getMessage()]);
    }

    exit;
}

//*Editar
if ($method === "PUT") {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data["id"] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Falta el campo 'id'"]);
        exit;
    }

    $campos_permitidos = [
        "nombre_oportunidad", "objetivo", "modalidad", "tipo_oportunidad", "socio",
        "sector", "tema", "poblacion_meta", "despacho", "direccion_envio", "fecha_inicio",
        "fecha_fin", "funcionario", "doc_pdf"
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
    $sql = "UPDATE oportunidades SET " . implode(", ", $updates) . " WHERE id = ?";

    try {
        // Actualiza tabla oportunidades
        if (!empty($updates)) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(str_repeat("s", count($values) - 1) . "i", ...$values);
            $stmt->execute();
        }

        echo json_encode(["message" => "Oportunidad actualizada correctamente"]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error interno del servidor: " . $e->getMessage()]);
    }
    exit;
}

//*Borrar
if ($method === "DELETE") {
    $id = $_GET["id"] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "Falta el parámetro 'id'"]);
        exit;
    }

    try {
        // Verifica si el registro existe
        $stmt = $conn->prepare("SELECT id FROM oportunidades WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            http_response_code(404);
            echo json_encode(["error" => "Registro no encontrado"]);
            exit;
        }

        // Elimina el registro en oportunidades
        $stmt = $conn->prepare("DELETE FROM oportunidades WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        echo json_encode(["message" => "Oportunidad eliminada correctamente"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al eliminar la oportunidad: " . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(["error" => "Método no permitido"]);
?>