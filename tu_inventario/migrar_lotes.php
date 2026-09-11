<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

if (!isAuthenticated() || !hasPermission('ADMIN')) {
    die('❌ Solo administradores pueden ejecutar esta migración');
}

$pdo = getConnection();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<title>Migración PEPS</title>";
echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
echo "<style>body { padding: 20px; font-family: Inter, sans-serif; }</style>";
echo "</head><body>";

echo "<h2>🔄 Migración de Movimientos Antiguos a Lotes PEPS</h2>";
echo "<hr>";

// ============ PASO 1: VERIFICAR ESTADO ACTUAL ============
echo "<h3>Paso 1: Verificando estado actual...</h3>";

$total_movimientos = $pdo->query("SELECT COUNT(*) as total FROM movimientos")->fetch()['total'];
$total_lotes = $pdo->query("SELECT COUNT(*) as total FROM lotes")->fetch()['total'];

echo "<div class='alert alert-info'>";
echo "<strong>Movimientos existentes:</strong> $total_movimientos<br>";
echo "<strong>Lotes existentes:</strong> $total_lotes";
echo "</div>";

if ($total_movimientos == 0) {
    die("<div class='alert alert-warning'>No hay movimientos para migrar</div>");
}

// ============ PASO 2: LIMPIAR LOTES EXISTENTES ============
echo "<h3>Paso 2: Limpiando lotes existentes...</h3>";

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("DELETE FROM consumo_lotes");
    $pdo->exec("DELETE FROM lotes");
    $pdo->exec("ALTER TABLE lotes AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE consumo_lotes AUTO_INCREMENT = 1");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "<div class='alert alert-success'>✅ Tablas limpiadas correctamente</div>";
} catch (PDOException $e) {
    die("<div class='alert alert-danger'>❌ Error al limpiar: " . $e->getMessage() . "</div>");
}

// ============ PASO 3: OBTENER MOVIMIENTOS ORDENADOS POR FECHA ============
echo "<h3>Paso 3: Procesando movimientos en orden cronológico...</h3>";

$movimientos = $pdo->query("
    SELECT * FROM movimientos 
    ORDER BY fecha_movimiento ASC, id ASC
")->fetchAll();

echo "<p>📊 Total a procesar: <strong>" . count($movimientos) . "</strong></p>";

// ============ PASO 4: PROCESAR CADA MOVIMIENTO ============
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; max-height: 400px; overflow-y: auto;'>";

$total_entradas = 0;
$total_salidas = 0;
$total_lotes = 0;
$total_consumos = 0;
$errores = [];

foreach ($movimientos as $mov) {
    try {
        if ($mov['tipo_movimiento'] == 'ENTRADA' || $mov['tipo_movimiento'] == 'AJUSTE') {
            // Crear lote para esta entrada
            $stmt = $pdo->prepare("
                INSERT INTO lotes 
                (id_producto, id_movimiento, cantidad_inicial, cantidad_disponible, precio_unitario, fecha_ingreso, activo) 
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $mov['id_producto'],
                $mov['id'],
                $mov['cantidad'],
                $mov['cantidad'],
                $mov['precio_unitario'],
                $mov['fecha_movimiento']
            ]);
            
            $total_entradas++;
            $total_lotes++;
            
            echo "<div>✅ Lote creado: Mov #{$mov['id']} - {$mov['cantidad']} unidades a $" . number_format($mov['precio_unitario'], 2) . " ({$mov['fecha_movimiento']})</div>";
            
        } elseif ($mov['tipo_movimiento'] == 'SALIDA') {
            $cantidad_pendiente = $mov['cantidad'];
            $costo_total = 0;
            
            // Obtener lotes disponibles ordenados por fecha (FIFO)
            $stmt = $pdo->prepare("
                SELECT id, cantidad_disponible, precio_unitario 
                FROM lotes 
                WHERE id_producto = ? AND cantidad_disponible > 0 AND activo = 1
                ORDER BY fecha_ingreso ASC, id ASC
            ");
            $stmt->execute([$mov['id_producto']]);
            $lotes = $stmt->fetchAll();
            
            foreach ($lotes as $lote) {
                if ($cantidad_pendiente <= 0) break;
                
                $cantidad_consumir = min($cantidad_pendiente, $lote['cantidad_disponible']);
                $subtotal = $cantidad_consumir * $lote['precio_unitario'];
                
                // Registrar consumo
                $stmt = $pdo->prepare("
                    INSERT INTO consumo_lotes 
                    (id_movimiento_salida, id_lote, cantidad_consumida, precio_unitario, subtotal, fecha_consumo) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $mov['id'],
                    $lote['id'],
                    $cantidad_consumir,
                    $lote['precio_unitario'],
                    $subtotal,
                    $mov['fecha_movimiento']
                ]);
                
                // Actualizar lote
                $stmt = $pdo->prepare("
                    UPDATE lotes 
                    SET cantidad_disponible = cantidad_disponible - ? 
                    WHERE id = ?
                ");
                $stmt->execute([$cantidad_consumir, $lote['id']]);
                
                // Desactivar si se agotó
                $stmt = $pdo->prepare("
                    UPDATE lotes SET activo = 0 
                    WHERE id = ? AND cantidad_disponible <= 0
                ");
                $stmt->execute([$lote['id']]);
                
                $cantidad_pendiente -= $cantidad_consumir;
                $costo_total += $subtotal;
                $total_consumos++;
            }
            
            // Actualizar el movimiento con costo PEPS y ganancia
            $ganancia = $mov['total'] - $costo_total;
            
            $stmt = $pdo->prepare("
                UPDATE movimientos 
                SET costo_peps = ?, ganancia = ? 
                WHERE id = ?
            ");
            $stmt->execute([$costo_total, $ganancia, $mov['id']]);
            
            $total_salidas++;
            
            echo "<div style='color: #0d6efd;'>💰 Salida ID {$mov['id']}: Costo PEPS = $" . number_format($costo_total, 2) . " | Ganancia = $" . number_format($ganancia, 2) . "</div>";
        }
        
    } catch (Exception $e) {
        $errores[] = "Movimiento ID {$mov['id']}: " . $e->getMessage();
        echo "<div style='color: red;'>❌ Error Mov #{$mov['id']}: " . $e->getMessage() . "</div>";
    }
}

echo "</div>";

// ============ PASO 5: RESUMEN ============
echo "<hr>";
echo "<h3>📊 Resumen de la Migración</h3>";
echo "<div class='row'>";
echo "<div class='col-md-3'><div class='alert alert-success'><strong>Entradas:</strong> $total_entradas</div></div>";
echo "<div class='col-md-3'><div class='alert alert-warning'><strong>Salidas:</strong> $total_salidas</div></div>";
echo "<div class='col-md-3'><div class='alert alert-info'><strong>Lotes creados:</strong> $total_lotes</div></div>";
echo "<div class='col-md-3'><div class='alert alert-primary'><strong>Consumos:</strong> $total_consumos</div></div>";
echo "</div>";

if (!empty($errores)) {
    echo "<div class='alert alert-danger'>";
    echo "<h5>⚠️ Errores encontrados:</h5>";
    echo "<ul>";
    foreach ($errores as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
    echo "</div>";
}

// ============ PASO 6: VERIFICACIÓN POR PRODUCTO ============
echo "<hr>";
echo "<h3>🔍 Verificación por Producto</h3>";

$stmt = $pdo->query("
    SELECT 
        p.id,
        p.codigo,
        p.nombre,
        COALESCE(SUM(l.cantidad_disponible), 0) as stock_lotes
    FROM productos p
    LEFT JOIN lotes l ON p.id = l.id_producto AND l.activo = 1
    WHERE p.activo = 1
    GROUP BY p.id
");

echo "<table class='table table-striped'>";
echo "<thead><tr><th>Código</th><th>Producto</th><th>Stock en Lotes</th><th>Lotes Activos</th></tr></thead><tbody>";

while ($row = $stmt->fetch()) {
    $stmt2 = $pdo->prepare("SELECT COUNT(*) as total FROM lotes WHERE id_producto = ? AND activo = 1");
    $stmt2->execute([$row['id']]);
    $lotes_activos = $stmt2->fetch()['total'];
    
    echo "<tr>";
    echo "<td>{$row['codigo']}</td>";
    echo "<td>{$row['nombre']}</td>";
    echo "<td><strong>{$row['stock_lotes']}</strong></td>";
    echo "<td>{$lotes_activos}</td>";
    echo "</tr>";
}
echo "</tbody></table>";

// ============ PASO 7: VER DETALLE DE LOTES ============
echo "<hr>";
echo "<h3>📦 Detalle de Lotes por Producto</h3>";

$stmt = $pdo->query("
    SELECT 
        l.*,
        p.codigo as producto_codigo,
        p.nombre as producto_nombre
    FROM lotes l
    JOIN productos p ON l.id_producto = p.id
    WHERE l.activo = 1 AND l.cantidad_disponible > 0
    ORDER BY l.id_producto, l.fecha_ingreso ASC
");

echo "<table class='table table-striped table-sm'>";
echo "<thead><tr><th>Producto</th><th>Lote ID</th><th>Cant. Inicial</th><th>Cant. Disponible</th><th>Precio</th><th>Fecha</th></tr></thead><tbody>";

while ($row = $stmt->fetch()) {
    echo "<tr>";
    echo "<td>{$row['producto_codigo']} - {$row['producto_nombre']}</td>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['cantidad_inicial']}</td>";
    echo "<td><strong>{$row['cantidad_disponible']}</strong></td>";
    echo "<td>$" . number_format($row['precio_unitario'], 2) . "</td>";
    echo "<td>{$row['fecha_ingreso']}</td>";
    echo "</tr>";
}
echo "</tbody></table>";

// ============ PASO 8: ESTADÍSTICAS FINALES ============
echo "<hr>";
echo "<h3>💰 Estadísticas Finales</h3>";

$valor = getValorInventarioPEPS();
$ganancia = getGananciaTotalPEPS();
$total_ventas = getTotalVentas();
$costo_ventas = getCostoVentasPEPS();

echo "<div class='row'>";
echo "<div class='col-md-3'><div class='alert alert-info'><strong>Valor Inventario:</strong><br>$" . number_format($valor, 2) . "</div></div>";
echo "<div class='col-md-3'><div class='alert alert-success'><strong>Total Ventas:</strong><br>$" . number_format($total_ventas, 2) . "</div></div>";
echo "<div class='col-md-3'><div class='alert alert-warning'><strong>Costo Ventas:</strong><br>$" . number_format($costo_ventas, 2) . "</div></div>";
echo "<div class='col-md-3'><div class='alert alert-primary'><strong>Ganancia Total:</strong><br>$" . number_format($ganancia, 2) . "</div></div>";
echo "</div>";

echo "<hr>";
echo "<div class='alert alert-success'>";
echo "<h4>✅ Migración completada</h4>";
echo "<p>Ahora el sistema PEPS tomará correctamente los lotes más antiguos primero.</p>";
echo "<p><a href='movimientos.php' class='btn btn-primary'>Ir a Movimientos</a> ";
echo "<a href='index.php' class='btn btn-secondary'>Ir al Dashboard</a></p>";
echo "</div>";

echo "</body></html>";
?>