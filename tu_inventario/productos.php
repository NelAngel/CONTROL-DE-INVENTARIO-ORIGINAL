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

// ============ PROCESAR FORMULARIO - CREAR PRODUCTO ============
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create') {
    if (!hasPermission('INVENTARIO')) {
        $message = '❌ No tienes permiso para crear productos';
    } else {
        try {
            // Validar campos obligatorios
            if (empty($_POST['codigo']) || empty($_POST['nombre']) || empty($_POST['precio_compra']) || empty($_POST['precio_venta'])) {
                throw new Exception('Código, Nombre, Precio Compra y Precio Venta son obligatorios');
            }
            
            $stmt = $pdo->prepare("INSERT INTO productos (codigo, nombre, descripcion, id_categoria, id_proveedor, precio_compra, precio_venta, stock_minimo, stock_maximo, ubicacion) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['codigo'],
                $_POST['nombre'],
                $_POST['descripcion'] ?? '',
                !empty($_POST['id_categoria']) ? $_POST['id_categoria'] : null,
                !empty($_POST['id_proveedor']) ? $_POST['id_proveedor'] : null,
                $_POST['precio_compra'],
                $_POST['precio_venta'],
                $_POST['stock_minimo'] ?? 5,
                $_POST['stock_maximo'] ?? 100,
                $_POST['ubicacion'] ?? ''
            ]);
            $id = $pdo->lastInsertId();
            logAudit('CREATE', 'productos', $id, null, $_POST);
            $message = '✅ Producto creado exitosamente';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = '❌ El código del producto ya está en uso';
            } else {
                $message = '❌ Error: ' . $e->getMessage();
            }
        } catch (Exception $e) {
            $message = '❌ ' . $e->getMessage();
        }
    }
}

// ============ PROCESAR FORMULARIO - ACTUALIZAR PRODUCTO ============
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    if (!hasPermission('INVENTARIO')) {
        $message = '❌ No tienes permiso para actualizar productos';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE productos SET codigo=?, nombre=?, descripcion=?, id_categoria=?, id_proveedor=?, precio_compra=?, precio_venta=?, stock_minimo=?, stock_maximo=?, ubicacion=? WHERE id=?");
            $stmt->execute([
                $_POST['codigo'],
                $_POST['nombre'],
                $_POST['descripcion'] ?? '',
                !empty($_POST['id_categoria']) ? $_POST['id_categoria'] : null,
                !empty($_POST['id_proveedor']) ? $_POST['id_proveedor'] : null,
                $_POST['precio_compra'],
                $_POST['precio_venta'],
                $_POST['stock_minimo'] ?? 5,
                $_POST['stock_maximo'] ?? 100,
                $_POST['ubicacion'] ?? '',
                $_POST['id']
            ]);
            logAudit('UPDATE', 'productos', $_POST['id']);
            $message = '✅ Producto actualizado exitosamente';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = '❌ El código del producto ya está en uso';
            } else {
                $message = '❌ Error: ' . $e->getMessage();
            }
        } catch (Exception $e) {
            $message = '❌ ' . $e->getMessage();
        }
    }
}

// ============ PROCESAR FORMULARIO - ELIMINAR PRODUCTO ============
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    if (!hasPermission('ADMIN')) {
        $message = '❌ No tienes permiso para eliminar productos';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE productos SET activo = 0 WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            logAudit('DELETE', 'productos', $_POST['id']);
            $message = '✅ Producto desactivado exitosamente';
        } catch (PDOException $e) {
            $message = '❌ Error: ' . $e->getMessage();
        }
    }
}

// ============ OBTENER DATOS (CON CÁLCULO PEPS) ============
$productos = $pdo->query("
    SELECT p.*, 
           c.nombre as categoria_nombre, 
           pr.nombre as proveedor_nombre,
           COALESCE(
               (SELECT SUM(l.cantidad_disponible) 
                FROM lotes l 
                WHERE l.id_producto = p.id AND l.activo = 1), 0
           ) as stock_actual
    FROM productos p
    LEFT JOIN categorias c ON p.id_categoria = c.id
    LEFT JOIN proveedores pr ON p.id_proveedor = pr.id
    WHERE p.activo = 1
    ORDER BY p.nombre
")->fetchAll();

$categorias = $pdo->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre")->fetchAll();
$proveedores = $pdo->query("SELECT * FROM proveedores WHERE activo = 1 ORDER BY nombre")->fetchAll();

// Estadísticas
$total_productos = count($productos);
$total_stock = array_sum(array_column($productos, 'stock_actual'));
$productos_bajo = count(array_filter($productos, function($p) {
    return $p['stock_actual'] <= $p['stock_minimo'];
}));

// Variables para el sidebar
$total_categorias = count($categorias);
$total_proveedores = count($proveedores);
$movimientos_hoy = getMovimientosHoy();
$total_usuarios = getTotalUsuarios();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Sistema de Inventario</title>
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
        .badge-modern.stock-bajo { background: #fee2e2; color: #dc2626; }
        .badge-modern.stock-normal { background: #dcfce7; color: #16a34a; }
        .badge-modern.stock-critico { background: #fef3c7; color: #d97706; }
        
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
                        <li><a href="productos.php" class="active"><i class="bi bi-box"></i> Productos <span class="badge-sidebar blue"><?= $total_productos ?></span></a></li>
                        <li><a href="movimientos.php"><i class="bi bi-arrows-exchange"></i> Movimientos <span class="badge-sidebar purple"><?= $movimientos_hoy ?></span></a></li>
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
            <main class="col-md-10 ms-sm-auto">
                <div class="content-area">
                    
                    <!-- ===== HEADER ===== -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="page-title">📦 Productos</h1>
                            <p class="page-subtitle">Gestión completa de tu inventario de productos</p>
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
                            <button type="button" class="btn-action btn-action-primary" data-bs-toggle="modal" data-bs-target="#productoModal">
                                <i class="bi bi-plus-circle"></i> Nuevo Producto
                            </button>
                            <a href="categorias.php" class="btn-action btn-action-info">
                                <i class="bi bi-tags"></i> Gestionar Categorías
                            </a>
                            <a href="proveedores.php" class="btn-action btn-action-cyan">
                                <i class="bi bi-truck"></i> Gestionar Proveedores
                            </a>
                            <a href="movimientos.php?action=entrada" class="btn-action btn-action-success">
                                <i class="bi bi-arrow-down-circle"></i> Entrada
                            </a>
                            <a href="movimientos.php?action=salida" class="btn-action btn-action-danger">
                                <i class="bi bi-arrow-up-circle"></i> Salida
                            </a>
                            <a href="movimientos.php?action=consumo" class="btn-action btn-action-warning">
                                <i class="bi bi-tools"></i> Consumo
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
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon blue"><i class="bi bi-box"></i></div>
                                <div class="stat-number"><?= number_format($total_productos) ?></div>
                                <div class="stat-label">Total Productos</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-check-circle"></i> Activos</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon green"><i class="bi bi-boxes"></i></div>
                                <div class="stat-number"><?= number_format($total_stock) ?></div>
                                <div class="stat-label">Unidades en Stock</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-arrow-up"></i> Total</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon <?= $productos_bajo > 0 ? 'red' : 'green' ?>">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                                <div class="stat-number text-<?= $productos_bajo > 0 ? 'danger' : 'success' ?>">
                                    <?= number_format($productos_bajo) ?>
                                </div>
                                <div class="stat-label">Con Stock Bajo</div>
                                <div class="mt-2">
                                    <span class="stat-change <?= $productos_bajo > 0 ? 'down' : 'up' ?>">
                                        <i class="bi bi-<?= $productos_bajo > 0 ? 'exclamation-circle' : 'check-circle' ?>"></i>
                                        <?= $productos_bajo > 0 ? 'Requiere atención' : 'Todo en orden' ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon purple"><i class="bi bi-tags"></i></div>
                                <div class="stat-number"><?= number_format($total_categorias) ?></div>
                                <div class="stat-label">Categorías</div>
                                <div class="mt-2">
                                    <span class="stat-change up"><i class="bi bi-truck"></i> <?= $total_proveedores ?> proveedores</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== TABLA DE PRODUCTOS ===== -->
                    <div class="section-card">
                        <div class="card-header-custom">
                            <h5><i class="bi bi-list-ul"></i> Lista de Productos</h5>
                            <div>
                                <span class="badge bg-secondary me-2"><?= $total_productos ?> registros</span>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#productoModal">
                                    <i class="bi bi-plus-circle"></i> Nuevo
                                </button>
                            </div>
                        </div>
                        <div class="card-body-custom">
                            <div class="table-responsive">
                                <table id="productosTable" class="table table-modern">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Nombre</th>
                                            <th>Categoría</th>
                                            <th>Proveedor</th>
                                            <th>Precio Compra</th>
                                            <th>Precio Venta</th>
                                            <th>Stock Actual</th>
                                            <th>Stock Mínimo</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($productos)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-4">
                                                    <i class="bi bi-inbox fs-1 d-block text-muted"></i>
                                                    <span class="text-muted">No hay productos registrados</span>
                                                    <br>
                                                    <button class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#productoModal">
                                                        <i class="bi bi-plus-circle"></i> Crear primer producto
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($productos as $producto): 
                                                $stock_class = $producto['stock_actual'] <= $producto['stock_minimo'] ? 'stock-bajo' : 'stock-normal';
                                                if ($producto['stock_actual'] == 0) $stock_class = 'stock-critico';
                                            ?>
                                            <tr>
                                                <td><span class="badge bg-secondary"><?= htmlspecialchars($producto['codigo']) ?></span></td>
                                                <td><strong><?= htmlspecialchars($producto['nombre']) ?></strong></td>
                                                <td><?= htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría') ?></td>
                                                <td><?= htmlspecialchars($producto['proveedor_nombre'] ?? 'Sin proveedor') ?></td>
                                                <td><?= moneda($producto['precio_compra']) ?></td>
                                                <td><?= moneda($producto['precio_venta']) ?></td>
                                                <td>
                                                    <span class="badge-modern <?= $stock_class ?>">
                                                        <?= $producto['stock_actual'] ?>
                                                        <?php if ($producto['stock_actual'] <= $producto['stock_minimo']): ?>
                                                            <i class="bi bi-exclamation-triangle"></i>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                                <td><?= $producto['stock_minimo'] ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="editProduct(<?= $producto['id'] ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteProduct(<?= $producto['id'] ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                    <a href="movimientos.php?action=entrada&producto_id=<?= $producto['id'] ?>" class="btn btn-sm btn-outline-success">
                                                        <i class="bi bi-arrow-down-circle"></i>
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

    <!-- ===== MODAL PARA CREAR/EDITAR PRODUCTO ===== -->
    <div class="modal fade" id="productoModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">📝 Nuevo Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="productoForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="create">
                        <input type="hidden" name="id" id="productoId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Código *</label>
                                <input type="text" name="codigo" id="codigo" class="form-control" placeholder="PROD-001" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre *</label>
                                <input type="text" name="nombre" id="nombre" class="form-control" placeholder="Nombre del producto" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" id="descripcion" class="form-control" rows="2" placeholder="Descripción detallada"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Categoría</label>
                                <select name="id_categoria" id="id_categoria" class="form-select">
                                    <option value="">Sin categoría</option>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Proveedor</label>
                                <select name="id_proveedor" id="id_proveedor" class="form-select">
                                    <option value="">Sin proveedor</option>
                                    <?php foreach ($proveedores as $prov): ?>
                                        <option value="<?= $prov['id'] ?>"><?= htmlspecialchars($prov['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Precio Compra *</label>
                                <input type="number" step="0.01" name="precio_compra" id="precio_compra" class="form-control" placeholder="0.00" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Precio Venta *</label>
                                <input type="number" step="0.01" name="precio_venta" id="precio_venta" class="form-control" placeholder="0.00" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Ubicación</label>
                                <input type="text" name="ubicacion" id="ubicacion" class="form-control" placeholder="Bodega A - Estante 3">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stock Mínimo</label>
                                <input type="number" name="stock_minimo" id="stock_minimo" class="form-control" value="5">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stock Máximo</label>
                                <input type="number" name="stock_maximo" id="stock_maximo" class="form-control" value="100">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Guardar Producto
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
            $('#productosTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                },
                order: [[1, 'asc']],
                pageLength: 25
            });
        });

        function editProduct(id) {
            $.get('get_producto.php?id=' + id, function(data) {
                $('#productoId').val(data.id);
                $('#codigo').val(data.codigo);
                $('#nombre').val(data.nombre);
                $('#descripcion').val(data.descripcion);
                $('#id_categoria').val(data.id_categoria);
                $('#id_proveedor').val(data.id_proveedor);
                $('#precio_compra').val(data.precio_compra);
                $('#precio_venta').val(data.precio_venta);
                $('#stock_minimo').val(data.stock_minimo);
                $('#stock_maximo').val(data.stock_maximo);
                $('#ubicacion').val(data.ubicacion);
                $('#formAction').val('update');
                $('#modalTitle').text('✏️ Editar Producto');
                $('#productoModal').modal('show');
            }).fail(function() {
                alert('Error al cargar los datos del producto');
            });
        }

        function deleteProduct(id) {
            if (confirm('¿Estás seguro de que deseas desactivar este producto?')) {
                $('<form method="POST">' +
                    '<input type="hidden" name="action" value="delete">' +
                    '<input type="hidden" name="id" value="' + id + '">' +
                    '</form>').appendTo('body').submit();
            }
        }

        $('#productoModal').on('hidden.bs.modal', function() {
            $('#productoForm')[0].reset();
            $('#formAction').val('create');
            $('#modalTitle').text('📝 Nuevo Producto');
            $('#productoId').val('');
        });
    </script>
</body>
</html>