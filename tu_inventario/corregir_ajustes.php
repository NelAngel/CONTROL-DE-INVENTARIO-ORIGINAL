<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

if (!isAuthenticated() || !hasPermission('ADMIN')) {
    die('❌ Solo administradores');
}

$pdo = getConnection();

echo "<!DOCTYPE html><html><head>";
echo "<meta charset='UTF-8'>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "<style>body { padding: 30px; font-family: 'Inter', sans-serif; }</style>";
echo "</head><body>";

echo "<h2>🔧 Corrección del PRODUCTO2 (ID 19)</h2>";
echo "<hr>";

// ============ 1. ANÁLISIS ============
echo "<h3>📊 Análisis del Producto</h3>";

// Stock en lotes
$stmt = $pdo->query("SELECT COALESCE(SUM(cantidad_disponible), 0) as total FROM lotes WHERE id_producto = 19 AND activo = 1");
$stock_lotes = $stmt->fetch()['total'];

// Stock en movimientos
$stmt = $pdo->query("
    SELECT COALESCE(
        SUM(CASE WHEN tipo_movimiento IN ('ENTRADA', 'AJUSTE') THEN cantidad ELSE 0 END) -
        SUM(CASE WHEN tipo_movimiento IN ('SALIDA', 'TRANSFERENCIA', 'CONSUMO') THEN cantidad ELSE 0 END), 0
    ) as total
    FROM movimientos WHERE id_producto = 19
");
$stock_movimientos = $stmt->fetch()['total'];

$diferencia = $stock_movimientos - $stock_lotes;

echo "<table class='table table-striped'>";
echo "<tr><td>Stock en Lotes:</td><td><strong>$stock_lotes</strong></td></tr>";
echo "<tr><td>Stock en Movimientos:</td><td><strong>$stock_movimientos</strong></td></tr>";
echo "<tr><td>Diferencia:</td><td><strong class='text-danger'>$diferencia</strong></td></tr>";
echo "</table>";

// ============ 2. VER MOVIMIENTOS SIN LOTE ============
echo "<h3>📋 Movimientos sin lote</h3>";

$stmt = $pdo->query("
    SELECT 
        m.id,
        m.tipo_movimiento,
        m.cantidad,
        m.precio_unitario,
        m.fecha_movimiento,
        m.comentario
    FROM movimientos m
    WHERE m.id_producto = 19
    AND NOT EXISTS (SELECT 1 FROM lotes l WHERE l.id_movimiento = m.id)
    ORDER BY m.id ASC
");
$sin_lote = $stmt->fetchAll();

if (empty($sin_lote)) {
    echo "<div class='alert alert-success'>✅ No hay movimientos sin lote</div>";
} else {
    echo "<table class='table table-striped'>";
    echo "<thead><tr><th>ID</th><th>Tipo</th><th>Cantidad</th><th>Precio</th><th>Fecha</th><th>Comentario</th></tr></thead><tbody>";
    foreach ($sin_lote as $m) {
        echo "<tr>";
        echo "<td>{$m['id']}</td>";
        echo "<td>{$m['tipo_movimiento']}</td>";
        echo "<td>{$m['cantidad']}</td>";
        echo "<td>S/ " . number_format($m['precio_unitario'], 2) . "</td>";
        echo "<td>" . date('d/m/Y H:i', strtotime($m['fecha_movimiento'])) . "</td>";
        echo "<td>{$m['comentario']}</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
}

// ============ 3. VER CONSUMOS DE LOTES ============
echo "<h3>📋 Consumos de lotes (salidas)</h3>";

$stmt = $pdo->query("
    SELECT 
        cl.id,
        cl.id_movimiento_salida,
        cl.id_lote,
        cl.cantidad_consumida,
        cl.precio_unitario,
        cl.subtotal,
        m.tipo_movimiento as tipo_mov,
        m.cantidad as cantidad_mov
    FROM consumo_lotes cl
    LEFT JOIN movimientos m ON cl.id_movimiento_salida = m.id
    WHERE cl.id_lote IN (SELECT id FROM lotes WHERE id_producto = 19)
    ORDER BY cl.id ASC
");

$consumos = $stmt->fetchAll();

if (empty($consumos)) {
    echo "<div class='alert alert-info'>No hay consumos registrados</div>";
} else {
    echo "<table class='table table-striped'>";
    echo "<thead><tr><th>ID</th><th>Mov Salida</th><th>Lote</th><th>Cantidad</th><th>Precio</th><th>Subtotal</th></tr></thead><tbody>";
    foreach ($consumos as $c) {
        echo "<tr>";
        echo "<td>{$c['id']}</td>";
        echo "<td>{$c['id_movimiento_salida']} ({$c['tipo_mov']})</td>";
        echo "<td>{$c['id_lote']}</td>";
        echo "<td>{$c['cantidad_consumida']}</td>";
        echo "<td>S/ " . number_format($c['precio_unitario'], 2) . "</td>";
        echo "<td>S/ " . number_format($c['subtotal'], 2) . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
}

// ============ 4. CALCULAR DIFERENCIA REAL ============
echo "<hr>";
echo "<h3>🎯 Cálculo de la Diferencia</h3>";

$stmt = $pdo->query("
    SELECT COALESCE(SUM(cantidad), 0) as total
    FROM movimientos 
    WHERE id_producto = 19 AND tipo_movimiento IN ('ENTRADA', 'AJUSTE')
");
$total_entradas = $stmt->fetch()['total'];

$stmt = $pdo->query("
    SELECT COALESCE(SUM(cantidad), 0) as total
    FROM movimientos 
    WHERE id_producto = 19 AND tipo_movimiento IN ('SALIDA', 'TRANSFERENCIA', 'CONSUMO')
");
$total_salidas = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COALESCE(SUM(cantidad_inicial), 0) as total FROM lotes WHERE id_producto = 19");
$total_lotes_creados = $stmt->fetch()['total'];

$stmt = $pdo->query("
    SELECT COALESCE(SUM(cl.cantidad_consumida), 0) as total
    FROM consumo_lotes cl
    WHERE cl.id_lote IN (SELECT id FROM lotes WHERE id_producto = 19)
");
$total_consumos = $stmt->fetch()['total'];

echo "<table class='table table-striped'>";
echo "<tr><td>Total Entradas (movimientos):</td><td><strong>$total_entradas</strong></td></tr>";
echo "<tr><td>Total Salidas (movimientos):</td><td><strong>$total_salidas</strong></td></tr>";
echo "<tr><td>Total Lotes Creados:</td><td><strong>$total_lotes_creados</strong></td></tr>";
echo "<tr><td>Total Consumos de Lotes:</td><td><strong>$total_consumos</strong></td></tr>";
echo "</table>";

// ============ 5. BOTÓN DE CORRECCIÓN ============
echo "<hr>";
echo "<h3>💡 Solución</h3>";

if ($diferencia == 0) {
    echo "<div class='alert alert-success'>";
    echo "✅ <strong>Todo está correcto.</strong> No hay diferencia.";
    echo "</div>";
} else {
    echo "<div class='alert alert-warning'>";
    echo "⚠️ Hay una diferencia de <strong>$diferencia</strong> unidades.";
    echo "<br>Haz clic en el botón para corregir.";
    echo "</div>";
    
    // Mostrar el formulario con el botón
    echo "<form method='POST'>";
    echo "<input type='hidden' name='corregir' value='1'>";
    echo "<button type='submit' class='btn btn-warning btn-lg'>";
    echo "🔧 Corregir Lotes Faltantes";
    echo "</button>";
    echo "</form>";
}

// ============ 6. PROCESAR LA CORRECCIÓN ============
if (isset($_POST['corregir'])) {
    try {
        $pdo->beginTransaction();
        
        // 1. Crear lotes faltantes para movimientos sin lote
        $stmt = $pdo->query("
            SELECT 
                m.id,
                m.cantidad,
                m.precio_unitario,
                m.fecha_movimiento,
                p.precio_compra
            FROM movimientos m
            JOIN productos p ON m.id_producto = p.id
            WHERE m.id_producto = 19
            AND m.tipo_movimiento IN ('ENTRADA', 'AJUSTE')
            AND NOT EXISTS (SELECT 1 FROM lotes l WHERE l.id_movimiento = m.id)
        ");
        $faltantes = $stmt->fetchAll();
        
        $creados = 0;
        foreach ($faltantes as $f) {
            $precio = $f['precio_unitario'] > 0 ? $f['precio_unitario'] : $f['precio_compra'];
            if ($precio <= 0) $precio = 100;
            
            $stmt = $pdo->prepare("
                INSERT INTO lotes 
                (id_producto, id_movimiento, cantidad_inicial, cantidad_disponible, precio_unitario, fecha_ingreso, activo) 
                VALUES (19, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $f['id'],
                $f['cantidad'],
                $f['cantidad'],
                $precio,
                $f['fecha_movimiento']
            ]);
            $creados++;
        }
        
        // 2. Recalcular stock de lotes
        $stmt = $pdo->query("SELECT COALESCE(SUM(cantidad_disponible), 0) as total FROM lotes WHERE id_producto = 19 AND activo = 1");
        $nuevo_stock = $stmt->fetch()['total'];
        
        $pdo->commit();
        
        echo "<div class='alert alert-success'>";
        echo "<h4>✅ Corrección completada</h4>";
        echo "Lotes creados: <strong>$creados</strong><br>";
        echo "Nuevo stock en lotes: <strong>$nuevo_stock</strong><br>";
        echo "Stock en movimientos: <strong>$stock_movimientos</strong><br>";
        if ($nuevo_stock == $stock_movimientos) {
            echo "<strong class='text-success'>🎉 ¡Sincronizado correctamente!</strong>";
        }
        echo "</div>";
        
        echo "<meta http-equiv='refresh' content='3;url=corregir_producto2.php'>";
        echo "<p>Recargando en 3 segundos...</p>";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div class='alert alert-danger'>❌ Error: " . $e->getMessage() . "</div>";
    }
}

echo "<hr>";
echo "<a href='index.php' class='btn btn-primary'>Ir al Dashboard</a>";
echo " ";
echo "<a href='productos.php' class='btn btn-secondary'>Ir a Productos</a>";
echo "</body></html>";
?>