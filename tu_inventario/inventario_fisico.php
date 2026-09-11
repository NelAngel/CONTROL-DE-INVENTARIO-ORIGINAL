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

// ============ PROCESAR FORMULARIO - CREAR CONTEO FÍSICO ============
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create') {
    if (!hasPermission('INVENTARIO')) {
        $message = '❌ No tienes permiso para realizar inventario físico';
    } else {
        try {
            // Validar campos obligatorios
            if (empty($_POST['id_producto']) || empty($_POST['cantidad_contada']) || empty($_POST['fecha_conteo'])) {
                throw new Exception('Todos los campos son obligatorios');
            }
            
            // Obtener stock actual del sistema
            $stock_sistema = getStock($_POST['id_producto']);
            $diferencia = $_POST['cantidad_contada'] - $stock_sistema;
            
            $stmt = $pdo->prepare("INSERT INTO inventario_fisico 
                                   (id_producto, cantidad_contada, cantidad_sistema, diferencia, fecha_conteo, id_usuario_responsable, comentario) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['id_producto'],
                $_POST['cantidad_contada'],
                $stock_sistema,
                $diferencia,
                $_POST['fecha_conteo'],
                $_SESSION['user_id'],
                $_POST['comentario'] ?? ''
            ]);
            
            $id = $pdo->lastInsertId();
            
            // Registrar en auditoría
            logAudit('CREATE', 'inventario_fisico', $id, null, [
                'producto' => $_POST['id_producto'],
                'contado' => $_POST['cantidad_contada'],
                'sistema' => $stock_sistema,
                'diferencia' => $diferencia
            ]);
            
            // Si hay diferencia y se quiere ajustar automáticamente
            if ($diferencia != 0 && isset($_POST['ajustar_stock']) && $_POST['ajustar_stock'] == '1') {
                // Crear un movimiento de ajuste automático
                $tipo_ajuste = $diferencia > 0 ? 'ENTRADA' : 'SALIDA';
                $cantidad_ajuste = abs($diferencia);
                
                $stmt = $pdo->prepare("INSERT INTO movimientos 
                                       (id_producto, tipo_movimiento, cantidad, precio_unitario, total, id_usuario, comentario) 
                                       VALUES (?, ?, ?, 0, 0, ?, ?)");
                $stmt->execute([
                    $_POST['id_producto'],
                    $tipo_ajuste,
                    $cantidad_ajuste,
                    $_SESSION['user_id'],
                    "Ajuste automático por inventario físico (conteo: {$_POST['cantidad_contada']}, sistema: $stock_sistema)"
                ]);
                
                $message = '✅ Conteo registrado y stock ajustado automáticamente';
            } else {
                $message = '✅ Conteo registrado exitosamente. Diferencia: ' . ($diferencia > 0 ? '+' : '') . $diferencia . ' unidades';
            }
            
        } catch (PDOException $e) {
            $message = '❌ Error: ' . $e->getMessage();
        } catch (Exception $e) {
            $message = '❌ ' . $e->getMessage();
        }
    }
}

// ============ PROCESAR FORMULARIO - AJUSTAR STOCK MANUALMENTE ============
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'ajustar') {
    if (!hasPermission('ADMIN')) {
        $message = '❌ No tienes permiso para ajustar stock';
    } else {
        try {
            $id = $_POST['id'];
            
            // Obtener datos del conteo
            $stmt = $pdo->prepare("SELECT * FROM inventario_fisico WHERE id = ?");
            $stmt->execute([$id]);
            $conteo = $stmt->fetch();
            
            if (!$conteo) {
                throw new Exception('Conteo no encontrado');
            }
            
            // Crear movimiento de ajuste
            $diferencia = $conteo['diferencia'];
            $tipo_ajuste = $diferencia > 0 ? 'ENTRADA' : 'SALIDA';
            $cantidad_ajuste = abs($diferencia);
            
            $stmt = $pdo->prepare("INSERT INTO movimientos 
                                   (id_producto, tipo_movimiento, cantidad, precio_unitario, total, id_usuario, comentario) 
                                   VALUES (?, ?, ?, 0, 0, ?, ?)");
            $stmt->execute([
                $conteo['id_producto'],
                $tipo_ajuste,
                $cantidad_ajuste,
                $_SESSION['user_id'],
                "Ajuste manual por inventario físico ID: $id"
            ]);
            
            logAudit('UPDATE', 'inventario_fisico', $id);
            $message = '✅ Stock ajustado exitosamente';
            
        } catch (PDOException $e) {
            $message = '❌ Error: ' . $e->getMessage();
        } catch (Exception $e) {
            $message = '❌ ' . $e->getMessage();
        }
    }
}

// ============ OBTENER PRODUCTOS PARA EL SELECT ============
$productos = $pdo->query("SELECT id, codigo, nombre FROM productos WHERE activo = 1 ORDER BY nombre")->fetchAll();

// ============ OBTENER CONTEOS RECIENTES ============
$conteos = $pdo->query("
    SELECT i.*, 
           p.nombre as producto_nombre, 
           p.codigo as producto_codigo,
           u.nombre_completo as responsable_nombre
    FROM inventario_fisico i
    JOIN productos p ON i.id_producto = p.id
    LEFT JOIN usuarios u ON i.id_usuario_responsable = u.id
    WHERE i.fecha_conteo >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY i.fecha_conteo DESC, i.id DESC
    LIMIT 100
")->fetchAll();

// ============ ESTADÍSTICAS ============
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_conteos,
        SUM(CASE WHEN diferencia != 0 THEN 1 ELSE 0 END) as conteos_con_diferencia,
        SUM(CASE WHEN diferencia > 0 THEN 1 ELSE 0 END) as conteos_positivos,
        SUM(CASE WHEN diferencia < 0 THEN 1 ELSE 0 END) as conteos_negativos,
        COUNT(DISTINCT id_producto) as productos_contados,
        COALESCE(SUM(ABS(diferencia)), 0) as total_diferencia
    FROM inventario_fisico
    WHERE fecha_conteo >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetch();

$total_productos = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1")->fetch()['total'];
$movimientos_hoy = $pdo->query("SELECT COUNT(*) as total FROM movimientos WHERE DATE(fecha_movimiento) = CURDATE()")->fetch()['total'];
$total_categorias = $pdo->query("SELECT COUNT(*) as total FROM categorias WHERE activo = 1")->fetch()['total'];
$total_proveedores = $pdo->query("SELECT COUNT(*) as total FROM proveedores WHERE activo = 1")->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario Físico - Sistema de Inventario</title>
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
        .badge-modern.diferencia-positiva { background: #dcfce7; color: #16a34a; }
        .badge-modern.diferencia-negativa { background: #fee2e2; color: #dc2626; }
        .badge-modern.diferencia-cero { background: #f3f4f6; color: #6b7280; }
        
        /* ===== FORMULARIO ===== */
        .form-modern .form-control,
        .form-modern .form-select {
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            padding: 10px 14px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        .form-modern .form-control:focus,
        .form-modern .form-select:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        .form-modern .form-label {
            font-weight: 600;
            font-size: 0.8rem;
            color: #4b5563;
        }
        .form-modern .form-check-input:checked {
            background-color: #4f46e5;
            border-color: #4f46e5;
        }
        
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
                        <li><a href="productos.php"><i class="bi bi-box"></i> Productos <span class="badge-sidebar blue"><?= $total_productos ?></span></a></li>
                        <li><a href="movimientos.php"><i class="bi bi-arrows-exchange"></i> Movimientos <span class="badge-sidebar purple"><?= $movimientos_hoy ?></span></a></li>
                        <li><a href="categorias.php"><i class="bi bi-tags"></i> Categorías <span class="badge-sidebar green"><?= $total_categorias ?></span></a></li>
                        <li><a href="proveedores.php"><i class="bi bi-truck"></i> Proveedores <span class="badge-sidebar cyan"><?= $total_proveedores ?></span></a></li>
<li><a href="inventario_fisico.php" class="active"><i class="bi bi-clipboard-check"></i> Inventario Físico</a></li>                        <li><a href="reportes.php"><i class="bi bi-file-earmark-text"></i> Reportes</a></li>
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
                            <h1 class="page-title">📋 Inventario Físico</h1>
                            <p class="page-subtitle">Conteo y ajuste de stock físico vs sistema</p>
                        </div>
                        <div>
                            <span class="badge bg-primary p-2">
                                <i class="bi bi-calendar3"></i> <?= date('d/m/Y') ?>
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

                    <!-- ===== TARJETAS DE ESTADÍSTICAS ===== -->
                    <div class="row g-3 mb-4">
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon blue"><i class="bi bi-clipboard-check"></i></div>
                                <div class="stat-number"><?= number_format($stats['total_conteos'] ?? 0) ?></div>
                                <div class="stat-label">Total Conteos</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-calendar-range"></i> Últimos 30 días</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon green"><i class="bi bi-box"></i></div>
                                <div class="stat-number"><?= number_format($stats['productos_contados'] ?? 0) ?></div>
                                <div class="stat-label">Productos Contados</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-percent"></i> <?= $total_productos > 0 ? round(($stats['productos_contados'] / $total_productos) * 100) : 0 ?>% del total</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon red"><i class="bi bi-arrow-up-circle text-danger"></i></div>
                                <div class="stat-number text-danger"><?= number_format($stats['conteos_negativos'] ?? 0) ?></div>
                                <div class="stat-label">Conteos con Faltante</div>
                                <div class="mt-2">
                                    <span class="stat-change down"><i class="bi bi-dash-circle"></i> Stock < sistema</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon orange"><i class="bi bi-arrow-down-circle text-success"></i></div>
                                <div class="stat-number text-success"><?= number_format($stats['conteos_positivos'] ?? 0) ?></div>
                                <div class="stat-label">Conteos con Excedente</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-plus-circle"></i> Stock > sistema</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== FORMULARIO DE CONTEO ===== -->
                    <div class="section-card mb-4">
                        <div class="card-header-custom">
                            <h5><i class="bi bi-plus-circle"></i> Nuevo Conteo Físico</h5>
                            <span class="text-muted" style="font-size: 0.8rem;">Registra el stock físico y compara con el sistema</span>
                        </div>
                        <div class="card-body-custom">
                            <form method="POST" class="form-modern" id="conteoForm">
                                <input type="hidden" name="action" value="create">
                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <label class="form-label">Producto *</label>
                                        <select name="id_producto" id="id_producto" class="form-select" required>
                                            <option value="">Seleccionar producto...</option>
                                            <?php foreach ($productos as $p): ?>
                                                <option value="<?= $p['id'] ?>">
                                                    <?= htmlspecialchars($p['codigo'] . ' - ' . $p['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (empty($productos)): ?>
                                            <div class="text-danger small mt-1">
                                                <i class="bi bi-exclamation-circle"></i> No hay productos registrados. 
                                                <a href="productos.php">Crea un producto primero</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Stock en Sistema</label>
                                        <input type="number" name="cantidad_sistema" id="cantidad_sistema" class="form-control" readonly value="0">
                                        <div class="form-text text-muted">Se carga automáticamente</div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Cantidad Contada *</label>
                                        <input type="number" name="cantidad_contada" id="cantidad_contada" class="form-control" placeholder="0" required min="0">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Fecha Conteo *</label>
                                        <input type="date" name="fecha_conteo" id="fecha_conteo" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-8">
                                        <label class="form-label">Comentario</label>
                                        <input type="text" name="comentario" id="comentario" class="form-control" placeholder="Observaciones del conteo...">
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="ajustar_stock" id="ajustar_stock" value="1" checked>
                                            <label class="form-check-label" for="ajustar_stock">
                                                <strong>Ajustar stock automáticamente</strong>
                                                <br>
                                                <small class="text-muted">Si hay diferencia, crea movimiento de ajuste</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Registrar Conteo
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- ===== TABLA DE CONTEOS ===== -->
                    <div class="section-card">
                        <div class="card-header-custom">
                            <h5><i class="bi bi-clock-history"></i> Historial de Conteos</h5>
                            <div>
                                <span class="badge bg-secondary me-2"><?= count($conteos) ?> registros</span>
                            </div>
                        </div>
                        <div class="card-body-custom">
                            <div class="table-responsive">
                                <table id="conteosTable" class="table table-modern">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Producto</th>
                                            <th>Stock Sistema</th>
                                            <th>Stock Contado</th>
                                            <th>Diferencia</th>
                                            <th>Responsable</th>
                                            <th>Comentario</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($conteos)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-4">
                                                    <i class="bi bi-inbox fs-1 d-block text-muted"></i>
                                                    <span class="text-muted">No hay conteos realizados en los últimos 30 días</span>
                                                    <br>
                                                    <span class="text-muted small">Registra tu primer conteo físico</span>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($conteos as $conteo): 
                                                $diferencia = $conteo['diferencia'];
                                                $diferencia_class = $diferencia > 0 ? 'diferencia-positiva' : ($diferencia < 0 ? 'diferencia-negativa' : 'diferencia-cero');
                                                $diferencia_signo = $diferencia > 0 ? '+' : '';
                                                $diferencia_icon = $diferencia > 0 ? 'arrow-up' : ($diferencia < 0 ? 'arrow-down' : 'dash');
                                                $diferencia_color = $diferencia > 0 ? 'text-success' : ($diferencia < 0 ? 'text-danger' : 'text-muted');
                                            ?>
                                            <tr>
                                                <td style="font-size: 0.8rem;">
                                                    <?= date('d/m/Y', strtotime($conteo['fecha_conteo'])) ?>
                                                    <br><span class="text-muted"><?= date('H:i', strtotime($conteo['fecha_conteo'])) ?></span>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($conteo['producto_nombre']) ?></strong>
                                                    <br><small class="text-muted"><?= htmlspecialchars($conteo['producto_codigo']) ?></small>
                                                </td>
                                                <td class="text-center"><strong><?= $conteo['cantidad_sistema'] ?></strong></td>
                                                <td class="text-center"><strong><?= $conteo['cantidad_contada'] ?></strong></td>
                                                <td class="text-center <?= $diferencia_color ?>">
                                                    <span class="badge-modern <?= $diferencia_class ?>">
                                                        <?= $diferencia_signo . $diferencia ?>
                                                        <i class="bi bi-<?= $diferencia_icon ?>"></i>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($conteo['responsable_nombre'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($conteo['comentario'] ?: '-') ?></td>
                                                <td>
                                                    <?php if ($conteo['diferencia'] != 0 && hasPermission('ADMIN')): ?>
                                                        <button class="btn btn-sm btn-outline-warning" onclick="ajustarStock(<?= $conteo['id'] ?>)">
                                                            <i class="bi bi-pencil-square"></i> Ajustar
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted" style="font-size: 0.75rem;">Sin ajuste</span>
                                                    <?php endif; ?>
                                                </td>
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
            $('#conteosTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                },
                order: [[0, 'desc']],
                pageLength: 25
            });
        });

        // ===== CARGAR STOCK DEL SISTEMA =====
        $('#id_producto').on('change', function() {
            const producto_id = $(this).val();
            if (producto_id) {
                $('#cantidad_sistema').val('Cargando...');
                
                $.get('get_stock_producto.php?id=' + producto_id, function(data) {
                    if (data.stock !== undefined) {
                        $('#cantidad_sistema').val(data.stock);
                        if (data.error) {
                            $('#cantidad_sistema').val('⚠️ ' + data.error);
                        }
                    } else {
                        $('#cantidad_sistema').val('Error');
                    }
                }).fail(function() {
                    $('#cantidad_sistema').val('❌ Error de conexión');
                });
            } else {
                $('#cantidad_sistema').val('');
            }
        });

        // ===== AJUSTAR STOCK =====
        function ajustarStock(id) {
            if (confirm('¿Estás seguro de que deseas ajustar el stock para este conteo?\n\nSe creará un movimiento automático para igualar el stock.')) {
                $('<form method="POST">' +
                    '<input type="hidden" name="action" value="ajustar">' +
                    '<input type="hidden" name="id" value="' + id + '">' +
                    '</form>').appendTo('body').submit();
            }
        }

        // ===== VALIDACIÓN =====
        $('#cantidad_contada').on('input', function() {
            if ($(this).val() < 0) {
                $(this).val(0);
            }
        });

        // ===== RESETEAR FORMULARIO =====
        $('#conteoForm').on('reset', function() {
            $('#cantidad_sistema').val('');
            $('#fecha_conteo').val('<?= date('Y-m-d') ?>');
        });
    </script>
</body>
</html>