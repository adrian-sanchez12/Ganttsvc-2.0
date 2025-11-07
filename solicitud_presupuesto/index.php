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
    
    // 🔍 DEBUG: Log de datos recibidos
    error_log("=== SOLICITUD_PRESUPUESTO POST ===");
    error_log("Datos recibidos: " . print_r($data, true));

    $requeridos = ["subpartida_contratacion_id", "descripcion", "fecha_solicitud_boleto", "total_factura"];
    foreach ($requeridos as $campo) {
        if (empty($data[$campo])) {
            error_log("❌ Campo obligatorio faltante: $campo");
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

        // 🔍 DEBUG: Log del SQL
        error_log("SQL: " . $sql);
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log("❌ Error al preparar statement: " . $conn->error);
            json_out(["error" => "Error al preparar statement: " . $conn->error], 500);
        }
        
        // 🔍 DEBUG: Valores que se van a insertar
        error_log("Valores a insertar:");
        error_log("  - subpartida_contratacion_id: " . ($data["subpartida_contratacion_id"] ?? 'NULL'));
        error_log("  - descripcion: " . ($data["descripcion"] ?? 'NULL'));
        error_log("  - fecha_solicitud_boleto: " . ($data["fecha_solicitud_boleto"] ?? 'NULL'));
        error_log("  - total_factura: " . ($data["total_factura"] ?? 'NULL'));
        
        // ✅ CORRECCIÓN: 23 parámetros, tipos correctos
        $stmt->bind_param(
            "isssssssssssssssssdssss", // i=int, s=string, d=decimal
            $data["subpartida_contratacion_id"],           // 1  - i
            $data["descripcion"],                          // 2  - s

            // Etapa 1: solicitud
            $data["fecha_solicitud_boleto"],               // 3  - s
            $data["hora_solicitud_boleto"] ?? null,        // 4  - s
            $data["oficio_solicitud"] ?? null,             // 5  - s
            $data["fecha_respuesta_solicitud"] ?? null,    // 6  - s
            $data["hora_respuesta_solicitud"] ?? null,     // 7  - s
            $data["cumple_solicitud"] ?? 'Pendiente',      // 8  - s

            // Etapa 2: emision
            $data["fecha_solicitud_emision"] ?? null,      // 9  - s
            $data["hora_solicitud_emision"] ?? null,       // 10 - s
            $data["oficio_emision"] ?? null,               // 11 - s
            $data["fecha_respuesta_emision"] ?? null,      // 12 - s
            $data["hora_respuesta_emision"] ?? null,       // 13 - s
            $data["cumple_emision"] ?? 'Pendiente',        // 14 - s

            // Etapa 3: recibido conforme
            $data["fecha_recibido_conforme"] ?? null,      // 15 - s
            $data["hora_recibido_conforme"] ?? null,       // 16 - s
            $data["oficio_recepcion"] ?? null,             // 17 - s

            // Etapa 4: facturación
            $data["numero_factura"] ?? null,               // 18 - s
            $data["total_factura"],                        // 19 - d (decimal)

            // Etapa 5: entrega a Dirección
            $data["fecha_entrega_direccion"] ?? null,      // 20 - s
            
            // Estado y metadatos
            $data["estado"] ?? 'Solicitud Inicial',        // 21 - s
            $data["activo"] ?? 1,                          // 22 - s
            $data["creado_por"] ?? null                    // 23 - s
        );
        
        error_log("Ejecutando statement...");
        
        if (!$stmt->execute()) {
            error_log("❌ Error al ejecutar: " . $stmt->error);
            json_out(["error" => "Error al ejecutar: " . $stmt->error], 500);
        }

        $insert_id = $conn->insert_id;
        error_log("✅ Solicitud creada exitosamente. ID: " . $insert_id);
        
        json_out([
            "message" => "Solicitud de presupuesto creada correctamente", 
            "id" => $insert_id
        ], 201);
        
    } catch (Exception $e) {
        error_log("❌ Exception: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        json_out(["error" => "Error al crear la solicitud: " . $e->getMessage()], 500);
    }
}

// =============================================
// PUT: Actualizar solicitud por ID
// =============================================
if ($method === "PUT") {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];
    $id = $data["id"] ?? null;
    
    error_log("=== SOLICITUD_PRESUPUESTO PUT ===");
    error_log("ID: " . ($id ?? 'NULL'));
    error_log("Datos: " . print_r($data, true));

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
            
            // Determinar tipo correcto
            if ($campo === "total_factura") {
                $types .= "d"; // decimal
            } elseif (is_numeric($data[$campo])) {
                $types .= "i"; // integer
            } else {
                $types .= "s"; // string
            }
        }
    }

    if (empty($updates)) {
        json_out(["error" => "No se enviaron campos para actualizar"], 400);
    }

    $sql = "UPDATE solicitud_presupuesto SET " . implode(", ", $updates) . " WHERE id = ?";
    $values[] = intval($id);
    $types .= "i";
    
    error_log("SQL UPDATE: " . $sql);
    error_log("Types: " . $types);
    error_log("Values: " . print_r($values, true));

    try {
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log("❌ Error al preparar UPDATE: " . $conn->error);
            json_out(["error" => "Error al preparar: " . $conn->error], 500);
        }
        
        $bind_names[] = $types;
        foreach ($values as $i => $val) {
            $bind_name = 'b' . $i;
            $$bind_name = $val;
            $bind_names[] = &$$bind_name;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
        
        if (!$stmt->execute()) {
            error_log("❌ Error al ejecutar UPDATE: " . $stmt->error);
            json_out(["error" => "Error al ejecutar: " . $stmt->error], 500);
        }

        error_log("✅ Solicitud actualizada. Filas afectadas: " . $stmt->affected_rows);
        json_out(["message" => "Solicitud actualizada correctamente"]);
        
    } catch (Exception $e) {
        error_log("❌ Exception en PUT: " . $e->getMessage());
        json_out(["error" => "Error al actualizar solicitud: " . $e->getMessage()], 500);
    }
}

// =============================================
// DELETE: Eliminar solicitud por ID
// =============================================
if ($method === "DELETE") {
    $id = $_GET["id"] ?? null;
    
    error_log("=== SOLICITUD_PRESUPUESTO DELETE ===");
    error_log("ID: " . ($id ?? 'NULL'));
    
    if (!$id) {
        json_out(["error" => "Falta el parámetro 'id'"], 400);
    }

    try {
        $stmt = $conn->prepare("DELETE FROM solicitud_presupuesto WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if (!$stmt->execute()) {
            error_log("❌ Error al ejecutar DELETE: " . $stmt->error);
            json_out(["error" => "Error al ejecutar: " . $stmt->error], 500);
        }

        error_log("✅ Solicitud eliminada. Filas afectadas: " . $stmt->affected_rows);
        json_out(["message" => "Solicitud eliminada correctamente"]);
        
    } catch (Exception $e) {
        error_log("❌ Exception en DELETE: " . $e->getMessage());
        json_out(["error" => "Error al eliminar la solicitud: " . $e->getMessage()], 500);
    }
}

json_out(["error" => "Método no permitido"], 405);
?>