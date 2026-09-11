<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? AND activo = 1");
    $stmt->execute([$usuario]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['nombre_completo'];
        $_SESSION['user_rol'] = $user['rol'];
        $_SESSION['user_email'] = $user['email'];
        
        logAudit('LOGIN', 'usuarios', $user['id']);
        
        header('Location: index.php');
        exit();
    } else {
        $error = "Usuario o contraseña incorrectos";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* ===== ESTILOS GLOBALES ===== */
        * {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f2f5;
            padding: 20px;
        }
        
        /* ===== CONTENEDOR PRINCIPAL ===== */
        .login-wrapper {
            display: flex;
            width: 100%;
            max-width: 1100px;
            min-height: 600px;
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        /* ===== LADO IZQUIERDO - FORMULARIO ===== */
        .login-form {
            flex: 1;
            padding: 50px 45px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-form .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 35px;
        }
        
        .login-form .logo .icon-box {
            width: 48px;
            height: 48px;
            background: #4f46e5;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.6rem;
            box-shadow: 0 8px 24px rgba(79, 70, 229, 0.3);
        }
        
        .login-form .logo h2 {
            font-weight: 800;
            font-size: 1.6rem;
            color: #1a2035;
            margin: 0;
        }
        
        .login-form .logo h2 span {
            color: #4f46e5;
        }
        
        .login-form .welcome-text {
            margin-bottom: 30px;
        }
        
        .login-form .welcome-text h3 {
            font-weight: 700;
            font-size: 1.5rem;
            color: #1a2035;
            margin-bottom: 6px;
        }
        
        .login-form .welcome-text p {
            color: #6b7280;
            font-size: 0.95rem;
            margin: 0;
        }
        
        /* ===== FORMULARIO ===== */
        .login-form .form-group {
            margin-bottom: 20px;
        }
        
        .login-form .form-group label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #374151;
            margin-bottom: 6px;
            display: block;
        }
        
        .login-form .form-group .input-group-custom {
            position: relative;
        }
        
        .login-form .form-group .input-group-custom .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 1.1rem;
            transition: color 0.3s;
        }
        
        .login-form .form-group .input-group-custom .form-control {
            padding: 12px 14px 12px 45px;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            font-size: 0.95rem;
            transition: all 0.3s;
            height: 52px;
            background: #fafbfc;
        }
        
        .login-form .form-group .input-group-custom .form-control:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
            background: white;
        }
        
        .login-form .form-group .input-group-custom .form-control:focus + .input-icon {
            color: #4f46e5;
        }
        
        .login-form .form-group .input-group-custom .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            font-size: 1.1rem;
            padding: 0;
        }
        
        .login-form .form-group .input-group-custom .toggle-password:hover {
            color: #4f46e5;
        }
        
        /* ===== OPCIONES ===== */
        .login-form .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .login-form .form-options .form-check {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .login-form .form-options .form-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #4f46e5;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .login-form .form-options .form-check label {
            font-size: 0.85rem;
            color: #6b7280;
            cursor: pointer;
            margin: 0;
        }
        
        .login-form .form-options a {
            font-size: 0.85rem;
            color: #4f46e5;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-form .form-options a:hover {
            text-decoration: underline;
        }
        
        /* ===== BOTÓN ===== */
        .login-form .btn-login {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: #4f46e5;
            color: white;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            height: 52px;
        }
        
        .login-form .btn-login:hover {
            background: #4338ca;
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(79, 70, 229, 0.35);
        }
        
        .login-form .btn-login:active {
            transform: translateY(0);
        }
        
        /* ===== CREDENCIALES POR DEFECTO ===== */
        .login-form .default-credentials {
            margin-top: 24px;
            padding: 16px 20px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px dashed #d1d5db;
            text-align: center;
        }
        
        .login-form .default-credentials small {
            color: #6b7280;
            font-size: 0.8rem;
        }
        
        .login-form .default-credentials .credential-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #e5e7eb;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #374151;
            margin: 0 3px;
        }
        
        .login-form .default-credentials .credential-badge i {
            font-size: 0.7rem;
        }
        
        /* ===== LADO DERECHO - BANNER ===== */
        .login-banner {
            flex: 1;
            background: linear-gradient(145deg, #1a2035 0%, #2d3748 100%);
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
            min-height: 500px;
        }
        
        .login-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 500px;
            height: 500px;
            background: rgba(79, 70, 229, 0.08);
            border-radius: 50%;
        }
        
        .login-banner::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: -20%;
            width: 400px;
            height: 400px;
            background: rgba(79, 70, 229, 0.05);
            border-radius: 50%;
        }
        
        .login-banner .banner-content {
            position: relative;
            z-index: 1;
        }
        
        .login-banner .banner-icon {
            font-size: 4rem;
            color: rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }
        
        .login-banner h2 {
            color: white;
            font-weight: 800;
            font-size: 2rem;
            margin-bottom: 12px;
        }
        
        .login-banner h2 span {
            color: #818cf8;
        }
        
        .login-banner p {
            color: rgba(255, 255, 255, 0.6);
            font-size: 1rem;
            line-height: 1.7;
            margin-bottom: 30px;
            max-width: 90%;
        }
        
        .login-banner .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .login-banner .feature-list li {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255, 255, 255, 0.75);
            font-size: 0.9rem;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .login-banner .feature-list li:last-child {
            border-bottom: none;
        }
        
        .login-banner .feature-list li i {
            color: #818cf8;
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
        }
        
        .login-banner .banner-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            color: rgba(255, 255, 255, 0.3);
            font-size: 0.75rem;
            text-align: center;
        }
        
        /* ===== ALERTA DE ERROR ===== */
        .alert-custom {
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 20px;
            border: none;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
        }
        
        .alert-custom.alert-danger {
            background: #fef2f2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }
        
        .alert-custom i {
            font-size: 1.2rem;
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .login-banner {
                display: none;
            }
            
            .login-wrapper {
                max-width: 480px;
                min-height: auto;
            }
            
            .login-form {
                padding: 40px 30px;
            }
        }
        
        @media (max-width: 576px) {
            .login-form {
                padding: 30px 20px;
            }
            
            .login-form .logo h2 {
                font-size: 1.3rem;
            }
            
            .login-form .welcome-text h3 {
                font-size: 1.2rem;
            }
            
            .login-form .default-credentials {
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- ===== LADO IZQUIERDO - FORMULARIO ===== -->
        <div class="login-form">
            <!-- Logo -->
            <div class="logo">
                <div class="icon-box">
                    <i class="bi bi-box-seam"></i>
                </div>
                <h2>Inventario<span>Pro</span></h2>
            </div>
            
            <!-- Texto de bienvenida -->
            <div class="welcome-text">
                <h3>¡Bienvenido de nuevo!</h3>
                <p>Ingresa tus credenciales para acceder al sistema</p>
            </div>
            
            <!-- Mensaje de error -->
            <?php if (isset($error)): ?>
                <div class="alert-custom alert-danger">
                    <i class="bi bi-exclamation-circle"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <!-- Formulario -->
            <form method="POST">
                <div class="form-group">
                    <label for="usuario"><i class="bi bi-person"></i> Usuario</label>
                    <div class="input-group-custom">
                        <input type="text" name="usuario" id="usuario" class="form-control" placeholder="Ingresa tu usuario" required autofocus>
                        <i class="bi bi-person input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="bi bi-lock"></i> Contraseña</label>
                    <div class="input-group-custom">
                        <input type="password" name="password" id="password" class="form-control" placeholder="Ingresa tu contraseña" required>
                        <i class="bi bi-lock input-icon"></i>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-options">
                    <div class="form-check">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Recordarme</label>
                    </div>
                    <a href="#">¿Olvidaste tu contraseña?</a>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Iniciar Sesión
                </button>
            </form>
            
            <!-- Credenciales por defecto -->
            <div class="default-credentials">
                <small>
                    <i class="bi bi-info-circle"></i>
                    Credenciales de prueba:
                    <span class="credential-badge"><i class="bi bi-person"></i> admin</span>
                    <span class="credential-badge"><i class="bi bi-key"></i> admin123</span>
                </small>
            </div>
        </div>
        
        <!-- ===== LADO DERECHO - BANNER ===== -->
        <div class="login-banner">
            <div class="banner-content">
                <div class="banner-icon">
                    <i class="bi bi-box-seam"></i>
                </div>
                
                <h2>Sistema de <span>Inventario</span></h2>
                
                <p>
                    Gestiona tu inventario de manera eficiente con control total de 
                    productos, movimientos, proveedores y más.
                </p>
                
                <ul class="feature-list">
                    <li>
                        <i class="bi bi-check-circle-fill"></i>
                        Control de stock en tiempo real
                    </li>
                    <li>
                        <i class="bi bi-check-circle-fill"></i>
                        Registro de entradas y salidas
                    </li>
                    <li>
                        <i class="bi bi-check-circle-fill"></i>
                        Reportes y análisis detallados
                    </li>
                    <li>
                        <i class="bi bi-check-circle-fill"></i>
                        Múltiples usuarios con roles
                    </li>
                    <li>
                        <i class="bi bi-check-circle-fill"></i>
                        Auditoría completa del sistema
                    </li>
                </ul>
                
                <div class="banner-footer">
                    <i class="bi bi-shield-check"></i>
                    Sistema seguro · Todos los derechos reservados
                </div>
            </div>
        </div>
    </div>

    <!-- ===== SCRIPT PARA MOSTRAR/OCULTAR CONTRASEÑA ===== -->
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'bi bi-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'bi bi-eye';
            }
        }
        
        // Detectar tecla Enter para enviar el formulario
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const form = document.querySelector('form');
                if (form) {
                    form.submit();
                }
            }
        });
    </script>
</body>
</html>