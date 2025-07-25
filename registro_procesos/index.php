<?php
function handleCORS() {
    header('Access-Control-Allow-Origin: *'); // Puedes ajustar el origen a tus necesidades
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
header("Access-Control-Allow-Origin: *");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once("../../db.php");

function formatDateForMariaDB($date) {
    return $date ? date("Y-m-d H:i:s", strtotime($date)) : null;
}

$method = $_SERVER["REQUEST_METHOD"];

if ($method === "GET") {
    $result = $conn->query("SELECT * FROM registro_procesos ORDER BY fecha_inicio DESC");
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    echo json_encode($rows);
    exit;
}

if ($method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    $stmt = $conn->prepare("INSERT INTO registro_procesos (convenio_id, entidad_proponente, autoridad_ministerial, funcionario_emisor, entidad_emisora, funcionario_receptor, entidad_receptora, registro_proceso, fecha_inicio, fecha_final, tipo_convenio, fase_registro, doc_pdf) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
        $data["fase_registro"],
        $data["doc_pdf"]
    );
    $stmt->execute();

    // Actualizar fase actual
    $last = $conn->query("SELECT fase_registro FROM registro_procesos WHERE convenio_id = {$data["convenio_id"]} ORDER BY id DESC LIMIT 1")->fetch_assoc();
    $conn->query("UPDATE convenios SET fase_actual = '{$last["fase_registro"]}' WHERE id = {$data["convenio_id"]}");

    echo json_encode(["message" => "Registro insertado y fase actualizada exitosamente"]);
    exit;
}

if ($method === "PUT") {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data["id"])) {
        http_response_code(400);
        echo json_encode(["error" => "ID es obligatorio"]);
        exit;
    }

    $allowedFields = [
        "entidad_proponente", 
        "autoridad_ministerial", 
        "funcionario_emisor",
        "entidad_emisora", 
        "funcionario_receptor", 
        "entidad_receptora",
        "registro_proceso", 
        "fecha_inicio", 
        "fecha_final", 
        "tipo_convenio",
        "fase_registro", 
        "doc_pdf"
    ];

    $setParts = [];
    $values = [];

    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $data)) {
            if ($field == "fecha_inicio" || $field == "fecha_final") {
                $values[] = formatDateForMariaDB($data[$field]);
            } else {
                $values[] = $data[$field];
            }
            $setParts[] = "$field = ?";
        }
    }

    if (empty($setParts)) {
        http_response_code(400);
        echo json_encode(["error" => "No se enviaron campos para actualizar"]);
        exit;
    }

    $values[] = $data["id"];
    $sql = "UPDATE registro_procesos SET " . implode(", ", $setParts) . " WHERE id = ?";

    $types = str_repeat("s", count($setParts)) . "i"; // Cambia 's' a otro tipo si tienes INT, DATE, etc.
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["error" => "Error al actualizar el registro", "detalles" => $stmt->error]);
        exit;
    }

    // Actualizar fase actual (igual que antes)
    $convenio_id = $data["convenio_id"] ?? null;
    if ($convenio_id) {
        $last = $conn->query("SELECT fase_registro FROM registro_procesos WHERE convenio_id = $convenio_id ORDER BY id DESC LIMIT 1")->fetch_assoc();
        $conn->query("UPDATE convenios SET fase_actual = '{$last["fase_registro"]}' WHERE id = $convenio_id");
    }

    echo json_encode(["message" => "Registro actualizado exitosamente"]);
    exit;
}

http_response_code(405);
echo json_encode(["error" => "Método no permitido"]);
?>
