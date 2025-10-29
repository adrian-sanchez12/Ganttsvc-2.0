<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ✅ Corrección de la ruta para db.php
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

function formatDateForMariaDB($date) {
    return $date ? date("Y-m-d H:i:s", strtotime($date)) : null;
}

$method = $_SERVER["REQUEST_METHOD"];

// === GET ===
if ($method === "GET") {
    $result = $conn->query("SELECT * FROM registro_procesos ORDER BY fecha_inicio DESC");
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    echo json_encode($rows);
    exit;
}

// === POST ===
if ($method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    $stmt = $conn->prepare("INSERT INTO registro_procesos (convenio_id, entidad_proponente, autoridad_ministerial, funcionario_emisor, entidad_emisora, funcionario_receptor, entidad_receptora, registro_proceso, fecha_inicio, fecha_final, tipo_convenio, fase_registro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "isssssssssss",
        $data["convenio_id"],
        $data["entidad_proponente"],
        $data["autoridad_ministerial"],
        $data["funcionario_emisor"],
        $data["entidad_emisora"],
        $data["funcionario_receptor"],
        $data["entidad_receptora"],
        $data["registro_proceso"],
        formatDateForMariaDB($data["fecha_inicio"]),
        formatDateForMariaDB($data["fecha_final"]),
        $data["tipo_convenio"],
        $data["fase_registro"]
    );
    $stmt->execute();

    // Actualizar fase actual del convenio
    $last = $conn->query("SELECT fase_registro FROM registro_procesos WHERE convenio_id = {$data["convenio_id"]} ORDER BY id DESC LIMIT 1")->fetch_assoc();
    $conn->query("UPDATE convenios SET fase_actual = '{$last["fase_registro"]}' WHERE id = {$data["convenio_id"]}");

    echo json_encode(["message" => "Registro insertado y fase actualizada exitosamente"]);
    exit;
}

// === PUT ===
if ($method === "PUT") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data["id"])) {
        http_response_code(400);
        echo json_encode(["error" => "ID es obligatorio"]);
        exit;
    }

    $fecha_inicio = formatDateForMariaDB($data["fecha_inicio"] ?? null);
    $fecha_final = formatDateForMariaDB($data["fecha_final"] ?? null);

    $stmt = $conn->prepare("UPDATE registro_procesos SET entidad_proponente = ?, autoridad_ministerial = ?, funcionario_emisor = ?, entidad_emisora = ?, funcionario_receptor = ?, entidad_receptora = ?, registro_proceso = ?, fecha_inicio = ?, fecha_final = ?, tipo_convenio = ?, fase_registro = ? WHERE id = ?");
    $stmt->bind_param(
        "sssssssssssi",
        $data["entidad_proponente"],
        $data["autoridad_ministerial"],
        $data["funcionario_emisor"],
        $data["entidad_emisora"],
        $data["funcionario_receptor"],
        $data["entidad_receptora"],
        $data["registro_proceso"],
        $fecha_inicio,
        $fecha_final,
        $data["tipo_convenio"],
        $data["fase_registro"],
        $data["id"]
    );

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["error" => "Error al actualizar el registro"]);
        exit;
    }

    // Actualizar fase actual si se tiene convenio_id
    if (isset($data["convenio_id"])) {
        $last = $conn->query("SELECT fase_registro FROM registro_procesos WHERE convenio_id = {$data["convenio_id"]} ORDER BY id DESC LIMIT 1")->fetch_assoc();
        $conn->query("UPDATE convenios SET fase_actual = '{$last["fase_registro"]}' WHERE id = {$data["convenio_id"]}");
    }

    echo json_encode(["message" => "Registro actualizado exitosamente"]);
    exit;
}

// === DELETE ===
if ($method === "DELETE") {
    $id = $_GET["id"] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(["error" => "ID es obligatorio"]);
        exit;
    }

    // Obtener convenio_id del registro a eliminar
    $stmt = $conn->prepare("SELECT convenio_id FROM registro_procesos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["error" => "Registro no encontrado"]);
        exit;
    }

    $row = $result->fetch_assoc();
    $convenio_id = $row["convenio_id"];

    // Eliminar registro
    $stmt = $conn->prepare("DELETE FROM registro_procesos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    // Recalcular fase actual
    $stmt = $conn->prepare("SELECT fase_registro FROM registro_procesos WHERE convenio_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("i", $convenio_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $fase = $result->num_rows > 0 ? $result->fetch_assoc()["fase_registro"] : "Negociación";

    $stmt = $conn->prepare("UPDATE convenios SET fase_actual = ? WHERE id = ?");
    $stmt->bind_param("si", $fase, $convenio_id);
    $stmt->execute();

    echo json_encode(["message" => "Registro eliminado exitosamente"]);
    exit;
}

// Si el método no está permitido
http_response_code(405);
echo json_encode(["error" => "Método no permitido"]);
