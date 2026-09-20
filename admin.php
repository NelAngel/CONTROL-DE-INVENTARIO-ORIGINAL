<?php
// admin.php - Panel de administración
session_start();

if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - Asistencia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            padding: 24px;
            color: #1a1a2e;
        }

        .container { max-width: 1400px; margin: 0 auto; }

        .header-bar {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px 24px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.04);
        }
        .header-bar .header-title { display: flex; align-items: center; gap: 12px; }
        .header-bar .header-title h1 { font-size: 22px; font-weight: 700; color: #1a1a2e; }
        .header-bar .header-title .badge {
            background: #eef2ff;
            color: #4f46e5;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .user-info { display: flex; align-items: center; gap: 16px; }
        .user-info .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 600;
            color: white;
        }
        .user-info .user-name { color: #1a1a2e; font-weight: 500; font-size: 14px; }
        .logout-btn {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 8px 18px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .logout-btn:hover { background: #fee2e2; transform: translateY(-1px); }

        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            flex-wrap: wrap;
            background: #ffffff;
            border-radius: 12px;
            padding: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.04);
        }
        .tab {
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            color: #6b7280;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tab:hover { color: #1a1a2e; background: #f3f4f6; }
        .tab.active {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        .tab .tab-icon { font-size: 16px; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 18px 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.04);
            display: flex;
            flex-direction: column;
            transition: all 0.3s;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08); }
        .stat-card .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 10px;
        }
        .stat-card .stat-number { font-size: 28px; font-weight: 700; color: #1a1a2e; line-height: 1.1; }
        .stat-card .stat-label { font-size: 13px; color: #6b7280; font-weight: 500; margin-top: 2px; }
        .stat-card .stat-change {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #f3f4f6;
            font-size: 12px;
            font-weight: 500;
            color: #6b7280;
        }

        .card {
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.04);
            transition: box-shadow 0.3s;
        }
        .card:hover { box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08); }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .card-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a2e;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-header .card-badge {
            background: #f3f4f6;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            color: #6b7280;
            font-weight: 500;
        }
        .card-subtitle { color: #6b7280; font-size: 13px; margin-top: 4px; }

        .fecha-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .fecha-form input[type="date"] {
            padding: 8px 12px;
            border: 1.5px solid #e5e7eb;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            color: #1a1a2e;
            background: #f9fafb;
            transition: all 0.3s;
        }
        .fecha-form input[type="date"]:focus {
            outline: none;
            border-color: #4f46e5;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
        .form-group { margin-bottom: 0; }
        .form-group label {
            display: block;
            color: #374151;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 5px;
        }
        .form-group label .required { color: #dc2626; }
        .form-group input {
            width: 100%;
            padding: 10px 14px;
            background: #f9fafb;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: #1a1a2e;
            transition: all 0.3s;
            outline: none;
        }
        .form-group input::placeholder { color: #9ca3af; }
        .form-group input:hover { background: #ffffff; border-color: #d1d5db; }
        .form-group input:focus {
            background: #ffffff;
            border-color: #4f46e5;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }
        .form-group input.error { border-color: #dc2626; background: #fef2f2; }
        .form-group input.success { border-color: #16a34a; background: #f0fdf4; }
        .form-actions { display: flex; gap: 15px; align-items: center; margin-top: 20px; }

        .btn {
            padding: 10px 22px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn:active { transform: scale(0.97); }

        .btn-success {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
        }
        .btn-warning { background: #fef3c7; border: 1px solid #fcd34d; color: #92400e; }
        .btn-info { background: #dbeafe; border: 1px solid #93c5fd; color: #1e40af; }
        .btn-danger { background: #fef2f2; border: 1px solid #fca5a5; color: #dc2626; }
        .btn-sm { padding: 5px 12px; font-size: 12px; border-radius: 6px; }

        .table-container { overflow-x: auto; border-radius: 12px; border: 1px solid #f3f4f6; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th {
            background: #f9fafb;
            color: #374151;
            font-weight: 600;
            text-align: left;
            padding: 12px 16px;
            border-bottom: 2px solid #f3f4f6;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; color: #1a1a2e; }
        tr:hover td { background: #f9fafb; }
        tr:last-child td { border-bottom: none; }

        .badge { display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-active { background: #dcfce7; color: #15803d; }
        .badge-inactive { background: #fee2e2; color: #dc2626; }
        .badge-present { background: #dcfce7; color: #15803d; }
        .badge-late { background: #fef3c7; color: #92400e; }
        .badge-absent { background: #fee2e2; color: #dc2626; }
        .badge-pending { background: #dbeafe; color: #1e40af; }
        .badge-ausente { background: #fee2e2; color: #dc2626; }
        .badge-device-ok { background: #dcfce7; color: #15803d; font-size: 10px; padding: 3px 8px; }
        .badge-device-pending { background: #fef3c7; color: #92400e; font-size: 10px; padding: 3px 8px; }

        .password-cell {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            background: #f3f4f6;
            padding: 3px 10px;
            border-radius: 6px;
            color: #374151;
        }
        .code-cell {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #4f46e5;
            background: #eef2ff;
            padding: 3px 10px;
            border-radius: 6px;
        }

        .accordion {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            transition: all 0.3s;
        }
        .accordion summary {
            list-style: none;
            cursor: pointer;
            padding: 16px 20px;
            background: #f9fafb;
            font-weight: 600;
            font-size: 15px;
            color: #1a1a2e;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s;
            user-select: none;
        }
        .accordion summary::-webkit-details-marker { display: none; }
        .accordion summary:hover { background: #f3f4f6; }
        .accordion summary.summary-danger { background: #fef2f2; color: #991b1b; }
        .accordion summary.summary-danger:hover { background: #fee2e2; }
        .accordion summary .chevron { font-size: 12px; transition: transform 0.3s; color: #6b7280; }
        .accordion[open] summary .chevron { transform: rotate(180deg); }
        .accordion-content { padding: 0; animation: slideDown 0.3s ease-out; }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .accordion-content .table-container { border: none; border-top: 1px solid #f3f4f6; border-radius: 0; }

        .validation-message { padding: 8px 14px; border-radius: 8px; margin-top: 6px; font-size: 13px; }
        .validation-message.error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }
        .validation-message.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
        .validation-message.warning { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }

        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid #e5e7eb;
            border-top-color: #4f46e5;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-text { display: none; color: #6b7280; font-size: 14px; }

        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }

        @media (max-width: 768px) {
            .header-bar { flex-direction: column; align-items: stretch; text-align: center; padding: 16px; }
            .header-bar .header-title { justify-content: center; }
            .user-info { justify-content: center; }
            .tabs { justify-content: center; }
            .tab { font-size: 12px; padding: 8px 14px; }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }
            .stat-card { padding: 14px 16px; }
            .stat-card .stat-number { font-size: 22px; }
            .stat-card .stat-icon { width: 36px; height: 36px; font-size: 18px; }
            .grid { grid-template-columns: 1fr; }
            .card { padding: 16px; }
            table { font-size: 11px; }
            th, td { padding: 6px 8px; }
            .btn { font-size: 12px; padding: 6px 12px; }
            .form-actions { flex-wrap: wrap; }
            .fecha-form { width: 100%; justify-content: center; }
        }

        @media (max-width: 480px) {
            body { padding: 12px; }
            .header-bar .header-title h1 { font-size: 18px; }
            .card-header h2 { font-size: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .stat-card { padding: 12px 14px; }
            .stat-card .stat-number { font-size: 20px; }
            .stat-card .stat-label { font-size: 11px; }
            .stat-card .stat-icon { width: 32px; height: 32px; font-size: 16px; }
            .stat-card .stat-change { font-size: 10px; }
        }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f3f4f6; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
    </style>
</head>
<body>
    <div class="container">

        <div class="header-bar">
            <div class="header-title">
                <h1>🎯 Panel de Control</h1>
                <span class="badge">v2.0</span>
            </div>
            <div class="user-info">
                <div class="user-avatar">A</div>
                <span class="user-name"><?php echo $_SESSION['usuario'] ?? 'Administrador'; ?></span>
                <a href="logout.php" class="logout-btn">
                    <span>🚪</span> Cerrar Sesión
                </a>
            </div>
        </div>

        <div class="tabs">
            <a href="#empleados" class="tab active">
                <span class="tab-icon">👥</span> Empleados
            </a>
            <a href="#cuentas" class="tab">
                <span class="tab-icon">🔐</span> Cuentas
            </a>
            <a href="#asistencia" class="tab">
                <span class="tab-icon">📊</span> Asistencia
            </a>
            <a href="reporte_mensual.php" class="tab">
                <span class="tab-icon">📅</span> Reporte Mensual
            </a>
        </div>

        <!-- Mensajes de éxito/error desde URL -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">✅ <?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">❌ <?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <!-- TARJETAS INFORMATIVAS -->
        <?php
        $total_activos = $conn->query("SELECT COUNT(*) as total FROM employees WHERE is_active = 1")->fetch_assoc()['total'] ?? 0;
        $total_empleados = $conn->query("SELECT COUNT(*) as total FROM employees")->fetch_assoc()['total'] ?? 0;

        $asistieron_hoy = $conn->query("
            SELECT COUNT(DISTINCT employee_id) as total 
            FROM attendance 
            WHERE DATE(check_in) = CURDATE()
        ")->fetch_assoc()['total'] ?? 0;

        $presentes_hoy = $conn->query("
            SELECT COUNT(DISTINCT employee_id) as total 
            FROM attendance 
            WHERE DATE(check_in) = CURDATE() AND status = 'present'
        ")->fetch_assoc()['total'] ?? 0;

        $tardanzas_hoy = $conn->query("
            SELECT COUNT(DISTINCT employee_id) as total 
            FROM attendance 
            WHERE DATE(check_in) = CURDATE() AND status = 'late'
        ")->fetch_assoc()['total'] ?? 0;

        $horas_extras_sql = "
            SELECT e.work_hours_required, a.total_hours
            FROM attendance a
            JOIN employees e ON a.employee_id = e.id
            WHERE DATE(a.check_in) = CURDATE() AND a.total_hours IS NOT NULL
        ";
        $he_result = $conn->query($horas_extras_sql);
        $total_horas_extras = 0;
        $empleados_con_extras = 0;
        $max_extras = 0;

        while ($he = $he_result->fetch_assoc()) {
            $req_diaria = (strtotime($he['work_hours_required']) - strtotime('00:00:00')) / 3600;
            $trabajadas = (strtotime($he['total_hours']) - strtotime('00:00:00')) / 3600;
            if ($trabajadas < 0) { $trabajadas = 0; }
            $extras = $trabajadas - $req_diaria;
            if ($extras > 0) {
                $total_horas_extras += $extras;
                $empleados_con_extras++;
                if ($extras > $max_extras) $max_extras = $extras;
            }
        }

        $ausentes_hoy = max(0, $total_activos - $asistieron_hoy);

        $pct_presentes  = $total_activos > 0 ? round(($presentes_hoy / $total_activos) * 100) : 0;
        $pct_ausentes   = $total_activos > 0 ? round(($ausentes_hoy / $total_activos) * 100) : 0;
        $pct_tardanzas  = $total_activos > 0 ? round(($tardanzas_hoy / $total_activos) * 100) : 0;
        $pct_asistencia = $total_activos > 0 ? round(($asistieron_hoy / $total_activos) * 100) : 0;
        ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #eef2ff; color: #4f46e5;">👥</div>
                <div class="stat-number"><?php echo $total_empleados; ?></div>
                <div class="stat-label">Total Empleados</div>
                <div class="stat-change">📈 Activos: <?php echo $total_activos; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #dcfce7; color: #16a34a;">✅</div>
                <div class="stat-number"><?php echo $asistieron_hoy; ?></div>
                <div class="stat-label">Asistieron Hoy</div>
                <div class="stat-change">🎯 <?php echo $pct_asistencia; ?>% de <?php echo $total_activos; ?> activos</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #dbeafe; color: #1e40af;">🟢</div>
                <div class="stat-number"><?php echo $presentes_hoy; ?></div>
                <div class="stat-label">Presentes Hoy</div>
                <div class="stat-change">📊 <?php echo $pct_presentes; ?>% del total activo</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">❌</div>
                <div class="stat-number"><?php echo $ausentes_hoy; ?></div>
                <div class="stat-label">Ausentes Hoy</div>
                <div class="stat-change">⚠️ <?php echo $pct_ausentes; ?>% del total activo</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #fef3c7; color: #92400e;">⏰</div>
                <div class="stat-number"><?php echo $tardanzas_hoy; ?></div>
                <div class="stat-label">Tardanzas Hoy</div>
                <div class="stat-change">🕐 <?php echo $pct_tardanzas; ?>% del total activo</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #fce7f3; color: #be185d;">💪</div>
                <div class="stat-number"><?php echo number_format($total_horas_extras, 1); ?>h</div>
                <div class="stat-label">Horas Extras Hoy</div>
                <div class="stat-change">
                    <?php if ($empleados_con_extras > 0): ?>
                        🏆 <?php echo $empleados_con_extras; ?> empleado<?php echo $empleados_con_extras > 1 ? 's' : ''; ?> · Máx: <?php echo number_format($max_extras, 1); ?>h
                    <?php else: ?>
                        📌 Sin horas extras hoy
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ASISTENCIA POR FECHA -->
        <?php
        $fecha_sel = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_sel)) {
            $fecha_sel = date('Y-m-d');
        }

        $asistieron_dia = $conn->query("
            SELECT COUNT(DISTINCT employee_id) as total 
            FROM attendance 
            WHERE DATE(check_in) = '$fecha_sel'
        ")->fetch_assoc()['total'] ?? 0;

        $ausentes_dia = max(0, $total_activos - $asistieron_dia);
        $pct_asistencia_dia = $total_activos > 0 ? round(($asistieron_dia / $total_activos) * 100) : 0;
        $texto_dia = ($fecha_sel == date('Y-m-d')) ? 'Hoy' : date('d/m/Y', strtotime($fecha_sel));
        ?>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2>📅 Asistencia por Fecha</h2>
                    <div class="card-subtitle">
                        Detalle de quién asistió y quién no · <strong><?php echo $texto_dia; ?></strong>
                    </div>
                </div>
                <form method="GET" action="" class="fecha-form">
                    <input type="date" name="fecha" value="<?php echo $fecha_sel; ?>" max="<?php echo date('Y-m-d'); ?>">
                    <button type="submit" class="btn btn-primary btn-sm">🔍 Ver</button>
                    <a href="admin.php" class="btn btn-info btn-sm">📅 Hoy</a>
                </form>
            </div>

            <div class="stats-grid" style="margin-bottom:0;">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #dcfce7; color: #16a34a;">✅</div>
                    <div class="stat-number"><?php echo $asistieron_dia; ?></div>
                    <div class="stat-label">Asistieron</div>
                    <div class="stat-change">🎯 <?php echo $pct_asistencia_dia; ?>% del total activo</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">❌</div>
                    <div class="stat-number"><?php echo $ausentes_dia; ?></div>
                    <div class="stat-label">Ausentes</div>
                    <div class="stat-change">⚠️ No ficharon este día</div>
                </div>
            </div>

            <details class="accordion" style="margin-top:20px;">
                <summary>
                    <span>✅ Ver quiénes asistieron (<?php echo $asistieron_dia; ?>)</span>
                    <span class="chevron">▼</span>
                </summary>
                <div class="accordion-content">
                    <div class="table-container">
                        <?php
                        $sql_asistieron = "
                            SELECT e.employee_number, e.full_name, e.phone,
                                   a.check_in, a.check_out, a.total_hours, a.status
                            FROM attendance a
                            JOIN employees e ON a.employee_id = e.id
                            WHERE DATE(a.check_in) = '$fecha_sel'
                            ORDER BY a.check_in ASC
                        ";
                        $res_asistieron = $conn->query($sql_asistieron);

                        if ($res_asistieron && $res_asistieron->num_rows > 0): ?>
                            <table>
                                <tr>
                                    <th>N°</th><th>Empleado</th><th>Teléfono</th>
                                    <th>Entrada</th><th>Salida</th><th>Horas</th><th>Estado</th>
                                </tr>
                                <?php while ($row = $res_asistieron->fetch_assoc()): 
                                    $estado = $row['status'];
                                    if ($estado == 'present') $texto_estado = '✅ Presente';
                                    elseif ($estado == 'late') $texto_estado = '⏰ Tarde';
                                    elseif ($estado == 'pending') $texto_estado = '⏳ Pendiente';
                                    else $texto_estado = ucfirst($estado);
                                ?>
                                    <tr>
                                        <td><strong><?php echo $row['employee_number']; ?></strong></td>
                                        <td><?php echo $row['full_name']; ?></td>
                                        <td><?php echo $row['phone']; ?></td>
                                        <td><?php echo $row['check_in'] ? date('H:i:s', strtotime($row['check_in'])) : '--:--'; ?></td>
                                        <td><?php echo $row['check_out'] ? date('H:i:s', strtotime($row['check_out'])) : '--:--'; ?></td>
                                        <td><?php echo $row['total_hours'] ? $row['total_hours'] . ' h' : '--'; ?></td>
                                        <td><span class="badge badge-<?php echo $estado; ?>"><?php echo $texto_estado; ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            </table>
                        <?php else: ?>
                            <p style="padding:20px; color:#6b7280;">Nadie asistió este día.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </details>

            <details class="accordion" style="margin-top:10px;">
                <summary class="summary-danger">
                    <span>❌ Ver quiénes NO asistieron (<?php echo $ausentes_dia; ?>)</span>
                    <span class="chevron">▼</span>
                </summary>
                <div class="accordion-content">
                    <div class="table-container">
                        <?php
                        $sql_ausentes = "
                            SELECT e.employee_number, e.full_name, e.phone
                            FROM employees e
                            LEFT JOIN attendance a 
                                ON e.id = a.employee_id 
                                AND DATE(a.check_in) = '$fecha_sel'
                            WHERE e.is_active = 1 AND a.id IS NULL
                            ORDER BY e.full_name ASC
                        ";
                        $res_ausentes = $conn->query($sql_ausentes);

                        if ($res_ausentes && $res_ausentes->num_rows > 0): ?>
                            <table>
                                <tr><th>N°</th><th>Empleado</th><th>Teléfono</th><th>Estado</th></tr>
                                <?php while ($row = $res_ausentes->fetch_assoc()): ?>
                                    <tr style="background:#fff5f5;">
                                        <td><strong><?php echo $row['employee_number']; ?></strong></td>
                                        <td><?php echo $row['full_name']; ?></td>
                                        <td><?php echo $row['phone']; ?></td>
                                        <td><span class="badge badge-ausente">❌ Ausente</span></td>
                                    </tr>
                                <?php endwhile; ?>
                            </table>
                        <?php else: ?>
                            <p style="padding:20px; color:#6b7280;">¡Todos asistieron este día! 🎉</p>
                        <?php endif; ?>
                    </div>
                </div>
            </details>
        </div>

        <!-- REGISTRAR EMPLEADO -->
        <div class="card" id="empleados">
            <div class="card-header">
                <div>
                    <h2>➕ Registrar Nuevo Empleado</h2>
                    <div class="card-subtitle">Completa todos los campos para dar de alta un nuevo trabajador</div>
                </div>
                <span class="card-badge">Obligatorio *</span>
            </div>

            <div id="mensajeGlobal" style="display:none;" class="validation-message"></div>

            <form id="formEmpleado" method="POST" action="add_employee.php">
                <div class="grid">
                    <div class="form-group">
                        <label>Número de Empleado <span class="required">*</span></label>
                        <input type="text" id="employee_number" name="employee_number" placeholder="EMP-001" required>
                        <div id="employee_number_msg" class="validation-message" style="display:none;"></div>
                    </div>
                    <div class="form-group">
                        <label>DNI <span class="required">*</span></label>
                        <input type="text" id="dni" name="dni" placeholder="12345678" required>
                        <div id="dni_msg" class="validation-message" style="display:none;"></div>
                    </div>
                    <div class="form-group">
                        <label>Nombres Completos <span class="required">*</span></label>
                        <input type="text" id="full_name" name="full_name" placeholder="Juan Pérez Gómez" required>
                        <div id="full_name_msg" class="validation-message" style="display:none;"></div>
                    </div>
                    <div class="form-group">
                        <label>Teléfono <span class="required">*</span></label>
                        <input type="text" id="phone" name="phone" placeholder="987654321" required>
                        <div id="phone_msg" class="validation-message" style="display:none;"></div>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="email" name="email" placeholder="juan@empresa.com">
                        <div id="email_msg" class="validation-message" style="display:none;"></div>
                    </div>
                    <div class="form-group">
                        <label>Usuario <span class="required">*</span></label>
                        <input type="text" id="username" name="username" placeholder="juan123" required>
                        <div id="username_msg" class="validation-message" style="display:none;"></div>
                    </div>
                    <div class="form-group">
                        <label>Contraseña <span class="required">*</span></label>
                        <input type="text" id="password" name="password" placeholder="Mínimo 6 caracteres" required>
                        <div id="password_msg" class="validation-message" style="display:none;"></div>
                    </div>
                    <div class="form-group">
                        <label>Horas requeridas <span class="required">*</span></label>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <input type="number" id="req_hours" name="req_hours" min="0" max="24" value="8" required
                                   style="width:90px; padding:10px 12px; border:1.5px solid #e5e7eb; border-radius:10px; font-size:14px; text-align:center;">
                            <span style="color:#6b7280; font-weight:600;">horas</span>
                            <input type="number" id="req_minutes" name="req_minutes" min="0" max="59" value="0" required
                                   style="width:90px; padding:10px 12px; border:1.5px solid #e5e7eb; border-radius:10px; font-size:14px; text-align:center;">
                            <span style="color:#6b7280; font-weight:600;">minutos</span>
                        </div>
                        <div style="font-size:12px; color:#9ca3af; margin-top:6px;">
                            💡 Ej: <strong>8 horas 0 minutos</strong> = jornada completa. <strong>4 horas 0 minutos</strong> = medio tiempo.
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" id="btnRegistrar" class="btn btn-success">
                        <span>➕</span> Registrar Empleado
                    </button>
                    <div id="spinner" class="spinner"></div>
                    <span id="loadingText" class="loading-text">Verificando...</span>
                </div>
            </form>
        </div>

        <!-- CUENTAS DE TRABAJADORES -->
        <div class="card" id="cuentas">
            <div class="card-header">
                <div>
                    <h2>🔐 Cuentas de Trabajadores</h2>
                    <div class="card-subtitle">Cada trabajador tiene usuario, contraseña y dispositivo autorizado</div>
                </div>
                <span class="card-badge"><?php
                    $count = $conn->query("SELECT COUNT(*) as total FROM employees")->fetch_assoc()['total'] ?? 0;
                    echo $count . ' cuentas';
                ?></span>
            </div>

            <div class="table-container">
                <?php
                $columns = $conn->query("SHOW COLUMNS FROM employees LIKE 'device_token'");
                if ($columns->num_rows == 0) {
                    echo "<p style='color:#dc2626; padding:20px;'>⚠️ Ejecuta primero el script SQL para agregar las columnas de dispositivo.</p>";
                } else {
                    $sql = "SELECT id, employee_number, dni, full_name, phone, email, username, password_hash, 
                                   is_active, last_login, login_attempts, work_hours_required,
                                   device_user_agent, device_ip, device_registered_at
                            FROM employees ORDER BY employee_number ASC";
                    $result = $conn->query($sql);

                    if ($result->num_rows > 0) {
                        echo "<table>";
                        echo "<tr>
                                <th>N°</th>
                                <th>Nombre</th>
                                <th>Usuario</th>
                                <th>Contraseña</th>
                                <th>Horas</th>
                                <th>Dispositivo</th>
                                <th>Último Login</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                              </tr>";
                        while ($row = $result->fetch_assoc()) {
                            $status_class = $row['is_active'] ? 'badge-active' : 'badge-inactive';
                            $status_text = $row['is_active'] ? '✅ Activo' : '❌ Inactivo';
                            $last_login = $row['last_login'] ? date('d/m/Y H:i', strtotime($row['last_login'])) : 'Nunca';

                            $password_display = '••••••';
                            if (!empty($row['password_hash']) && password_verify('123456', $row['password_hash'])) {
                                $password_display = '123456 (temp)';
                            }

                            $partes = explode(':', $row['work_hours_required'] ?? '08:00:00');
                            $hh = intval($partes[0] ?? 8);
                            $mm = intval($partes[1] ?? 0);
                            $texto_horas = $hh . 'h';
                            if ($mm > 0) $texto_horas .= ' ' . $mm . 'min';

                            // Estado del dispositivo
                            $device_display = '';
                            if (!empty($row['device_registered_at'])) {
                                $device_modelo = '📱';
                                if (preg_match('/(Android|iPhone|iPad|Windows|Macintosh)/i', $row['device_user_agent'] ?? '', $m)) {
                                    $device_modelo = '📱 ' . $m[1];
                                }
                                $device_display = "<span class='badge badge-device-ok'>✅ " . $device_modelo . "</span>";
                                $device_display .= "<br><small style='color:#9ca3af; font-size:10px;'>" . date('d/m/Y', strtotime($row['device_registered_at'])) . "</small>";
                            } else {
                                $device_display = "<span class='badge badge-device-pending'>⏳ Sin registrar</span>";
                            }

                            echo "<tr>";
                            echo "<td><strong>{$row['employee_number']}</strong></td>";
                            echo "<td>{$row['full_name']}<br><small style='color:#9ca3af;'>DNI: {$row['dni']}</small></td>";
                            echo "<td><code class='code-cell'>{$row['username']}</code></td>";
                            echo "<td><span class='password-cell'>{$password_display}</span></td>";
                            echo "<td><strong>{$texto_horas}</strong></td>";
                            echo "<td>{$device_display}</td>";
                            echo "<td>{$last_login}</td>";
                            echo "<td><span class='badge $status_class'>$status_text</span></td>";
                            echo "<td style='white-space:nowrap;'>
                                    <a href='reset_password.php?id={$row['id']}' class='btn btn-warning btn-sm' onclick='return confirm(\"¿Resetear contraseña a 123456?\")' title='Resetear contraseña'>🔑</a>
                                    <a href='reset_device.php?id={$row['id']}' class='btn btn-danger btn-sm' onclick='return confirm(\"¿Permitir nuevo dispositivo para este empleado? Deberá iniciar sesión desde el nuevo celular.\")' title='Resetear dispositivo'>📱</a>
                                    <a href='toggle_employee.php?id={$row['id']}' class='btn btn-info btn-sm' title='Activar/Desactivar'>🔁</a>
                                    <a href='generate_qr.php?id={$row['id']}' class='btn btn-primary btn-sm' title='Ver QR'>🔲</a>
                                  </td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                    } else {
                        echo "<p style='padding:20px; color:#6b7280;'>No hay empleados registrados.</p>";
                    }
                }
                ?>
            </div>
        </div>

        <!-- ASISTENCIA DEL DÍA -->
        <div class="card" id="asistencia">
            <div class="card-header">
                <div>
                    <h2>📊 Asistencia del Día</h2>
                    <div class="card-subtitle">Registros de entrada y salida de hoy · <?php echo date('l, d/m/Y'); ?></div>
                </div>
                <span class="card-badge"><?php
                    $count_att = $conn->query("SELECT COUNT(DISTINCT employee_id) as total FROM attendance WHERE DATE(check_in) = CURDATE()")->fetch_assoc()['total'] ?? 0;
                    echo $count_att . ' empleados';
                ?></span>
            </div>

            <div class="table-container">
                <?php
                $today = date('Y-m-d');
                $sql = "SELECT e.employee_number, e.full_name, e.work_hours_required, 
                               a.check_in, a.check_out, a.total_hours, a.status 
                        FROM attendance a 
                        JOIN employees e ON a.employee_id = e.id 
                        WHERE DATE(a.check_in) = '$today'
                        ORDER BY a.check_in DESC";
                $result = $conn->query($sql);

                if ($result->num_rows > 0) {
                    echo "<table>";
                    echo "<tr><th>N°</th><th>Empleado</th><th>Entrada</th><th>Salida</th><th>Horas</th><th>Extras</th><th>Estado</th></tr>";
                    while ($row = $result->fetch_assoc()) {
                        $status_class = "badge-{$row['status']}";
                        
                        $extras_texto = '<span style="color:#9ca3af;">--</span>';
                        if ($row['total_hours'] && $row['work_hours_required']) {
                            $req = (strtotime($row['work_hours_required']) - strtotime('00:00:00')) / 3600;
                            $trabajadas_row = (strtotime($row['total_hours']) - strtotime('00:00:00')) / 3600;
                            if ($trabajadas_row < 0) { $trabajadas_row = 0; }
                            $extras = $trabajadas_row - $req;
                            if ($extras > 0) {
                                $extras_texto = '<strong style="color:#be185d;">+' . number_format($extras, 2) . 'h</strong>';
                            } elseif ($extras < 0) {
                                $extras_texto = '<span style="color:#dc2626;">' . number_format($extras, 2) . 'h</span>';
                            } else {
                                $extras_texto = '<span style="color:#16a34a;">0.00h</span>';
                            }
                        }
                        
                        echo "<tr>";
                        echo "<td>{$row['employee_number']}</td>";
                        echo "<td>{$row['full_name']}</td>";
                        echo "<td>" . date('H:i:s', strtotime($row['check_in'])) . "</td>";
                        echo "<td>" . ($row['check_out'] ? date('H:i:s', strtotime($row['check_out'])) : '--:--') . "</td>";
                        echo "<td>" . ($row['total_hours'] ? $row['total_hours'] . ' h' : '--:--') . "</td>";
                        echo "<td>{$extras_texto}</td>";
                        echo "<td><span class='badge $status_class'>" . ucfirst($row['status']) . "</span></td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<p style='padding:20px; color:#6b7280;'>No hay registros de asistencia para hoy.</p>";
                }
                ?>
            </div>
        </div>

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('formEmpleado');
        const btnRegistrar = document.getElementById('btnRegistrar');
        const spinner = document.getElementById('spinner');
        const loadingText = document.getElementById('loadingText');
        const mensajeGlobal = document.getElementById('mensajeGlobal');

        function mostrarMensajeGlobal(mensaje, tipo) {
            mensajeGlobal.style.display = 'block';
            mensajeGlobal.textContent = mensaje;
            mensajeGlobal.className = 'validation-message ' + tipo;
            setTimeout(() => { mensajeGlobal.style.display = 'none'; }, 5000);
        }

        function validarCampo(campo, valor, msg, input) {
            return fetch('validar_empleado.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'campo=' + encodeURIComponent(campo) + '&valor=' + encodeURIComponent(valor)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.existe) {
                    msg.style.display = 'block';
                    msg.textContent = data.mensaje;
                    msg.className = 'validation-message error';
                    input.classList.add('error');
                    input.classList.remove('success');
                    return false;
                }
                msg.style.display = 'none';
                input.classList.remove('error');
                input.classList.add('success');
                return true;
            })
            .catch(function() {
                return true;
            });
        }

        document.getElementById('employee_number').addEventListener('blur', function() {
            const valor = this.value.trim();
            const msg = document.getElementById('employee_number_msg');
            if (!valor) return;
            validarCampo('employee_number', valor, msg, this);
        });

        document.getElementById('dni').addEventListener('blur', function() {
            const valor = this.value.trim();
            const msg = document.getElementById('dni_msg');
            if (valor.length < 8) {
                msg.style.display = 'block';
                msg.textContent = '⚠️ El DNI debe tener al menos 8 dígitos';
                msg.className = 'validation-message warning';
                this.classList.add('error');
            } else {
                validarCampo('dni', valor, msg, this);
            }
        });

        document.getElementById('phone').addEventListener('blur', function() {
            const valor = this.value.trim();
            const msg = document.getElementById('phone_msg');
            if (valor.length < 9) {
                msg.style.display = 'block';
                msg.textContent = '⚠️ El teléfono debe tener al menos 9 dígitos';
                msg.className = 'validation-message warning';
                this.classList.add('error');
            } else {
                validarCampo('phone', valor, msg, this);
            }
        });

        document.getElementById('email').addEventListener('blur', function() {
            const valor = this.value.trim();
            const msg = document.getElementById('email_msg');
            if (!valor) {
                msg.style.display = 'none';
                this.classList.remove('error');
                return;
            }
            validarCampo('email', valor, msg, this);
        });

        document.getElementById('username').addEventListener('blur', function() {
            const valor = this.value.trim();
            const msg = document.getElementById('username_msg');
            if (valor.length < 3) {
                msg.style.display = 'block';
                msg.textContent = '⚠️ El usuario debe tener al menos 3 caracteres';
                msg.className = 'validation-message warning';
                this.classList.add('error');
            } else if (valor.includes(' ')) {
                msg.style.display = 'block';
                msg.textContent = '⚠️ El usuario no puede contener espacios';
                msg.className = 'validation-message warning';
                this.classList.add('error');
            } else {
                validarCampo('username', valor, msg, this);
            }
        });

        document.getElementById('password').addEventListener('blur', function() {
            const valor = this.value.trim();
            const msg = document.getElementById('password_msg');
            if (valor.length < 6) {
                msg.style.display = 'block';
                msg.textContent = '⚠️ La contraseña debe tener al menos 6 caracteres';
                msg.className = 'validation-message warning';
                this.classList.add('error');
            } else {
                msg.style.display = 'none';
                this.classList.remove('error');
                this.classList.add('success');
            }
        });

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            spinner.style.display = 'block';
            loadingText.style.display = 'inline';
            btnRegistrar.disabled = true;
            btnRegistrar.textContent = 'Registrando...';

            fetch('add_employee.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                spinner.style.display = 'none';
                loadingText.style.display = 'none';
                btnRegistrar.disabled = false;
                btnRegistrar.textContent = 'Registrar Empleado';

                if (data.success) {
                    mostrarMensajeGlobal(data.message, 'success');
                    setTimeout(() => { location.reload(); }, 2000);
                } else {
                    mostrarMensajeGlobal(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                spinner.style.display = 'none';
                loadingText.style.display = 'none';
                btnRegistrar.disabled = false;
                btnRegistrar.textContent = 'Registrar Empleado';
                mostrarMensajeGlobal('❌ Error al registrar el empleado', 'error');
            });
        });
    });
    </script>

</body>
</html>