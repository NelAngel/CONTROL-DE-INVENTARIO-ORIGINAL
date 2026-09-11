<?php
require_once 'config.php';

// Verificar autenticación
if (!isAuthenticated()) {
    header('Location: login.php');
    exit();
}

$pdo = getConnection();

// ============ ESTADÍSTICAS PRINCIPALES ============
// Total de productos
$total_productos = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1")->fetch()['total'];

// Productos con stock bajo
$productos_bajo_stock = $pdo->query("
    SELECT 
        p.id, 
        p.nombre, 
        p.codigo, 
        p.stock_minimo,
        p.precio_compra,
        COALESCE(
            (SELECT SUM(CASE WHEN m.tipo_movimiento = 'ENTRADA' OR m.tipo_movimiento = 'AJUSTE' THEN m.cantidad ELSE 0 END)
             - SUM(CASE WHEN m.tipo_movimiento = 'SALIDA' OR m.tipo_movimiento = 'TRANSFERENCIA' THEN m.cantidad ELSE 0 END)
             FROM movimientos m WHERE m.id_producto = p.id), 0
        ) as stock_actual
    FROM productos p
    WHERE p.activo = 1
")->fetchAll();

$productos_bajo_stock = array_filter($productos_bajo_stock, function($p) {
    return $p['stock_actual'] <= $p['stock_minimo'];
});

$total_bajo_stock = count($productos_bajo_stock);

// Movimientos del día
$movimientos_hoy = $pdo->query("SELECT COUNT(*) as total FROM movimientos WHERE DATE(fecha_movimiento) = CURDATE()")->fetch()['total'];

// Movimientos del mes
$movimientos_mes = $pdo->query("SELECT COUNT(*) as total FROM movimientos WHERE MONTH(fecha_movimiento) = MONTH(CURDATE()) AND YEAR(fecha_movimiento) = YEAR(CURDATE())")->fetch()['total'];

// Total de categorías
$total_categorias = $pdo->query("SELECT COUNT(*) as total FROM categorias WHERE activo = 1")->fetch()['total'];

// Total de proveedores
$total_proveedores = $pdo->query("SELECT COUNT(*) as total FROM proveedores WHERE activo = 1")->fetch()['total'];

// ============ VALOR TOTAL DEL INVENTARIO ============
$valor_inventario = $pdo->query("
    SELECT COALESCE(SUM(
        p.precio_compra * (
            SELECT COALESCE(SUM(
                CASE 
                    WHEN m.tipo_movimiento = 'ENTRADA' THEN m.cantidad
                    WHEN m.tipo_movimiento = 'AJUSTE' THEN m.cantidad
                    WHEN m.tipo_movimiento = 'SALIDA' THEN -m.cantidad
                    WHEN m.tipo_movimiento = 'TRANSFERENCIA' THEN -m.cantidad
                    ELSE 0 
                END
            ), 0)
            FROM movimientos m 
            WHERE m.id_producto = p.id
        )
    ), 0) as total
    FROM productos p
    WHERE p.activo = 1
")->fetch()['total'];

// ============ GANANCIAS (NUEVO) ============
// Total de ventas (ingresos por salidas)
$total_ventas = $pdo->query("
    SELECT COALESCE(SUM(total), 0) as total 
    FROM movimientos 
    WHERE tipo_movimiento = 'SALIDA'
")->fetch()['total'];

// Costo de los productos vendidos (precio de compra × cantidad vendida)
// Costo de los productos vendidos (COSTO PEPS REAL)
$costo_ventas = $pdo->query("
    SELECT COALESCE(SUM(costo_peps), 0) as total
    FROM movimientos 
    WHERE tipo_movimiento = 'SALIDA'
")->fetch()['total'];

// Ganancia neta = Ventas - Costo de ventas
$ganancia_neta = $total_ventas - $costo_ventas;

// ============ ÚLTIMOS MOVIMIENTOS ============
$movimientos_recientes = $pdo->query("
    SELECT m.*, 
           p.nombre as producto_nombre, 
           p.codigo as producto_codigo,
           u.nombre_completo as usuario_nombre
    FROM movimientos m
    JOIN productos p ON m.id_producto = p.id
    LEFT JOIN usuarios u ON m.id_usuario = u.id
    ORDER BY m.fecha_movimiento DESC
    LIMIT 10
")->fetchAll();

// ============ PRODUCTOS PRÓXIMOS A AGOTARSE ============
$productos_criticos = $pdo->query("
    SELECT 
        p.id, 
        p.nombre, 
        p.codigo, 
        p.stock_minimo,
        p.stock_maximo,
        p.precio_compra,
        COALESCE(
            (SELECT SUM(CASE WHEN m.tipo_movimiento = 'ENTRADA' OR m.tipo_movimiento = 'AJUSTE' THEN m.cantidad ELSE 0 END)
             - SUM(CASE WHEN m.tipo_movimiento = 'SALIDA' OR m.tipo_movimiento = 'TRANSFERENCIA' THEN m.cantidad ELSE 0 END)
             FROM movimientos m WHERE m.id_producto = p.id), 0
        ) as stock_actual
    FROM productos p
    WHERE p.activo = 1
    HAVING stock_actual <= stock_minimo * 2
    ORDER BY stock_actual ASC
    LIMIT 5
")->fetchAll();

// ============ TOP 5 PRODUCTOS MÁS VENDIDOS ============
$top_productos = $pdo->query("
    SELECT 
        p.id,
        p.nombre,
        p.codigo,
        SUM(m.cantidad) as total_vendido,
        SUM(m.total) as total_ingresos,
        SUM(m.cantidad * p.precio_compra) as total_costo
    FROM movimientos m
    JOIN productos p ON m.id_producto = p.id
    WHERE m.tipo_movimiento = 'SALIDA'
    AND m.fecha_movimiento >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY p.id
    ORDER BY total_vendido DESC
    LIMIT 5
")->fetchAll();

// ============ VARIABLES PARA EL SIDEBAR ============
$total_productos_sidebar = $total_productos;
$movimientos_hoy_sidebar = $movimientos_hoy;
$total_categorias_sidebar = $total_categorias;
$total_proveedores_sidebar = $total_proveedores;
$total_conteos = $pdo->query("SELECT COUNT(*) as total FROM inventario_fisico WHERE fecha_conteo >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch()['total'];
$total_usuarios = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = 1")->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ===== ESTILOS GLOBALES ===== */
        * { font-family: 'Inter', sans-serif; }
        body { background: #f0f2f5; }
        
        /* ===== SIDEBAR ===== */
        .sidebar {
            min-height: 100vh;
            background: #1a2035;
            color: white;
        }
        .sidebar a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            padding: 10px 20px;
            display: block;
            border-radius: 8px;
            margin: 4px 0;
            transition: all 0.3s;
        }
        .sidebar a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .sidebar a.active {
            background: #2d3748;
            color: white;
            border-left: 3px solid #4f46e5;
        }
        .sidebar a i { margin-right: 10px; width: 20px; }
        .sidebar .user-info {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar .user-info h5 { color: white; margin-bottom: 2px; }
        .sidebar .user-info small { color: rgba(255,255,255,0.5); }
        
        .badge-sidebar {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(255,255,255,0.15);
            color: white;
            float: right;
        }
        .badge-sidebar.blue { background: rgba(59, 130, 246, 0.3); color: #60a5fa; }
        .badge-sidebar.green { background: rgba(34, 197, 94, 0.3); color: #4ade80; }
        .badge-sidebar.red { background: rgba(239, 68, 68, 0.3); color: #f87171; }
        .badge-sidebar.purple { background: rgba(139, 92, 246, 0.3); color: #a78bfa; }
        .badge-sidebar.orange { background: rgba(245, 158, 11, 0.3); color: #fbbf24; }
        .badge-sidebar.cyan { background: rgba(6, 182, 212, 0.3); color: #67e8f9; }
        
        /* ===== CONTENIDO ===== */
        .content-area { padding: 25px 30px; }
        .page-title { font-weight: 700; font-size: 1.8rem; color: #1a2035; }
        .page-subtitle { color: #6b7280; font-size: 0.95rem; }
        
        /* ===== ACCIONES RÁPIDAS ===== */
        .quick-actions {
            background: white;
            border-radius: 16px;
            padding: 16px 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            border: 1px solid #e5e7eb;
            margin-bottom: 24px;
        }
        .quick-actions .btn-action {
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }
        .quick-actions .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        .quick-actions .btn-action i { font-size: 1.1rem; }
        .btn-action-primary { background: #4f46e5; color: white; }
        .btn-action-primary:hover { background: #4338ca; color: white; }
        .btn-action-success { background: #22c55e; color: white; }
        .btn-action-success:hover { background: #16a34a; color: white; }
        .btn-action-danger { background: #ef4444; color: white; }
        .btn-action-danger:hover { background: #dc2626; color: white; }
        .btn-action-info { background: #0ea5e9; color: white; }
        .btn-action-info:hover { background: #0284c7; color: white; }
        .btn-action-secondary { background: #6b7280; color: white; }
        .btn-action-secondary:hover { background: #4b5563; color: white; }
        .btn-action-warning { background: #f59e0b; color: white; }
        .btn-action-warning:hover { background: #d97706; color: white; }
        
        /* ===== TARJETAS DE ESTADÍSTICAS ===== */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 18px 22px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            border: 1px solid #e5e7eb;
            transition: all 0.3s;
            height: 100%;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.08);
        }
        .stat-card .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            margin-bottom: 10px;
        }
        .stat-card .stat-icon.blue { background: #e0f2fe; color: #0284c7; }
        .stat-card .stat-icon.green { background: #dcfce7; color: #16a34a; }
        .stat-card .stat-icon.red { background: #fee2e2; color: #dc2626; }
        .stat-card .stat-icon.orange { background: #fef3c7; color: #d97706; }
        .stat-card .stat-icon.purple { background: #ede9fe; color: #7c3aed; }
        .stat-card .stat-icon.cyan { background: #cffafe; color: #0891b2; }
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            color: #1a2035;
            line-height: 1.2;
        }
        .stat-card .stat-label {
            color: #6b7280;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .stat-card .stat-change {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            display: inline-block;
        }
        .stat-card .stat-change.up { background: #dcfce7; color: #16a34a; }
        .stat-card .stat-change.down { background: #fee2e2; color: #dc2626; }
        .stat-card .stat-change.ganancia { background: #dbeafe; color: #2563eb; }
        
        /* ===== TARJETAS DE SECCIONES ===== */
        .section-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }
        .section-card .card-header-custom {
            padding: 14px 22px;
            background: #fafbfc;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .section-card .card-header-custom h5 {
            font-weight: 600;
            margin: 0;
            color: #1a2035;
            font-size: 1rem;
        }
        .section-card .card-header-custom h5 i { margin-right: 8px; }
        .section-card .card-body-custom { padding: 18px 22px; }
        
        /* ===== TABLA ===== */
        .table-modern {
            font-size: 0.9rem;
        }
        .table-modern th {
            font-weight: 600;
            color: #6b7280;
            border-bottom: 2px solid #e5e7eb;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table-modern td {
            vertical-align: middle;
            padding: 10px 12px;
        }
        .table-modern tr:hover { background: #f9fafb; }
        
        /* ===== BADGES ===== */
        .badge-modern {
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.75rem;
        }
        .badge-modern.entrada { background: #dcfce7; color: #16a34a; }
        .badge-modern.salida { background: #fee2e2; color: #dc2626; }
        .badge-modern.ajuste { background: #fef3c7; color: #d97706; }
        .badge-modern.transferencia { background: #e0f2fe; color: #0284c7; }
        .badge-modern.stock-bajo { background: #fee2e2; color: #dc2626; }
        .badge-modern.stock-critico { background: #fef3c7; color: #d97706; }
        .badge-modern.stock-normal { background: #dcfce7; color: #16a34a; }
        
        /* ===== ALERTAS ===== */
        .alert-stock {
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.9rem;
            border-left: 4px solid;
        }
        .alert-stock.alert-warning { border-left-color: #f59e0b; }
        .alert-stock.alert-danger { border-left-color: #ef4444; }
        .alert-stock .badge { font-size: 0.8rem; }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .content-area { padding: 15px; }
            .stat-card .stat-number { font-size: 1.3rem; }
            .page-title { font-size: 1.4rem; }
            .quick-actions .btn-action {
                padding: 8px 14px;
                font-size: 0.8rem;
                width: 100%;
                justify-content: center;
            }
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
                        <li><a href="index.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                        <li><a href="productos.php"><i class="bi bi-box"></i> Productos <span class="badge-sidebar blue"><?= $total_productos_sidebar ?></span></a></li>
                        <li><a href="movimientos.php"><i class="bi bi-arrows-exchange"></i> Movimientos <span class="badge-sidebar purple"><?= $movimientos_hoy_sidebar ?></span></a></li>
                        <li><a href="categorias.php"><i class="bi bi-tags"></i> Categorías <span class="badge-sidebar green"><?= $total_categorias_sidebar ?></span></a></li>
                        <li><a href="proveedores.php"><i class="bi bi-truck"></i> Proveedores <span class="badge-sidebar cyan"><?= $total_proveedores_sidebar ?></span></a></li>
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
                            <h1 class="page-title">📊 Dashboard</h1>
                            <p class="page-subtitle">Resumen general de tu negocio</p>
                        </div>
                        <div>
                            <span class="badge bg-primary p-2">
                                <i class="bi bi-calendar3"></i> <?= date('d/m/Y') ?>
                            </span>
                        </div>
                    </div>

                    <!-- ===== ACCIONES RÁPIDAS ===== -->
                    <div class="quick-actions">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="fw-bold me-2"><i class="bi bi-lightning-fill text-warning"></i> Acciones Rápidas:</span>
                            <a href="productos.php" class="btn-action btn-action-primary">
                                <i class="bi bi-plus-circle"></i> Nuevo Producto
                            </a>
                            <a href="movimientos.php?action=entrada" class="btn-action btn-action-success">
                                <i class="bi bi-arrow-down-circle"></i> Nueva Entrada
                            </a>
                            <a href="movimientos.php?action=salida" class="btn-action btn-action-danger">
                                <i class="bi bi-arrow-up-circle"></i> Nueva Salida
                            </a>
                            <a href="inventario_fisico.php" class="btn-action btn-action-info">
                                <i class="bi bi-clipboard-check"></i> Inventario Físico
                            </a>
                            <a href="reportes.php" class="btn-action btn-action-secondary">
                                <i class="bi bi-file-earmark-text"></i> Ver Reportes
                            </a>
                        </div>
                    </div>

                    <!-- ===== TARJETAS DE ESTADÍSTICAS ===== -->
                    <div class="row g-4 mb-4">
                        <!-- Tarjeta 1: Productos -->
                        <div class="col-xl-2 col-lg-4 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon blue"><i class="bi bi-box"></i></div>
                                <div class="stat-number"><?= number_format($total_productos) ?></div>
                                <div class="stat-label">Productos</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-tags"></i> <?= $total_categorias ?> cats.</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tarjeta 2: Stock Bajo -->
                        <div class="col-xl-2 col-lg-4 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon <?= $total_bajo_stock > 0 ? 'red' : 'green' ?>">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                                <div class="stat-number text-<?= $total_bajo_stock > 0 ? 'danger' : 'success' ?>">
                                    <?= number_format($total_bajo_stock) ?>
                                </div>
                                <div class="stat-label">Stock Bajo</div>
                                <div class="mt-2">
                                    <span class="stat-change <?= $total_bajo_stock > 0 ? 'down' : 'up' ?>">
                                        <?= $total_bajo_stock > 0 ? '⚠️ Atención' : '✅ Todo bien' ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tarjeta 3: Movimientos -->
                        <div class="col-xl-2 col-lg-4 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon purple"><i class="bi bi-arrow-left-right"></i></div>
                                <div class="stat-number"><?= number_format($movimientos_hoy) ?></div>
                                <div class="stat-label">Mov. Hoy</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-calendar-month"></i> <?= number_format($movimientos_mes) ?> este mes</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tarjeta 4: Valor Inventario -->
                        <div class="col-xl-2 col-lg-4 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon cyan"><i class="bi bi-currency-dollar"></i></div>
                                <div class="stat-number">S/<?= number_format($valor_inventario, 0) ?></div>
                                <div class="stat-label">Inventario</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-truck"></i> <?= $total_proveedores ?> proveedores</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tarjeta 5: Total Ventas -->
                        <div class="col-xl-2 col-lg-4 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon green"><i class="bi bi-cart-check"></i></div>
                                <div class="stat-number">S/<?= number_format($total_ventas, 0) ?></div>
                                <div class="stat-label">Total Ventas</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-arrow-up"></i> Ingresos</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tarjeta 6: GANANCIA NETA (DESTACADA) -->
                        <div class="col-xl-2 col-lg-4 col-md-6">
                            <div class="stat-card" style="border: 2px solid <?= $ganancia_neta >= 0 ? '#22c55e' : '#ef4444' ?>;">
                                <div class="stat-icon <?= $ganancia_neta >= 0 ? 'green' : 'red' ?>">
                                    <i class="bi bi-graph-up-arrow"></i>
                                </div>
                                <div class="stat-number text-<?= $ganancia_neta >= 0 ? 'success' : 'danger' ?>">
                                    S/<?= number_format($ganancia_neta, 0) ?>
                                </div>
                                <div class="stat-label">💰 Ganancia Neta</div>
                                <div class="mt-2">
                                    <span class="stat-change <?= $ganancia_neta >= 0 ? 'up' : 'down' ?>">
                                        <?= $ganancia_neta >= 0 ? '📈 Ganancia' : '📉 Pérdida' ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== RESUMEN DE GANANCIAS (detalle) ===== -->
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <div class="card bg-light">
                                <div class="card-body py-3">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <span class="text-muted">Total Ventas</span>
                                            <h5 class="text-success mb-0">S/<?= number_format($total_ventas, 2) ?></h5>
                                        </div>
                                        <div class="col-4">
                                            <span class="text-muted">Costo de Ventas</span>
                                            <h5 class="text-danger mb-0">S/<?= number_format($costo_ventas, 2) ?></h5>
                                        </div>
                                        <div class="col-4">
                                            <span class="text-muted">💰 Ganancia Neta</span>
                                            <h5 class="<?= $ganancia_neta >= 0 ? 'text-success' : 'text-danger' ?> mb-0">
                                                S/<?= number_format($ganancia_neta, 2) ?>
                                            </h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <!-- ===== ALERTAS DE STOCK BAJO ===== -->
                        <div class="col-xl-4">
                            <div class="section-card">
                                <div class="card-header-custom">
                                    <h5><i class="bi bi-exclamation-triangle text-warning"></i> Alertas de Stock</h5>
                                    <span class="badge bg-<?= $total_bajo_stock > 0 ? 'danger' : 'success' ?>">
                                        <?= $total_bajo_stock > 0 ? $total_bajo_stock . ' alertas' : 'Sin alertas' ?>
                                    </span>
                                </div>
                                <div class="card-body-custom">
                                    <?php if (empty($productos_bajo_stock)): ?>
                                        <div class="text-center py-4">
                                            <i class="bi bi-check-circle-fill text-success" style="font-size: 2.5rem;"></i>
                                            <p class="text-muted mt-2">¡Todo en orden! Todos los productos tienen stock suficiente.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($productos_bajo_stock as $producto): ?>
                                            <div class="alert alert-stock alert-<?= $producto['stock_actual'] == 0 ? 'danger' : 'warning' ?> mb-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong><?= htmlspecialchars($producto['nombre']) ?></strong>
                                                        <br>
                                                        <small class="text-muted">Código: <?= htmlspecialchars($producto['codigo']) ?></small>
                                                    </div>
                                                    <div class="text-end">
                                                        <span class="badge bg-<?= $producto['stock_actual'] == 0 ? 'danger' : 'warning' ?>">
                                                            <?= $producto['stock_actual'] ?> / <?= $producto['stock_minimo'] ?>
                                                        </span>
                                                        <br>
                                                        <a href="movimientos.php?action=entrada&producto_id=<?= $producto['id'] ?>" class="btn btn-sm btn-primary mt-1">
                                                            <i class="bi bi-plus-circle"></i> Reabastecer
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- ===== ÚLTIMOS MOVIMIENTOS ===== -->
                        <div class="col-xl-8">
                            <div class="section-card">
                                <div class="card-header-custom">
                                    <h5><i class="bi bi-clock-history"></i> Últimos Movimientos</h5>
                                    <a href="movimientos.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
                                </div>
                                <div class="card-body-custom">
                                    <div class="table-responsive">
                                        <table class="table table-modern">
                                            <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Producto</th>
                                                    <th>Tipo</th>
                                                    <th>Cantidad</th>
                                                    <th>Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($movimientos_recientes)): ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center py-3 text-muted">
                                                            <i class="bi bi-inbox"></i> No hay movimientos recientes
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($movimientos_recientes as $mov): ?>
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
                                                                    <?php endif; ?>
                                                                </span>
                                                            </td>
                                                            <td class="text-center"><strong><?= $mov['cantidad'] ?></strong></td>
                                                            <td>S/<?= number_format($mov['total'], 2) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== TOP PRODUCTOS Y STOCK CRÍTICO ===== -->
                    <div class="row g-4 mt-0">
                        <!-- ===== TOP PRODUCTOS VENDIDOS ===== -->
                        <div class="col-xl-6">
                            <div class="section-card">
                                <div class="card-header-custom">
                                    <h5><i class="bi bi-trophy text-warning"></i> Top Productos Vendidos</h5>
                                    <span class="text-muted" style="font-size: 0.8rem;">Últimos 30 días</span>
                                </div>
                                <div class="card-body-custom">
                                    <?php if (empty($top_productos)): ?>
                                        <div class="text-center py-4 text-muted">
                                            <i class="bi bi-bar-chart" style="font-size: 2rem;"></i>
                                            <p class="mt-2">Sin ventas registradas en el último mes</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($top_productos as $index => $producto): 
                                            $ganancia_producto = $producto['total_ingresos'] - $producto['total_costo'];
                                        ?>
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="me-3" style="width: 30px; text-align: center;">
                                                    <span class="badge bg-<?= $index == 0 ? 'warning' : ($index == 1 ? 'secondary' : ($index == 2 ? 'orange' : 'light')) ?> rounded-circle" style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;">
                                                        <?= $index + 1 ?>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <strong><?= htmlspecialchars($producto['nombre']) ?></strong>
                                                    <br><small class="text-muted"><?= htmlspecialchars($producto['codigo']) ?></small>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-primary"><?= $producto['total_vendido'] ?> und.</span>
                                                    <br><small class="text-muted">Ganancia: S/<?= number_format($ganancia_producto, 2) ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- ===== PRODUCTOS CON STOCK CRÍTICO ===== -->
                        <div class="col-xl-6">
                            <div class="section-card">
                                <div class="card-header-custom">
                                    <h5><i class="bi bi-exclamation-circle text-danger"></i> Stock Crítico</h5>
                                    <span class="badge bg-<?= count($productos_criticos) > 0 ? 'warning' : 'success' ?>">
                                        <?= count($productos_criticos) > 0 ? count($productos_criticos) . ' productos' : 'Sin productos críticos' ?>
                                    </span>
                                </div>
                                <div class="card-body-custom">
                                    <?php if (empty($productos_criticos)): ?>
                                        <div class="text-center py-4 text-muted">
                                            <i class="bi bi-check-circle-fill text-success" style="font-size: 2.5rem;"></i>
                                            <p class="mt-2">Todos los productos tienen stock saludable</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($productos_criticos as $producto): 
                                            $porcentaje = ($producto['stock_actual'] / $producto['stock_maximo']) * 100;
                                            $color = $porcentaje <= 25 ? 'danger' : ($porcentaje <= 50 ? 'warning' : 'info');
                                        ?>
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="flex-grow-1">
                                                    <strong><?= htmlspecialchars($producto['nombre']) ?></strong>
                                                    <br>
                                                    <div class="progress" style="height: 6px; width: 100%;">
                                                        <div class="progress-bar bg-<?= $color ?>" style="width: <?= $porcentaje ?>%;"></div>
                                                    </div>
                                                    <small class="text-muted">
                                                        Stock: <?= $producto['stock_actual'] ?> / <?= $producto['stock_maximo'] ?> 
                                                        (Mínimo: <?= $producto['stock_minimo'] ?>)
                                                    </small>
                                                </div>
                                                <div class="ms-3">
                                                    <span class="badge bg-<?= $color ?>">
                                                        <?= round($porcentaje) ?>%
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
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
    <script>
        // Animación de entrada para las tarjetas
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .section-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 * index);
            });
        });
    </script>
</body>
</html>