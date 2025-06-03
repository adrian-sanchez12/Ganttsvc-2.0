<?php
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

$logFile = __DIR__ . "/upload_debug.log";
function logMessage($message) {
    global $logFile;
    file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] $message\n", FILE_APPEND);
}

logMessage("Solicitud recibida: " . $_SERVER['REQUEST_METHOD']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    logMessage(" Método no permitido.");
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

if (!isset($_FILES['file']) || !isset($_POST['id'])) {
    http_response_code(400);
    logMessage(" Falta archivo o ID. POST: " . json_encode($_POST));
    echo json_encode(['error' => 'Faltan parámetros: archivo o ID']);
    exit;
}

$file = $_FILES['file'];
$id = $_POST['id'];
logMessage("📄 Archivo recibido: " . json_encode($file));

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(500);
    logMessage(" Error al subir el archivo. Código: " . $file['error']);
    echo json_encode(['error' => 'Error al subir el archivo']);
    exit;
}

$allowedTypes = ['application/pdf'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

logMessage(" Tipo MIME detectado: $mimeType");

if (!in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    logMessage(" Tipo no permitido.");
    echo json_encode(['error' => 'Solo se permiten archivos PDF']);
    exit;
}

$uploadDir = __DIR__ . '/../public/uploads/';
$baseUrl = '/gantt-backend/public/uploads/';

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
    logMessage("📁 Carpeta creada: $uploadDir");
}

$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = "documento-$id-" . uniqid() . ".$extension";
$destination = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    http_response_code(500);
    logMessage(" No se pudo mover el archivo a: $destination");
    echo json_encode(['error' => 'No se pudo guardar el archivo']);
    exit;
}

$url = $baseUrl . $filename;
logMessage(" Archivo subido exitosamente a $url");

http_response_code(200);
echo json_encode(['url' => $url]);
exit;
