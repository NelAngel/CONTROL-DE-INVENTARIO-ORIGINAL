<nav class="col-md-2 d-md-block sidebar p-0" style="min-height: 100vh; background: #2c3e50; color: white;">
    <div class="position-sticky">
        <div class="p-3 border-bottom border-secondary">
            <h4><i class="bi bi-box-seam"></i> Inventario</h4>
            <small>Bienvenido, <?= htmlspecialchars($_SESSION['user_name']) ?></small>
        </div>
        <ul class="nav flex-column p-2">
            <li><a href="index.php" class="text-white text-decoration-none d-block p-2 rounded <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-primary' : '' ?>" style="margin: 3px 0;">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a></li>
            <li><a href="productos.php" class="text-white text-decoration-none d-block p-2 rounded <?= basename($_SERVER['PHP_SELF']) == 'productos.php' ? 'bg-primary' : '' ?>" style="margin: 3px 0;">
                <i class="bi bi-box"></i> Productos
            </a></li>
            <li><a href="movimientos.php" class="text-white text-decoration-none d-block p-2 rounded <?= basename($_SERVER['PHP_SELF']) == 'movimientos.php' ? 'bg-primary' : '' ?>" style="margin: 3px 0;">
                <i class="bi bi-arrows-exchange"></i> Movimientos
            </a></li>
            <li><a href="categorias.php" class="text-white text-decoration-none d-block p-2 rounded <?= basename($_SERVER['PHP_SELF']) == 'categorias.php' ? 'bg-primary' : '' ?>" style="margin: 3px 0;">
                <i class="bi bi-tags"></i> Categorías
            </a></li>
            <li><a href="proveedores.php" class="text-white text-decoration-none d-block p-2 rounded <?= basename($_SERVER['PHP_SELF']) == 'proveedores.php' ? 'bg-primary' : '' ?>" style="margin: 3px 0;">
                <i class="bi bi-truck"></i> Proveedores
            </a></li>
            <li><a href="inventario_fisico.php" class="text-white text-decoration-none d-block p-2 rounded <?= basename($_SERVER['PHP_SELF']) == 'inventario_fisico.php' ? 'bg-primary' : '' ?>" style="margin: 3px 0;">
                <i class="bi bi-clipboard-check"></i> Inventario Físico
            </a></li>
            <li><a href="reportes.php" class="text-white text-decoration-none d-block p-2 rounded <?= basename($_SERVER['PHP_SELF']) == 'reportes.php' ? 'bg-primary' : '' ?>" style="margin: 3px 0;">
                <i class="bi bi-file-earmark-text"></i> Reportes
            </a></li>
            <?php if (hasPermission('ADMIN')): ?>
            <li><a href="usuarios.php" class="text-white text-decoration-none d-block p-2 rounded <?= basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'bg-primary' : '' ?>" style="margin: 3px 0;">
                <i class="bi bi-people"></i> Usuarios
            </a></li>
            <li><a href="auditoria.php" class="text-white text-decoration-none d-block p-2 rounded <?= basename($_SERVER['PHP_SELF']) == 'auditoria.php' ? 'bg-primary' : '' ?>" style="margin: 3px 0;">
                <i class="bi bi-clock-history"></i> Auditoría
            </a></li>
            <?php endif; ?>
            <li><hr class="border-secondary"></li>
            <li><a href="logout.php" class="text-white text-decoration-none d-block p-2 rounded" style="margin: 3px 0;">
                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
            </a></li>
        </ul>
    </div>
</nav>