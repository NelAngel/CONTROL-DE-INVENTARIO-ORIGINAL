<?php
// reset_password.php - Resetear contraseña de empleado
require_once 'config.php';
session_start();

if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $new_password = '123456';
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    $sql = "UPDATE employees SET password_hash = '$password_hash', login_attempts = 0 WHERE id = $id";
    if ($conn->query($sql)) {
        // Obtener el nombre del empleado para el mensaje
        $name_sql = "SELECT full_name FROM employees WHERE id = $id";
        $name_result = $conn->query($name_sql);
        $name = $name_result->fetch_assoc()['full_name'] ?? 'Empleado';
        header("Location: admin.php?success=Contraseña de $name restablecida a: 123456");
    } else {
        header('Location: admin.php?error=' . $conn->error);
    }
} else {
    header('Location: admin.php');
}
$conn->close();
?>