<?php
// reporte_mensual.php - Reporte mensual de cumplimiento de horas
require_once 'config.php';
session_start();

if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header('Location: login.php');
    exit;
}

// Mes seleccionado (por defecto: mes actual)
$mes_sel = isset($_GET['mes']) ? $_GET['mes'] : date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $mes_sel)) {
    $mes_sel = date('Y-m');
}

// Nombre del mes en español
$meses = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo',
    '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
    '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre',
    '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
];

$partes = explode('-', $mes_sel);
$anio = $partes[0];
$mes_num = $partes[1];
$nombre_mes = $meses[$mes_num] . ' ' . $anio;

// Días laborales del mes (lunes a viernes)
$dias_laborales_mes = 0;
$dias_en_mes = cal_days_in_month(CAL_GREGORIAN, intval($mes_num), intval($anio));
for ($d = 1; $d <= $dias_en_mes; $d++) {
    $fecha = sprintf('%04d-%02d-%02d', $anio, $mes_num, $d);
    $dia_semana = date('N', strtotime($fecha));
    if ($dia_semana <= 5) {
        $dias_laborales_mes++;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Mensual - Control de Horas</title>
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

        .header-bar h1 { font-size: 22px; font-weight: 700; color: #1a1a2e; }

        .user-info { display: flex; align-items: center; gap: 16px; }
        .user-name { color: #1a1a2e; font-weight: 500; font-size: 14px; }

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

        .card-header h2 { font-size: 18px; font-weight: 600; color: #1a1a2e; }
        .card-subtitle { color: #6b7280; font-size: 13px; margin-top: 4px; }

        .mes-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }

        .mes-form input[type="month"] {
            padding: 10px 14px;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            color: #1a1a2e;
            background: #f9fafb;
            transition: all 0.3s;
        }
        .mes-form input[type="month"]:focus {
            outline: none;
            border-color: #4f46e5;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

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
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
        }
        .btn-info { background: #dbeafe; border: 1px solid #93c5fd; color: #1e40af; }
        .btn-back { background: #f3f4f6; border: 1px solid #e5e7eb; color: #374151; }
        .btn-excel {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.25);
        }

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
        }

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
        td { padding: 14px 16px; border-bottom: 1px solid #f3f4f6; color: #1a1a2e; }
        tr:hover td { background: #f9fafb; }
        tr:last-child td { border-bottom: none; }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-danger { background: #fee2e2; color: #dc2626; }
        .badge-warning { background: #fef3c7; color: #92400e; }

        .horas-cumplidas { color: #15803d; font-weight: 700; }
        .horas-faltantes { color: #dc2626; font-weight: 700; }
        .diferencia-positiva { color: #15803d; font-weight: 600; }
        .diferencia-negativa { color: #dc2626; font-weight: 600; }

        .progress-bar {
            background: #f3f4f6;
            border-radius: 12px;
            height: 8px;
            overflow: hidden;
            margin-top: 6px;
        }
        .progress-fill { height: 100%; border-radius: 12px; transition: width 0.5s ease-out; }
        .progress-fill.green { background: linear-gradient(90deg, #22c55e 0%, #16a34a 100%); }
        .progress-fill.yellow { background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%); }
        .progress-fill.red { background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%); }

        @media (max-width: 768px) {
            body { padding: 16px; }
            .header-bar { flex-direction: column; text-align: center; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            table { font-size: 12px; }
            th, td { padding: 10px 12px; }
            .mes-form { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

    <div class="container">

        <div class="header-bar">
            <div>
                <h1>📊 Reporte Mensual de Horas</h1>
                <div style="color: #6b7280; font-size: 13px; margin-top: 4px;">
                    Cumplimiento de horas requeridas · <strong><?php echo $nombre_mes; ?></strong>
                </div>
            </div>
            <div class="user-info">
                <span class="user-name">👤 <?php echo $_SESSION['usuario'] ?? 'Administrador'; ?></span>
                <a href="admin.php" class="btn btn-back">⬅️ Volver al Panel</a>
                <a href="logout.php" class="logout-btn">🚪 Salir</a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2>📅 Seleccionar Mes</h2>
                    <div class="card-subtitle">Elige el mes para ver el reporte de cumplimiento</div>
                </div>
                <form method="GET" action="" class="mes-form">
                    <input type="month" name="mes" value="<?php echo $mes_sel; ?>" max="<?php echo date('Y-m'); ?>">
                    <button type="submit" class="btn btn-primary">🔍 Ver</button>
                    <a href="reporte_mensual.php" class="btn btn-info">📅 Mes Actual</a>
                    <a href="exportar_excel.php?mes=<?php echo $mes_sel; ?>" class="btn btn-excel">📥 Exportar Excel</a>
                </form>
            </div>
        </div>

        <!-- RESUMEN GENERAL -->
        <?php
        // ============================================
        // CONSULTA CORREGIDA: convierte TIME a horas decimales
        // ============================================
        $resumen_sql = "
            SELECT 
                e.id,
                e.work_hours_required,
                COUNT(DISTINCT DATE(a.check_in)) as dias_trabajados,
                COALESCE(SUM(TIME_TO_SEC(a.total_hours)) / 3600, 0) as horas_trabajadas
            FROM employees e
            LEFT JOIN attendance a ON e.id = a.employee_id 
                AND DATE_FORMAT(a.check_in, '%Y-%m') = '$mes_sel'
                AND a.total_hours IS NOT NULL
            WHERE e.is_active = 1
            GROUP BY e.id
        ";
        $resumen_result = $conn->query($resumen_sql);
        
        $total_cumplieron = 0;
        $total_no_cumplieron = 0;
        $total_horas_empresa = 0;

        while ($r = $resumen_result->fetch_assoc()) {
            $req_diaria = (strtotime($r['work_hours_required']) - strtotime('00:00:00')) / 3600;
            $req_mes = $req_diaria * $r['dias_trabajados'];
            $total_horas_empresa += $r['horas_trabajadas'];
            
            if ($r['dias_trabajados'] > 0 && $r['horas_trabajadas'] >= $req_mes) {
                $total_cumplieron++;
            } else {
                $total_no_cumplieron++;
            }
        }
        ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #eef2ff; color: #4f46e5;">📅</div>
                <div class="stat-number"><?php echo $dias_laborales_mes; ?></div>
                <div class="stat-label">Días laborales del mes</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #dcfce7; color: #16a34a;">✅</div>
                <div class="stat-number"><?php echo $total_cumplieron; ?></div>
                <div class="stat-label">Cumplieron</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #fee2e2; color: #dc2626;">❌</div>
                <div class="stat-number"><?php echo $total_no_cumplieron; ?></div>
                <div class="stat-label">No cumplieron</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #dbeafe; color: #1e40af;">⏱️</div>
                <div class="stat-number"><?php echo number_format($total_horas_empresa, 0); ?>h</div>
                <div class="stat-label">Total horas empresa</div>
            </div>
        </div>

        <!-- TABLA DETALLADA -->
        <div class="card">
            <div class="card-header">
                <div>
                    <h2>👥 Detalle por Empleado</h2>
                    <div class="card-subtitle">Horas trabajadas vs horas requeridas en el mes</div>
                </div>
            </div>

            <div class="table-container">
                <?php
                // ============================================
                // CONSULTA CORREGIDA: convierte TIME a horas decimales
                // ============================================
                $sql = "
                    SELECT 
                        e.employee_number,
                        e.full_name,
                        e.work_hours_required,
                        COUNT(DISTINCT DATE(a.check_in)) as dias_trabajados,
                        COALESCE(SUM(TIME_TO_SEC(a.total_hours)) / 3600, 0) as horas_trabajadas
                    FROM employees e
                    LEFT JOIN attendance a ON e.id = a.employee_id 
                        AND DATE_FORMAT(a.check_in, '%Y-%m') = '$mes_sel'
                        AND a.total_hours IS NOT NULL
                    WHERE e.is_active = 1
                    GROUP BY e.id
                    ORDER BY e.employee_number ASC
                ";
                $result = $conn->query($sql);

                if ($result && $result->num_rows > 0): ?>
                    <table>
                        <tr>
                            <th>N°</th>
                            <th>Empleado</th>
                            <th>Horas/día</th>
                            <th>Días trabajados</th>
                            <th>Horas requeridas</th>
                            <th>Horas trabajadas</th>
                            <th>Diferencia</th>
                            <th>Progreso</th>
                            <th>Estado</th>
                        </tr>
                        <?php while ($row = $result->fetch_assoc()): 
                            $req_diaria = (strtotime($row['work_hours_required']) - strtotime('00:00:00')) / 3600;
                            $req_mes = $req_diaria * $row['dias_trabajados'];
                            $trabajadas = floatval($row['horas_trabajadas']);
                            $diferencia = $trabajadas - $req_mes;
                            $cumplio = ($req_mes > 0 && $trabajadas >= $req_mes);
                            $pct = $req_mes > 0 ? min(150, round(($trabajadas / $req_mes) * 100)) : 0;
                            
                            if ($pct >= 100) $color_bar = 'green';
                            elseif ($pct >= 70) $color_bar = 'yellow';
                            else $color_bar = 'red';
                            
                            $partes_req = explode(':', $row['work_hours_required']);
                            $hh = intval($partes_req[0] ?? 0);
                            $mm = intval($partes_req[1] ?? 0);
                            $texto_req_dia = $hh . 'h';
                            if ($mm > 0) $texto_req_dia .= ' ' . $mm . 'min';
                        ?>
                            <tr>
                                <td><strong><?php echo $row['employee_number']; ?></strong></td>
                                <td><?php echo $row['full_name']; ?></td>
                                <td><?php echo $texto_req_dia; ?></td>
                                <td><?php echo $row['dias_trabajados']; ?> días</td>
                                <td><strong><?php echo number_format($req_mes, 2); ?>h</strong></td>
                                <td class="<?php echo $cumplio ? 'horas-cumplidas' : 'horas-faltantes'; ?>">
                                    <?php echo number_format($trabajadas, 2); ?>h
                                </td>
                                <td class="<?php echo $diferencia >= 0 ? 'diferencia-positiva' : 'diferencia-negativa'; ?>">
                                    <?php echo ($diferencia >= 0 ? '+' : '') . number_format($diferencia, 2); ?>h
                                </td>
                                <td style="min-width: 120px;">
                                    <div class="progress-bar">
                                        <div class="progress-fill <?php echo $color_bar; ?>" style="width: <?php echo min(100, $pct); ?>%;"></div>
                                    </div>
                                    <div style="font-size: 11px; color: #6b7280; text-align: center; margin-top: 3px;">
                                        <?php echo $pct; ?>%
                                    </div>
                                </td>
                                <td>
                                    <?php if ($cumplio): ?>
                                        <span class="badge badge-success">✅ Cumplió</span>
                                    <?php elseif ($row['dias_trabajados'] == 0): ?>
                                        <span class="badge badge-warning">⚠️ Sin registros</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">❌ No cumplió</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </table>
                <?php else: ?>
                    <p style="padding: 20px; color: #6b7280; text-align: center;">
                        No hay empleados activos registrados.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- EXPLICACIÓN -->
        <div class="card" style="background: #f9fafb; border: 1px dashed #e5e7eb;">
            <h2 style="font-size: 15px; color: #374151; margin-bottom: 10px;">ℹ️ ¿Cómo se calculan las horas requeridas?</h2>
            <ul style="padding-left: 20px; color: #6b7280; font-size: 13px; line-height: 1.8;">
                <li><strong>Horas requeridas del mes</strong> = Horas diarias del empleado × Días que trabajó</li>
                <li>Ejemplo: Si Mary trabaja 8h/día y trabajó 2 días → 8 × 2 = <strong>16 horas requeridas</strong></li>
                <li>Si trabajó 16 horas o más → cumplió ✅</li>
                <li>Si trabajó menos de 16 horas → no cumplió ❌</li>
                <li><strong>Nota:</strong> Los tiempos se convierten de formato TIME (00:31:00) a decimal (0.52h) para el cálculo correcto</li>
            </ul>
        </div>

    </div>

</body>
</html>