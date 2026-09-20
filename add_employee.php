<?php
// add_employee.php - Registrar empleado con usuario y contraseña
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

session_start();

if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => '❌ No autorizado. Debes iniciar sesión.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y limpiar datos
    $employee_number = $conn->real_escape_string($_POST['employee_number'] ?? '');
    $dni = $conn->real_escape_string($_POST['dni'] ?? '');
    $full_name = $conn->real_escape_string($_POST['full_name'] ?? '');
    $phone = $conn->real_escape_string($_POST['phone'] ?? '');
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $username = $conn->real_escape_string($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Horas requeridas: recibir horas y minutos por separado
    $req_hours = intval($_POST['req_hours'] ?? 8);
    $req_minutes = intval($_POST['req_minutes'] ?? 0);
    
    // Validar rangos
    if ($req_hours < 0 || $req_hours > 24) $req_hours = 8;
    if ($req_minutes < 0 || $req_minutes > 59) $req_minutes = 0;
    
    // Construir el TIME en formato HH:MM:SS
    $work_hours = sprintf('%02d:%02d:00', $req_hours, $req_minutes);
    
    // Validar campos obligatorios
    if (empty($employee_number) || empty($dni) || empty($full_name) || empty($phone) || empty($username) || empty($password)) {
        $response['message'] = '❌ Todos los campos son obligatorios';
        echo json_encode($response);
        exit;
    }
    
    // Validar contraseña
    if (strlen($password) < 6) {
        $response['message'] = '❌ La contraseña debe tener al menos 6 caracteres';
        echo json_encode($response);
        exit;
    }
    
    // ============================================
    // VALIDACIÓN DE DUPLICADOS
    // ============================================
    
    // 1. Verificar número de empleado
    $check_sql = "SELECT id FROM employees WHERE employee_number = '$employee_number'";
    if ($conn->query($check_sql)->num_rows > 0) {
        $response['message'] = "❌ El número de empleado '$employee_number' ya existe";
        echo json_encode($response);
        exit;
    }
    
    // 2. Verificar DNI
    $check_sql = "SELECT id FROM employees WHERE dni = '$dni'";
    if ($conn->query($check_sql)->num_rows > 0) {
        $response['message'] = "❌ El DNI '$dni' ya está registrado";
        echo json_encode($response);
        exit;
    }
    
    // 3. Verificar username
    $check_sql = "SELECT id FROM employees WHERE username = '$username'";
    if ($conn->query($check_sql)->num_rows > 0) {
        $response['message'] = "❌ El usuario '$username' ya existe";
        echo json_encode($response);
        exit;
    }
    
    // 4. Verificar teléfono
    $check_sql = "SELECT id FROM employees WHERE phone = '$phone'";
    if ($conn->query($check_sql)->num_rows > 0) {
        $response['message'] = "❌ El teléfono '$phone' ya está registrado";
        echo json_encode($response);
        exit;
    }
    
    // 5. Verificar email (si se proporcionó)
    if (!empty($email)) {
        $check_sql = "SELECT id FROM employees WHERE email = '$email'";
        if ($conn->query($check_sql)->num_rows > 0) {
            $response['message'] = "❌ El email '$email' ya está registrado";
            echo json_encode($response);
            exit;
        }
    }
    
    // ============================================
    // REGISTRAR NUEVO EMPLEADO
    // ============================================
    
    // Hashear contraseña
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Generar token único para el QR (máximo 50 caracteres)
    $qr_token = 'EMP_' . strtoupper(substr(md5(uniqid() . $dni . $employee_number . time()), 0, 12));
    
    $sql = "INSERT INTO employees (employee_number, dni, full_name, phone, email, username, password_hash, qr_token, work_hours_required) 
            VALUES ('$employee_number', '$dni', '$full_name', '$phone', '$email', '$username', '$password_hash', '$qr_token', '$work_hours')";
    
    if ($conn->query($sql)) {
        $response['success'] = true;
        $response['message'] = "✅ Empleado '$full_name' registrado correctamente | 👤 Usuario: $username | 🔑 Contraseña: $password | ⏱ Jornada: {$req_hours}h {$req_minutes}min";
        $response['qr_token'] = $qr_token;
        $response['employee_id'] = $conn->insert_id;
        $response['username'] = $username;
        $response['password'] = $password;
    } else {
        $response['message'] = "❌ Error al registrar: " . $conn->error;
    }
} else {
    $response['message'] = '❌ Método no permitido';
}

echo json_encode($response);
$conn->close();
?>