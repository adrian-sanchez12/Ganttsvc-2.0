<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');
header('Access-Control-Max-Age: 1728000');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once(__DIR__ . "/../../db.php");
$method = $_SERVER['REQUEST_METHOD'];

function json_out($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================
// GET: Obtener todas o una solicitud por ID
// =============================================
if ($method === "GET") {
    try {
        $id = $_GET["id"] ?? null;
        if ($id) {
            $stmt = $conn->prepare("SELECT * FROM solicitud_presupuesto WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows === 0) {
                json_out(["error" => "Registro no encontrado"], 404);
            }
            json_out($res->fetch_assoc());
        } else {
            $result = $conn->query("SELECT * FROM solicitud_presupuesto ORDER BY fecha_creacion DESC");
            json_out($result->fetch_all(MYSQLI_ASSOC));
        }
    } catch (Exception $e) {
        json_out(["error" => "Error al obtener registros: " . $e->getMessage()], 500);
    }
}

// =============================================
// POST: Crear nueva solicitud de presupuesto
// =============================================
if ($method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    $requeridos = ["subpartida_contratacion_id", "descripcion", "fecha_solicitud_boleto", "total_factura"];
    foreach ($requeridos as $campo) {
        if (empty($data[$campo])) {
            json_out(["error" => "El campo '$campo' es obligatorio"], 400);
        }
    }

    try {
        $sql = "INSERT INTO solicitud_presupuesto (
            subpartida_contratacion_id, descripcion, fecha_solicitud_boleto, hora_solicitud_boleto,
            oficio_solicitud, fecha_respuesta_solicitud, hora_respuesta_solicitud, cumple_solicitud,
            fecha_solicitud_emision, hora_solicitud_emision, oficio_emision,
            fecha_respuesta_emision, hora_respuesta_emision, cumple_emision,
            fecha_recibido_conforme, hora_recibido_conforme, oficio_recepcion,
            numero_factura, total_factura, fecha_entrega_direccion,
            estado, activo, creado_por
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "isssssssssssssssssssdss",
            $data["subpartida_contratacion_id"],
            $data["descripcion"],

            //Etapa 1: solicitud
            $data["fecha_solicitud_boleto"],
            $data["hora_solicitud_boleto"],
            $data["oficio_solicitud"],
            $data["fecha_respuesta_solicitud"],
            $data["hora_respuesta_solicitud"],
            $data["cumple_solicitud"],

            //Etapa 2: emision
            $data["fecha_solicitud_emision"],
            $data["hora_solicitud_emision"],
            $data["oficio_emision"],
            $data["fecha_respuesta_emision"],
            $data["hora_respuesta_emision"],
            $data["cumple_emision"],

            //Etapa 3: recibido conforme
            $data["fecha_recibido_conforme"],
            $data["hora_recibido_conforme"],
            $data["oficio_recepcion"],

            //Etapa 4: facturación
            $data["numero_factura"],
            $data["total_factura"],

            //Etapa 5: entrega a Dirección
            $data["fecha_entrega_direccion"],
            
            //Estado y metadatos
            $data["estado"],
            $data["activo"] ?? 1,
            $data["creado_por"]
        );
        $stmt->execute();

        json_out(["message" => "Solicitud de presupuesto creada correctamente"]);
    } catch (Exception $e) {
        json_out(["error" => "Error al crear la solicitud: " . $e->getMessage()], 500);
    }
}

// =============================================
// PUT: Actualizar solicitud por ID
// =============================================
if ($method === "PUT") {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];
    $id = $data["id"] ?? null;

    if (!$id) {
        json_out(["error" => "Falta el campo 'id'"], 400);
    }

    $permitidos = [
        "descripcion", "fecha_solicitud_boleto", "hora_solicitud_boleto", "oficio_solicitud",
        "fecha_respuesta_solicitud", "hora_respuesta_solicitud", "cumple_solicitud",
        "fecha_solicitud_emision", "hora_solicitud_emision", "oficio_emision",
        "fecha_respuesta_emision", "hora_respuesta_emision", "cumple_emision",
        "fecha_recibido_conforme", "hora_recibido_conforme", "oficio_recepcion",
        "numero_factura", "total_factura", "fecha_entrega_direccion",
        "estado", "activo"
    ];

    $updates = [];
    $values = [];
    $types = "";

    foreach ($permitidos as $campo) {
        if (array_key_exists($campo, $data)) {
            $updates[] = "$campo = ?";
            $values[] = $data[$campo];
            $types .= is_numeric($data[$campo]) ? "d" : "s";
        }
    }

    if (empty($updates)) {
        json_out(["error" => "No se enviaron campos para actualizar"], 400);
    }

    $sql = "UPDATE solicitud_presupuesto SET " . implode(", ", $updates) . " WHERE id = ?";
    $values[] = intval($id);
    $types .= "i";

    try {
        $stmt = $conn->prepare($sql);
        $bind_names[] = $types;
        foreach ($values as $i => $val) {
            $bind_name = 'b' . $i;
            $$bind_name = $val;
            $bind_names[] = &$$bind_name;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
        $stmt->execute();

        json_out(["message" => "Solicitud actualizada correctamente"]);
    } catch (Exception $e) {
        json_out(["error" => "Error al actualizar solicitud: " . $e->getMessage()], 500);
    }
}

// =============================================
// DELETE: Eliminar solicitud por ID
// =============================================
if ($method === "DELETE") {
    $id = $_GET["id"] ?? null;
    if (!$id) {
        json_out(["error" => "Falta el parámetro 'id'"], 400);
    }

    try {
        $stmt = $conn->prepare("DELETE FROM solicitud_presupuesto WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        json_out(["message" => "Solicitud eliminada correctamente"]);
    } catch (Exception $e) {
        json_out(["error" => "Error al eliminar la solicitud: " . $e->getMessage()], 500);
    }
}

json_out(["error" => "Método no permitido"], 405);
?>
