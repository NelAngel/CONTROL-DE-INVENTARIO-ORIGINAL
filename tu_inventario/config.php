<?php
// ============================================================
// CONFIGURACIÓN DEL SISTEMA DE INVENTARIO CON PEPS
// ============================================================

// ============ CONFIGURACIÓN DE BASE DE DATOS ============
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '123123');
define('DB_NAME', 'inventario_db');

// ============ CONFIGURACIÓN DE SESIÓN ============
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// FUNCIONES DE CONEXIÓN
// ============================================================

function getConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

// ============================================================
// FUNCIONES DE AUTENTICACIÓN Y PERMISOS
// ============================================================

function isAuthenticated() {
    return isset($_SESSION['user_id']);
}

function hasPermission($rol_required) {
    if (!isAuthenticated()) return false;
    $roles = ['CONSULTA' => 1, 'INVENTARIO' => 2, 'ADMIN' => 3];
    $user_rol = $_SESSION['user_rol'] ?? 'CONSULTA';
    return $roles[$user_rol] >= $roles[$rol_required];
}

// ============================================================
// FUNCIONES DE AUDITORÍA
// ============================================================

function logAudit($action, $table, $record_id, $old_data = null, $new_data = null) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO auditoria 
            (id_usuario, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_usuario) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            $table,
            $record_id,
            $old_data ? json_encode($old_data) : null,
            $new_data ? json_encode($new_data) : null,
            $_SERVER['REMOTE_ADDR'] ?? 'N/A'
        ]);
    } catch (PDOException $e) {
        error_log("Error en auditoría: " . $e->getMessage());
    }
}

// ============================================================
// FUNCIONES DE STOCK
// ============================================================

function getStock($producto_id) {
    return getStockLotes($producto_id);
}

// ============================================================
// FUNCIONES PEPS
// ============================================================

function registrarEntradaPEPS($pdo, $producto_id, $cantidad, $precio_unitario, $movimiento_id) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO lotes 
            (id_producto, id_movimiento, cantidad_inicial, cantidad_disponible, precio_unitario, activo) 
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $producto_id,
            $movimiento_id,
            $cantidad,
            $cantidad,
            $precio_unitario
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        throw new Exception("Error al registrar lote: " . $e->getMessage());
    }
}

function consumirLotesPEPS($pdo, $producto_id, $cantidad_salida, $movimiento_salida_id) {
    $cantidad_pendiente = $cantidad_salida;
    $costo_total = 0;
    $consumos = [];
    
    $stmt = $pdo->prepare("
        SELECT id, cantidad_disponible, precio_unitario, fecha_ingreso
        FROM lotes 
        WHERE id_producto = ? AND cantidad_disponible > 0 AND activo = 1
        ORDER BY fecha_ingreso ASC, id ASC
        FOR UPDATE
    ");
    $stmt->execute([$producto_id]);
    $lotes = $stmt->fetchAll();
    
    $stock_total = array_sum(array_column($lotes, 'cantidad_disponible'));
    if ($stock_total < $cantidad_salida) {
        throw new Exception("Stock insuficiente. Stock actual: $stock_total, Solicitado: $cantidad_salida");
    }
    
    foreach ($lotes as $lote) {
        if ($cantidad_pendiente <= 0) break;
        
        $cantidad_consumir = min($cantidad_pendiente, $lote['cantidad_disponible']);
        $subtotal = $cantidad_consumir * $lote['precio_unitario'];
        
        $stmt = $pdo->prepare("
            INSERT INTO consumo_lotes 
            (id_movimiento_salida, id_lote, cantidad_consumida, precio_unitario, subtotal) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $movimiento_salida_id,
            $lote['id'],
            $cantidad_consumir,
            $lote['precio_unitario'],
            $subtotal
        ]);
        
        $stmt = $pdo->prepare("
            UPDATE lotes 
            SET cantidad_disponible = cantidad_disponible - ? 
            WHERE id = ?
        ");
        $stmt->execute([$cantidad_consumir, $lote['id']]);
        
        $stmt = $pdo->prepare("
            UPDATE lotes 
            SET activo = 0 
            WHERE id = ? AND cantidad_disponible <= 0
        ");
        $stmt->execute([$lote['id']]);
        
        $cantidad_pendiente -= $cantidad_consumir;
        $costo_total += $subtotal;
        
        $consumos[] = [
            'lote_id' => $lote['id'],
            'cantidad' => $cantidad_consumir,
            'precio' => $lote['precio_unitario'],
            'subtotal' => $subtotal,
            'fecha_lote' => $lote['fecha_ingreso']
        ];
    }
    
    return [
        'costo_total' => $costo_total,
        'consumos' => $consumos
    ];
}

function getStockLotes($producto_id) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(cantidad_disponible), 0) as stock 
            FROM lotes 
            WHERE id_producto = ? AND activo = 1
        ");
        $stmt->execute([$producto_id]);
        $result = $stmt->fetch();
        return (int)($result['stock'] ?? 0);
    } catch (PDOException $e) {
        return 0;
    }
}

function getValorInventarioPEPS() {
    try {
        $pdo = getConnection();
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(cantidad_disponible * precio_unitario), 0) as total
            FROM lotes 
            WHERE cantidad_disponible > 0 AND activo = 1
        ");
        $result = $stmt->fetch();
        return (float)($result['total'] ?? 0);
    } catch (PDOException $e) {
        return 0;
    }
}

function getLotesProducto($producto_id) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT 
                l.*, 
                m.fecha_movimiento,
                m.comentario
            FROM lotes l
            LEFT JOIN movimientos m ON l.id_movimiento = m.id
            WHERE l.id_producto = ? AND l.cantidad_disponible > 0 AND l.activo = 1
            ORDER BY l.fecha_ingreso ASC
        ");
        $stmt->execute([$producto_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getGananciaTotalPEPS() {
    try {
        $pdo = getConnection();
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(ganancia), 0) as total
            FROM movimientos 
            WHERE tipo_movimiento = 'SALIDA'
        ");
        $result = $stmt->fetch();
        return (float)($result['total'] ?? 0);
    } catch (PDOException $e) {
        return 0;
    }
}

function getTotalVentas() {
    try {
        $pdo = getConnection();
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(total), 0) as total
            FROM movimientos 
            WHERE tipo_movimiento = 'SALIDA'
        ");
        $result = $stmt->fetch();
        return (float)($result['total'] ?? 0);
    } catch (PDOException $e) {
        return 0;
    }
}

function getCostoVentasPEPS() {
    try {
        $pdo = getConnection();
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(costo_peps), 0) as total
            FROM movimientos 
            WHERE tipo_movimiento = 'SALIDA'
        ");
        $result = $stmt->fetch();
        return (float)($result['total'] ?? 0);
    } catch (PDOException $e) {
        return 0;
    }
}

function getCostoConsumo() {
    try {
        $pdo = getConnection();
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(COALESCE(costo_peps, total)), 0) as total
            FROM movimientos 
            WHERE tipo_movimiento = 'CONSUMO'
        ");
        $result = $stmt->fetch();
        return (float)($result['total'] ?? 0);
    } catch (PDOException $e) {
        return 0;
    }
}

function checkLowStockPEPS() {
    try {
        $pdo = getConnection();
        $stmt = $pdo->query("
            SELECT 
                p.id, 
                p.nombre, 
                p.codigo, 
                p.stock_minimo,
                COALESCE(SUM(l.cantidad_disponible), 0) as stock_actual
            FROM productos p
            LEFT JOIN lotes l ON p.id = l.id_producto AND l.activo = 1
            WHERE p.activo = 1
            GROUP BY p.id
            HAVING stock_actual <= p.stock_minimo
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getStockActual($producto_id) {
    return getStockLotes($producto_id);
}

function conteoFueAjustado($pdo, $conteo) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM movimientos
            WHERE id_producto = ?
              AND (
                  comentario LIKE ?
                  OR comentario LIKE ?
              )
        ");
        $stmt->execute([
            (int)$conteo['id_producto'],
            '%Ajuste manual por inventario físico ID: ' . (int)$conteo['id'] . '%',
            '%conteo: ' . (int)$conteo['cantidad_contada'] . ', sistema: ' . (int)$conteo['cantidad_sistema'] . '%'
        ]);
        return (int)$stmt->fetch()['total'] > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// ============================================================
// FUNCIONES DE ESTADÍSTICAS
// ============================================================

function getTotalProductos() {
    try {
        $pdo = getConnection();
        return (int)$pdo->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1")->fetch()['total'];
    } catch (PDOException $e) { return 0; }
}

function getTotalCategorias() {
    try {
        $pdo = getConnection();
        return (int)$pdo->query("SELECT COUNT(*) as total FROM categorias WHERE activo = 1")->fetch()['total'];
    } catch (PDOException $e) { return 0; }
}

function getTotalProveedores() {
    try {
        $pdo = getConnection();
        return (int)$pdo->query("SELECT COUNT(*) as total FROM proveedores WHERE activo = 1")->fetch()['total'];
    } catch (PDOException $e) { return 0; }
}

function getMovimientosHoy() {
    try {
        $pdo = getConnection();
        return (int)$pdo->query("SELECT COUNT(*) as total FROM movimientos WHERE DATE(fecha_movimiento) = CURDATE()")->fetch()['total'];
    } catch (PDOException $e) { return 0; }
}

function getMovimientosMes() {
    try {
        $pdo = getConnection();
        return (int)$pdo->query("SELECT COUNT(*) as total FROM movimientos WHERE MONTH(fecha_movimiento) = MONTH(CURDATE()) AND YEAR(fecha_movimiento) = YEAR(CURDATE())")->fetch()['total'];
    } catch (PDOException $e) { return 0; }
}

function getTotalUsuarios() {
    try {
        $pdo = getConnection();
        return (int)$pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = 1")->fetch()['total'];
    } catch (PDOException $e) { return 0; }
}

function getTotalConteos() {
    try {
        $pdo = getConnection();
        return (int)$pdo->query("SELECT COUNT(*) as total FROM inventario_fisico WHERE fecha_conteo >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch()['total'];
    } catch (PDOException $e) { return 0; }
}

// ============================================================
// FUNCIONES AUXILIARES  ← ¡AQUÍ ESTÁ LA CLAVE!
// ============================================================

/**
 * Formatear moneda en Soles
 */
function moneda($amount, $decimales = 2) {
    return 'S/ ' . number_format($amount, $decimales);
}

function formatSoles($amount, $decimales = 2) {
    return 'S/ ' . number_format($amount, $decimales);
}

function formatMoney($amount) {
    return 'S/ ' . number_format($amount, 2);
}

function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return 'N/A';
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    if (empty($datetime)) return 'N/A';
    return date($format, strtotime($datetime));
}

function tableExists($table_name) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table_name]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function getNextProductCode($prefix = 'PROD-') {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT codigo FROM productos WHERE codigo LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$prefix . '%']);
        $last = $stmt->fetch();
        
        if ($last) {
            $numero = intval(substr($last['codigo'], strlen($prefix))) + 1;
            return $prefix . str_pad($numero, 4, '0', STR_PAD_LEFT);
        }
        return $prefix . '0001';
    } catch (PDOException $e) {
        return $prefix . '0001';
    }
}

function verificarInstalacionPEPS() {
    $tablas_requeridas = ['lotes', 'consumo_lotes'];
    $faltantes = [];
    
    foreach ($tablas_requeridas as $tabla) {
        if (!tableExists($tabla)) {
            $faltantes[] = $tabla;
        }
    }
    
    return [
        'instalado' => empty($faltantes),
        'faltantes' => $faltantes
    ];
}

?>