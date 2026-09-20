<?php
// report.php - Reporte de cumplimiento de horas
require_once 'config.php';
session_start();

if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reporte de Cumplimiento</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f0f2f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .cumple { color: #28a745; font-weight: bold; }
        .no-cumple { color: #dc3545; font-weight: bold; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
        .header-bar {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        .logout-btn:hover { background: #c82333; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-bar">
            <h1 style="margin:0;">📊 Reporte de Cumplimiento</h1>
            <div class="user-info">
                <span class="user-name">👤 <?php echo $_SESSION['usuario'] ?? 'Administrador'; ?></span>
                <a href="logout.php" class="logout-btn">🚪 Cerrar Sesión</a>
            </div>
        </div>
        
        <div class="card">
            <a href="admin.php" class="btn">⬅️ Volver al Panel</a>
        </div>
        
        <div class="card">
            <h2>📅 Semana del <?php echo date('d/m/Y', strtotime('monday this week')); ?> al <?php echo date('d/m/Y', strtotime('sunday this week')); ?></h2>
            <?php
            $start_week = date('Y-m-d', strtotime('monday this week'));
            $end_week = date('Y-m-d', strtotime('sunday this week'));
            
            $sql = "SELECT e.id, e.employee_number, e.full_name, e.work_hours_required,
                    SUM(TIME_TO_SEC(a.total_hours)) / 3600 as total_hours_week,
                    COUNT(DISTINCT DATE(a.check_in)) as days_worked
                    FROM employees e
                    LEFT JOIN attendance a ON e.id = a.employee_id 
                        AND DATE(a.check_in) BETWEEN '$start_week' AND '$end_week'
                    GROUP BY e.id
                    ORDER BY e.employee_number ASC";
            
            $result = $conn->query($sql);
            
            if ($result->num_rows > 0) {
                echo "<table>";
                echo "<tr><th>N°</th><th>Empleado</th><th>Horas Requeridas</th><th>Horas Trabajadas</th><th>Días Trabajados</th><th>Estado</th></tr>";
                while ($row = $result->fetch_assoc()) {
                    $required = strtotime($row['work_hours_required']);
                    $hours_worked = round($row['total_hours_week'] ?? 0, 2);
                    $required_hours = ($required - strtotime('00:00:00')) / 3600;
                    $cumple = $hours_worked >= ($required_hours * 5);
                    
                    echo "<tr>";
                    echo "<td>{$row['employee_number']}</td>";
                    echo "<td>{$row['full_name']}</td>";
                    echo "<td>" . ($required_hours * 5) . "h</td>";
                    echo "<td>" . number_format($hours_worked, 2) . "h</td>";
                    echo "<td>{$row['days_worked']} días</td>";
                    echo "<td class='" . ($cumple ? 'cumple' : 'no-cumple') . "'>" . ($cumple ? '✅ Cumple' : '❌ No cumple') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No hay empleados registrados.</p>";
            }
            ?>
        </div>
    </div>
</body>
</html>