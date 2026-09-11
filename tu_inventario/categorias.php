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

// ============ PROCESAR FORMULARIO - CREAR CATEGORÍA ============
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create') {
    if (!hasPermission('INVENTARIO')) {
        $message = '❌ No tienes permiso para crear categorías';
    } else {
        try {
            if (empty($_POST['nombre'])) {
                throw new Exception('El nombre de la categoría es obligatorio');
            }
            
            $stmt = $pdo->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)");
            $stmt->execute([
                $_POST['nombre'],
                $_POST['descripcion'] ?? ''
            ]);
            
            $id = $pdo->lastInsertId();
            logAudit('CREATE', 'categorias', $id, null, $_POST);
            $message = '✅ Categoría creada exitosamente';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = '❌ Ya existe una categoría con ese nombre';
            } else {
                $message = '❌ Error: ' . $e->getMessage();
            }
        } catch (Exception $e) {
            $message = '❌ ' . $e->getMessage();
        }
    }
}

// ============ PROCESAR FORMULARIO - ACTUALIZAR CATEGORÍA ============
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    if (!hasPermission('INVENTARIO')) {
        $message = '❌ No tienes permiso para actualizar categorías';
    } else {
        try {
            if (empty($_POST['nombre'])) {
                throw new Exception('El nombre de la categoría es obligatorio');
            }
            
            $stmt = $pdo->prepare("UPDATE categorias SET nombre = ?, descripcion = ? WHERE id = ?");
            $stmt->execute([
                $_POST['nombre'],
                $_POST['descripcion'] ?? '',
                $_POST['id']
            ]);
            
            logAudit('UPDATE', 'categorias', $_POST['id']);
            $message = '✅ Categoría actualizada exitosamente';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = '❌ Ya existe una categoría con ese nombre';
            } else {
                $message = '❌ Error: ' . $e->getMessage();
            }
        } catch (Exception $e) {
            $message = '❌ ' . $e->getMessage();
        }
    }
}

// ============ PROCESAR FORMULARIO - ELIMINAR CATEGORÍA ============
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    if (!hasPermission('ADMIN')) {
        $message = '❌ No tienes permiso para eliminar categorías';
    } else {
        try {
            // Verificar si hay productos usando esta categoría
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM productos WHERE id_categoria = ? AND activo = 1");
            $stmt->execute([$_POST['id']]);
            $count = $stmt->fetch();
            
            if ($count['total'] > 0) {
                throw new Exception("No se puede eliminar la categoría porque tiene {$count['total']} productos asociados");
            }
            
            $stmt = $pdo->prepare("UPDATE categorias SET activo = 0 WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            
            logAudit('DELETE', 'categorias', $_POST['id']);
            $message = '✅ Categoría desactivada exitosamente';
        } catch (PDOException $e) {
            $message = '❌ Error: ' . $e->getMessage();
        } catch (Exception $e) {
            $message = '❌ ' . $e->getMessage();
        }
    }
}

// ============ OBTENER CATEGORÍAS ============
$categorias = $pdo->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM productos p WHERE p.id_categoria = c.id AND p.activo = 1) as total_productos
    FROM categorias c 
    WHERE c.activo = 1 
    ORDER BY c.nombre
")->fetchAll();

// ============ ESTADÍSTICAS ============
$total_categorias = count($categorias);
$total_productos = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1")->fetch()['total'];
$categorias_con_productos = count(array_filter($categorias, function($c) {
    return $c['total_productos'] > 0;
}));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías - Sistema de Inventario</title>
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
        .btn-action-cyan { background: #06b6d4; color: white; }
        .btn-action-cyan:hover { background: #0891b2; color: white; }
        
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
        .badge-modern.con-productos { background: #dcfce7; color: #16a34a; }
        .badge-modern.sin-productos { background: #fef3c7; color: #d97706; }
        
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
                        <li><a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                        <li><a href="productos.php"><i class="bi bi-box"></i> Productos</a></li>
                        <li><a href="movimientos.php"><i class="bi bi-arrows-exchange"></i> Movimientos</a></li>
                        <li><a href="categorias.php" class="active"><i class="bi bi-tags"></i> Categorías <span class="badge-sidebar green float-end"><?= $total_categorias ?></span></a></li>
                        <li><a href="proveedores.php"><i class="bi bi-truck"></i> Proveedores</a></li>
                        <li><a href="inventario_fisico.php"><i class="bi bi-clipboard-check"></i> Inventario Físico</a></li>
                        <li><a href="reportes.php"><i class="bi bi-file-earmark-text"></i> Reportes</a></li>
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
            <main class="col-md-10 ms-sm-auto">
                <div class="content-area">
                    
                    <!-- ===== HEADER ===== -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="page-title">🏷️ Categorías</h1>
                            <p class="page-subtitle">Organiza tus productos por categorías</p>
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
                            <button type="button" class="btn-action btn-action-primary" data-bs-toggle="modal" data-bs-target="#categoriaModal">
                                <i class="bi bi-plus-circle"></i> Nueva Categoría
                            </button>
                            <a href="productos.php" class="btn-action btn-action-info">
                                <i class="bi bi-box"></i> Ver Productos
                            </a>
                            <a href="reportes.php?tipo_reporte=categorias" class="btn-action btn-action-secondary">
                                <i class="bi bi-file-earmark-text"></i> Ver Reportes
                            </a>
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
                        <div class="col-xl-4 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon blue"><i class="bi bi-tags"></i></div>
                                <div class="stat-number"><?= number_format($total_categorias) ?></div>
                                <div class="stat-label">Total Categorías</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-check-circle"></i> Activas</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon green"><i class="bi bi-box"></i></div>
                                <div class="stat-number"><?= number_format($total_productos) ?></div>
                                <div class="stat-label">Productos Totales</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-boxes"></i> En inventario</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon orange"><i class="bi bi-box-seam"></i></div>
                                <div class="stat-number"><?= number_format($categorias_con_productos) ?></div>
                                <div class="stat-label">Categorías con Productos</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-percent"></i> <?= $total_categorias > 0 ? round(($categorias_con_productos / $total_categorias) * 100) : 0 ?>% del total</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== TABLA DE CATEGORÍAS ===== -->
                    <div class="section-card">
                        <div class="card-header-custom">
                            <h5><i class="bi bi-list-ul"></i> Lista de Categorías</h5>
                            <div>
                                <span class="badge bg-secondary me-2"><?= $total_categorias ?> registros</span>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#categoriaModal">
                                    <i class="bi bi-plus-circle"></i> Nueva
                                </button>
                            </div>
                        </div>
                        <div class="card-body-custom">
                            <div class="table-responsive">
                                <table id="categoriasTable" class="table table-modern">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>Descripción</th>
                                            <th>Productos</th>
                                            <th>Fecha Creación</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($categorias)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4">
                                                    <i class="bi bi-inbox fs-1 d-block text-muted"></i>
                                                    <span class="text-muted">No hay categorías registradas</span>
                                                    <br>
                                                    <button class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#categoriaModal">
                                                        <i class="bi bi-plus-circle"></i> Crear primera categoría
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($categorias as $categoria): ?>
                                            <tr>
                                                <td><?= $categoria['id'] ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($categoria['nombre']) ?></strong>
                                                </td>
                                                <td><?= htmlspecialchars($categoria['descripcion'] ?: 'Sin descripción') ?></td>
                                                <td>
                                                    <span class="badge-modern <?= $categoria['total_productos'] > 0 ? 'con-productos' : 'sin-productos' ?>">
                                                        <i class="bi bi-box"></i> <?= $categoria['total_productos'] ?>
                                                        <?php if ($categoria['total_productos'] == 0): ?>
                                                            <span class="text-muted">(vacía)</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                                <td><?= date('d/m/Y', strtotime($categoria['fecha_creacion'])) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="editCategoria(<?= $categoria['id'] ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php if ($categoria['total_productos'] == 0): ?>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteCategoria(<?= $categoria['id'] ?>, '<?= htmlspecialchars($categoria['nombre']) ?>')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-secondary" disabled title="No se puede eliminar, tiene productos asociados">
                                                            <i class="bi bi-lock"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <a href="productos.php?categoria=<?= $categoria['id'] ?>" class="btn btn-sm btn-outline-info">
                                                        <i class="bi bi-box"></i>
                                                    </a>
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

    <!-- ===== MODAL PARA CREAR/EDITAR CATEGORÍA ===== -->
    <div class="modal fade" id="categoriaModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">📝 Nueva Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="categoriaForm" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="create">
                        <input type="hidden" name="id" id="categoriaId">
                        
                        <div class="mb-3">
                            <label class="form-label">Nombre de la Categoría *</label>
                            <input type="text" name="nombre" id="nombre" class="form-control" placeholder="Ej: Electrónicos" required autofocus>
                            <div class="form-text text-muted">El nombre debe ser único y descriptivo.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" id="descripcion" class="form-control" rows="3" placeholder="Descripción de la categoría..."></textarea>
                            <div class="form-text text-muted">Opcional: Describe qué tipo de productos pertenecen a esta categoría.</div>
                        </div>

                        <!-- Ejemplos de categorías comunes -->
                        <div class="p-3 bg-light rounded">
                            <small class="text-muted d-block mb-2">💡 <strong>Ejemplos de categorías:</strong></small>
                            <div class="d-flex flex-wrap gap-1">
                                <span class="badge bg-primary">Electrónicos</span>
                                <span class="badge bg-success">Ropa</span>
                                <span class="badge bg-danger">Alimentos</span>
                                <span class="badge bg-warning text-dark">Hogar</span>
                                <span class="badge bg-info">Juguetes</span>
                                <span class="badge bg-secondary">Libros</span>
                                <span class="badge bg-dark">Deportes</span>
                                <span class="badge bg-primary">Automotriz</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Guardar Categoría
                        </button>
                    </div>
                </form>
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
            $('#categoriasTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                },
                order: [[0, 'desc']],
                pageLength: 25
            });
        });

        function editCategoria(id) {
            $.get('get_categoria.php?id=' + id, function(data) {
                $('#categoriaId').val(data.id);
                $('#nombre').val(data.nombre);
                $('#descripcion').val(data.descripcion);
                $('#formAction').val('update');
                $('#modalTitle').text('✏️ Editar Categoría');
                $('#categoriaModal').modal('show');
            }).fail(function() {
                alert('Error al cargar los datos de la categoría');
            });
        }

        function deleteCategoria(id, nombre) {
            if (confirm('¿Estás seguro de que deseas desactivar la categoría "' + nombre + '"?\n\nLos productos asociados a esta categoría no se eliminarán, solo quedarán sin categoría.')) {
                $('<form method="POST">' +
                    '<input type="hidden" name="action" value="delete">' +
                    '<input type="hidden" name="id" value="' + id + '">' +
                    '</form>').appendTo('body').submit();
            }
        }

        $('#categoriaModal').on('hidden.bs.modal', function() {
            $('#categoriaForm')[0].reset();
            $('#formAction').val('create');
            $('#modalTitle').text('📝 Nueva Categoría');
            $('#categoriaId').val('');
            $('.form-control').removeClass('is-invalid is-valid');
        });
    </script>
</body>
</html>