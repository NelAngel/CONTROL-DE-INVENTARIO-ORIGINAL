<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
if (!isAuthenticated()) { 
    header('Location: login.php'); 
    exit(); 
}

$pdo = getConnection();
$message = '';

// ============ PROCESAR MOVIMIENTO ============
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create') {
    if (!hasPermission('INVENTARIO')) {
        $message = '❌ No tienes permiso para realizar movimientos';
    } else {
        try {
            $pdo->beginTransaction();
            
            $tipo = $_POST['tipo_movimiento'];
            $producto_id = (int)$_POST['id_producto'];
            $cantidad = (int)$_POST['cantidad'];
            $comentario = $_POST['comentario'] ?? '';
            
            // Validaciones
            if ($cantidad <= 0) {
                throw new Exception("La cantidad debe ser mayor a 0");
            }
            
            if ($producto_id <= 0) {
                throw new Exception("Debes seleccionar un producto");
            }
            
            // Obtener datos del producto
            $stmt = $pdo->prepare("SELECT precio_compra, precio_venta FROM productos WHERE id = ? AND activo = 1");
            $stmt->execute([$producto_id]);
            $producto = $stmt->fetch();
            
            if (!$producto) {
                throw new Exception("Producto no encontrado o inactivo");
            }
            
            // ============ LÓGICA SEGÚN TIPO ============
            if ($tipo == 'ENTRADA') {
                $precio_unitario = !empty($_POST['precio_unitario']) ? (float)$_POST['precio_unitario'] : $producto['precio_compra'];
                $total = $cantidad * $precio_unitario;
                $costo_peps = 0;
                $ganancia = 0;
                
            } elseif ($tipo == 'SALIDA') {
                $precio_unitario = $producto['precio_venta'];
                $total = $cantidad * $precio_unitario;
                
                $stock_disponible = getStockLotes($producto_id);
                if ($stock_disponible < $cantidad) {
                    throw new Exception("Stock insuficiente. Disponible: $stock_disponible, Solicitado: $cantidad");
                }
                
                $costo_peps = 0;
                $ganancia = 0;
                
            } elseif ($tipo == 'CONSUMO') {
                // CONSUMO: Descuenta stock pero NO genera venta ni ganancia
                $precio_unitario = $producto['precio_compra'];
                $total = $cantidad * $precio_unitario;
                
                $stock_disponible = getStockLotes($producto_id);
                if ($stock_disponible < $cantidad) {
                    throw new Exception("Stock insuficiente. Disponible: $stock_disponible, Solicitado: $cantidad");
                }
                
                $costo_peps = 0;
                $ganancia = 0;
                
            } else {
                // AJUSTE y TRANSFERENCIA no se registran por esta vía.
                // Los ajustes de stock deben realizarse desde Inventario Físico.
                throw new Exception("Tipo de movimiento no soportado");
            }
            
            // ============ PASO 1: REGISTRAR MOVIMIENTO ============
            $stmt = $pdo->prepare("
                INSERT INTO movimientos 
                (id_producto, tipo_movimiento, cantidad, precio_unitario, total, costo_peps, ganancia, id_usuario, comentario) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $producto_id, 
                $tipo, 
                $cantidad, 
                $precio_unitario, 
                $total, 
                $costo_peps, 
                $ganancia, 
                $_SESSION['user_id'], 
                $comentario
            ]);
            $movimiento_id = $pdo->lastInsertId();
            
            // ============ PASO 2: REGISTRAR EN LOTES ============
            if ($tipo == 'ENTRADA') {
                registrarEntradaPEPS($pdo, $producto_id, $cantidad, $precio_unitario, $movimiento_id);
                
            } elseif ($tipo == 'SALIDA' || $tipo == 'CONSUMO') {
                $resultado = consumirLotesPEPS($pdo, $producto_id, $cantidad, $movimiento_id);
                $costo_peps = $resultado['costo_total'];
                
                if ($tipo == 'SALIDA') {
                    $ganancia = $total - $costo_peps;
                    $stmt = $pdo->prepare("UPDATE movimientos SET costo_peps = ?, ganancia = ? WHERE id = ?");
                    $stmt->execute([$costo_peps, $ganancia, $movimiento_id]);
                } else {
                    // CONSUMO: el total y el precio unitario reflejan el costo PEPS real de los lotes consumidos
                    $precio_unitario = $cantidad > 0 ? $costo_peps / $cantidad : 0;
                    $stmt = $pdo->prepare("UPDATE movimientos SET costo_peps = ?, ganancia = 0, total = ?, precio_unitario = ? WHERE id = ?");
                    $stmt->execute([$costo_peps, $costo_peps, $precio_unitario, $movimiento_id]);
                }
            }
            
            logAudit('CREATE', 'movimientos', $movimiento_id, null, $_POST);
            
            $pdo->commit();
            
            if ($tipo == 'SALIDA') {
                $message = "✅ Salida registrada. Costo PEPS: " . moneda($costo_peps) . " | Ganancia: " . moneda($ganancia);
            } elseif ($tipo == 'CONSUMO') {
                $message = "✅ Consumo registrado. Costo: " . moneda($costo_peps) . " (uso interno)";
            } else {
                $message = '✅ Movimiento registrado exitosamente';
            }
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = '❌ Error: ' . $e->getMessage();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = '❌ Error de BD: ' . $e->getMessage();
        }
    }
}

// ============ OBTENER DATOS ============
$productos = $pdo->query("
    SELECT 
        p.id, 
        p.codigo, 
        p.nombre, 
        p.precio_compra, 
        p.precio_venta,
        COALESCE((SELECT SUM(cantidad_disponible) FROM lotes WHERE id_producto = p.id AND activo = 1), 0) as stock_actual
    FROM productos p 
    WHERE p.activo = 1 
    ORDER BY p.nombre
")->fetchAll();

$movimientos = $pdo->query("
    SELECT m.*, 
           p.nombre as producto_nombre, 
           p.codigo as producto_codigo, 
           u.nombre_completo as usuario_nombre 
    FROM movimientos m 
    JOIN productos p ON m.id_producto = p.id 
    LEFT JOIN usuarios u ON m.id_usuario = u.id 
    ORDER BY m.fecha_movimiento DESC 
    LIMIT 100
")->fetchAll();

// ============ ESTADÍSTICAS ============
$total_movimientos = count($movimientos);
$total_entradas = array_sum(array_column(array_filter($movimientos, function($m) {
    return $m['tipo_movimiento'] == 'ENTRADA';
}), 'cantidad'));
$total_salidas = array_sum(array_column(array_filter($movimientos, function($m) {
    return $m['tipo_movimiento'] == 'SALIDA';
}), 'cantidad'));
$total_consumos = array_sum(array_column(array_filter($movimientos, function($m) {
    return $m['tipo_movimiento'] == 'CONSUMO';
}), 'cantidad'));
$total_movimientos_hoy = getMovimientosHoy();

// ============ VARIABLES PARA EL SIDEBAR ============
$total_productos_sidebar = getTotalProductos();
$movimientos_hoy_sidebar = $total_movimientos_hoy;
$total_categorias = getTotalCategorias();
$total_proveedores = getTotalProveedores();
$total_conteos = getTotalConteos();
$total_usuarios = getTotalUsuarios();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos PEPS - Sistema de Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f0f2f5; }
        
        .sidebar { min-height: 100vh; background: #1a2035; color: white; }
        .sidebar a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            padding: 10px 20px;
            display: block;
            border-radius: 8px;
            margin: 4px 0;
            transition: all 0.3s;
        }
        .sidebar a:hover { background: rgba(255,255,255,0.1); color: white; }
        .sidebar a.active { background: #2d3748; color: white; border-left: 3px solid #4f46e5; }
        .sidebar a i { margin-right: 10px; width: 20px; }
        .sidebar .user-info { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar .user-info h5 { color: white; margin-bottom: 2px; }
        .sidebar .user-info small { color: rgba(255,255,255,0.5); }
        
        .badge-sidebar {
            display: inline-block; padding: 2px 10px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 600;
            background: rgba(255,255,255,0.15); color: white; float: right;
        }
        .badge-sidebar.blue { background: rgba(59, 130, 246, 0.3); color: #60a5fa; }
        .badge-sidebar.green { background: rgba(34, 197, 94, 0.3); color: #4ade80; }
        .badge-sidebar.red { background: rgba(239, 68, 68, 0.3); color: #f87171; }
        .badge-sidebar.purple { background: rgba(139, 92, 246, 0.3); color: #a78bfa; }
        .badge-sidebar.orange { background: rgba(245, 158, 11, 0.3); color: #fbbf24; }
        .badge-sidebar.cyan { background: rgba(6, 182, 212, 0.3); color: #67e8f9; }
        
        .content-area { padding: 25px 30px; }
        .page-title { font-weight: 700; font-size: 1.8rem; color: #1a2035; }
        .page-subtitle { color: #6b7280; font-size: 0.95rem; }
        
        .stat-card {
            background: white; border-radius: 16px; padding: 18px 22px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e5e7eb;
            transition: all 0.3s; height: 100%;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,0.08); }
        .stat-card .stat-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; margin-bottom: 10px;
        }
        .stat-card .stat-icon.blue { background: #e0f2fe; color: #0284c7; }
        .stat-card .stat-icon.green { background: #dcfce7; color: #16a34a; }
        .stat-card .stat-icon.red { background: #fee2e2; color: #dc2626; }
        .stat-card .stat-icon.purple { background: #ede9fe; color: #7c3aed; }
        .stat-card .stat-icon.orange { background: #fef3c7; color: #d97706; }
        .stat-card .stat-number { font-size: 1.8rem; font-weight: 800; color: #1a2035; line-height: 1.2; }
        .stat-card .stat-label { color: #6b7280; font-size: 0.85rem; font-weight: 500; }
        .stat-card .stat-change {
            font-size: 0.75rem; font-weight: 600;
            padding: 2px 10px; border-radius: 20px; display: inline-block;
        }
        .stat-card .stat-change.up { background: #dcfce7; color: #16a34a; }
        .stat-card .stat-change.down { background: #fee2e2; color: #dc2626; }
        .stat-card .stat-change.consumo { background: #fef3c7; color: #d97706; }
        
        .section-card {
            background: white; border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e5e7eb;
            overflow: hidden;
        }
        .section-card .card-header-custom {
            padding: 14px 22px; background: #fafbfc;
            border-bottom: 1px solid #e5e7eb;
            display: flex; justify-content: space-between; align-items: center;
        }
        .section-card .card-header-custom h5 { font-weight: 600; margin: 0; color: #1a2035; font-size: 1rem; }
        .section-card .card-header-custom h5 i { margin-right: 8px; }
        .section-card .card-body-custom { padding: 18px 22px; }
        
        .table-modern { font-size: 0.9rem; }
        .table-modern th {
            font-weight: 600; color: #6b7280;
            border-bottom: 2px solid #e5e7eb;
            font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .table-modern td { vertical-align: middle; padding: 10px 12px; }
        .table-modern tr:hover { background: #f9fafb; }
        
        .badge-modern {
            padding: 4px 12px; border-radius: 20px;
            font-weight: 500; font-size: 0.75rem;
        }
        .badge-modern.entrada { background: #dcfce7; color: #16a34a; }
        .badge-modern.salida { background: #fee2e2; color: #dc2626; }
        .badge-modern.ajuste { background: #fef3c7; color: #d97706; }
        .badge-modern.transferencia { background: #e0f2fe; color: #0284c7; }
        .badge-modern.consumo { background: #fef3c7; color: #d97706; }
        
        .form-modern .form-control,
        .form-modern .form-select {
            border-radius: 10px; border: 1px solid #e5e7eb;
            padding: 10px 14px; font-size: 0.9rem; transition: all 0.3s;
        }
        .form-modern .form-control:focus,
        .form-modern .form-select:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        .form-modern .form-label { font-weight: 600; font-size: 0.8rem; color: #4b5563; }
        
        .precio-info {
            background: #f0f9ff; border: 1px solid #bae6fd;
            border-radius: 10px; padding: 10px 14px; font-size: 0.85rem;
        }
        .precio-info .precio-valor { font-weight: 700; color: #0284c7; }
        
        .alert-peps {
            background: #eff6ff; border: 1px solid #bfdbfe;
            border-radius: 10px; padding: 12px 16px; font-size: 0.85rem;
            color: #1e40af; margin-bottom: 20px;
        }
        
        .alert-consumo {
            background: #fef3c7; border: 1px solid #fde68a;
            border-radius: 10px; padding: 12px 16px; font-size: 0.85rem;
            color: #92400e; margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .content-area { padding: 15px; }
            .stat-card .stat-number { font-size: 1.3rem; }
            .page-title { font-size: 1.4rem; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- ===== SIDEBAR ===== -->
            <nav class="col-md-2 d-md-block sidebar p-0">
                <div class="position-sticky">
                    <div class="user-info">
                        <h5><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['user_name']) ?></h5>
                        <small><i class="bi bi-shield-check"></i> <?= $_SESSION['user_rol'] ?></small>
                    </div>
                    <ul class="nav flex-column p-2">
                        <li><a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                        <li><a href="productos.php"><i class="bi bi-box"></i> Productos <span class="badge-sidebar blue"><?= $total_productos_sidebar ?></span></a></li>
                        <li><a href="movimientos.php" class="active"><i class="bi bi-arrows-exchange"></i> Movimientos <span class="badge-sidebar purple"><?= $movimientos_hoy_sidebar ?></span></a></li>
                        <li><a href="categorias.php"><i class="bi bi-tags"></i> Categorías <span class="badge-sidebar green"><?= $total_categorias ?></span></a></li>
                        <li><a href="proveedores.php"><i class="bi bi-truck"></i> Proveedores <span class="badge-sidebar cyan"><?= $total_proveedores ?></span></a></li>
                        <li><a href="inventario_fisico.php"><i class="bi bi-clipboard-check"></i> Inventario Físico</a></li>
                        <li><a href="reportes.php"><i class="bi bi-file-earmark-text"></i> Reportes</a></li>
                        <?php if (hasPermission('ADMIN')): ?>
                        <li><a href="usuarios.php"><i class="bi bi-people"></i> Usuarios <span class="badge-sidebar blue"><?= $total_usuarios ?></span></a></li>
                        <li><a href="auditoria.php"><i class="bi bi-clock-history"></i> Auditoría</a></li>
                        <?php endif; ?>
                        <li><hr class="border-secondary"></li>
                        <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
                    </ul>
                </div>
            </nav>

            <!-- ===== CONTENIDO PRINCIPAL ===== -->
            <main class="col-md-10 ms-sm-auto px-4 py-3">
                <div class="content-area">
                    
                    <!-- ===== HEADER ===== -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="page-title">🔄 Movimientos PEPS</h1>
                            <p class="page-subtitle">Sistema PEPS (Primero en Entrar, Primero en Salir)</p>
                        </div>
                        <div>
                            <span class="badge bg-success p-2">
                                <i class="bi bi-check-circle"></i> PEPS Activo
                            </span>
                        </div>
                    </div>

                    <!-- ===== MENSAJES ===== -->
                    <?php if ($message): ?>
                        <div class="alert alert-<?= strpos($message, '✅') !== false ? 'success' : 'danger' ?> alert-dismissible fade show">
                            <?= $message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- ===== INFO PEPS ===== -->
                    <div class="alert-peps">
                        <i class="bi bi-info-circle"></i>
                        <strong>Sistema PEPS:</strong> Cada compra crea un lote con su precio. 
                        Al vender, se consume el lote más antiguo primero.
                        <br>
                        <strong>🔧 CONSUMO:</strong> Usa esta opción para materiales de uso interno 
                        (tornillos, cables, etc.) que NO se venden.
                        <br>
                        <strong>✏️ AJUSTES:</strong> Los ajustes de stock se realizan desde <a href="inventario_fisico.php">Inventario Físico</a>.
                    </div>

                    <!-- ===== TARJETAS DE ESTADÍSTICAS ===== -->
                    <div class="row g-3 mb-4">
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon blue"><i class="bi bi-arrow-left-right"></i></div>
                                <div class="stat-number"><?= number_format($total_movimientos) ?></div>
                                <div class="stat-label">Total Movimientos</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-calendar3"></i> <?= $total_movimientos_hoy ?> hoy</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon green"><i class="bi bi-arrow-down-circle"></i></div>
                                <div class="stat-number text-success"><?= number_format($total_entradas) ?></div>
                                <div class="stat-label">Unidades en Entradas</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-plus-circle"></i> Stock aumenta</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon red"><i class="bi bi-arrow-up-circle"></i></div>
                                <div class="stat-number text-danger"><?= number_format($total_salidas) ?></div>
                                <div class="stat-label">Unidades en Salidas</div>
                                <div class="mt-2">
                                    <span class="stat-change down"><i class="bi bi-dash-circle"></i> Stock disminuye</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon orange"><i class="bi bi-tools"></i></div>
                                <div class="stat-number text-warning"><?= number_format($total_consumos) ?></div>
                                <div class="stat-label">Unidades en Consumo</div>
                                <div class="mt-2">
                                    <span class="stat-change consumo"><i class="bi bi-wrench"></i> Uso interno</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== FORMULARIO DE MOVIMIENTOS ===== -->
                    <div class="section-card mb-4">
                        <div class="card-header-custom">
                            <h5><i class="bi bi-plus-circle"></i> Registrar Nuevo Movimiento</h5>
                            <span class="text-muted" style="font-size: 0.8rem;">Sistema PEPS activado</span>
                        </div>
                        <div class="card-body-custom">
                            <form method="POST" class="form-modern" id="movimientoForm">
                                <input type="hidden" name="action" value="create">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Tipo *</label>
                                        <select name="tipo_movimiento" id="tipo_movimiento" class="form-select" required>
                                            <option value="ENTRADA" <?= isset($_GET['action']) && $_GET['action'] == 'entrada' ? 'selected' : '' ?>>📥 Entrada (Compra)</option>
                                            <option value="SALIDA" <?= isset($_GET['action']) && $_GET['action'] == 'salida' ? 'selected' : '' ?>>📤 Salida (Venta)</option>
                                            <option value="CONSUMO" <?= isset($_GET['action']) && $_GET['action'] == 'consumo' ? 'selected' : '' ?>>🔧 Consumo (Uso interno)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Producto *</label>
                                        <select name="id_producto" id="id_producto" class="form-select" required>
                                            <option value="">Seleccionar...</option>
                                            <?php foreach ($productos as $p): ?>
                                                <option value="<?= $p['id'] ?>" 
                                                        data-precio-compra="<?= $p['precio_compra'] ?>"
                                                        data-precio-venta="<?= $p['precio_venta'] ?>"
                                                        data-stock="<?= $p['stock_actual'] ?>">
                                                    <?= htmlspecialchars($p['codigo'] . ' - ' . $p['nombre']) ?>
                                                    (Stock: <?= $p['stock_actual'] ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3" id="precioField">
                                        <label class="form-label">Precio de Compra</label>
                                        <input type="number" step="0.01" name="precio_unitario" id="precio_unitario" class="form-control" placeholder="0.00" min="0">
                                        <div class="form-text text-muted">Se carga automáticamente</div>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label">Cantidad *</label>
                                        <input type="number" name="cantidad" id="cantidad" class="form-control" required min="1" placeholder="0">
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <div class="precio-info" id="precioInfo" style="display: none;">
                                            <i class="bi bi-info-circle"></i>
                                            <strong id="precioInfoLabel">Precio:</strong> 
                                            <span class="precio-valor" id="precioInfoValor">S/ 0.00</span>
                                            <span class="text-muted ms-2" id="precioInfoText"></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <label class="form-label">Comentario</label>
                                        <input type="text" name="comentario" class="form-control" placeholder="Motivo del movimiento...">
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Registrar Movimiento
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- ===== TABLA DE MOVIMIENTOS ===== -->
                    <div class="section-card">
                        <div class="card-header-custom">
                            <h5><i class="bi bi-clock-history"></i> Historial de Movimientos</h5>
                            <div>
                                <span class="badge bg-secondary me-2"><?= $total_movimientos ?> registros</span>
                                <a href="reportes.php?tipo_reporte=movimientos" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-file-earmark-text"></i> Ver Reportes
                                </a>
                            </div>
                        </div>
                        <div class="card-body-custom">
                            <div class="table-responsive">
                                <table id="movimientosTable" class="table table-modern">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Producto</th>
                                            <th>Tipo</th>
                                            <th>Cantidad</th>
                                            <th>Precio</th>
                                            <th>Total</th>
                                            <th>Costo PEPS</th>
                                            <th>Ganancia</th>
                                            <th>Usuario</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($movimientos)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-4">
                                                    <i class="bi bi-inbox fs-1 d-block text-muted"></i>
                                                    <span class="text-muted">No hay movimientos registrados</span>
                                                    <br>
                                                    <span class="text-muted small">Registra una entrada o salida para comenzar</span>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($movimientos as $mov): ?>
                                            <tr>
                                                <td style="font-size: 0.8rem;">
                                                    <?= date('d/m/Y', strtotime($mov['fecha_movimiento'])) ?>
                                                    <br><span class="text-muted"><?= date('H:i', strtotime($mov['fecha_movimiento'])) ?></span>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($mov['producto_nombre']) ?></strong>
                                                    <br><small class="text-muted"><?= htmlspecialchars($mov['producto_codigo']) ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge-modern <?= strtolower($mov['tipo_movimiento']) ?>">
                                                        <?= $mov['tipo_movimiento'] ?>
                                                        <?php if ($mov['tipo_movimiento'] == 'ENTRADA'): ?>
                                                            <i class="bi bi-arrow-up"></i>
                                                        <?php elseif ($mov['tipo_movimiento'] == 'SALIDA'): ?>
                                                            <i class="bi bi-arrow-down"></i>
                                                        <?php elseif ($mov['tipo_movimiento'] == 'CONSUMO'): ?>
                                                            <i class="bi bi-tools"></i>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                                <td class="text-center"><strong><?= $mov['cantidad'] ?></strong></td>
                                                <td><?= moneda($mov['precio_unitario']) ?></td>
                                                <td><?= moneda($mov['total']) ?></td>
                                                <td>
                                                    <?php if ($mov['tipo_movimiento'] == 'SALIDA' || $mov['tipo_movimiento'] == 'CONSUMO'): ?>
                                                        <span class="text-warning fw-bold"><?= moneda($mov['costo_peps']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($mov['tipo_movimiento'] == 'SALIDA'): ?>
                                                        <span class="<?= $mov['ganancia'] >= 0 ? 'text-success' : 'text-danger' ?> fw-bold">
                                                            <?= moneda($mov['ganancia']) ?>
                                                        </span>
                                                    <?php elseif ($mov['tipo_movimiento'] == 'CONSUMO'): ?>
                                                        <span class="badge bg-warning text-dark" style="font-size: 0.7rem;">Uso interno</span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($mov['usuario_nombre'] ?? 'Sistema') ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- ===== SCRIPTS ===== -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#movimientosTable').DataTable({
                language: { url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json' },
                order: [[0, 'desc']],
                pageLength: 25
            });
        });

        // ===== ACTUALIZAR INTERFAZ SEGÚN TIPO =====
        function actualizarInterfaz() {
            const tipo = document.getElementById('tipo_movimiento').value;
            const precioField = document.getElementById('precioField');
            const precioInput = document.getElementById('precio_unitario');
            const precioInfo = document.getElementById('precioInfo');
            const productoSelect = document.getElementById('id_producto');
            const selectedOption = productoSelect.options[productoSelect.selectedIndex];
            
            const precioCompra = parseFloat(selectedOption.dataset.precioCompra) || 0;
            const precioVenta = parseFloat(selectedOption.dataset.precioVenta) || 0;
            const stock = parseInt(selectedOption.dataset.stock) || 0;
            
            if (tipo === 'ENTRADA') {
                precioField.style.display = 'block';
                precioInput.required = true;
                
                if (selectedOption.value) {
                    precioInput.value = precioCompra.toFixed(2);
                } else {
                    precioInput.value = '';
                }
                
                precioInfo.style.display = 'none';
                
            } else if (tipo === 'SALIDA') {
                precioField.style.display = 'none';
                precioInput.required = false;
                precioInput.value = '';
                
                if (selectedOption.value) {
                    document.getElementById('precioInfoLabel').textContent = 'Precio de venta:';
                    document.getElementById('precioInfoValor').textContent = 'S/ ' + precioVenta.toFixed(2);
                    document.getElementById('precioInfoText').textContent = '(Stock disponible: ' + stock + ' unidades)';
                    precioInfo.style.display = 'block';
                } else {
                    precioInfo.style.display = 'none';
                }
                
            } else if (tipo === 'CONSUMO') {
                precioField.style.display = 'none';
                precioInput.required = false;
                precioInput.value = '';
                
                if (selectedOption.value) {
                    document.getElementById('precioInfoLabel').textContent = 'Costo de consumo:';
                    document.getElementById('precioInfoValor').textContent = 'S/ ' + precioCompra.toFixed(2);
                    document.getElementById('precioInfoText').textContent = '(Stock disponible: ' + stock + ' unidades - NO genera ganancia)';
                    precioInfo.style.display = 'block';
                } else {
                    precioInfo.style.display = 'none';
                }
                
            } else {
                precioField.style.display = 'none';
                precioInput.required = false;
                precioInput.value = '';
                
                if (selectedOption.value) {
                    document.getElementById('precioInfoLabel').textContent = 'Costo:';
                    document.getElementById('precioInfoValor').textContent = 'S/ ' + precioCompra.toFixed(2);
                    document.getElementById('precioInfoText').textContent = '(se usa el costo de compra)';
                    precioInfo.style.display = 'block';
                } else {
                    precioInfo.style.display = 'none';
                }
            }
        }

        document.getElementById('tipo_movimiento').addEventListener('change', actualizarInterfaz);
        document.getElementById('id_producto').addEventListener('change', actualizarInterfaz);
        actualizarInterfaz();
    </script>
</body>
</html>