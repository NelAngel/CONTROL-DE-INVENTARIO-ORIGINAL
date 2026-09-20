<?php
// exportar_excel.php
require_once 'config.php';
session_start();

if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header('Location: login.php');
    exit;
}

$mes_sel = isset($_GET['mes']) ? $_GET['mes'] : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mes_sel)) {
    $mes_sel = date('Y-m');
}

// Cabeceras para forzar descarga Excel
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename=reporte_' . $mes_sel . '.xls');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF"; // BOM UTF-8 para que Excel reconozca tildes
?>
<table border="1">
    <thead>
        <tr style="background: #4f46e5; color: white;">
            <th>N° Empleado</th>
            <th>Nombre</th>
            <th>Horas/día</th>
            <th>Días trabajados</th>
            <th>Horas requeridas</th>
            <th>Horas trabajadas</th>
            <th>Diferencia</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        <?php
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

        while ($row = $result->fetch_assoc()) {
            $req_diaria = (strtotime($row['work_hours_required']) - strtotime('00:00:00')) / 3600;
            $req_mes = $req_diaria * $row['dias_trabajados'];
            $trabajadas = floatval($row['horas_trabajadas']);
            $diferencia = $trabajadas - $req_mes;
            $cumplio = ($req_mes > 0 && $trabajadas >= $req_mes);

            $partes_req = explode(':', $row['work_hours_required']);
            $hh = intval($partes_req[0] ?? 0);
            $mm = intval($partes_req[1] ?? 0);
            $texto_req_dia = $hh . 'h';
            if ($mm > 0) $texto_req_dia .= ' ' . $mm . 'min';

            echo "<tr>";
            echo "<td>" . $row['employee_number'] . "</td>";
            echo "<td>" . $row['full_name'] . "</td>";
            echo "<td>" . $texto_req_dia . "</td>";
            echo "<td>" . $row['dias_trabajados'] . "</td>";
            echo "<td>" . number_format($req_mes, 2) . "</td>";
            echo "<td>" . number_format($trabajadas, 2) . "</td>";
            echo "<td>" . number_format($diferencia, 2) . "</td>";
            echo "<td>" . ($cumplio ? 'CUMPLIÓ' : 'NO CUMPLIÓ') . "</td>";
            echo "</tr>";
        }
        ?>
    </tbody>
</table>