<?php
require_once 'config.php';
if (!isAuthenticated()) { 
    http_response_code(401);
    exit(); 
}

$id = $_GET['id'] ?? 0;

try {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT * FROM categorias WHERE id = ? AND activo = 1");
    $stmt->execute([$id]);
    $categoria = $stmt->fetch();
    
    if (!$categoria) {
        http_response_code(404);
        echo json_encode(['error' => 'Categoría no encontrada']);
        exit();
    }
    
    header('Content-Type: application/json');
    echo json_encode($categoria);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>