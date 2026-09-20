<?php
// logout_worker.php - Cerrar sesión del trabajador
session_start();

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la sesión
session_destroy();

// Redirigir al login de trabajadores
header('Location: login_worker.php');
exit;
?>