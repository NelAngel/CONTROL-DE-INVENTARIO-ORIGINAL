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

// OBTENER FECHAS PARA FILTROS
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$tipo_reporte = $_GET['tipo_reporte'] ?? 'movimientos';
$producto_filtro = $_GET['producto_id'] ?? '';

// ============ REPORTE DE MOVIMIENTOS ============
if ($tipo_reporte == 'movimientos') {
    $sql = "
        SELECT 
            m.*,
            p.nombre as producto_nombre,
            p.codigo as producto_codigo,
            u.nombre_completo as usuario_nombre,
            c.nombre as categoria_nombre
        FROM movimientos m
        JOIN productos p ON m.id_producto = p.id
        LEFT JOIN usuarios u ON m.id_usuario = u.id
        LEFT JOIN categorias c ON p.id_categoria = c.id
        WHERE DATE(m.fecha_movimiento) BETWEEN ? AND ?
    ";
    
    $params = [$fecha_inicio, $fecha_fin];
    
    if (!empty($producto_filtro)) {
        $sql .= " AND m.id_producto = ?";
        $params[] = $producto_filtro;
    }
    
    $sql .= " ORDER BY m.fecha_movimiento DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reporte_data = $stmt->fetchAll();
    
    // Estadísticas
    $total_entradas = array_sum(array_column(array_filter($reporte_data, function($m) {
        return $m['tipo_movimiento'] == 'ENTRADA';
    }), 'cantidad'));
    
    $total_salidas = array_sum(array_column(array_filter($reporte_data, function($m) {
        return $m['tipo_movimiento'] == 'SALIDA';
    }), 'cantidad'));
    
    $total_movimientos = count($reporte_data);
}

// ============ REPORTE DE STOCK ============
if ($tipo_reporte == 'stock') {
    $sql = "
        SELECT 
            p.*,
            c.nombre as categoria_nombre,
            pr.nombre as proveedor_nombre,
            COALESCE(
                (SELECT SUM(CASE WHEN m.tipo_movimiento IN ('ENTRADA', 'AJUSTE') THEN m.cantidad ELSE 0 END) -
                 SUM(CASE WHEN m.tipo_movimiento IN ('SALIDA', 'TRANSFERENCIA') THEN m.cantidad ELSE 0 END)
                 FROM movimientos m WHERE m.id_producto = p.id), 0
            ) as stock_actual,
            (p.precio_venta * COALESCE(
                (SELECT SUM(CASE WHEN m.tipo_movimiento IN ('ENTRADA', 'AJUSTE') THEN m.cantidad ELSE 0 END) -
                 SUM(CASE WHEN m.tipo_movimiento IN ('SALIDA', 'TRANSFERENCIA') THEN m.cantidad ELSE 0 END)
                 FROM movimientos m WHERE m.id_producto = p.id), 0
            )) as valor_inventario
        FROM productos p
        LEFT JOIN categorias c ON p.id_categoria = c.id
        LEFT JOIN proveedores pr ON p.id_proveedor = pr.id
        WHERE p.activo = 1
    ";
    
    if (!empty($producto_filtro)) {
        $sql .= " AND p.id = ?";
        $params = [$producto_filtro];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        $stmt = $pdo->query($sql);
    }
    
    $reporte_data = $stmt->fetchAll();
    
    // Estadísticas
    $total_productos = count($reporte_data);
    $valor_total_inventario = array_sum(array_column($reporte_data, 'valor_inventario'));
    $productos_bajo_stock = count(array_filter($reporte_data, function($p) {
        return $p['stock_actual'] <= $p['stock_minimo'];
    }));
}

// ============ REPORTE DE PRODUCTOS MÁS VENDIDOS ============
if ($tipo_reporte == 'top_productos') {
    $sql = "
        SELECT 
            p.id,
            p.codigo,
            p.nombre,
            p.precio_venta,
            c.nombre as categoria_nombre,
            COUNT(m.id) as total_ventas,
            SUM(m.cantidad) as cantidad_vendida,
            SUM(m.total) as total_ingresos
        FROM movimientos m
        JOIN productos p ON m.id_producto = p.id
        LEFT JOIN categorias c ON p.id_categoria = c.id
        WHERE m.tipo_movimiento = 'SALIDA'
        AND DATE(m.fecha_movimiento) BETWEEN ? AND ?
    ";
    
    $params = [$fecha_inicio, $fecha_fin];
    
    if (!empty($producto_filtro)) {
        $sql .= " AND m.id_producto = ?";
        $params[] = $producto_filtro;
    }
    
    $sql .= " GROUP BY p.id ORDER BY cantidad_vendida DESC LIMIT 20";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reporte_data = $stmt->fetchAll();
    
    // Estadísticas
    $total_ventas = array_sum(array_column($reporte_data, 'cantidad_vendida'));
    $total_ingresos = array_sum(array_column($reporte_data, 'total_ingresos'));
}

// ============ REPORTE DE PROVEEDORES ============
if ($tipo_reporte == 'proveedores') {
    $sql = "
        SELECT 
            pr.*,
            COUNT(DISTINCT p.id) as total_productos,
            COALESCE(SUM(m.cantidad), 0) as total_compras,
            COALESCE(SUM(m.total), 0) as total_invertido,
            MAX(m.fecha_movimiento) as ultima_compra
        FROM proveedores pr
        LEFT JOIN productos p ON pr.id = p.id_proveedor AND p.activo = 1
        LEFT JOIN movimientos m ON p.id = m.id_producto AND m.tipo_movimiento = 'ENTRADA'
        WHERE pr.activo = 1
        GROUP BY pr.id
        ORDER BY total_compras DESC
    ";
    
    $stmt = $pdo->query($sql);
    $reporte_data = $stmt->fetchAll();
    
    // Estadísticas
    $total_proveedores = count($reporte_data);
    $total_inversion_proveedores = array_sum(array_column($reporte_data, 'total_invertido'));
}

// ============ REPORTE DE CATEGORÍAS ============
if ($tipo_reporte == 'categorias') {
    $sql = "
        SELECT 
            c.*,
            COUNT(DISTINCT p.id) as total_productos,
            COALESCE(SUM(CASE WHEN m.tipo_movimiento = 'ENTRADA' THEN m.cantidad ELSE 0 END), 0) as total_entradas,
            COALESCE(SUM(CASE WHEN m.tipo_movimiento = 'SALIDA' THEN m.cantidad ELSE 0 END), 0) as total_salidas,
            COALESCE(SUM(m.total), 0) as total_movimiento
        FROM categorias c
        LEFT JOIN productos p ON c.id = p.id_categoria AND p.activo = 1
        LEFT JOIN movimientos m ON p.id = m.id_producto
        WHERE c.activo = 1
        GROUP BY c.id
        ORDER BY total_productos DESC
    ";
    
    $stmt = $pdo->query($sql);
    $reporte_data = $stmt->fetchAll();
}

// ============ VARIABLES PARA EL SIDEBAR ============
$total_productos_sidebar = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1")->fetch()['total'];
$movimientos_hoy = $pdo->query("SELECT COUNT(*) as total FROM movimientos WHERE DATE(fecha_movimiento) = CURDATE()")->fetch()['total'];
$total_categorias = $pdo->query("SELECT COUNT(*) as total FROM categorias WHERE activo = 1")->fetch()['total'];
$total_proveedores = $pdo->query("SELECT COUNT(*) as total FROM proveedores WHERE activo = 1")->fetch()['total'];
$total_conteos = $pdo->query("SELECT COUNT(*) as total FROM inventario_fisico WHERE fecha_conteo >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch()['total'];

// OBTENER PRODUCTOS PARA FILTRO
$productos = $pdo->query("SELECT id, codigo, nombre FROM productos WHERE activo = 1 ORDER BY nombre")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sistema de Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
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
        
        /* Badges de sidebar */
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
        
        /* ===== FILTROS ===== */
        .filter-section {
            background: white;
            border-radius: 16px;
            padding: 20px 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            border: 1px solid #e5e7eb;
            margin-bottom: 24px;
        }
        .filter-section .form-label {
            font-weight: 600;
            font-size: 0.8rem;
            color: #4b5563;
            margin-bottom: 4px;
        }
        .filter-section .form-control,
        .filter-section .form-select {
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            padding: 8px 14px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        .filter-section .form-control:focus,
        .filter-section .form-select:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
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
        .badge-modern.stock-normal { background: #dcfce7; color: #16a34a; }
        .badge-modern.stock-critico { background: #fef3c7; color: #d97706; }
        
        /* ===== RESPONSIVE ===== */
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
                        <li><a href="movimientos.php"><i class="bi bi-arrows-exchange"></i> Movimientos <span class="badge-sidebar purple"><?= $movimientos_hoy ?></span></a></li>
                        <li><a href="categorias.php"><i class="bi bi-tags"></i> Categorías <span class="badge-sidebar green"><?= $total_categorias ?></span></a></li>
                        <li><a href="proveedores.php"><i class="bi bi-truck"></i> Proveedores <span class="badge-sidebar cyan"><?= $total_proveedores ?></span></a></li>
                        <li><a href="inventario_fisico.php"><i class="bi bi-clipboard-check"></i> Inventario Físico</a></li>
                        <li><a href="reportes.php" class="active"><i class="bi bi-file-earmark-text"></i> Reportes</a></li>
                        <?php if (hasPermission('ADMIN')): ?>
                        <li><a href="usuarios.php"><i class="bi bi-people"></i> Usuarios</a></li>
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
                            <h1 class="page-title">📄 Reportes</h1>
                            <p class="page-subtitle">Análisis y estadísticas del inventario</p>
                        </div>
                        <div>
                            <span class="badge bg-primary p-2">
                                <i class="bi bi-calendar3"></i> <?= date('d/m/Y') ?>
                            </span>
                        </div>
                    </div>

                    <!-- ===== FILTROS ===== -->
                    <div class="filter-section">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label">Tipo de Reporte</label>
                                <select name="tipo_reporte" class="form-select" onchange="this.form.submit()">
                                    <option value="movimientos" <?= $tipo_reporte == 'movimientos' ? 'selected' : '' ?>>Movimientos</option>
                                    <option value="stock" <?= $tipo_reporte == 'stock' ? 'selected' : '' ?>>Stock Actual</option>
                                    <option value="top_productos" <?= $tipo_reporte == 'top_productos' ? 'selected' : '' ?>>Productos Más Vendidos</option>
                                    <option value="proveedores" <?= $tipo_reporte == 'proveedores' ? 'selected' : '' ?>>Proveedores</option>
                                    <option value="categorias" <?= $tipo_reporte == 'categorias' ? 'selected' : '' ?>>Categorías</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Fecha Inicio</label>
                                <input type="date" name="fecha_inicio" class="form-control" value="<?= $fecha_inicio ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Fecha Fin</label>
                                <input type="date" name="fecha_fin" class="form-control" value="<?= $fecha_fin ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Producto</label>
                                <select name="producto_id" class="form-select">
                                    <option value="">Todos los productos</option>
                                    <?php foreach ($productos as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= $producto_filtro == $p['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['codigo'] . ' - ' . $p['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Generar
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- ===== RESULTADOS DEL REPORTE ===== -->
                    <?php if (isset($reporte_data) && !empty($reporte_data)): ?>
                        
                        <!-- ===== ESTADÍSTICAS ===== -->
                        <div class="row g-3 mb-4">
                            <?php if ($tipo_reporte == 'movimientos'): ?>
                                <div class="col-xl-3 col-lg-6 col-md-6">
                                    <div class="stat-card">
                                        <div class="stat-icon blue"><i class="bi bi-arrow-left-right"></i></div>
                                        <div class="stat-number"><?= $total_movimientos ?></div>
                                        <div class="stat-label">Total Movimientos</div>
                                    </div>
                                </div>
                                <div class="col-xl-3 col-lg-6 col-md-6">
                                    <div class="stat-card">
                                        <div class="stat-icon green"><i class="bi bi-arrow-down-circle"></i></div>
                                        <div class="stat-number text-success"><?= $total_entradas ?></div>
                                        <div class="stat-label">Entradas</div>
                                    </div>
                                </div>
                                <div class="col-xl-3 col-lg-6 col-md-6">
                                    <div class="stat-card">
                                        <div class="stat-icon red"><i class="bi bi-arrow-up-circle"></i></div>
                                        <div class="stat-number text-danger"><?= $total_salidas ?></div>
                                        <div class="stat-label">Salidas</div>
                                    </div>
                                </div>
                                <div class="col-xl-3 col-lg-6 col-md-6">
                                    <div class="stat-card">
                                        <div class="stat-icon purple"><i class="bi bi-currency-dollar"></i></div>
                                        <div class="stat-number">S/<?= number_format(array_sum(array_column($reporte_data, 'total')), 2) ?></div>
                                        <div class="stat-label">Total Movimientos</div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($tipo_reporte == 'stock'): ?>
                                <div class="col-xl-4 col-lg-6 col-md-6">
                                    <div class="stat-card">
                                        <div class="stat-icon blue"><i class="bi bi-box"></i></div>
                                        <div class="stat-number"><?= $total_productos ?></div>
                                        <div class="stat-label">Total Productos</div>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-6">
                                    <div class="stat-card">
                                        <div class="stat-icon green"><i class="bi bi-currency-dollar"></i></div>
                                        <div class="stat-number">S/<?= number_format($valor_total_inventario, 2) ?></div>
                                        <div class="stat-label">Valor Inventario</div>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-6">
                                    <div class="stat-card">
                                        <div class="stat-icon red"><i class="bi bi-exclamation-triangle"></i></div>
                                        <div class="stat-number text-warning"><?= $productos_bajo_stock ?></div>
                                        <div class="stat-label">Productos con Stock Bajo</div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($tipo_reporte == 'top_productos'): ?>
                                <div class="col-xl-4 col-lg-6 col-md-6">
                                    <div class="stat-card">
                                        <div class="stat-icon blue"><i class="bi bi-box"></i></div>
                                        <div class="stat-number"><?= count($reporte_data) ?></div>
                                        <div class="stat-label">Productos con Ventas</div>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-6">
                                    <div class="stat-card">
                                        <div class="stat-icon green"><i class="bi bi-cart"></i></div>
                                        <div class="stat-number"><?= $total_ventas ?></div>
                                        <div class="stat-label">Unidades Vendidas</div>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-6">
                                    <div class="stat-card">
                                        <div class="stat-icon purple"><i class="bi bi-currency-dollar"></i></div>
                                        <div class="stat-number">S/<?= number_format($total_ingresos, 2) ?></div>
                                        <div class="stat-label">Total Ingresos</div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($tipo_reporte == 'proveedores'): ?>
                                <div class="col-xl-4 col-lg-6 col-md-6">
                                    <div class="stat-card">
                                        <div class="stat-icon blue"><i class="bi bi-truck"></i></div>
                                        <div class="stat-number"><?= $total_proveedores ?></div>
                                        <div class="stat-label">Total Proveedores</div>
                                    </div>
                                </div>
                                <div class="col-xl-4 col-lg-6 col-md-6">
                                    <div class="stat-card">
                                        <div class="stat-icon green"><i class="bi bi-currency-dollar"></i></div>
                                        <div class="stat-number">S/<?= number_format($total_inversion_proveedores, 2) ?></div>
                                        <div class="stat-label">Total Inversión</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- ===== TABLA DEL REPORTE ===== -->
                        <div class="section-card">
                            <div class="card-header-custom">
                                <h5><i class="bi bi-table"></i> Detalle del Reporte</h5>
                                <div>
                                    <button class="btn btn-sm btn-success" onclick="exportarCSV()">
                                        <i class="bi bi-file-earmark-excel"></i> CSV
                                    </button>
                                    <button class="btn btn-sm btn-secondary" onclick="window.print()">
                                        <i class="bi bi-printer"></i> Imprimir
                                    </button>
                                </div>
                            </div>
                            <div class="card-body-custom">
                                <div class="table-responsive">
                                    <table id="reporteTable" class="table table-modern">
                                        <thead>
                                            <tr>
                                                <?php if ($tipo_reporte == 'movimientos'): ?>
                                                    <th>Fecha</th>
                                                    <th>Producto</th>
                                                    <th>Código</th>
                                                    <th>Categoría</th>
                                                    <th>Tipo</th>
                                                    <th>Cantidad</th>
                                                    <th>Precio</th>
                                                    <th>Total</th>
                                                    <th>Usuario</th>
                                                    <th>Comentario</th>
                                                <?php endif; ?>

                                                <?php if ($tipo_reporte == 'stock'): ?>
                                                    <th>Código</th>
                                                    <th>Producto</th>
                                                    <th>Categoría</th>
                                                    <th>Proveedor</th>
                                                    <th>Precio Venta</th>
                                                    <th>Stock Actual</th>
                                                    <th>Stock Mínimo</th>
                                                    <th>Valor Inventario</th>
                                                    <th>Estado</th>
                                                <?php endif; ?>

                                                <?php if ($tipo_reporte == 'top_productos'): ?>
                                                    <th>#</th>
                                                    <th>Código</th>
                                                    <th>Producto</th>
                                                    <th>Categoría</th>
                                                    <th>Precio Venta</th>
                                                    <th>Unidades</th>
                                                    <th>Ventas</th>
                                                    <th>Ingresos</th>
                                                <?php endif; ?>

                                                <?php if ($tipo_reporte == 'proveedores'): ?>
                                                    <th>Proveedor</th>
                                                    <th>RUC</th>
                                                    <th>Teléfono</th>
                                                    <th>Email</th>
                                                    <th>Productos</th>
                                                    <th>Compras</th>
                                                    <th>Invertido</th>
                                                    <th>Última Compra</th>
                                                <?php endif; ?>

                                                <?php if ($tipo_reporte == 'categorias'): ?>
                                                    <th>Categoría</th>
                                                    <th>Productos</th>
                                                    <th>Entradas</th>
                                                    <th>Salidas</th>
                                                    <th>Total</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($tipo_reporte == 'movimientos'): ?>
                                                <?php foreach ($reporte_data as $row): ?>
                                                    <tr>
                                                        <td><?= date('d/m/Y H:i', strtotime($row['fecha_movimiento'])) ?></td>
                                                        <td><?= htmlspecialchars($row['producto_nombre']) ?></td>
                                                        <td><?= htmlspecialchars($row['producto_codigo']) ?></td>
                                                        <td><?= htmlspecialchars($row['categoria_nombre'] ?? 'N/A') ?></td>
                                                        <td><span class="badge-modern <?= strtolower($row['tipo_movimiento']) ?>"><?= $row['tipo_movimiento'] ?></span></td>
                                                        <td><?= $row['cantidad'] ?></td>
                                                        <td>S/<?= number_format($row['precio_unitario'], 2) ?></td>
                                                        <td>S/<?= number_format($row['total'], 2) ?></td>
                                                        <td><?= htmlspecialchars($row['usuario_nombre'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($row['comentario'] ?: '') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>

                                            <?php if ($tipo_reporte == 'stock'): ?>
                                                <?php foreach ($reporte_data as $row): 
                                                    $estado = $row['stock_actual'] <= $row['stock_minimo'] ? 'Bajo' : 'Normal';
                                                    $estado_class = $row['stock_actual'] <= $row['stock_minimo'] ? 'stock-bajo' : 'stock-normal';
                                                    if ($row['stock_actual'] == 0) $estado_class = 'stock-critico';
                                                ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['codigo']) ?></td>
                                                        <td><?= htmlspecialchars($row['nombre']) ?></td>
                                                        <td><?= htmlspecialchars($row['categoria_nombre'] ?? 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($row['proveedor_nombre'] ?? 'N/A') ?></td>
                                                        <td>S/<?= number_format($row['precio_venta'], 2) ?></td>
                                                        <td><strong><?= $row['stock_actual'] ?></strong></td>
                                                        <td><?= $row['stock_minimo'] ?></td>
                                                        <td>S/<?= number_format($row['valor_inventario'], 2) ?></td>
                                                        <td><span class="badge-modern <?= $estado_class ?>"><?= $estado ?></span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>

                                            <?php if ($tipo_reporte == 'top_productos'): ?>
                                                <?php $i = 1; foreach ($reporte_data as $row): ?>
                                                    <tr>
                                                        <td><?= $i++ ?></td>
                                                        <td><?= htmlspecialchars($row['codigo']) ?></td>
                                                        <td><?= htmlspecialchars($row['nombre']) ?></td>
                                                        <td><?= htmlspecialchars($row['categoria_nombre'] ?? 'N/A') ?></td>
                                                        <td>S/<?= number_format($row['precio_venta'], 2) ?></td>
                                                        <td><strong><?= $row['cantidad_vendida'] ?></strong></td>
                                                        <td><?= $row['total_ventas'] ?></td>
                                                        <td>S/<?= number_format($row['total_ingresos'], 2) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>

                                            <?php if ($tipo_reporte == 'proveedores'): ?>
                                                <?php foreach ($reporte_data as $row): ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($row['nombre']) ?></strong></td>
                                                        <td><?= htmlspecialchars($row['ruc'] ?: 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($row['telefono'] ?: 'N/A') ?></td>
                                                        <td><?= htmlspecialchars($row['email'] ?: 'N/A') ?></td>
                                                        <td><span class="badge bg-info"><?= $row['total_productos'] ?></span></td>
                                                        <td><?= $row['total_compras'] ?></td>
                                                        <td>S/<?= number_format($row['total_invertido'], 2) ?></td>
                                                        <td><?= $row['ultima_compra'] ? date('d/m/Y', strtotime($row['ultima_compra'])) : 'N/A' ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>

                                            <?php if ($tipo_reporte == 'categorias'): ?>
                                                <?php foreach ($reporte_data as $row): ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($row['nombre']) ?></strong></td>
                                                        <td><span class="badge bg-primary"><?= $row['total_productos'] ?></span></td>
                                                        <td><?= $row['total_entradas'] ?></td>
                                                        <td><?= $row['total_salidas'] ?></td>
                                                        <td>S/<?= number_format($row['total_movimiento'], 2) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            No hay datos para el período seleccionado.
                            <?php if ($tipo_reporte == 'movimientos'): ?>
                                <br>Prueba a registrar algunos <a href="movimientos.php">movimientos</a> primero.
                            <?php endif; ?>
                            <?php if ($tipo_reporte == 'stock'): ?>
                                <br>Prueba a crear algunos <a href="productos.php">productos</a> primero.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
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
            $('#reporteTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                },
                order: [[0, 'desc']],
                pageLength: 25
            });
        });

        function exportarCSV() {
            const table = document.getElementById('reporteTable');
            const rows = table.querySelectorAll('tr');
            let csv = [];
            
            const headers = [];
            const headerCells = rows[0].querySelectorAll('th');
            headerCells.forEach(th => headers.push(th.textContent));
            csv.push(headers.join(','));
            
            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].querySelectorAll('td');
                const rowData = [];
                cells.forEach(td => {
                    let text = td.textContent.trim().replace(/,/g, ';');
                    rowData.push(text);
                });
                csv.push(rowData.join(','));
            }
            
            const blob = new Blob(['\uFEFF' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'reporte_' + new Date().toISOString().split('T')[0] + '.csv';
            link.click();
        }
    </script>
</body>
</html>