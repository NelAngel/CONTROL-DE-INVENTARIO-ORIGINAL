<?php
// validar_empleado.php - Validación en tiempo real
require_once 'config.php';
session_start();

if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['existe' => false, 'mensaje' => '❌ No autorizado']);
    exit;
}

header('Content-Type: application/json');

$response = ['existe' => false, 'mensaje' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campo = $_POST['campo'] ?? '';
    $valor = $conn->real_escape_string($_POST['valor'] ?? '');
    
    if (empty($campo) || empty($valor)) {
        $response['mensaje'] = 'Datos incompletos';
        echo json_encode($response);
        exit;
    }
    
    // Mapeo de campos a columnas
    $campos_validos = [
        'employee_number' => 'employee_number',
        'dni' => 'dni',
        'email' => 'email',
        'phone' => 'phone',
        'username' => 'username'
    ];
    
    if (!isset($campos_validos[$campo])) {
        $response['mensaje'] = 'Campo no válido';
        echo json_encode($response);
        exit;
    }
    
    $columna = $campos_validos[$campo];
    
    // Verificar si el valor ya existe
    $sql = "SELECT id, full_name FROM employees WHERE $columna = '$valor'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $employee = $result->fetch_assoc();
        $response['existe'] = true;
        $response['mensaje'] = "❌ El valor ya está registrado para: " . $employee['full_name'];
    } else {
        $response['existe'] = false;
        $response['mensaje'] = "✅ Disponible";
    }
}

$conn->close();
echo json_encode($response);
?>