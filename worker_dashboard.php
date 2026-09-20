<?php
// worker_dashboard.php - Panel del trabajador (versión robusta)
// Forzar no-caché para móviles
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

require_once 'config.php';
session_start();

if (!isset($_SESSION['worker_logged_in']) || $_SESSION['worker_logged_in'] !== true) {
    header('Location: login_worker.php');
    exit;
}

$worker_id = $_SESSION['worker_id'] ?? 0;
$worker_name = $_SESSION['worker_name'] ?? 'Trabajador';
$worker_number = $_SESSION['worker_number'] ?? 'N/A';
$worker_qr = $_SESSION['worker_qr'] ?? '';

// ============================================
// DATOS DEL EMPLEADO (con valores seguros)
// ============================================
$emp_sql = "SELECT work_hours_required FROM employees WHERE id = $worker_id";
$emp_result = $conn->query($emp_sql);

if ($emp_result && $emp_result->num_rows > 0) {
    $employee = $emp_result->fetch_assoc();
} else {
    $employee = ['work_hours_required' => '08:00:00'];
}

$horas_requeridas_raw = $employee['work_hours_required'] ?? '08:00:00';
if (empty($horas_requeridas_raw) || $horas_requeridas_raw == '00:00:00') {
    $horas_requeridas_raw = '08:00:00';
}

$partes = explode(':', $horas_requeridas_raw);
$req_horas = intval($partes[0] ?? 8);
$req_minutos = intval($partes[1] ?? 0);
$horas_requeridas_decimal = $req_horas + ($req_minutos / 60);

if ($horas_requeridas_decimal <= 0) {
    $horas_requeridas_decimal = 8;
}

$texto_horas_requeridas = $req_horas . 'h';
if ($req_minutos > 0) $texto_horas_requeridas .= ' ' . $req_minutos . 'min';

// Primer nombre seguro
$primer_nombre = 'compañero';
if (!empty(trim($worker_name))) {
    $partes_nombre = explode(' ', trim($worker_name));
    if (!empty($partes_nombre[0])) {
        $primer_nombre = $partes_nombre[0];
    }
}

// ============================================
// HOY (solo registros cerrados y con horas)
// ============================================
$today = date('Y-m-d');
$hoy_sql = "SELECT check_in, check_out, total_hours, status 
            FROM attendance 
            WHERE employee_id = $worker_id 
            AND DATE(check_in) = '$today' 
            ORDER BY check_in DESC LIMIT 1";
$hoy_result = $conn->query($hoy_sql);
$hoy = ($hoy_result && $hoy_result->num_rows > 0) ? $hoy_result->fetch_assoc() : null;

$hoy_abierto = ($hoy && (empty($hoy['check_out']) || empty($hoy['total_hours'])));

$horas_hoy = 0;
if ($hoy && !empty($hoy['total_hours']) && !empty($hoy['check_out'])) {
    $horas_hoy = (strtotime($hoy['total_hours']) - strtotime('00:00:00')) / 3600;
    if ($horas_hoy < 0) {
        $horas_hoy = 0;
    }
}
$cumplio_hoy = ($horas_hoy >= $horas_requeridas_decimal && $horas_hoy > 0);
$pct_hoy = $horas_requeridas_decimal > 0 ? min(100, round(($horas_hoy / $horas_requeridas_decimal) * 100)) : 0;

// ============================================
// SEMANA ACTUAL (lunes a domingo)
// ============================================
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));

$semana_sql = "SELECT 
                COUNT(DISTINCT DATE(check_in)) as dias,
                COALESCE(SUM(TIME_TO_SEC(total_hours)) / 3600, 0) as horas
              FROM attendance 
              WHERE employee_id = $worker_id 
              AND DATE(check_in) BETWEEN '$week_start' AND '$week_end'
              AND check_out IS NOT NULL
              AND total_hours IS NOT NULL
              AND total_hours <> '00:00:00'";
$semana_result = $conn->query($semana_sql);
$semana = ($semana_result && $semana_result->num_rows > 0) ? $semana_result->fetch_assoc() : ['dias' => 0, 'horas' => 0];

$dias_trabajados_semana = intval($semana['dias'] ?? 0);
$total_horas_semana = floatval($semana['horas'] ?? 0);

$horas_esperadas_semana = $horas_requeridas_decimal * 5;
$cumplio_semana = ($total_horas_semana >= $horas_esperadas_semana && $total_horas_semana > 0);
$pct_semana = $horas_esperadas_semana > 0 ? min(100, round(($total_horas_semana / $horas_esperadas_semana) * 100)) : 0;

// ============================================
// MES ACTUAL
// ============================================
$mes_actual = date('Y-m');
$mes_sql = "SELECT 
                COUNT(DISTINCT DATE(check_in)) as dias,
                COALESCE(SUM(TIME_TO_SEC(total_hours)) / 3600, 0) as horas
            FROM attendance 
            WHERE employee_id = $worker_id 
            AND DATE_FORMAT(check_in, '%Y-%m') = '$mes_actual'
            AND check_out IS NOT NULL
            AND total_hours IS NOT NULL
            AND total_hours <> '00:00:00'";
$mes_result = $conn->query($mes_sql);
$mes = ($mes_result && $mes_result->num_rows > 0) ? $mes_result->fetch_assoc() : ['dias' => 0, 'horas' => 0];

$dias_trabajados_mes = intval($mes['dias'] ?? 0);
$total_horas_mes = floatval($mes['horas'] ?? 0);

$horas_requeridas_mes = $horas_requeridas_decimal * $dias_trabajados_mes;
$cumplio_mes = ($total_horas_mes >= $horas_requeridas_mes && $total_horas_mes > 0);
$pct_mes = $horas_requeridas_mes > 0 ? min(100, round(($total_horas_mes / $horas_requeridas_mes) * 100)) : 0;
$promedio_diario = $dias_trabajados_mes > 0 ? $total_horas_mes / $dias_trabajados_mes : 0;
$horas_extras_mes = $total_horas_mes - $horas_requeridas_mes;

// ============================================
// HISTORIAL ÚLTIMOS 30 DÍAS
// ============================================
$hist_sql = "SELECT 
                DATE(check_in) as fecha,
                MIN(check_in) as primera_entrada,
                MAX(check_out) as ultima_salida,
                SUM(TIME_TO_SEC(total_hours)) / 3600 as horas_dia
             FROM attendance 
             WHERE employee_id = $worker_id 
             AND check_in >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             AND check_out IS NOT NULL
             AND total_hours IS NOT NULL
             AND total_hours <> '00:00:00'
             GROUP BY DATE(check_in)
             ORDER BY fecha DESC";
$hist_result = $conn->query($hist_sql);

// ============================================
// NOMBRE DEL MES
// ============================================
$meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
          '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
$mes_num = date('m');
$nombre_mes = $meses[$mes_num] . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Panel - Control de Horas</title>
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

        .container { max-width: 1000px; margin: 0 auto; }

        /* HEADER */
        .header {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.04);
        }
        .header .user-info h1 { font-size: 22px; font-weight: 700; color: #1a1a2e; }
        .header .user-info .subtitle { color: #6b7280; font-size: 14px; margin-top: 2px; }
        .logout-btn {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 8px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .logout-btn:hover { background: #fee2e2; }

        /* CELEBRACIÓN */
        .celebration {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 2px solid #fbbf24;
            border-radius: 16px;
            padding: 28px 24px;
            margin-bottom: 24px;
            text-align: center;
            animation: celebrationPop 0.6s ease-out;
            position: relative;
            overflow: hidden;
        }
        .celebration::before {
            content: '🎉';
            position: absolute;
            top: -10px;
            left: 20px;
            font-size: 40px;
            animation: floatEmoji 3s ease-in-out infinite;
        }
        .celebration::after {
            content: '🎊';
            position: absolute;
            top: -10px;
            right: 20px;
            font-size: 40px;
            animation: floatEmoji 3s ease-in-out infinite reverse;
        }
        @keyframes floatEmoji {
            0%, 100% { transform: translateY(0) rotate(-10deg); }
            50% { transform: translateY(-10px) rotate(10deg); }
        }
        @keyframes celebrationPop {
            0% { opacity: 0; transform: scale(0.9); }
            50% { transform: scale(1.02); }
            100% { opacity: 1; transform: scale(1); }
        }
        .celebration .emoji-big { font-size: 72px; margin-bottom: 10px; display: block; }
        .celebration h2 { color: #92400e; font-size: 26px; font-weight: 800; margin-bottom: 8px; }
        .celebration p { color: #78350f; font-size: 15px; line-height: 1.6; }
        .celebration .highlight {
            display: inline-block;
            background: #fbbf24;
            color: #78350f;
            padding: 6px 18px;
            border-radius: 20px;
            font-weight: 700;
            margin: 10px 4px 0;
        }

        .card {
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.04);
        }
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
        .card-header .badge {
            background: #f3f4f6;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            color: #6b7280;
            font-weight: 500;
        }

        /* QR */
        .qr-container { text-align: center; padding: 20px; }
        .qr-container .instruccion { color: #6b7280; font-size: 13px; margin-bottom: 10px; }
        .qr-container img {
            max-width: 220px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 12px;
            background: white;
        }
        .btn-scanner {
            display: inline-block;
            padding: 14px 30px;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            margin-top: 15px;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.25);
            transition: all 0.3s;
        }
        .btn-scanner:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(34, 197, 94, 0.35); }

        /* STATS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
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
        .stat-card .stat-number { font-size: 26px; font-weight: 700; color: #1a1a2e; line-height: 1.1; }
        .stat-card .stat-label { font-size: 13px; color: #6b7280; font-weight: 500; margin-top: 2px; }
        .stat-card .stat-change {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #f3f4f6;
            font-size: 12px;
            font-weight: 500;
            color: #6b7280;
        }

        /* PROGRESO */
        .progress-container { margin-top: 15px; }
        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #374151;
            margin-bottom: 6px;
            font-weight: 500;
        }
        .progress-bar {
            background: #f3f4f6;
            border-radius: 12px;
            height: 24px;
            overflow: hidden;
            position: relative;
        }
        .progress-fill {
            height: 100%;
            border-radius: 12px;
            transition: width 1s ease-out;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: 700;
        }
        .progress-fill.green { background: linear-gradient(90deg, #22c55e 0%, #16a34a 100%); }
        .progress-fill.yellow { background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%); }
        .progress-fill.red { background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%); }

        /* TABLA */
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

        .badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-cumplio { background: #dcfce7; color: #15803d; font-size: 12px; padding: 4px 12px; }
        .badge-no-cumplio { background: #fef3c7; color: #92400e; font-size: 12px; padding: 4px 12px; }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: #6b7280;
        }
        .empty-state .empty-icon { font-size: 48px; margin-bottom: 10px; display: block; opacity: 0.5; }
        .empty-state p { font-size: 14px; }

        @media (max-width: 768px) {
            body { padding: 16px; }
            .header { flex-direction: column; text-align: center; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .stat-card { padding: 14px 16px; }
            .stat-card .stat-number { font-size: 20px; }
            .stat-card .stat-icon { width: 36px; height: 36px; font-size: 18px; }
            .card { padding: 16px; }
            table { font-size: 12px; }
            th, td { padding: 8px 10px; }
            .celebration h2 { font-size: 20px; }
            .celebration .emoji-big { font-size: 56px; }
        }
    </style>
</head>
<body>

    <div class="container">

        <!-- HEADER -->
        <div class="header">
            <div class="user-info">
                <h1>👋 Hola, <?php echo htmlspecialchars($worker_name); ?></h1>
                <div class="subtitle">
                    N° Empleado: <?php echo htmlspecialchars($worker_number); ?> · 
                    Jornada diaria: <strong><?php echo $texto_horas_requeridas; ?></strong>
                </div>
            </div>
            <a href="logout_worker.php" class="logout-btn">🚪 Cerrar Sesión</a>
        </div>

        <!-- ============================================ -->
        <!-- MENSAJE DE FELICITACIONES (HOY) -->
        <!-- ============================================ -->
        <?php if ($cumplio_hoy): ?>
            <div class="celebration">
                <span class="emoji-big">🎉</span>
                <h2>¡Felicidades, <?php echo htmlspecialchars($primer_nombre); ?>!</h2>
                <p>
                    Has completado tu jornada de hoy.<br>
                    Trabajaste <strong><?php echo number_format($horas_hoy, 2); ?> horas</strong> 
                    de las <strong><?php echo $texto_horas_requeridas; ?></strong> requeridas.
                </p>
                <span class="highlight">💪 ¡Excelente trabajo!</span>
            </div>
        <?php endif; ?>

        <!-- ============================================ -->
        <!-- QR Y FICHAJE -->
        <!-- ============================================ -->
        <div class="card">
            <div class="card-header">
                <h2>📱 Fichaje Rápido</h2>
                <span class="badge">Tu QR personal</span>
            </div>
            <div class="qr-container">
                <p class="instruccion">Escanea este QR para registrar tu entrada o salida</p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=<?php echo urlencode($worker_qr); ?>" alt="Tu QR">
                <br>
                <a href="scanner.php" class="btn-scanner">📷 Escanear QR</a>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- ESTADO DE HOY -->
        <!-- ============================================ -->
        <div class="card">
            <div class="card-header">
                <h2>📊 Estado de Hoy</h2>
                <span class="badge"><?php echo date('d/m/Y'); ?></span>
            </div>

            <?php if ($hoy): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #dcfce7; color: #16a34a;">🕐</div>
                        <div class="stat-number"><?php echo $hoy['check_in'] ? date('H:i', strtotime($hoy['check_in'])) : '--:--'; ?></div>
                        <div class="stat-label">Hora de entrada</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">🕐</div>
                        <div class="stat-number"><?php echo $hoy['check_out'] ? date('H:i', strtotime($hoy['check_out'])) : '--:--'; ?></div>
                        <div class="stat-label">Hora de salida</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #dbeafe; color: #1e40af;">⏱️</div>
                        <div class="stat-number"><?php echo $hoy['total_hours'] ? number_format($horas_hoy, 2) . 'h' : '--'; ?></div>
                        <div class="stat-label">Horas trabajadas hoy</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #fef3c7; color: #92400e;">🎯</div>
                        <div class="stat-number"><?php echo $texto_horas_requeridas; ?></div>
                        <div class="stat-label">Horas requeridas</div>
                    </div>
                </div>

                <?php if ($hoy_abierto): ?>
                    <div style="background:#dbeafe; border:1px solid #93c5fd; border-radius:12px; padding:16px; margin-top:15px; text-align:center;">
                        <p style="font-size:28px; margin-bottom:6px;">⏳</p>
                        <p style="color:#1e40af; font-weight:700; font-size:15px;">
                            Tu jornada está en curso
                        </p>
                        <p style="color:#1e40af; font-size:13px; margin-top:4px;">
                            Entraste a las <strong><?php echo date('H:i', strtotime($hoy['check_in'])); ?></strong> — aún no registras tu salida.<br>
                            Escanea tu QR cuando termines tu turno.
                        </p>
                    </div>
                <?php elseif ($horas_hoy > 0): ?>
                    <div class="progress-container">
                        <div class="progress-label">
                            <span>Progreso del día</span>
                            <span><?php echo number_format($horas_hoy, 2); ?>h / <?php echo $texto_horas_requeridas; ?></span>
                        </div>
                        <div class="progress-bar">
                            <?php $color_hoy = $pct_hoy >= 100 ? 'green' : ($pct_hoy >= 50 ? 'yellow' : 'red'); ?>
                            <div class="progress-fill <?php echo $color_hoy; ?>" style="width: <?php echo $pct_hoy; ?>%;">
                                <?php echo $pct_hoy; ?>%
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon">📌</span>
                    <p>No has fichado hoy</p>
                    <p style="font-size: 13px; margin-top: 5px;">Escanea tu QR para registrar tu entrada</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ============================================ -->
        <!-- RESUMEN DE LA SEMANA -->
        <!-- ============================================ -->
        <div class="card">
            <div class="card-header">
                <h2>📅 Esta Semana</h2>
                <span class="badge">
                    <?php echo date('d/m', strtotime($week_start)) . ' - ' . date('d/m', strtotime($week_end)); ?>
                </span>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #dcfce7; color: #16a34a;">📅</div>
                    <div class="stat-number"><?php echo $dias_trabajados_semana; ?></div>
                    <div class="stat-label">Días trabajados</div>
                    <div class="stat-change">Días que viniste esta semana</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #dbeafe; color: #1e40af;">⏱️</div>
                    <div class="stat-number"><?php echo number_format($total_horas_semana, 1); ?>h</div>
                    <div class="stat-label">Horas trabajadas</div>
                    <div class="stat-change">Total de la semana</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #fef3c7; color: #92400e;">🎯</div>
                    <div class="stat-number"><?php echo number_format($horas_esperadas_semana, 0); ?>h</div>
                    <div class="stat-label">Meta semanal</div>
                    <div class="stat-change"><?php echo $horas_requeridas_decimal; ?>h × 5 días</div>
                </div>
            </div>

            <div class="progress-container">
                <div class="progress-label">
                    <span>Progreso semanal</span>
                    <span><?php echo number_format($total_horas_semana, 1); ?>h / <?php echo number_format($horas_esperadas_semana, 1); ?>h</span>
                </div>
                <div class="progress-bar">
                    <?php $color_sem = $pct_semana >= 100 ? 'green' : ($pct_semana >= 50 ? 'yellow' : 'red'); ?>
                    <div class="progress-fill <?php echo $color_sem; ?>" style="width: <?php echo $pct_semana; ?>%;">
                        <?php echo $pct_semana; ?>%
                    </div>
                </div>
            </div>

            <?php if ($cumplio_semana): ?>
                <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 2px solid #fbbf24; border-radius: 12px; padding: 18px; margin-top: 15px; text-align: center;">
                    <p style="font-size: 32px; margin-bottom: 5px;">🏆</p>
                    <p style="color: #92400e; font-weight: 700; font-size: 16px;">
                        ¡Felicidades! Cumpliste tu meta semanal
                    </p>
                    <p style="color: #78350f; font-size: 14px; margin-top: 5px;">
                        Trabajaste <strong><?php echo number_format($total_horas_semana, 1); ?>h</strong> 
                        de <strong><?php echo number_format($horas_esperadas_semana, 0); ?>h</strong> requeridas
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ============================================ -->
        <!-- RESUMEN DEL MES -->
        <!-- ============================================ -->
        <div class="card">
            <div class="card-header">
                <h2>📆 Este Mes · <?php echo $nombre_mes; ?></h2>
                <span class="badge"><?php echo $dias_trabajados_mes; ?> días trabajados</span>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #dcfce7; color: #16a34a;">📅</div>
                    <div class="stat-number"><?php echo $dias_trabajados_mes; ?></div>
                    <div class="stat-label">Días trabajados</div>
                    <div class="stat-change">Días que viniste este mes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #dbeafe; color: #1e40af;">⏱️</div>
                    <div class="stat-number"><?php echo number_format($total_horas_mes, 1); ?>h</div>
                    <div class="stat-label">Horas trabajadas</div>
                    <div class="stat-change">Total del mes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #fef3c7; color: #92400e;">🎯</div>
                    <div class="stat-number"><?php echo number_format($horas_requeridas_mes, 1); ?>h</div>
                    <div class="stat-label">Horas requeridas</div>
                    <div class="stat-change"><?php echo $horas_requeridas_decimal; ?>h × <?php echo $dias_trabajados_mes; ?> días</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #eef2ff; color: #4f46e5;">📊</div>
                    <div class="stat-number"><?php echo number_format($promedio_diario, 1); ?>h</div>
                    <div class="stat-label">Promedio diario</div>
                    <div class="stat-change">Horas por día trabajado</div>
                </div>
            </div>

            <div class="progress-container">
                <div class="progress-label">
                    <span>Progreso del mes</span>
                    <span><?php echo number_format($total_horas_mes, 1); ?>h / <?php echo number_format($horas_requeridas_mes, 1); ?>h</span>
                </div>
                <div class="progress-bar">
                    <?php $color_mes = $pct_mes >= 100 ? 'green' : ($pct_mes >= 70 ? 'yellow' : 'red'); ?>
                    <div class="progress-fill <?php echo $color_mes; ?>" style="width: <?php echo $pct_mes; ?>%;">
                        <?php echo $pct_mes; ?>%
                    </div>
                </div>
            </div>

            <?php if ($cumplio_mes && $dias_trabajados_mes > 0): ?>
                <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 2px solid #fbbf24; border-radius: 12px; padding: 20px; margin-top: 15px; text-align: center;">
                    <p style="font-size: 40px; margin-bottom: 8px;">🏆</p>
                    <p style="color: #92400e; font-weight: 800; font-size: 18px; margin-bottom: 8px;">
                        ¡Felicidades! Cumpliste tus horas este mes
                    </p>
                    <p style="color: #78350f; font-size: 14px; line-height: 1.6;">
                        Trabajaste <strong><?php echo number_format($total_horas_mes, 1); ?>h</strong> 
                        de <strong><?php echo number_format($horas_requeridas_mes, 1); ?>h</strong> requeridas
                        <?php if ($horas_extras_mes > 0): ?>
                            <br>🎯 <strong style="color: #be185d; font-size: 16px;">+<?php echo number_format($horas_extras_mes, 1); ?>h extras</strong>
                        <?php endif; ?>
                    </p>
                    <span class="highlight" style="display: inline-block; background: #fbbf24; color: #78350f; padding: 6px 18px; border-radius: 20px; font-weight: 700; margin-top: 12px;">
                        💪 ¡Sigue así!
                    </span>
                </div>
            <?php elseif ($dias_trabajados_mes > 0): ?>
                <div style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 12px; padding: 14px; margin-top: 15px; text-align: center;">
                    <p style="color: #92400e; font-weight: 600; font-size: 14px;">
                        ⏳ Te faltan <strong><?php echo number_format(abs($horas_extras_mes), 1); ?>h</strong> para cumplir tu meta del mes
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ============================================ -->
        <!-- HISTORIAL DETALLADO -->
        <!-- ============================================ -->
        <div class="card">
            <div class="card-header">
                <h2>📋 Historial Detallado</h2>
                <span class="badge">Últimos 30 días</span>
            </div>

            <div class="table-container">
                <?php if ($hist_result && $hist_result->num_rows > 0): ?>
                    <table>
                        <tr>
                            <th>Fecha</th>
                            <th>Día</th>
                            <th>Entrada</th>
                            <th>Salida</th>
                            <th>Horas</th>
                            <th>Cumplió</th>
                        </tr>
                        <?php 
                        $dias_semana = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
                        while ($row = $hist_result->fetch_assoc()): 
                            $fecha = $row['fecha'];
                            $dia_nombre = $dias_semana[date('w', strtotime($fecha))];
                            $horas_dia = floatval($row['horas_dia'] ?? 0);
                            $cumplio_dia = ($horas_dia >= $horas_requeridas_decimal && $horas_dia > 0);
                        ?>
                            <tr>
                                <td><strong><?php echo date('d/m/Y', strtotime($fecha)); ?></strong></td>
                                <td style="text-transform: capitalize;"><?php echo $dia_nombre; ?></td>
                                <td><?php echo $row['primera_entrada'] ? date('H:i', strtotime($row['primera_entrada'])) : '--:--'; ?></td>
                                <td><?php echo $row['ultima_salida'] ? date('H:i', strtotime($row['ultima_salida'])) : '--:--'; ?></td>
                                <td><strong><?php echo number_format($horas_dia, 2); ?>h</strong></td>
                                <td>
                                    <?php if ($cumplio_dia): ?>
                                        <span class="badge badge-cumplio">✅ Cumplió</span>
                                    <?php else: ?>
                                        <span class="badge badge-no-cumplio">⚠️ No cumplió</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="empty-icon">📋</span>
                        <p>No hay registros en los últimos 30 días</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

</body>
</html>