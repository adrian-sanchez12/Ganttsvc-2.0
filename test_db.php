<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = 'localhost';
$db = 'ganttmep_gantt';
$user = 'ganttmep_daic';
$pass = 'Daic21320112.';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die('Error de conexión a la base de datos: ' . $conn->connect_error);
} else {
    echo 'Conexión exitosa a la base de datos.';
}
?>