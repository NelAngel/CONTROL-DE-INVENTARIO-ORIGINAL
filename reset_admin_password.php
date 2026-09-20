<?php
// reset_admin_password.php - Restablece la contraseña del administrador a 123456
require_once 'config.php';
session_start();

if (isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true) {
    header('Location: admin.php');
    exit;
}

$new_hash = password_hash('123456', PASSWORD_DEFAULT);
$escaped_hash = $conn->real_escape_string($new_hash);

$u = $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('admin_user', 'admin') ON DUPLICATE KEY UPDATE setting_value = 'admin'");
$h = $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('admin_pass_hash', '$escaped_hash') ON DUPLICATE KEY UPDATE setting_value = '$escaped_hash'");

if ($u && $h) {
    header('Location: login.php?msg=Contraseña del administrador restablecida. Usuario: admin · Contraseña: 123456');
} else {
    header('Location: login.php?msg=' . urlencode('Error al restablecer: ' . $conn->error));
}
$conn->close();
?>