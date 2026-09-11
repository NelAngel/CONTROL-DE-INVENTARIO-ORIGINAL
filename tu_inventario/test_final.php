<?php
require_once 'config.php';

echo "=== DIAGNÓSTICO COMPLETO ===<br><br>";

$pdo = getConnection();

// 1. Ver que el usuario admin existe y sus datos
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = 'admin'");
$stmt->execute();
$user = $stmt->fetch();

if (!$user) {
    die("❌ El usuario 'admin' NO existe en la base de datos");
}

echo "✅ Usuario encontrado:<br>";
echo "ID: " . $user['id'] . "<br>";
echo "Usuario: " . $user['usuario'] . "<br>";
echo "Activo: " . ($user['activo'] ? 'SÍ' : 'NO') . "<br>";
echo "Rol: " . $user['rol'] . "<br>";
echo "Hash almacenado: " . $user['password'] . "<br>";
echo "Longitud del hash: " . strlen($user['password']) . " caracteres<br><br>";

// 2. Probar la contraseña admin123
$password_prueba = 'admin123';
$verifica = password_verify($password_prueba, $user['password']);

echo "Probando contraseña: 'admin123'<br>";
echo "Resultado: " . ($verifica ? '✅ CORRECTA' : '❌ INCORRECTA') . "<br><br>";

// 3. Generar un nuevo hash y actualizar
if (!$verifica) {
    echo "🔧 GENERANDO NUEVO HASH...<br>";
    $nuevo_hash = password_hash('admin123', PASSWORD_DEFAULT);
    echo "Nuevo hash generado: " . $nuevo_hash . "<br>";
    
    $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE usuario = 'admin'");
    $stmt->execute([$nuevo_hash]);
    
    echo "✅ Contraseña actualizada en la base de datos<br><br>";
    
    // Verificar que funcionó
    $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE usuario = 'admin'");
    $stmt->execute();
    $user = $stmt->fetch();
    
    echo "Nuevo hash en BD: " . $user['password'] . "<br>";
    echo "Verificando nuevamente: " . (password_verify('admin123', $user['password']) ? '✅ CORRECTA' : '❌ INCORRECTA') . "<br>";
}

echo "<br><a href='login.php'>Ir al Login</a>";
?>