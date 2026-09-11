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

// ============ PROCESAR FORMULARIO - CREAR PROVEEDOR ============
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create') {
    if (!hasPermission('INVENTARIO')) {
        $message = '❌ No tienes permiso para crear proveedores';
    } else {
        try {
            if (empty($_POST['nombre'])) {
                throw new Exception('El nombre del proveedor es obligatorio');
            }
            
            $stmt = $pdo->prepare("INSERT INTO proveedores (nombre, ruc, direccion, telefono, email, contacto) 
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['nombre'],
                $_POST['ruc'] ?? '',
                $_POST['direccion'] ?? '',
                $_POST['telefono'] ?? '',
                $_POST['email'] ?? '',
                $_POST['contacto'] ?? ''
            ]);
            
            $id = $pdo->lastInsertId();
            logAudit('CREATE', 'proveedores', $id, null, $_POST);
            $message = '✅ Proveedor creado exitosamente';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = '❌ Ya existe un proveedor con ese RUC';
            } else {
                $message = '❌ Error: ' . $e->getMessage();
            }
        } catch (Exception $e) {
            $message = '❌ ' . $e->getMessage();
        }
    }
}

// ============ PROCESAR FORMULARIO - ACTUALIZAR PROVEEDOR ============
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    if (!hasPermission('INVENTARIO')) {
        $message = '❌ No tienes permiso para actualizar proveedores';
    } else {
        try {
            if (empty($_POST['nombre'])) {
                throw new Exception('El nombre del proveedor es obligatorio');
            }
            
            $stmt = $pdo->prepare("UPDATE proveedores SET nombre=?, ruc=?, direccion=?, telefono=?, email=?, contacto=? WHERE id=?");
            $stmt->execute([
                $_POST['nombre'],
                $_POST['ruc'] ?? '',
                $_POST['direccion'] ?? '',
                $_POST['telefono'] ?? '',
                $_POST['email'] ?? '',
                $_POST['contacto'] ?? '',
                $_POST['id']
            ]);
            
            logAudit('UPDATE', 'proveedores', $_POST['id']);
            $message = '✅ Proveedor actualizado exitosamente';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = '❌ Ya existe un proveedor con ese RUC';
            } else {
                $message = '❌ Error: ' . $e->getMessage();
            }
        } catch (Exception $e) {
            $message = '❌ ' . $e->getMessage();
        }
    }
}

// ============ PROCESAR FORMULARIO - ELIMINAR PROVEEDOR ============
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    if (!hasPermission('ADMIN')) {
        $message = '❌ No tienes permiso para eliminar proveedores';
    } else {
        try {
            // Verificar si hay productos usando este proveedor
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM productos WHERE id_proveedor = ? AND activo = 1");
            $stmt->execute([$_POST['id']]);
            $count = $stmt->fetch();
            
            if ($count['total'] > 0) {
                throw new Exception("No se puede eliminar el proveedor porque tiene {$count['total']} productos asociados");
            }
            
            $stmt = $pdo->prepare("UPDATE proveedores SET activo = 0 WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            
            logAudit('DELETE', 'proveedores', $_POST['id']);
            $message = '✅ Proveedor desactivado exitosamente';
        } catch (PDOException $e) {
            $message = '❌ Error: ' . $e->getMessage();
        } catch (Exception $e) {
            $message = '❌ ' . $e->getMessage();
        }
    }
}

// ============ OBTENER PROVEEDORES ============
$proveedores = $pdo->query("
    SELECT p.*, 
           (SELECT COUNT(*) FROM productos pr WHERE pr.id_proveedor = p.id AND pr.activo = 1) as total_productos
    FROM proveedores p 
    WHERE p.activo = 1 
    ORDER BY p.nombre
")->fetchAll();

// ============ ESTADÍSTICAS ============
$total_proveedores = count($proveedores);
$total_productos = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1")->fetch()['total'];
$proveedores_con_productos = count(array_filter($proveedores, function($p) {
    return $p['total_productos'] > 0;
}));
$total_compras = $pdo->query("SELECT COALESCE(SUM(total), 0) as total FROM movimientos WHERE tipo_movimiento = 'ENTRADA'")->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proveedores - Sistema de Inventario</title>
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
                        <li><a href="categorias.php"><i class="bi bi-tags"></i> Categorías</a></li>
                        <li><a href="proveedores.php" class="active"><i class="bi bi-truck"></i> Proveedores <span class="badge-sidebar cyan float-end"><?= $total_proveedores ?></span></a></li>
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
                            <h1 class="page-title">🚚 Proveedores</h1>
                            <p class="page-subtitle">Gestión de proveedores y sus productos</p>
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
                                <div class="stat-icon blue"><i class="bi bi-truck"></i></div>
                                <div class="stat-number"><?= number_format($total_proveedores) ?></div>
                                <div class="stat-label">Total Proveedores</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-check-circle"></i> Activos</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon green"><i class="bi bi-box"></i></div>
                                <div class="stat-number"><?= number_format($total_productos) ?></div>
                                <div class="stat-label">Productos Totales</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-boxes"></i> En inventario</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon orange"><i class="bi bi-box-seam"></i></div>
                                <div class="stat-number"><?= number_format($proveedores_con_productos) ?></div>
                                <div class="stat-label">Proveedores con Productos</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-percent"></i> <?= $total_proveedores > 0 ? round(($proveedores_con_productos / $total_proveedores) * 100) : 0 ?>% del total</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon purple"><i class="bi bi-currency-dollar"></i></div>
                                <div class="stat-number">S/<?= number_format($total_compras, 0) ?></div>
                                <div class="stat-label">Total en Compras</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-arrow-up"></i> Histórico</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== TABLA DE PROVEEDORES ===== -->
                    <div class="section-card">
                        <div class="card-header-custom">
                            <h5><i class="bi bi-list-ul"></i> Lista de Proveedores</h5>
                            <div>
                                <span class="badge bg-secondary me-2"><?= $total_proveedores ?> registros</span>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#proveedorModal">
                                    <i class="bi bi-plus-circle"></i> Nuevo
                                </button>
                            </div>
                        </div>
                        <div class="card-body-custom">
                            <div class="table-responsive">
                                <table id="proveedoresTable" class="table table-modern">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>RUC</th>
                                            <th>Teléfono</th>
                                            <th>Email</th>
                                            <th>Contacto</th>
                                            <th>Productos</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($proveedores)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-4">
                                                    <i class="bi bi-inbox fs-1 d-block text-muted"></i>
                                                    <span class="text-muted">No hay proveedores registrados</span>
                                                    <br>
                                                    <button class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#proveedorModal">
                                                        <i class="bi bi-plus-circle"></i> Crear primer proveedor
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($proveedores as $proveedor): ?>
                                            <tr>
                                                <td><?= $proveedor['id'] ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($proveedor['nombre']) ?></strong>
                                                    <?php if (!empty($proveedor['direccion'])): ?>
                                                        <br><small class="text-muted"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($proveedor['direccion']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center"><?= htmlspecialchars($proveedor['ruc'] ?: 'N/A') ?></td>
                                                <td><?= htmlspecialchars($proveedor['telefono'] ?: 'N/A') ?></td>
                                                <td><?= htmlspecialchars($proveedor['email'] ?: 'N/A') ?></td>
                                                <td><?= htmlspecialchars($proveedor['contacto'] ?: 'N/A') ?></td>
                                                <td>
                                                    <span class="badge-modern <?= $proveedor['total_productos'] > 0 ? 'con-productos' : 'sin-productos' ?>">
                                                        <i class="bi bi-box"></i> <?= $proveedor['total_productos'] ?>
                                                        <?php if ($proveedor['total_productos'] == 0): ?>
                                                            <span class="text-muted">(vacío)</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="editProveedor(<?= $proveedor['id'] ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php if ($proveedor['total_productos'] == 0): ?>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteProveedor(<?= $proveedor['id'] ?>, '<?= htmlspecialchars($proveedor['nombre']) ?>')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-secondary" disabled title="No se puede eliminar, tiene productos asociados">
                                                            <i class="bi bi-lock"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <a href="productos.php?proveedor=<?= $proveedor['id'] ?>" class="btn btn-sm btn-outline-info">
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

    <!-- ===== MODAL PARA CREAR/EDITAR PROVEEDOR ===== -->
    <div class="modal fade" id="proveedorModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">📝 Nuevo Proveedor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="proveedorForm" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="create">
                        <input type="hidden" name="id" id="proveedorId">
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Nombre del Proveedor *</label>
                                <input type="text" name="nombre" id="nombre" class="form-control" placeholder="Nombre de la empresa" required autofocus>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">RUC</label>
                                <input type="text" name="ruc" id="ruc" class="form-control" placeholder="20-12345678-9" maxlength="20">
                                <div class="form-text text-muted">Número de identificación fiscal</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Dirección</label>
                            <input type="text" name="direccion" id="direccion" class="form-control" placeholder="Calle, número, ciudad, país">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="text" name="telefono" id="telefono" class="form-control" placeholder="(809) 555-1234">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" id="email" class="form-control" placeholder="proveedor@empresa.com">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Persona de Contacto</label>
                            <input type="text" name="contacto" id="contacto" class="form-control" placeholder="Nombre del contacto principal">
                            <div class="form-text text-muted">Persona encargada de las comunicaciones con el proveedor.</div>
                        </div>

                        <!-- Ejemplos de proveedores comunes -->
                        <div class="p-3 bg-light rounded">
                            <small class="text-muted d-block mb-2">💡 <strong>Ejemplos de proveedores:</strong></small>
                            <div class="d-flex flex-wrap gap-1">
                                <span class="badge bg-primary">Electrónica S.A.</span>
                                <span class="badge bg-success">Distribuidora XYZ</span>
                                <span class="badge bg-danger">Importaciones Global</span>
                                <span class="badge bg-warning text-dark">Proveedor Local</span>
                                <span class="badge bg-info">Mayorista Central</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Guardar Proveedor
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
            $('#proveedoresTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                },
                order: [[0, 'desc']],
                pageLength: 25
            });
        });

        function editProveedor(id) {
            $.get('get_proveedor.php?id=' + id, function(data) {
                $('#proveedorId').val(data.id);
                $('#nombre').val(data.nombre);
                $('#ruc').val(data.ruc);
                $('#direccion').val(data.direccion);
                $('#telefono').val(data.telefono);
                $('#email').val(data.email);
                $('#contacto').val(data.contacto);
                $('#formAction').val('update');
                $('#modalTitle').text('✏️ Editar Proveedor');
                $('#proveedorModal').modal('show');
            }).fail(function() {
                alert('Error al cargar los datos del proveedor');
            });
        }

        function deleteProveedor(id, nombre) {
            if (confirm('¿Estás seguro de que deseas desactivar el proveedor "' + nombre + '"?\n\nLos productos asociados a este proveedor no se eliminarán, solo quedarán sin proveedor.')) {
                $('<form method="POST">' +
                    '<input type="hidden" name="action" value="delete">' +
                    '<input type="hidden" name="id" value="' + id + '">' +
                    '</form>').appendTo('body').submit();
            }
        }

        $('#proveedorModal').on('hidden.bs.modal', function() {
            $('#proveedorForm')[0].reset();
            $('#formAction').val('create');
            $('#modalTitle').text('📝 Nuevo Proveedor');
            $('#proveedorId').val('');
            $('.form-control').removeClass('is-invalid is-valid');
        });

        // Formatear RUC automáticamente
        $('#ruc').on('input', function() {
            let valor = $(this).val().replace(/\D/g, '');
            if (valor.length > 0) {
                let formatted = valor;
                if (valor.length >= 3) {
                    formatted = valor.substring(0, 2) + '-' + valor.substring(2);
                }
                if (valor.length >= 11) {
                    formatted = valor.substring(0, 2) + '-' + valor.substring(2, 10) + '-' + valor.substring(10);
                }
                $(this).val(formatted);
            }
        });
    </script>
</body>
</html>