<?php
// reset_device.php - Permite al admin autorizar un nuevo dispositivo
require_once 'config.php';
session_start();

if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $sql = "UPDATE employees 
            SET device_user_agent = NULL,
                device_ip = NULL,
                device_token = NULL,
                device_registered_at = NULL,
                device_reset_required = TRUE
            WHERE id = $id";
    
    if ($conn->query($sql)) {
        header('Location: admin.php?success=Dispositivo reseteado. El empleado puede registrarse desde un nuevo celular.');
    } else {
        header('Location: admin.php?error=' . urlencode($conn->error));
    }
} else {
    header('Location: admin.php');
}
$conn->close();
?>