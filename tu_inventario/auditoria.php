<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// Verificar autenticación
if (!isAuthenticated()) { 
    header('Location: login.php'); 
    exit(); 
}

// Verificar permisos de administrador
if (!hasPermission('ADMIN')) {
    die('❌ Acceso denegado. Solo los administradores pueden ver la auditoría.');
}

$pdo = getConnection();
$message = '';

// OBTENER FILTROS
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$usuario_filtro = $_GET['usuario_id'] ?? '';
$accion_filtro = $_GET['accion'] ?? '';
$tabla_filtro = $_GET['tabla'] ?? '';

// OBTENER USUARIOS PARA FILTRO
$usuarios = $pdo->query("SELECT id, usuario, nombre_completo FROM usuarios WHERE activo = 1 ORDER BY nombre_completo")->fetchAll();

// CONSTRUIR CONSULTA
$sql = "
    SELECT a.*, 
           u.usuario,
           u.nombre_completo as usuario_nombre,
           u.rol as usuario_rol
    FROM auditoria a
    LEFT JOIN usuarios u ON a.id_usuario = u.id
    WHERE DATE(a.fecha) BETWEEN ? AND ?
";

$params = [$fecha_inicio, $fecha_fin];

if (!empty($usuario_filtro)) {
    $sql .= " AND a.id_usuario = ?";
    $params[] = $usuario_filtro;
}

if (!empty($accion_filtro)) {
    $sql .= " AND a.accion = ?";
    $params[] = $accion_filtro;
}

if (!empty($tabla_filtro)) {
    $sql .= " AND a.tabla_afectada = ?";
    $params[] = $tabla_filtro;
}

$sql .= " ORDER BY a.fecha DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$auditoria = $stmt->fetchAll();

// OBTENER ESTADÍSTICAS
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        COUNT(DISTINCT id_usuario) as usuarios_activos,
        COUNT(DISTINCT tabla_afectada) as tablas_afectadas
    FROM auditoria
    WHERE DATE(fecha) BETWEEN '{$fecha_inicio}' AND '{$fecha_fin}'
")->fetch();

$acciones_stats = $pdo->query("
    SELECT accion, COUNT(*) as total
    FROM auditoria
    WHERE DATE(fecha) BETWEEN '{$fecha_inicio}' AND '{$fecha_fin}'
    GROUP BY accion
    ORDER BY total DESC
")->fetchAll();

// OBTENER TABLAS PARA FILTRO
$tablas = $pdo->query("
    SELECT DISTINCT tabla_afectada 
    FROM auditoria 
    WHERE tabla_afectada IS NOT NULL 
    ORDER BY tabla_afectada
")->fetchAll();

// OBTENER ACCIONES PARA FILTRO
$acciones = $pdo->query("
    SELECT DISTINCT accion 
    FROM auditoria 
    ORDER BY accion
")->fetchAll();

// ============ PROCESAR LIMPIEZA DE AUDITORÍA ============
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'clean') {
    try {
        $fecha_limite = date('Y-m-d', strtotime('-90 days'));
        
        $stmt = $pdo->prepare("DELETE FROM auditoria WHERE DATE(fecha) < ?");
        $stmt->execute([$fecha_limite]);
        
        $message = '✅ Auditoría limpiada. Registros eliminados: ' . $stmt->rowCount();
        
    } catch (PDOException $e) {
        $message = '❌ Error: ' . $e->getMessage();
    }
}

// ============ VARIABLES PARA EL SIDEBAR ============
$total_productos_sidebar = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1")->fetch()['total'];
$movimientos_hoy = $pdo->query("SELECT COUNT(*) as total FROM movimientos WHERE DATE(fecha_movimiento) = CURDATE()")->fetch()['total'];
$total_categorias = $pdo->query("SELECT COUNT(*) as total FROM categorias WHERE activo = 1")->fetch()['total'];
$total_proveedores = $pdo->query("SELECT COUNT(*) as total FROM proveedores WHERE activo = 1")->fetch()['total'];
$total_conteos = $pdo->query("SELECT COUNT(*) as total FROM inventario_fisico WHERE fecha_conteo >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch()['total'];
$total_usuarios = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = 1")->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría - Sistema de Inventario</title>
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
        .filter-section .btn-periodo {
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: 8px;
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
        .badge-modern.create { background: #dcfce7; color: #16a34a; }
        .badge-modern.update { background: #fef3c7; color: #d97706; }
        .badge-modern.delete { background: #fee2e2; color: #dc2626; }
        .badge-modern.login { background: #e0f2fe; color: #0284c7; }
        .badge-modern.logout { background: #f3f4f6; color: #6b7280; }
        
        /* ===== MODAL ===== */
        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        .modal-header {
            border-bottom: 1px solid #e5e7eb;
            padding: 16px 24px;
        }
        .modal-footer {
            border-top: 1px solid #e5e7eb;
            padding: 16px 24px;
        }
        .modal-datos {
            max-height: 300px;
            overflow-y: auto;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            white-space: pre-wrap;
            word-break: break-all;
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
                        <li><a href="productos.php"><i class="bi bi-box"></i> Productos <span class="badge-sidebar blue"><?= $total_productos_sidebar ?></span></a></li>
                        <li><a href="movimientos.php"><i class="bi bi-arrows-exchange"></i> Movimientos <span class="badge-sidebar purple"><?= $movimientos_hoy ?></span></a></li>
                        <li><a href="categorias.php"><i class="bi bi-tags"></i> Categorías <span class="badge-sidebar green"><?= $total_categorias ?></span></a></li>
                        <li><a href="proveedores.php"><i class="bi bi-truck"></i> Proveedores <span class="badge-sidebar cyan"><?= $total_proveedores ?></span></a></li>
                        <li><a href="inventario_fisico.php"><i class="bi bi-clipboard-check"></i> Inventario Físico</a></li>
                        <li><a href="reportes.php"><i class="bi bi-file-earmark-text"></i> Reportes</a></li>
                        <?php if (hasPermission('ADMIN')): ?>
                        <li><a href="usuarios.php"><i class="bi bi-people"></i> Usuarios <span class="badge-sidebar blue"><?= $total_usuarios ?></span></a></li>
                        <li><a href="auditoria.php" class="active"><i class="bi bi-clock-history"></i> Auditoría</a></li>
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
                            <h1 class="page-title">📋 Auditoría del Sistema</h1>
                            <p class="page-subtitle">Registro detallado de todas las acciones del sistema</p>
                        </div>
                        <div>
                            <span class="badge bg-primary p-2">
                                <i class="bi bi-calendar3"></i> <?= date('d/m/Y') ?>
                            </span>
                            <button class="btn btn-outline-danger btn-sm ms-2" onclick="limpiarAuditoria()">
                                <i class="bi bi-trash"></i> Limpiar (90 días)
                            </button>
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
                                <div class="stat-icon blue"><i class="bi bi-list-check"></i></div>
                                <div class="stat-number"><?= number_format($stats['total'] ?? 0) ?></div>
                                <div class="stat-label">Total Eventos</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-calendar-range"></i> En el período</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon green"><i class="bi bi-people"></i></div>
                                <div class="stat-number"><?= $stats['usuarios_activos'] ?? 0 ?></div>
                                <div class="stat-label">Usuarios Activos</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-person-check"></i> Han realizado acciones</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon purple"><i class="bi bi-table"></i></div>
                                <div class="stat-number"><?= $stats['tablas_afectadas'] ?? 0 ?></div>
                                <div class="stat-label">Tablas Afectadas</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-database"></i> Módulos usados</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon orange"><i class="bi bi-calendar-range"></i></div>
                                <div class="stat-number" style="font-size: 1rem; font-weight: 600;">
                                    <?= date('d/m/Y', strtotime($fecha_inicio)) ?> - <?= date('d/m/Y', strtotime($fecha_fin)) ?>
                                </div>
                                <div class="stat-label">Período</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-clock"></i> Filtro aplicado</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== RESUMEN DE ACCIONES ===== -->
                    <?php if (!empty($acciones_stats)): ?>
                    <div class="row g-2 mb-4">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-center gap-2 p-3 bg-white rounded-16" style="border-radius: 16px; border: 1px solid #e5e7eb;">
                                <span class="fw-bold me-2"><i class="bi bi-bar-chart"></i> Acciones:</span>
                                <?php foreach ($acciones_stats as $accion): ?>
                                <span class="badge-modern <?= strtolower($accion['accion']) ?> d-flex align-items-center gap-1" style="padding: 4px 12px; font-size: 0.8rem;">
                                    <?= $accion['accion'] ?>
                                    <span class="badge bg-white text-dark rounded-pill" style="font-size: 0.7rem;"><?= $accion['total'] ?></span>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ===== FILTROS ===== -->
                    <div class="filter-section">
                        <form method="GET" id="filterForm">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-2">
                                    <label class="form-label">Fecha Inicio</label>
                                    <input type="date" name="fecha_inicio" class="form-control" value="<?= $fecha_inicio ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Fecha Fin</label>
                                    <input type="date" name="fecha_fin" class="form-control" value="<?= $fecha_fin ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Usuario</label>
                                    <select name="usuario_id" class="form-select">
                                        <option value="">Todos</option>
                                        <?php foreach ($usuarios as $u): ?>
                                            <option value="<?= $u['id'] ?>" <?= $usuario_filtro == $u['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($u['usuario']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Acción</label>
                                    <select name="accion" class="form-select">
                                        <option value="">Todas</option>
                                        <?php foreach ($acciones as $a): ?>
                                            <option value="<?= $a['accion'] ?>" <?= $accion_filtro == $a['accion'] ? 'selected' : '' ?>>
                                                <?= $a['accion'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Tabla</label>
                                    <select name="tabla" class="form-select">
                                        <option value="">Todas</option>
                                        <?php foreach ($tablas as $t): ?>
                                            <option value="<?= $t['tabla_afectada'] ?>" <?= $tabla_filtro == $t['tabla_afectada'] ? 'selected' : '' ?>>
                                                <?= $t['tabla_afectada'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search"></i> Filtrar
                                    </button>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-12">
                                    <div class="d-flex gap-1">
                                        <span class="fw-bold small me-2" style="color: #6b7280;">Período rápido:</span>
                                        <button type="button" class="btn btn-outline-secondary btn-periodo" onclick="setPeriodo('hoy')">Hoy</button>
                                        <button type="button" class="btn btn-outline-secondary btn-periodo" onclick="setPeriodo('semana')">Semana</button>
                                        <button type="button" class="btn btn-outline-secondary btn-periodo" onclick="setPeriodo('mes')">Mes</button>
                                        <button type="button" class="btn btn-outline-secondary btn-periodo" onclick="setPeriodo('trimestre')">Trimestre</button>
                                        <a href="auditoria.php" class="btn btn-outline-secondary btn-periodo">
                                            <i class="bi bi-arrow-counterclockwise"></i> Limpiar
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- ===== TABLA DE AUDITORÍA ===== -->
                    <div class="section-card">
                        <div class="card-header-custom">
                            <h5><i class="bi bi-list-ul"></i> Registros de Auditoría</h5>
                            <span class="badge bg-secondary"><?= count($auditoria) ?> registros</span>
                        </div>
                        <div class="card-body-custom">
                            <div class="table-responsive">
                                <table id="auditoriaTable" class="table table-modern">
                                    <thead>
                                        <tr>
                                            <th>Fecha/Hora</th>
                                            <th>Usuario</th>
                                            <th>Acción</th>
                                            <th>Tabla</th>
                                            <th>Registro</th>
                                            <th>IP</th>
                                            <th>Detalles</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($auditoria)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4">
                                                    <i class="bi bi-inbox fs-1 d-block text-muted"></i>
                                                    <span class="text-muted">No hay registros de auditoría para el período seleccionado</span>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($auditoria as $row): ?>
                                            <tr>
                                                <td style="font-size: 0.8rem;">
                                                    <?= date('d/m/Y', strtotime($row['fecha'])) ?>
                                                    <br><span class="text-muted"><?= date('H:i:s', strtotime($row['fecha'])) ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($row['id_usuario']): ?>
                                                        <strong><?= htmlspecialchars($row['usuario'] ?? 'Usuario') ?></strong>
                                                        <br><span class="text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($row['usuario_nombre'] ?? '') ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Sistema</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge-modern <?= strtolower($row['accion']) ?>" style="font-size: 0.7rem;">
                                                        <?= $row['accion'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($row['tabla_afectada']): ?>
                                                        <span class="badge bg-secondary" style="font-size: 0.7rem;"><?= htmlspecialchars($row['tabla_afectada']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center"><?= $row['registro_id'] ?: '-' ?></td>
                                                <td><code style="font-size: 0.7rem;"><?= htmlspecialchars($row['ip_usuario'] ?: 'N/A') ?></code></td>
                                                <td>
                                                    <?php if ($row['datos_anteriores'] || $row['datos_nuevos']): ?>
                                                        <button class="btn btn-sm btn-outline-info" onclick="verDetalle(<?= $row['id'] ?>, '<?= htmlspecialchars($row['datos_anteriores']) ?>', '<?= htmlspecialchars($row['datos_nuevos']) ?>')" style="padding: 2px 8px; font-size: 0.7rem;">
                                                            <i class="bi bi-eye"></i> Ver
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
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

                    <!-- ===== INFORMACIÓN ===== -->
                    <div class="alert alert-secondary mt-3" style="font-size: 0.85rem;">
                        <i class="bi bi-info-circle"></i>
                        <strong>Información:</strong>
                        <ul class="mb-0 mt-1">
                            <li>Registra todas las acciones importantes (CREATE, UPDATE, DELETE, LOGIN, LOGOUT)</li>
                            <li>Los registros con más de 90 días se eliminan automáticamente</li>
                            <li>Solo administradores tienen acceso a esta sección</li>
                        </ul>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- ===== MODAL PARA VER DETALLES ===== -->
    <div class="modal fade" id="detalleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="bi bi-file-earmark-text"></i> Detalles del Cambio</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white py-1">
                                    <small><i class="bi bi-arrow-left"></i> Datos Anteriores</small>
                                </div>
                                <div class="card-body p-2">
                                    <div class="modal-datos" id="datosAnteriores" style="max-height: 200px; background: #fff5f5;">Sin datos</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white py-1">
                                    <small><i class="bi bi-arrow-right"></i> Datos Nuevos</small>
                                </div>
                                <div class="card-body p-2">
                                    <div class="modal-datos" id="datosNuevos" style="max-height: 200px; background: #f0fff4;">Sin datos</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SCRIPTS ===== -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#auditoriaTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                },
                order: [[0, 'desc']],
                pageLength: 25,
                responsive: true,
                columnDefs: [
                    { orderable: false, targets: [6] }
                ]
            });
        });

        function verDetalle(id, datosAnteriores, datosNuevos) {
            try {
                let oldData = datosAnteriores || 'Sin datos anteriores';
                let newData = datosNuevos || 'Sin datos nuevos';
                
                try {
                    if (datosAnteriores) {
                        const parsed = JSON.parse(datosAnteriores);
                        oldData = JSON.stringify(parsed, null, 2);
                    }
                } catch(e) {}
                
                try {
                    if (datosNuevos) {
                        const parsed = JSON.parse(datosNuevos);
                        newData = JSON.stringify(parsed, null, 2);
                    }
                } catch(e) {}
                
                document.getElementById('datosAnteriores').textContent = oldData;
                document.getElementById('datosNuevos').textContent = newData;
                
                const modal = new bootstrap.Modal(document.getElementById('detalleModal'));
                modal.show();
            } catch(e) {
                alert('Error al mostrar los detalles');
            }
        }

        function limpiarAuditoria() {
            if (confirm('¿Eliminar registros con más de 90 días?\n\nEsta acción no se puede deshacer.')) {
                $('<form method="POST">' +
                    '<input type="hidden" name="action" value="clean">' +
                    '</form>').appendTo('body').submit();
            }
        }

        function setPeriodo(tipo) {
            const hoy = new Date();
            let fechaInicio = new Date();
            let fechaFin = new Date(hoy);
            
            switch(tipo) {
                case 'hoy':
                    fechaInicio = new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate());
                    break;
                case 'semana':
                    fechaInicio = new Date(hoy);
                    fechaInicio.setDate(hoy.getDate() - 7);
                    break;
                case 'mes':
                    fechaInicio = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
                    break;
                case 'trimestre':
                    const mesActual = hoy.getMonth();
                    const inicioTrimestre = Math.floor(mesActual / 3) * 3;
                    fechaInicio = new Date(hoy.getFullYear(), inicioTrimestre, 1);
                    break;
                default:
                    return;
            }
            
            const formatDate = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };
            
            document.querySelector('input[name="fecha_inicio"]').value = formatDate(fechaInicio);
            document.querySelector('input[name="fecha_fin"]').value = formatDate(fechaFin);
            
            document.getElementById('filterForm').submit();
        }
    </script>
</body>
</html>