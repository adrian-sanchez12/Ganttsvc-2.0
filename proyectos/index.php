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

// ==============================
// GET: lista todos o uno por NumProyecto
// ==============================
if ($method === "GET") {
    try {
        $NumProyecto = $_GET["NumProyecto"] ?? null;

        if ($NumProyecto !== null && $NumProyecto !== '') {
            $stmt = $conn->prepare("SELECT * FROM proyecto WHERE NumProyecto = ?");
            $stmt->bind_param("i", $NumProyecto);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                json_out(["error" => "Proyecto no encontrado"], 404);
            }
            $row = $res->fetch_assoc();
            json_out($row);
        } else {
            $result = $conn->query("SELECT * FROM proyecto ORDER BY NumProyecto DESC");
            $proyectos = $result->fetch_all(MYSQLI_ASSOC);
            json_out($proyectos);
        }
    } catch (Exception $e) {
        json_out(["error" => "Error al obtener proyectos: " . $e->getMessage()], 500);
    }
}

// ==============================
// POST: insertar nuevo proyecto
// ==============================
if ($method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];
    $NumProyecto = isset($data["NumProyecto"]) ? intval($data["NumProyecto"]) : 0;
    $NombreProyecto = trim($data["NombreProyecto"] ?? "");
    $FechaAprovacion = trim($data["FechaAprovacion"] ?? "");

    if (!$NombreProyecto || !$FechaAprovacion) {
        json_out(["error" => "Los campos 'NombreProyecto' y 'FechaAprovacion' son obligatorios"], 400);
    }

    try {
        if ($NumProyecto > 0) {
            $check = $conn->prepare("SELECT COUNT(*) AS total FROM proyecto WHERE NumProyecto = ?");
            $check->bind_param("i", $NumProyecto);
            $check->execute();
            $res = $check->get_result()->fetch_assoc();
            if ($res["total"] > 0) {
                json_out(["error" => "El número de proyecto ya existe."], 409);
            }
        }

        // Si pasa la validación, ejecutar INSERT normal
        $sql = "INSERT INTO proyecto (
            NumProyecto, ActorCooperacion, NombreActor, NombreProyecto,
            FechaAprovacion, EtapaProyecto, TipoProyecto, CostoTotal,
            ContrapartidaInstitucion, Documentos, Observaciones, Objetivos,
            Resultados, Tematicas, Dependencia, Ano, ContrapartidaCooperante,
            Areas, InstitucionSolicitante, Region
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "isssssssssssssssssss",
            $NumProyecto,
            $data["ActorCooperacion"],
            $data["NombreActor"],
            $data["NombreProyecto"],
            $data["FechaAprovacion"],
            $data["EtapaProyecto"],
            $data["TipoProyecto"],
            $data["CostoTotal"],
            $data["ContrapartidaInstitucion"],
            $data["Documentos"],
            $data["Observaciones"],
            $data["Objetivos"],
            $data["Resultados"],
            $data["Tematicas"],
            $data["Dependencia"],
            $data["Ano"],
            $data["ContrapartidaCooperante"],
            $data["Areas"],
            $data["InstitucionSolicitante"],
            $data["Region"]
        );
        $stmt->execute();

        json_out(["message" => "Proyecto creado correctamente"]);
    } catch (Exception $e) {
        json_out(["error" => "Error al guardar el proyecto: " . $e->getMessage()], 500);
    }
}

// ==============================
// PUT: actualizar proyecto (por NumProyecto)
// ==============================
if ($method === "PUT") {
    $data = json_decode(file_get_contents("php://input"), true) ?? [];
    $NumProyecto = $data["NumProyecto"] ?? null;

    if (!$NumProyecto) {
        json_out(["error" => "Falta el campo 'NumProyecto'"], 400);
    }

    // Lista blanca de campos actualizables (coinciden con columnas)
    $permitidos = [
        "ActorCooperacion", "NombreActor", "NombreProyecto", "FechaAprovacion",
        "EtapaProyecto", "TipoProyecto", "CostoTotal", "ContrapartidaInstitucion",
        "Documentos", "Observaciones", "Objetivos", "Resultados", "Tematicas",
        "Dependencia", "Ano", "ContrapartidaCooperante", "Areas",
        "InstitucionSolicitante", "Region"
    ];

    $updates = [];
    $values  = [];
    $types   = ""; // tipos para bind_param

    foreach ($permitidos as $campo) {
        if (array_key_exists($campo, $data)) {
            $updates[] = "$campo = ?";
            $values[]  = is_null($data[$campo]) ? null : trim((string)$data[$campo]);
            $types    .= "s"; // todos los campos son strings en la tabla, salvo NumProyecto que va al final como int
        }
    }

    if (empty($updates)) {
        json_out(["error" => "No se enviaron campos para actualizar"], 400);
    }

    $sql = "UPDATE proyecto SET " . implode(", ", $updates) . " WHERE NumProyecto = ?";
    $values[] = intval($NumProyecto);
    $types   .= "i";

    try {
        $stmt = $conn->prepare($sql);

        // bind_param requiere referencias
        $bind_names[] = $types;
        for ($i = 0; $i < count($values); $i++) {
            $bind_name = 'bind' . $i;
            $$bind_name = $values[$i];
            $bind_names[] = &$$bind_name;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);

        $stmt->execute();
        json_out(["message" => "Proyecto actualizado correctamente"]);
    } catch (Exception $e) {
        json_out(["error" => "Error al actualizar el proyecto: " . $e->getMessage()], 500);
    }
}

// ==============================
// DELETE: eliminar por NumProyecto
// ==============================
if ($method === "DELETE") {
    $NumProyecto = $_GET["NumProyecto"] ?? null;

    if (!$NumProyecto) {
        json_out(["error" => "Falta el parámetro 'NumProyecto'"], 400);
    }

    try {
        // Verifica existencia
        $stmt = $conn->prepare("SELECT NumProyecto FROM proyecto WHERE NumProyecto = ?");
        $stmt->bind_param("i", $NumProyecto);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            json_out(["error" => "Proyecto no encontrado"], 404);
        }

        // Elimina
        $stmt = $conn->prepare("DELETE FROM proyecto WHERE NumProyecto = ?");
        $stmt->bind_param("i", $NumProyecto);
        $stmt->execute();

        json_out(["message" => "Proyecto eliminado correctamente"]);
    } catch (Exception $e) {
        json_out(["error" => "Error al eliminar el proyecto: " . $e->getMessage()], 500);
    }
}

json_out(["error" => "Método no permitido"], 405);
