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

        //Para conseguir todos los funcionarios relacionados a x oportunidad
        foreach ($oportunidades as &$oportunidad) {
            $id = $oportunidad["id"];
            $stmt = $conn->prepare("
                SELECT f.id_funcionario, f.nombre_funcionario
                FROM oportunidades_funcionario ofa
                INNER JOIN funcionario f ON ofa.id_funcionario = f.id_funcionario
                WHERE ofa.id_oportunidades = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $oportunidad["funcionarios"] = $res->fetch_all(MYSQLI_ASSOC);
        }

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
    $modalidad = trim($data["modalidad"] ?? "");
    $tipo_oportunidad = trim($data["tipo_oportunidad"] ?? "");
    $socio = trim($data["socio"] ?? "");
    $sector = trim($data["sector"] ?? "");
    $fecha = trim($data["fecha"] ?? "");
    $despacho = trim($data["despacho"] ?? "");
    $tema = trim($data["tema"] ?? "");
    $funcionarios = $data["funcionarios"] ?? [];

    // Valida campos obligatorios
    if (!$nombre_oportunidad || !$fecha || !$socio) {
        http_response_code(400);
        echo json_encode(["error" => "Faltan campos obligatorios como nombre_oportunidad, fecha o socio"]);
        exit;
    }

    try {
        $stmt = $conn->prepare(query: "
            INSERT INTO oportunidades (
                nombre_oportunidad, modalidad, tipo_oportunidad, socio,
                sector, fecha, despacho, tema
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "ssssssss",
            $nombre_oportunidad,
            $modalidad,
            $tipo_oportunidad,
            $socio,
            $sector,
            $fecha,
            $despacho,
            $tema
        );

        $stmt->execute();
        $id_oportunidad = $conn->insert_id; //Consigue el id para la tabla de oportunidades_funcionario

        // Añade cada funcionario a su tabla (del mismo nombre)
        if (!empty($funcionarios) && is_array($funcionarios)) {
            foreach ($funcionarios as $nombre_funcionario) {
                $nombre_funcionario = trim($nombre_funcionario);

                // Busca si ya existe
                $stmtBuscar = $conn->prepare("SELECT id_funcionario FROM funcionario WHERE nombre_funcionario = ?");
                $stmtBuscar->bind_param("s", $nombre_funcionario);
                $stmtBuscar->execute();
                $resultado = $stmtBuscar->get_result();

                if ($resultado->num_rows > 0) {
                    $fila = $resultado->fetch_assoc();
                    $id_funcionario = $fila["id_funcionario"];
                }
                //Crea el funcionario si no existe 
                else {
                    $stmtInsert = $conn->prepare("INSERT INTO funcionario (nombre_funcionario) VALUES (?)");
                    $stmtInsert->bind_param("s", $nombre_funcionario);
                    $stmtInsert->execute();
                    $id_funcionario = $conn->insert_id;
                }

                // Se crea la relación para la tabla oportunidades_funcionario
                $stmtRelacion = $conn->prepare("INSERT INTO oportunidades_funcionario (id_oportunidades, id_funcionario) VALUES (?, ?)");
                $stmtRelacion->bind_param("ii", $id_oportunidad, $id_funcionario);
                $stmtRelacion->execute();
            }
        }

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
        "nombre_oportunidad", "modalidad", "tipo_oportunidad", "socio",
        "sector", "fecha", "despacho", "tema"
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

        // También edita la info de relaciones si viene
        if (isset($data["funcionarios"]) && is_array($data["funcionarios"])) {
            $nombres_funcionarios = $data["funcionarios"];

            // Elimina las relaciones actuales en oportunidades_funcionario
            $stmtDel = $conn->prepare("DELETE FROM oportunidades_funcionario WHERE id_oportunidades = ?");
            $stmtDel->bind_param("i", $id);
            $stmtDel->execute();

            foreach ($nombres_funcionarios as $nombre_funcionario) {
                $nombre_funcionario = trim($nombre_funcionario);

                // Verifica si ya existe el funcionario
                $stmtBuscar = $conn->prepare("SELECT id_funcionario FROM funcionario WHERE nombre_funcionario = ?");
                $stmtBuscar->bind_param("s", $nombre_funcionario);
                $stmtBuscar->execute();
                $res = $stmtBuscar->get_result();

                if ($res->num_rows > 0) {
                    $fila = $res->fetch_assoc();
                    $id_funcionario = $fila["id_funcionario"];
                }
                //Sino, lo crea 
                else {
                    $stmtInsert = $conn->prepare("INSERT INTO funcionario (nombre_funcionario) VALUES (?)");
                    $stmtInsert->bind_param("s", $nombre_funcionario);
                    $stmtInsert->execute();
                    $id_funcionario = $conn->insert_id;
                }

                // Inserta relación 
                $stmtRelacion = $conn->prepare("
                    INSERT INTO oportunidades_funcionario (id_oportunidades, id_funcionario) 
                    VALUES (?, ?)
                ");
                $stmtRelacion->bind_param("ii", $id, $id_funcionario);
                $stmtRelacion->execute();
            }
        }

        echo json_encode(["message" => "Oportunidad y funcionarios actualizados correctamente"]);

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
        // La relación se elimina automaticamente por el cascade en la base de datos
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