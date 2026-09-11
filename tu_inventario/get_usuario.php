<?php
require_once 'config.php';

if (!isAuthenticated()) { 
    http_response_code(401);
    exit(); 
}

if (!hasPermission('ADMIN')) {
    http_response_code(403);
    exit(); 
}

$id = $_GET['id'] ?? 0;

try {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT id, usuario, nombre_completo, email, rol, activo FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        http_response_code(404);
        echo json_encode(['error' => 'Usuario no encontrado']);
        exit();
    }
    
    header('Content-Type: application/json');
    echo json_encode($usuario);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>