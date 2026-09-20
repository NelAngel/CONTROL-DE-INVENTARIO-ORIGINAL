<?php
require_once 'config.php';
if (!isAuthenticated()) { 
    http_response_code(401);
    exit(); 
}

$id = $_GET['id'] ?? 0;

try {
    $pdo = getConnection();
    
    // VERIFICAR QUE EL PRODUCTO EXISTE
    $stmt = $pdo->prepare("SELECT id, nombre, codigo, activo FROM productos WHERE id = ?");
    $stmt->execute([$id]);
    $producto = $stmt->fetch();
    
    if (!$producto) {
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Producto no encontrado',
            'stock' => 0,
            'nombre' => 'Producto no existe',
            'codigo' => 'N/A'
        ]);
        exit();
    }
    
    if ($producto['activo'] == 0) {
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Producto inactivo',
            'stock' => 0,
            'nombre' => $producto['nombre'] . ' (INACTIVO)',
            'codigo' => $producto['codigo']
        ]);
        exit();
    }
    
    // ✅ CALCULAR STOCK DESDE LOTES (PEPS) - ¡ESTO ES LO CORRECTO!
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(cantidad_disponible), 0) as stock 
        FROM lotes 
        WHERE id_producto = ? AND activo = 1
    ");
    $stmt->execute([$id]);
    $result = $stmt->fetch();
    
    $stock = (int)($result['stock'] ?? 0);
    
    // ENVIAR RESPUESTA
    header('Content-Type: application/json');
    echo json_encode([
        'stock' => $stock,
        'nombre' => $producto['nombre'],
        'codigo' => $producto['codigo'],
        'id' => $producto['id'],
        'mensaje' => 'Stock calculado con PEPS'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Error en la base de datos: ' . $e->getMessage(),
        'stock' => 0
    ]);
}
?>