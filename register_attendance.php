<?php
// register_attendance.php - Registro de asistencia por QR (versión blindada)
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'config.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

// ============================================
// 1. VERIFICAR SESIÓN
// ============================================
if (!isset($_SESSION['worker_logged_in']) || $_SESSION['worker_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => '❌ Debes iniciar sesión primero']);
    exit;
}

if (!isset($_GET['token'])) {
    echo json_encode(['status' => 'error', 'message' => '❌ No se recibió token']);
    exit;
}

$token = $conn->real_escape_string($_GET['token']);
$worker_id = $_SESSION['worker_id'];
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$user_agent = $conn->real_escape_string($_SERVER['HTTP_USER_AGENT'] ?? '');

// ============================================
// 2. VERIFICAR QUE EL QR PERTENECE AL TRABAJADOR
// ============================================
$sql = "SELECT id, full_name, employee_number, is_active 
        FROM employees 
        WHERE id = $worker_id AND qr_token = '$token' AND is_active = 1";
$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    echo json_encode([
        'status' => 'error',
        'message' => '❌ Este QR no corresponde a tu cuenta'
    ]);
    exit;
}

$employee = $result->fetch_assoc();
$employee_id = $employee['id'];
$full_name = $employee['full_name'];
$employee_number = $employee['employee_number'];

$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');

// ============================================
// 2.1. DETECTAR TARDANZA SEGÚN HORA DE INICIO DE JORNADA
// ============================================
$hora_actual = date('H:i:s', strtotime($now));
$work_start = '09:00:00';
$settings_res = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'work_start_time'");
if ($settings_res && $settings_res->num_rows > 0) {
    $s_row = $settings_res->fetch_assoc();
    if (!empty($s_row['setting_value'])) {
        $work_start = $s_row['setting_value'];
    }
}
$status_entrada = ($hora_actual > $work_start) ? 'late' : 'present';

// ============================================
// 3. LIMPIAR REGISTROS ABIERTOS DE DÍAS ANTERIORES
// ============================================
$close_old = "UPDATE attendance 
              SET check_out = check_in, 
                  total_hours = 0,
                  status = 'present'
              WHERE employee_id = $employee_id 
              AND DATE(check_in) < '$today' 
              AND check_out IS NULL";
$conn->query($close_old);

// ============================================
// 4. BUSCAR REGISTRO ABIERTO DE HOY
// ============================================
$check_sql = "SELECT id, check_in FROM attendance 
              WHERE employee_id = $employee_id 
              AND DATE(check_in) = '$today' 
              AND check_out IS NULL
              ORDER BY check_in DESC LIMIT 1";
$check_result = $conn->query($check_sql);

if ($check_result && $check_result->num_rows > 0) {
    // ============================================
    // REGISTRAR SALIDA
    // ============================================
    $record = $check_result->fetch_assoc();
    $attendance_id = $record['id'];
    $check_in_time = $record['check_in'];

    // Calcular minutos desde la entrada
    $diff_segundos = strtotime($now) - strtotime($check_in_time);
    $diff_minutos = $diff_segundos / 60;

    // ⚠️ VALIDACIÓN: evitar salidas con menos de 1 minuto
    if ($diff_minutos < 1) {
        echo json_encode([
            'status' => 'error',
            'message' => '⏱️ Deben pasar al menos 1 minuto desde tu entrada para registrar la salida'
        ]);
        exit;
    }

    // ⚠️ VALIDACIÓN: si pasaron más de 16 horas, es un registro abandonado
    if ($diff_minutos > 16 * 60) {
        // Cerrar el registro viejo sin contar las horas
        $conn->query("UPDATE attendance 
                      SET check_out = check_in, 
                          total_hours = 0,
                          status = 'present'
                      WHERE id = $attendance_id");
        
        // Crear un nuevo registro de entrada
        $insert_sql = "INSERT INTO attendance (employee_id, check_in, status, ip_address, device_info) 
                       VALUES ($employee_id, '$now', '$status_entrada', '$ip', '$user_agent')";
        
        if ($conn->query($insert_sql)) {
            echo json_encode([
                'status' => 'success',
                'message' => "✅ Nueva entrada registrada para $full_name",
                'action' => 'check_in',
                'employee' => $full_name,
                'employee_number' => $employee_number,
                'time' => $now
            ]);
        }
        exit;
    }

    // Calcular horas trabajadas (formato TIME válido para la columna total_hours)
    $diff_hours = round($diff_segundos / 3600, 2);
    $total_hours_time = sprintf('%02d:%02d:%02d',
        floor($diff_segundos / 3600),
        floor(($diff_segundos % 3600) / 60),
        $diff_segundos % 60
    );

    $update_sql = "UPDATE attendance 
                   SET check_out = '$now', 
                       total_hours = '$total_hours_time',
                       ip_address = '$ip',
                       device_info = '$user_agent'
                   WHERE id = $attendance_id";

    if ($conn->query($update_sql)) {
        // Registrar en log
        $log_sql = "INSERT INTO qr_scan_log (employee_id, scan_type, ip_address, user_agent) 
                    VALUES ($employee_id, 'check_out', '$ip', '$user_agent')";
        $conn->query($log_sql);

        echo json_encode([
            'status' => 'success',
            'message' => "✅ ¡Salida registrada! $full_name",
            'action' => 'check_out',
            'employee' => $full_name,
            'employee_number' => $employee_number,
            'hours' => $diff_hours . ' horas'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => '❌ Error al registrar salida: ' . $conn->error
        ]);
    }

} else {
    // ============================================
    // REGISTRAR ENTRADA
    // ============================================
    
    // ⚠️ VALIDACIÓN: evitar múltiples entradas en el mismo minuto
    $last_scan_sql = "SELECT check_in FROM attendance 
                      WHERE employee_id = $employee_id 
                      AND DATE(check_in) = '$today' 
                      ORDER BY check_in DESC LIMIT 1";
    $last_scan_result = $conn->query($last_scan_sql);
    
    if ($last_scan_result && $last_scan_result->num_rows > 0) {
        $last_scan = $last_scan_result->fetch_assoc();
        $diff_min = (strtotime($now) - strtotime($last_scan['check_in'])) / 60;
        
        if ($diff_min < 1) {
            echo json_encode([
                'status' => 'error',
                'message' => '⏱️ Ya registraste tu entrada hace menos de 1 minuto'
            ]);
            exit;
        }
    }

    $insert_sql = "INSERT INTO attendance (employee_id, check_in, status, ip_address, device_info) 
                   VALUES ($employee_id, '$now', '$status_entrada', '$ip', '$user_agent')";

    if ($conn->query($insert_sql)) {
        $log_sql = "INSERT INTO qr_scan_log (employee_id, scan_type, ip_address, user_agent) 
                    VALUES ($employee_id, 'check_in', '$ip', '$user_agent')";
        $conn->query($log_sql);

        echo json_encode([
            'status' => 'success',
            'message' => "✅ ¡Bienvenido! $full_name",
            'action' => 'check_in',
            'employee' => $full_name,
            'employee_number' => $employee_number,
            'time' => $now
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => '❌ Error al registrar entrada: ' . $conn->error
        ]);
    }
}

$conn->close();
?>