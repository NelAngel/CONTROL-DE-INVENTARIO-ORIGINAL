<?php
// toggle_employee.php - Activar/Desactivar empleado
require_once 'config.php';
session_start();

if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Obtener estado actual
    $sql = "SELECT is_active FROM employees WHERE id = $id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $new_status = $row['is_active'] ? 0 : 1;
        
        $update_sql = "UPDATE employees SET is_active = $new_status WHERE id = $id";
        if ($conn->query($update_sql)) {
            header('Location: admin.php?success=Estado actualizado');
        } else {
            header('Location: admin.php?error=' . $conn->error);
        }
    } else {
        header('Location: admin.php?error=Empleado no encontrado');
    }
} else {
    header('Location: admin.php');
}
$conn->close();
?>