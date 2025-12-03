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
// GET: Obtener todos o uno por ID
// =============================================
if ($method === "GET") {
    try {
        $id = $_GET["id"] ?? null;

        if ($id) {
            $stmt = $conn->prepare("SELECT * FROM subpartida_contratacion WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows === 0) {
                json_out(["error" => "Registro no encontrado"], 404);
            }
            json_out($res->fetch_assoc());
        } else {
            $result = $conn->query("SELECT * FROM subpartida_contratacion ORDER BY fecha_creacion DESC");
            json_out($result->fetch_all(MYSQLI_ASSOC));
        }
    } catch (Exception $e) {
        json_out(["error" => "Error al obtener registros: " . $e->getMessage()], 500);
    }
}


// =============================================
// POST: Crear nueva subpartida_contratacion
// =============================================
if ($method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    $campos_obligatorios = ["subpartida", "ano_contrato", "nombre_subpartida", "numero_contratacion", "presupuesto_asignado"];
    foreach ($campos_obligatorios as $campo) {
        if (!isset($data[$campo]) || $data[$campo] === "" || $data[$campo] === null) {
            json_out(["error" => "El campo '$campo' es obligatorio"], 400);
        }
    }

    try {
        $sql = "INSERT INTO subpartida_contratacion (
            subpartida, ano_contrato, nombre_subpartida, descripcion_contratacion,
            numero_contratacion, numero_contrato, numero_orden_compra, orden_pedido_sicop,
            presupuesto_asignado, activo, creado_por
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = $conn->prepare($sql);
        
        // Crear variables para bind_param (requiere referencias)
        $subpartida = $data["subpartida"];
        $ano_contrato = intval($data["ano_contrato"]);
        $nombre_subpartida = $data["nombre_subpartida"];
        $descripcion_contratacion = $data["descripcion_contratacion"] ?? null;
        $numero_contratacion = $data["numero_contratacion"];
        $numero_contrato = $data["numero_contrato"] ?? null;
        $numero_orden_compra = $data["numero_orden_compra"] ?? null;
        $orden_pedido_sicop = $data["orden_pedido_sicop"] ?? null;
        $presupuesto_asignado = floatval($data["presupuesto_asignado"]);
        $activo = intval($data["activo"] ?? 1);
        $creado_por = $data["creado_por"] ?? null;
        
        $stmt->bind_param(
            "sissssssdis",
            $subpartida,
            $ano_contrato,
            $nombre_subpartida,
            $descripcion_contratacion,
            $numero_contratacion,
            $numero_contrato,
            $numero_orden_compra,
            $orden_pedido_sicop,
            $presupuesto_asignado,
            $activo,
            $creado_por
        );
        $stmt->execute();

        json_out(["message" => "Subpartida creada correctamente", "id" => $conn->insert_id], 201);
    } catch (Exception $e) {
        json_out(["error" => "Error al crear subpartida: " . $e->getMessage()], 500);
    }
}

// =============================================
// PUT: Actualizar subpartida_contratacion por ID
// =============================================
if ($method === "PUT") {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];
    $id = $data["id"] ?? null;

    if (!$id) {
        json_out(["error" => "Falta el campo 'id'"], 400);
    }

    $permitidos = [
        "subpartida", "ano_contrato", "nombre_subpartida", "descripcion_contratacion",
        "numero_contratacion", "numero_contrato", "numero_orden_compra", "orden_pedido_sicop",
        "presupuesto_asignado", "activo", "creado_por"
    ];

    $updates = [];
    $values = [];
    $types = "";

    foreach ($permitidos as $campo) {
        if (array_key_exists($campo, $data)) {
            $updates[] = "$campo = ?";
            if ($campo === "presupuesto_asignado") {
                $values[] = floatval($data[$campo]);
                $types .= "d";
            } elseif ($campo === "ano_contrato" || $campo === "activo") {
                $values[] = intval($data[$campo]);
                $types .= "i";
            } else {
                $values[] = $data[$campo];
                $types .= "s";
            }
        }
    }

    if (empty($updates)) {
        json_out(["error" => "No se enviaron campos para actualizar"], 400);
    }

    $sql = "UPDATE subpartida_contratacion SET " . implode(", ", $updates) . " WHERE id = ?";
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

        json_out(["message" => "Subpartida actualizada correctamente"]);
    } catch (Exception $e) {
        json_out(["error" => "Error al actualizar: " . $e->getMessage()], 500);
    }
}

// =============================================
// DELETE: Eliminar por ID
// =============================================
if ($method === "DELETE") {
    // Permitir id por GET o por cuerpo JSON
    $id = $_GET["id"] ?? null;
    if (!$id) {
        $data = json_decode(file_get_contents("php://input"), true) ?? [];
        $id = isset($data["id"]) ? intval($data["id"]) : null;
    }

    if (!$id) {
        json_out(["error" => "Falta el parámetro 'id'"], 400);
    }

    try {
        $stmt = $conn->prepare("DELETE FROM subpartida_contratacion WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        json_out(["message" => "Subpartida eliminada correctamente"]);
    } catch (Exception $e) {
        json_out(["error" => "Error al eliminar: " . $e->getMessage()], 500);
    }
}

json_out(["error" => "Método no permitido"], 405);
?>
