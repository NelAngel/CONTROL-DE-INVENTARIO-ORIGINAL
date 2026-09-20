<?php
// config.php - Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '123123');
define('DB_NAME', 'attendance_db');

// Crear conexión
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Configurar zona horaria (ajusta según tu ubicación)
date_default_timezone_set('America/Lima');

// Configurar charset para español
$conn->set_charset("utf8mb4");

// Función para obtener nombre del día en español
function obtenerDiaEspanol($fecha) {
    $dias = array('domingo','lunes','martes','miercoles','jueves','viernes','sabado');
    return $dias[date('w', strtotime($fecha))];
}

// Función para calcular horas trabajadas
function calcularHoras($entrada, $salida) {
    $diff = strtotime($salida) - strtotime($entrada);
    $horas = floor($diff / 3600);
    $minutos = floor(($diff % 3600) / 60);
    return sprintf("%02d:%02d:00", $horas, $minutos);
}

// Función para obtener IP del usuario
function obtenerIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}
?>