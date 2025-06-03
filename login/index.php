<?php
function handleCORS() {
    header('Access-Control-Allow-Origin: *'); // Cambia esto si quieres restringir
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

include_once("../../db.php");

// Obtener el cuerpo del request
$data = json_decode(file_get_contents("php://input"), true);

$correo = $data['correo'] ?? '';
$contrasena = $data['contrasena'] ?? '';

// Validar campos obligatorios
if (!$correo || !$contrasena) {
    http_response_code(400);
    echo json_encode(["error" => "Correo y contraseña son obligatorios"]);
    exit();
}

// Buscar el usuario por correo
$stmt = $conn->prepare("SELECT * FROM usuario WHERE correo = ?");
$stmt->bind_param("s", $correo);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["error" => "Usuario no encontrado"]);
    exit();
}

$usuario = $res->fetch_assoc();

// Verificar contraseña en texto plano
if ($usuario['contrasena'] !== $contrasena) {
    http_response_code(401);
    echo json_encode(["error" => "Contraseña incorrecta"]);
    exit();
}

// Éxito
echo json_encode([
    "success" => true,
    "user" => [
        "id" => $usuario["id"],
        "nombre" => $usuario["nombre"],
        "correo" => $usuario["correo"],
        "rol" => $usuario["rol"]
    ]
]);
exit();
?>
