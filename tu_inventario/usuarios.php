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
    die('❌ Acceso denegado. Solo los administradores pueden gestionar usuarios.');
}

$pdo = getConnection();
$message = '';

// ============ PROCESAR FORMULARIO - CREAR USUARIO ============
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create') {
    try {
        // Validar campos obligatorios
        if (empty($_POST['usuario']) || empty($_POST['password']) || empty($_POST['nombre_completo'])) {
            throw new Exception('Usuario, contraseña y nombre completo son obligatorios');
        }
        
        // Verificar que el usuario no exista
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM usuarios WHERE usuario = ?");
        $stmt->execute([$_POST['usuario']]);
        $exists = $stmt->fetch();
        
        if ($exists['total'] > 0) {
            throw new Exception('El nombre de usuario ya está en uso');
        }
        
        // Encriptar contraseña
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        // Insertar usuario
        $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, password, nombre_completo, email, rol, activo) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['usuario'],
            $password_hash,
            $_POST['nombre_completo'],
            $_POST['email'] ?? '',
            $_POST['rol'] ?? 'CONSULTA',
            isset($_POST['activo']) ? 1 : 0
        ]);
        
        $id = $pdo->lastInsertId();
        logAudit('CREATE', 'usuarios', $id, null, $_POST);
        $message = '✅ Usuario creado exitosamente';
        
    } catch (PDOException $e) {
        $message = '❌ Error: ' . $e->getMessage();
    } catch (Exception $e) {
        $message = '❌ ' . $e->getMessage();
    }
}

// ============ PROCESAR FORMULARIO - ACTUALIZAR USUARIO ============
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    try {
        if (empty($_POST['nombre_completo'])) {
            throw new Exception('El nombre completo es obligatorio');
        }
        
        // Verificar que el usuario no esté intentando usar un nombre de usuario existente
        if (!empty($_POST['usuario'])) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM usuarios WHERE usuario = ? AND id != ?");
            $stmt->execute([$_POST['usuario'], $_POST['id']]);
            $exists = $stmt->fetch();
            
            if ($exists['total'] > 0) {
                throw new Exception('El nombre de usuario ya está en uso');
            }
        }
        
        // Construir consulta SQL dinámica
        $sql = "UPDATE usuarios SET nombre_completo = ?, email = ?, rol = ?, activo = ?";
        $params = [
            $_POST['nombre_completo'],
            $_POST['email'] ?? '',
            $_POST['rol'] ?? 'CONSULTA',
            isset($_POST['activo']) ? 1 : 0
        ];
        
        // Si se proporciona una nueva contraseña, actualizarla
        if (!empty($_POST['password'])) {
            $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql .= ", password = ?";
            $params[] = $password_hash;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $_POST['id'];
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        logAudit('UPDATE', 'usuarios', $_POST['id']);
        $message = '✅ Usuario actualizado exitosamente';
        
    } catch (PDOException $e) {
        $message = '❌ Error: ' . $e->getMessage();
    } catch (Exception $e) {
        $message = '❌ ' . $e->getMessage();
    }
}

// ============ PROCESAR FORMULARIO - ELIMINAR USUARIO ============
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    try {
        // No permitir eliminar el usuario actual
        if ($_POST['id'] == $_SESSION['user_id']) {
            throw new Exception('No puedes eliminar tu propio usuario');
        }
        
        // No permitir eliminar el usuario admin principal
        $stmt = $pdo->prepare("SELECT usuario FROM usuarios WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $user = $stmt->fetch();
        
        if ($user && $user['usuario'] == 'admin') {
            throw new Exception('No puedes eliminar el usuario administrador principal');
        }
        
        $stmt = $pdo->prepare("UPDATE usuarios SET activo = 0 WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        
        logAudit('DELETE', 'usuarios', $_POST['id']);
        $message = '✅ Usuario desactivado exitosamente';
        
    } catch (PDOException $e) {
        $message = '❌ Error: ' . $e->getMessage();
    } catch (Exception $e) {
        $message = '❌ ' . $e->getMessage();
    }
}

// ============ PROCESAR FORMULARIO - REACTIVAR USUARIO ============
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'reactivate') {
    try {
        $stmt = $pdo->prepare("UPDATE usuarios SET activo = 1 WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        
        logAudit('UPDATE', 'usuarios', $_POST['id']);
        $message = '✅ Usuario reactivado exitosamente';
        
    } catch (PDOException $e) {
        $message = '❌ Error: ' . $e->getMessage();
    }
}

// ============ PROCESAR FORMULARIO - CAMBIAR CONTRASEÑA ============
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'change_password') {
    try {
        if (empty($_POST['new_password']) || strlen($_POST['new_password']) < 6) {
            throw new Exception('La contraseña debe tener al menos 6 caracteres');
        }
        
        // Verificar contraseña actual
        $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!password_verify($_POST['current_password'], $user['password'])) {
            throw new Exception('Contraseña actual incorrecta');
        }
        
        $new_password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        $stmt->execute([$new_password_hash, $_SESSION['user_id']]);
        
        logAudit('UPDATE', 'usuarios', $_SESSION['user_id']);
        $message = '✅ Contraseña actualizada exitosamente';
        
    } catch (PDOException $e) {
        $message = '❌ Error: ' . $e->getMessage();
    } catch (Exception $e) {
        $message = '❌ ' . $e->getMessage();
    }
}

// ============ OBTENER USUARIOS ============
$usuarios = $pdo->query("
    SELECT u.*,
           (SELECT COUNT(*) FROM auditoria a WHERE a.id_usuario = u.id) as total_auditoria
    FROM usuarios u
    ORDER BY u.activo DESC, u.nombre_completo
")->fetchAll();

// ============ ESTADÍSTICAS ============
$total_usuarios = count($usuarios);
$usuarios_activos = count(array_filter($usuarios, function($u) { return $u['activo'] == 1; }));
$usuarios_inactivos = $total_usuarios - $usuarios_activos;
$admins = count(array_filter($usuarios, function($u) { return $u['rol'] == 'ADMIN' && $u['activo'] == 1; }));

// ============ VARIABLES PARA EL SIDEBAR ============
$total_productos_sidebar = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1")->fetch()['total'];
$movimientos_hoy = $pdo->query("SELECT COUNT(*) as total FROM movimientos WHERE DATE(fecha_movimiento) = CURDATE()")->fetch()['total'];
$total_categorias = $pdo->query("SELECT COUNT(*) as total FROM categorias WHERE activo = 1")->fetch()['total'];
$total_proveedores = $pdo->query("SELECT COUNT(*) as total FROM proveedores WHERE activo = 1")->fetch()['total'];
$total_conteos = $pdo->query("SELECT COUNT(*) as total FROM inventario_fisico WHERE fecha_conteo >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - Sistema de Inventario</title>
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
        .badge-modern.admin { background: #fee2e2; color: #dc2626; }
        .badge-modern.inventario { background: #fef3c7; color: #d97706; }
        .badge-modern.consulta { background: #e0f2fe; color: #0284c7; }
        .badge-modern.activo { background: #dcfce7; color: #16a34a; }
        .badge-modern.inactivo { background: #f3f4f6; color: #6b7280; }
        
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
                        <li><a href="productos.php"><i class="bi bi-box"></i> Productos <span class="badge-sidebar blue"><?= $total_productos_sidebar ?></span></a></li>
                        <li><a href="movimientos.php"><i class="bi bi-arrows-exchange"></i> Movimientos <span class="badge-sidebar purple"><?= $movimientos_hoy ?></span></a></li>
                        <li><a href="categorias.php"><i class="bi bi-tags"></i> Categorías <span class="badge-sidebar green"><?= $total_categorias ?></span></a></li>
                        <li><a href="proveedores.php"><i class="bi bi-truck"></i> Proveedores <span class="badge-sidebar cyan"><?= $total_proveedores ?></span></a></li>
                        <li><a href="inventario_fisico.php"><i class="bi bi-clipboard-check"></i> Inventario Físico</a></li>
                        <li><a href="reportes.php"><i class="bi bi-file-earmark-text"></i> Reportes</a></li>
                        <?php if (hasPermission('ADMIN')): ?>
                        <li><a href="usuarios.php" class="active"><i class="bi bi-people"></i> Usuarios <span class="badge-sidebar blue"><?= $total_usuarios ?></span></a></li>
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
                            <h1 class="page-title">👥 Usuarios</h1>
                            <p class="page-subtitle">Gestión de usuarios y permisos del sistema</p>
                        </div>
                        <div>
                            <span class="badge bg-primary p-2">
                                <i class="bi bi-calendar3"></i> <?= date('d/m/Y') ?>
                            </span>
                            <button type="button" class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#cambiarPasswordModal">
                                <i class="bi bi-key"></i> Cambiar mi Contraseña
                            </button>
                            <button type="button" class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#usuarioModal">
                                <i class="bi bi-person-plus"></i> Nuevo Usuario
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
                                <div class="stat-icon blue"><i class="bi bi-people"></i></div>
                                <div class="stat-number"><?= $total_usuarios ?></div>
                                <div class="stat-label">Total Usuarios</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-person-check"></i> Registrados</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon green"><i class="bi bi-person-check"></i></div>
                                <div class="stat-number text-success"><?= $usuarios_activos ?></div>
                                <div class="stat-label">Usuarios Activos</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-check-circle"></i> Activos</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon red"><i class="bi bi-person-x"></i></div>
                                <div class="stat-number text-danger"><?= $usuarios_inactivos ?></div>
                                <div class="stat-label">Usuarios Inactivos</div>
                                <div class="mt-2">
                                    <span class="stat-change down"><i class="bi bi-x-circle"></i> Inactivos</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon orange"><i class="bi bi-shield-lock"></i></div>
                                <div class="stat-number text-warning"><?= $admins ?></div>
                                <div class="stat-label">Administradores</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-shield-check"></i> Con acceso total</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== TABLA DE USUARIOS ===== -->
                    <div class="section-card">
                        <div class="card-header-custom">
                            <h5><i class="bi bi-list-ul"></i> Lista de Usuarios</h5>
                            <span class="badge bg-secondary"><?= $total_usuarios ?> registros</span>
                        </div>
                        <div class="card-body-custom">
                            <div class="table-responsive">
                                <table id="usuariosTable" class="table table-modern">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Usuario</th>
                                            <th>Nombre Completo</th>
                                            <th>Email</th>
                                            <th>Rol</th>
                                            <th>Estado</th>
                                            <th>Fecha Registro</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($usuarios as $usuario): ?>
                                        <tr>
                                            <td><?= $usuario['id'] ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($usuario['usuario']) ?></strong>
                                                <?php if ($usuario['id'] == $_SESSION['user_id']): ?>
                                                    <span class="badge bg-info ms-1">TÚ</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($usuario['nombre_completo']) ?></td>
                                            <td><?= htmlspecialchars($usuario['email'] ?: 'N/A') ?></td>
                                            <td>
                                                <span class="badge-modern <?= strtolower($usuario['rol']) ?>">
                                                    <?= $usuario['rol'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge-modern <?= $usuario['activo'] == 1 ? 'activo' : 'inactivo' ?>">
                                                    <?= $usuario['activo'] == 1 ? 'Activo' : 'Inactivo' ?>
                                                </span>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($usuario['fecha_registro'])) ?></td>
                                            <td>
                                                <?php if ($usuario['id'] != $_SESSION['user_id']): ?>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="editUsuario(<?= $usuario['id'] ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php if ($usuario['activo'] == 1): ?>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteUsuario(<?= $usuario['id'] ?>, '<?= htmlspecialchars($usuario['usuario']) ?>')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-success" onclick="reactivateUsuario(<?= $usuario['id'] ?>, '<?= htmlspecialchars($usuario['usuario']) ?>')">
                                                            <i class="bi bi-arrow-counterclockwise"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Usuario actual</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ===== INFORMACIÓN DE ROLES ===== -->
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i>
                        <strong>Roles disponibles:</strong>
                        <ul class="mb-0 mt-2">
                            <li><span class="badge-modern admin">ADMIN</span> - Acceso total al sistema</li>
                            <li><span class="badge-modern inventario">INVENTARIO</span> - Gestionar productos y movimientos</li>
                            <li><span class="badge-modern consulta">CONSULTA</span> - Solo ver información</li>
                        </ul>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- ===== MODAL PARA CREAR/EDITAR USUARIO ===== -->
    <div class="modal fade" id="usuarioModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">👤 Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="usuarioForm" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="create">
                        <input type="hidden" name="id" id="usuarioId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Usuario *</label>
                                <input type="text" name="usuario" id="usuario" class="form-control" placeholder="ej: jperez" required>
                                <div class="form-text text-muted">Nombre de usuario para iniciar sesión</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contraseña *</label>
                                <input type="password" name="password" id="password" class="form-control" placeholder="Mínimo 6 caracteres" required minlength="6">
                                <div class="form-text text-muted">Mínimo 6 caracteres</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nombre Completo *</label>
                            <input type="text" name="nombre_completo" id="nombre_completo" class="form-control" placeholder="Juan Pérez" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="email" class="form-control" placeholder="juan@empresa.com">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Rol *</label>
                                <select name="rol" id="rol" class="form-select" required>
                                    <option value="CONSULTA">Consulta (Solo lectura)</option>
                                    <option value="INVENTARIO">Inventario (Gestionar productos y movimientos)</option>
                                    <option value="ADMIN">Administrador (Acceso total)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3 d-flex align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="activo" id="activo" value="1" checked>
                                    <label class="form-check-label" for="activo">
                                        Usuario activo
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Guardar Usuario
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ===== MODAL PARA CAMBIAR CONTRASEÑA ===== -->
    <div class="modal fade" id="cambiarPasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">🔑 Cambiar mi Contraseña</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" class="form-modern">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-3">
                            <label class="form-label">Contraseña Actual *</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nueva Contraseña *</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6">
                            <div class="form-text text-muted">Mínimo 6 caracteres</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirmar Nueva Contraseña *</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-key"></i> Cambiar Contraseña
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
            $('#usuariosTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                },
                order: [[0, 'desc']],
                pageLength: 25
            });
        });

        function editUsuario(id) {
            $.get('get_usuario.php?id=' + id, function(data) {
                $('#usuarioId').val(data.id);
                $('#usuario').val(data.usuario);
                $('#nombre_completo').val(data.nombre_completo);
                $('#email').val(data.email);
                $('#rol').val(data.rol);
                $('#activo').prop('checked', data.activo == 1);
                $('#formAction').val('update');
                $('#modalTitle').text('✏️ Editar Usuario');
                $('#password').prop('required', false);
                $('#password').attr('placeholder', 'Dejar en blanco para mantener la actual');
                $('#usuarioModal').modal('show');
            }).fail(function() {
                alert('Error al cargar los datos del usuario');
            });
        }

        function deleteUsuario(id, usuario) {
            if (confirm('¿Estás seguro de que deseas desactivar al usuario "' + usuario + '"?')) {
                $('<form method="POST">' +
                    '<input type="hidden" name="action" value="delete">' +
                    '<input type="hidden" name="id" value="' + id + '">' +
                    '</form>').appendTo('body').submit();
            }
        }

        function reactivateUsuario(id, usuario) {
            if (confirm('¿Deseas reactivar al usuario "' + usuario + '"?')) {
                $('<form method="POST">' +
                    '<input type="hidden" name="action" value="reactivate">' +
                    '<input type="hidden" name="id" value="' + id + '">' +
                    '</form>').appendTo('body').submit();
            }
        }

        $('#usuarioModal').on('hidden.bs.modal', function() {
            $('#usuarioForm')[0].reset();
            $('#formAction').val('create');
            $('#modalTitle').text('👤 Nuevo Usuario');
            $('#usuarioId').val('');
            $('#password').prop('required', true);
            $('#password').attr('placeholder', 'Mínimo 6 caracteres');
            $('#activo').prop('checked', true);
            $('.form-control').removeClass('is-invalid is-valid');
        });

        // Validación de contraseña en cambio de contraseña
        $('input[name="confirm_password"]').on('input', function() {
            const newPass = $('input[name="new_password"]').val();
            const confirmPass = $(this).val();
            
            if (confirmPass.length > 0 && newPass !== confirmPass) {
                $(this).addClass('is-invalid');
                $(this).siblings('.invalid-feedback').remove();
                $(this).after('<div class="invalid-feedback">Las contraseñas no coinciden</div>');
            } else {
                $(this).removeClass('is-invalid');
                $(this).siblings('.invalid-feedback').remove();
            }
        });

        $('#usuario').on('input', function() {
            const value = $(this).val();
            if (value.length < 3) {
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
        });
    </script>
</body>
</html>