<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Horas - Inicio</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #0f0f1a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Fondo con gradientes animados */
        body::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background:
                radial-gradient(ellipse at 20% 30%, rgba(102, 126, 234, 0.2) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 70%, rgba(118, 75, 162, 0.2) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 50%, rgba(79, 70, 229, 0.1) 0%, transparent 70%);
            z-index: 0;
            animation: gradientShift 15s ease-in-out infinite;
        }

        @keyframes gradientShift {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        /* Partículas decorativas */
        .particles {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(165, 180, 252, 0.4);
            border-radius: 50%;
            animation: float 20s infinite linear;
        }

        .particle:nth-child(1) { left: 10%; animation-delay: 0s; animation-duration: 25s; }
        .particle:nth-child(2) { left: 20%; animation-delay: 2s; animation-duration: 20s; width: 6px; height: 6px; }
        .particle:nth-child(3) { left: 35%; animation-delay: 4s; animation-duration: 22s; }
        .particle:nth-child(4) { left: 50%; animation-delay: 1s; animation-duration: 18s; width: 3px; height: 3px; }
        .particle:nth-child(5) { left: 65%; animation-delay: 3s; animation-duration: 24s; }
        .particle:nth-child(6) { left: 80%; animation-delay: 5s; animation-duration: 19s; width: 5px; height: 5px; }
        .particle:nth-child(7) { left: 90%; animation-delay: 0.5s; animation-duration: 21s; }
        .particle:nth-child(8) { left: 45%; animation-delay: 2.5s; animation-duration: 23s; width: 4px; height: 4px; }

        @keyframes float {
            0% { transform: translateY(100vh) scale(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-10vh) scale(1); opacity: 0; }
        }

        /* Contenedor principal */
        .container {
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 48px 36px 36px;
            max-width: 420px;
            width: 100%;
            box-shadow:
                0 25px 50px -12px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
            text-align: center;
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px) scale(0.96); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* LOGO */
        .logo-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 88px;
            height: 88px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 24px;
            font-size: 44px;
            margin-bottom: 20px;
            box-shadow: 0 12px 40px rgba(102, 126, 234, 0.4);
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); box-shadow: 0 12px 40px rgba(102, 126, 234, 0.4); }
            50% { transform: scale(1.05); box-shadow: 0 16px 50px rgba(102, 126, 234, 0.6); }
        }

        h1 {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, #a5b4fc 0%, #c4b5fd 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
        }

        .subtitle {
            color: rgba(255, 255, 255, 0.5);
            font-size: 14px;
            margin-bottom: 32px;
        }

        .badge {
            display: inline-block;
            margin-bottom: 28px;
            padding: 4px 14px;
            background: rgba(102, 126, 234, 0.15);
            border: 1px solid rgba(102, 126, 234, 0.2);
            border-radius: 20px;
            color: rgba(165, 180, 252, 0.9);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* BOTONES */
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 18px 20px;
            color: white;
            text-decoration: none;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            margin: 12px 0;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn:active {
            transform: translateY(0) scale(0.98);
        }

        .btn-scanner {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            box-shadow: 0 6px 20px rgba(34, 197, 94, 0.3);
        }

        .btn-scanner:hover {
            box-shadow: 0 10px 30px rgba(34, 197, 94, 0.4);
        }

        .btn-admin {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.3);
        }

        .btn-admin:hover {
            box-shadow: 0 10px 30px rgba(245, 158, 11, 0.4);
        }

        .btn .icon {
            font-size: 20px;
        }

        .btn .label {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            text-align: left;
        }

        .btn .label small {
            font-size: 11px;
            font-weight: 400;
            opacity: 0.85;
            margin-top: 2px;
        }

        /* INFO */
        .info-box {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 14px;
            padding: 16px;
            margin-top: 24px;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.6);
            line-height: 1.6;
        }

        .info-box .info-icon {
            display: inline-block;
            margin-right: 4px;
        }

        .clock {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 16px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.5);
            font-family: 'Courier New', monospace;
            font-weight: 500;
        }

        /* FOOTER */
        .footer {
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            font-size: 11px;
            color: rgba(255, 255, 255, 0.3);
            letter-spacing: 0.5px;
        }

        /* RESPONSIVE */
        @media (max-width: 480px) {
            .container {
                padding: 36px 24px 28px;
                border-radius: 20px;
            }
            .logo-icon {
                width: 72px;
                height: 72px;
                font-size: 36px;
            }
            h1 { font-size: 24px; }
            .btn { padding: 16px 18px; font-size: 15px; }
            .btn .icon { font-size: 18px; }
        }
    </style>
</head>
<body>

    <!-- PARTÍCULAS -->
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <!-- CONTENEDOR -->
    <div class="container">

        <!-- LOGO -->
        <div class="logo-icon">⏰</div>

        <!-- TÍTULO -->
        <h1>Control de Horas</h1>
        <p class="subtitle">Sistema de fichaje por código QR</p>
        <span class="badge">🚀 Acceso rápido</span>

        <!-- BOTÓN: ESCANEAR QR -->
        <a href="scanner.php" class="btn btn-scanner">
            <span class="icon">📷</span>
            <span class="label">
                Escanear QR
                <small>Registra tu entrada o salida</small>
            </span>
        </a>

        <!-- BOTÓN: PANEL ADMIN -->
        <a href="login.php" class="btn btn-admin">
            <span class="icon">⚙️</span>
            <span class="label">
                Panel Administrador
                <small>Acceso restringido</small>
            </span>
        </a>

        <!-- INFO -->
        <div class="info-box">
            <span class="info-icon">📱</span>
            Escanea tu código QR desde tu celular para registrar tu entrada o salida.
        </div>

        <!-- RELOJ -->
        <div class="clock">
            🕐 <?php echo date('d/m/Y - H:i:s'); ?>
        </div>

        <!-- FOOTER -->
        <div class="footer">
            Sistema de Control de Horas · v2.0
        </div>

    </div>

</body>
</html>