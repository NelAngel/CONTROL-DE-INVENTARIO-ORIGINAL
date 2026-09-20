<?php
// logout.php
session_start();

// Eliminar la cookie de "Recordarme"
setcookie('admin_remember', '', time() - 3600, '/');

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la sesión
session_destroy();

// Redirigir al login
header('Location: login.php');
exit;
?>