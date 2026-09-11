<?php
require_once 'config.php';
if (!isAuthenticated()) { 
    http_response_code(401);
    exit(); 
}

$id = $_GET['id'] ?? 0;

try {
    $pdo = getConnection();
    
    // Calcular stock actual
    $stmt = $pdo->prepare("
        SELECT COALESCE(
            (SELECT SUM(CASE WHEN m.tipo_movimiento IN ('ENTRADA', 'AJUSTE') THEN m.cantidad ELSE 0 END) -
             SUM(CASE WHEN m.tipo_movimiento IN ('SALIDA', 'TRANSFERENCIA') THEN m.cantidad ELSE 0 END)
             FROM movimientos m WHERE m.id_producto = ?), 0) as stock
    ");
    $stmt->execute([$id]);
    $result = $stmt->fetch();
    
    // También obtener info del producto
    $stmt = $pdo->prepare("SELECT nombre, codigo FROM productos WHERE id = ? AND activo = 1");
    $stmt->execute([$id]);
    $producto = $stmt->fetch();
    
    header('Content-Type: application/json');
    echo json_encode([
        'stock' => $result['stock'] ?? 0,
        'nombre' => $producto['nombre'] ?? '',
        'codigo' => $producto['codigo'] ?? ''
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>