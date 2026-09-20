<?php
// login_worker.php - Login de trabajadores con verificación de dispositivo
require_once 'config.php';
session_start();

if (isset($_SESSION['worker_logged_in']) && $_SESSION['worker_logged_in'] === true) {
    header('Location: worker_dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    
    $current_user_agent = $conn->real_escape_string($_SERVER['HTTP_USER_AGENT'] ?? '');
    $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    $sql = "SELECT id, full_name, username, password_hash, is_active, qr_token, employee_number,
                   device_user_agent, device_ip, device_token, device_registered_at, device_reset_required
            FROM employees WHERE username = '$username'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $employee = $result->fetch_assoc();
        
        if ($employee['is_active'] == 0) {
            $error = '❌ Tu cuenta está desactivada. Contacta al administrador.';
        } elseif (password_verify($password, $employee['password_hash'])) {
            
            $device_ok = false;
            $es_primer_login = empty($employee['device_user_agent']) && empty($employee['device_token']);
            $reset_autorizado = $employee['device_reset_required'] == 1;
            
            if ($es_primer_login || $reset_autorizado) {
                // Primer login o reset autorizado: registrar dispositivo
                $device_token = bin2hex(random_bytes(32));
                
                $update_device = "UPDATE employees 
                                  SET device_user_agent = '$current_user_agent',
                                      device_ip = '$current_ip',
                                      device_token = '$device_token',
                                      device_registered_at = NOW(),
                                      device_reset_required = FALSE
                                  WHERE id = " . $employee['id'];
                $conn->query($update_device);
                
                setcookie('device_token', $device_token, time() + (86400 * 30), '/');
                
                $device_ok = true;
                
            } else {
                // Login recurrente: verificar dispositivo
                $cookie_token = $_COOKIE['device_token'] ?? '';
                $user_agent_ok = ($employee['device_user_agent'] === $current_user_agent);
                $ip_ok = ($employee['device_ip'] === $current_ip);
                $token_ok = ($employee['device_token'] === $cookie_token);
                
                $coincidencias = 0;
                if ($user_agent_ok) $coincidencias++;
                if ($ip_ok) $coincidencias++;
                if ($token_ok) $coincidencias++;
                
                if ($coincidencias >= 2) {
                    $device_ok = true;
                } else {
                    $error = '🚫 <strong>Dispositivo no autorizado</strong><br>
                              <small>Por seguridad, solo puedes fichar desde el celular con el que te registraste. 
                              Si cambiaste de teléfono, pide al administrador que autorice tu nuevo dispositivo.</small>';
                }
            }
            
            if ($device_ok) {
                $_SESSION['worker_logged_in'] = true;
                $_SESSION['worker_id'] = $employee['id'];
                $_SESSION['worker_name'] = $employee['full_name'];
                $_SESSION['worker_number'] = $employee['employee_number'];
                $_SESSION['worker_qr'] = $employee['qr_token'];
                
                $update_sql = "UPDATE employees SET last_login = NOW(), login_attempts = 0 WHERE id = " . $employee['id'];
                $conn->query($update_sql);
                
                header('Location: worker_dashboard.php');
                exit;
            }
            
        } else {
            $update_sql = "UPDATE employees SET login_attempts = login_attempts + 1 WHERE id = " . $employee['id'];
            $conn->query($update_sql);
            $error = '❌ Contraseña incorrecta';
        }
    } else {
        $error = '❌ Usuario no encontrado';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Trabajador</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            max-width: 400px;
            width: 100%;
        }
        .icon { text-align: center; font-size: 48px; margin-bottom: 10px; }
        h1 { text-align: center; color: #1a1a2e; margin-bottom: 8px; font-size: 24px; }
        .subtitle { text-align: center; color: #6b7280; margin-bottom: 30px; font-size: 14px; }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; font-weight: 500; color: #374151; font-size: 13px; }
        input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4); }
        .error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
            line-height: 1.5;
        }
        .footer { text-align: center; margin-top: 20px; font-size: 13px; color: #6b7280; }
        .footer a { color: #667eea; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">👷</div>
        <h1>Iniciar Sesión</h1>
        <p class="subtitle">Accede para fichar con tu QR</p>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>👤 Usuario</label>
                <input type="text" name="username" placeholder="Tu usuario" required autofocus>
            </div>
            <div class="form-group">
                <label>🔑 Contraseña</label>
                <input type="password" name="password" placeholder="Tu contraseña" required>
            </div>
            <button type="submit" class="btn">🔐 Ingresar</button>
        </form>
        
        <div class="footer">
            ¿No tienes cuenta? Solicita tu cuenta al administrador.
        </div>
    </div>
</body>
</html>