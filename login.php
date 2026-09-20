<?php
// login.php
require_once 'config.php';
session_start();

// Credenciales del administrador (desde settings, respaldo admin/123456)
$admin_user = 'admin';
$admin_pass = '123456';
$admin_pass_hash = '';

$set_res = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('admin_user','admin_pass_hash')");
if ($set_res) {
    while ($r = $set_res->fetch_assoc()) {
        $vals[$r['setting_key']] = $r['setting_value'];
    }
    if (!empty($vals['admin_user'])) $admin_user = $vals['admin_user'];
    if (!empty($vals['admin_pass_hash'])) $admin_pass_hash = $vals['admin_pass_hash'];
}

function admin_master_token($user, $pass, $hash) {
    return md5($user . '::' . ($hash !== '' ? $hash : $pass) . '::remember_salt');
}

// Auto-login por cookie "Recordarme"
if (!isset($_SESSION['autenticado']) && isset($_COOKIE['admin_remember'])) {
    if ($_COOKIE['admin_remember'] === admin_master_token($admin_user, $admin_pass, $admin_pass_hash)) {
        $_SESSION['autenticado'] = true;
        $_SESSION['usuario'] = $admin_user;
        header('Location: admin.php');
        exit;
    }
}

// Si ya está autenticado, redirigir al panel
if (isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true) {
    header('Location: admin.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';

    $pass_valida = ($admin_pass_hash !== '') ? password_verify($password, $admin_pass_hash) : ($password === $admin_pass);

    if ($usuario == $admin_user && $pass_valida) {
        $_SESSION['autenticado'] = true;
        $_SESSION['usuario'] = $usuario;
        if (!empty($_POST['remember'])) {
            setcookie('admin_remember', admin_master_token($admin_user, $admin_pass, $admin_pass_hash), time() + (86400 * 30), '/');
        }
        header('Location: admin.php');
        exit;
    } else {
        $error = '❌ Usuario o contraseña incorrectos';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Panel Administrador</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: #0f0f1a;
            position: relative;
            overflow: hidden;
        }

        /* ============================================ */
        /* FONDO ANIMADO CON GRADIENTES */
        /* ============================================ */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(ellipse at 20% 50%, rgba(102, 126, 234, 0.15) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 50%, rgba(118, 75, 162, 0.15) 0%, transparent 60%),
                radial-gradient(ellipse at 50% 100%, rgba(102, 126, 234, 0.08) 0%, transparent 50%);
            z-index: 0;
        }

        /* Partículas decorativas */
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(102, 126, 234, 0.3);
            border-radius: 50%;
            animation: float 20s infinite linear;
        }

        .particle:nth-child(1) { left: 10%; animation-delay: 0s; animation-duration: 25s; }
        .particle:nth-child(2) { left: 20%; animation-delay: 2s; animation-duration: 20s; width: 6px; height: 6px; }
        .particle:nth-child(3) { left: 30%; animation-delay: 4s; animation-duration: 22s; }
        .particle:nth-child(4) { left: 40%; animation-delay: 1s; animation-duration: 18s; width: 3px; height: 3px; }
        .particle:nth-child(5) { left: 50%; animation-delay: 3s; animation-duration: 24s; }
        .particle:nth-child(6) { left: 60%; animation-delay: 5s; animation-duration: 19s; width: 5px; height: 5px; }
        .particle:nth-child(7) { left: 70%; animation-delay: 0.5s; animation-duration: 21s; }
        .particle:nth-child(8) { left: 80%; animation-delay: 2.5s; animation-duration: 23s; width: 4px; height: 4px; }
        .particle:nth-child(9) { left: 90%; animation-delay: 4.5s; animation-duration: 17s; }
        .particle:nth-child(10) { left: 15%; animation-delay: 3.5s; animation-duration: 26s; width: 7px; height: 7px; }
        .particle:nth-child(11) { left: 45%; animation-delay: 1.5s; animation-duration: 20s; }
        .particle:nth-child(12) { left: 75%; animation-delay: 2.8s; animation-duration: 22s; width: 3px; height: 3px; }

        @keyframes float {
            0% { transform: translateY(100vh) scale(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-10vh) scale(1); opacity: 0; }
        }

        /* ============================================ */
        /* CONTENEDOR PRINCIPAL */
        /* ============================================ */
        .login-container {
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 48px 40px 40px;
            width: 100%;
            max-width: 420px;
            animation: slideUp 0.8s ease-out;
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px) scale(0.96);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* ============================================ */
        /* HEADER */
        /* ============================================ */
        .login-header {
            text-align: center;
            margin-bottom: 36px;
        }

        .login-header .logo-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            font-size: 32px;
            margin-bottom: 16px;
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
            transition: transform 0.3s;
        }

        .login-header .logo-icon:hover {
            transform: scale(1.05) rotate(-5deg);
        }

        .login-header h1 {
            color: #ffffff;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }

        .login-header .subtitle {
            color: rgba(255, 255, 255, 0.5);
            font-size: 14px;
            font-weight: 400;
        }

        .login-header .badge {
            display: inline-block;
            margin-top: 10px;
            padding: 4px 14px;
            background: rgba(102, 126, 234, 0.2);
            border: 1px solid rgba(102, 126, 234, 0.2);
            border-radius: 20px;
            color: rgba(255, 255, 255, 0.6);
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* ============================================ */
        /* MENSAJES DE ERROR */
        /* ============================================ */
        .error {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 24px;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            animation: shake 0.5s ease-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20% { transform: translateX(-8px); }
            40% { transform: translateX(8px); }
            60% { transform: translateX(-5px); }
            80% { transform: translateX(5px); }
        }

        /* ============================================ */
        /* FORMULARIO */
        /* ============================================ */
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            color: rgba(255, 255, 255, 0.7);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 6px;
            letter-spacing: 0.3px;
        }

        .form-group .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .form-group .input-icon {
            position: absolute;
            left: 14px;
            color: rgba(255, 255, 255, 0.3);
            font-size: 18px;
            pointer-events: none;
            transition: color 0.3s;
        }

        .form-group input {
            width: 100%;
            padding: 14px 14px 14px 48px;
            background: rgba(255, 255, 255, 0.06);
            border: 1.5px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            color: #ffffff;
            transition: all 0.3s;
            outline: none;
        }

        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.25);
            font-weight: 400;
        }

        .form-group input:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.15);
        }

        .form-group input:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
        }

        .form-group input:focus + .input-icon {
            color: #667eea;
        }

        /* ============================================ */
        /* OPCIONES ADICIONALES */
        /* ============================================ */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .form-options .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: rgba(255, 255, 255, 0.5);
            font-size: 13px;
            cursor: pointer;
        }

        .form-options .remember-me input[type="checkbox"] {
            appearance: none;
            -webkit-appearance: none;
            width: 16px;
            height: 16px;
            background: rgba(255, 255, 255, 0.06);
            border: 1.5px solid rgba(255, 255, 255, 0.15);
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            flex-shrink: 0;
        }

        .form-options .remember-me input[type="checkbox"]:checked {
            background: #667eea;
            border-color: #667eea;
        }

        .form-options .remember-me input[type="checkbox"]:checked::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 11px;
            font-weight: 700;
        }

        .form-options a {
            color: rgba(255, 255, 255, 0.4);
            font-size: 13px;
            text-decoration: none;
            transition: color 0.3s;
        }

        .form-options a:hover {
            color: #667eea;
        }

        /* ============================================ */
        /* BOTÓN */
        /* ============================================ */
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            color: #ffffff;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            letter-spacing: 0.3px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.35);
        }

        .btn-login:active {
            transform: translateY(0) scale(0.98);
        }

        .btn-login .btn-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        /* Efecto de brillo en el botón */
        .btn-login::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            transform: scale(0);
            transition: transform 0.5s;
            pointer-events: none;
        }

        .btn-login:hover::after {
            transform: scale(1);
        }

        /* ============================================ */
        /* FOOTER */
        /* ============================================ */
        .login-footer {
            text-align: center;
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }

        .login-footer p {
            color: rgba(255, 255, 255, 0.3);
            font-size: 12px;
            font-weight: 400;
        }

        .login-footer .version {
            display: inline-block;
            margin-top: 6px;
            padding: 2px 12px;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 12px;
            color: rgba(255, 255, 255, 0.2);
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        /* ============================================ */
        /* RESPONSIVE */
        /* ============================================ */
        @media (max-width: 480px) {
            .login-container {
                padding: 32px 24px 28px;
                border-radius: 20px;
            }

            .login-header .logo-icon {
                width: 60px;
                height: 60px;
                font-size: 28px;
            }

            .login-header h1 {
                font-size: 22px;
            }

            .form-group input {
                padding: 12px 12px 12px 44px;
                font-size: 14px;
            }

            .form-options {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            .btn-login {
                padding: 14px;
                font-size: 15px;
            }
        }

        @media (max-width: 360px) {
            .login-container {
                padding: 24px 16px 20px;
            }

            .login-header .logo-icon {
                width: 50px;
                height: 50px;
                font-size: 24px;
            }

            .login-header h1 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>

    <!-- PARTÍCULAS DECORATIVAS -->
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <!-- ============================================ -->
    <!-- LOGIN CONTAINER -->
    <!-- ============================================ -->
    <div class="login-container">

        <!-- HEADER -->
        <div class="login-header">
            <div class="logo-icon">🔐</div>
            <h1>Panel Administrador</h1>
            <p class="subtitle">Control de Horas · Sistema de Fichaje</p>
            <span class="badge">🚀 Acceso Restringido</span>
        </div>

        <!-- MENSAJE DE ÉXITO (post-reseteo) -->
        <?php if (isset($_GET['msg'])): ?>
            <div class="error" style="background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d;">
                <span>✅</span>
                <?php echo htmlspecialchars($_GET['msg']); ?>
            </div>
        <?php endif; ?>

        <!-- MENSAJE DE ERROR -->
        <?php if ($error): ?>
            <div class="error">
                <span>⚠️</span>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- FORMULARIO -->
        <form method="POST" action="">

            <div class="form-group">
                <label>👤 Usuario</label>
                <div class="input-wrapper">
                    <span class="input-icon">👤</span>
                    <input type="text" name="usuario" placeholder="Ingresa tu usuario" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label>🔑 Contraseña</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔑</span>
                    <input type="password" name="password" placeholder="Ingresa tu contraseña" required>
                </div>
            </div>

            <!-- OPCIONES -->
            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember">
                    Recordarme
                </label>
                <a href="reset_admin_password.php" onclick="return confirm('¿Restablecer la contraseña del administrador a 123456?');">¿Olvidaste tu contraseña?</a>
            </div>

            <!-- BOTÓN -->
            <button type="submit" class="btn-login">
                <span class="btn-content">
                    <span>🚀</span>
                    Ingresar al Panel
                </span>
            </button>

        </form>

        <!-- FOOTER -->
        <div class="login-footer">
            <p>Sistema de Control de Horas</p>
            <span class="version">v2.0 · Administración</span>
        </div>

    </div>

</body>
</html>