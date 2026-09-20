<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Lector QR - Control de Horas</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #0f0f1a;
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Fondo con gradientes animados */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(ellipse at 20% 30%, rgba(102, 126, 234, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 70%, rgba(118, 75, 162, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 50%, rgba(79, 70, 229, 0.08) 0%, transparent 70%);
            z-index: 0;
            pointer-events: none;
        }

        .container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 480px;
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 28px 24px;
            box-shadow:
                0 25px 50px -12px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* HEADER */
        .header {
            text-align: center;
            margin-bottom: 24px;
        }

        .header .logo-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            font-size: 30px;
            margin-bottom: 12px;
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3); }
            50% { transform: scale(1.05); box-shadow: 0 8px 40px rgba(102, 126, 234, 0.5); }
        }

        .header h1 {
            font-size: 22px;
            font-weight: 700;
            background: linear-gradient(135deg, #a5b4fc 0%, #c4b5fd 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
        }

        .header p {
            color: rgba(255, 255, 255, 0.4);
            font-size: 13px;
            margin-top: 4px;
        }

        /* BADGE DE USUARIO */
        .user-badge {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
            border: 1px solid rgba(102, 126, 234, 0.25);
            padding: 12px 16px;
            border-radius: 14px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .user-badge .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #4ade80;
            box-shadow: 0 0 8px #4ade80;
            animation: blink 2s ease-in-out infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .user-badge strong {
            color: #a5b4fc;
            font-weight: 600;
        }

        /* WARNING SESIÓN */
        .session-warning {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(220, 38, 38, 0.15) 100%);
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 20px;
            border-radius: 14px;
            margin-bottom: 20px;
            text-align: center;
        }

        .session-warning .icon {
            font-size: 40px;
            margin-bottom: 10px;
            display: block;
        }

        .session-warning p {
            color: #fca5a5;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .session-warning a {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .session-warning a:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        /* AVISO NAVEGADOR ANTIGUO */
        .navegador-antiguo {
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid rgba(251, 191, 36, 0.25);
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #fcd34d;
            text-align: center;
        }

        /* ESCÁNER */
        .scanner-wrapper {
            position: relative;
            margin-bottom: 20px;
        }

        #qr-reader {
            width: 100%;
            background: #000;
            border-radius: 18px;
            overflow: hidden;
            border: 2px solid rgba(102, 126, 234, 0.3);
            box-shadow: 0 0 40px rgba(102, 126, 234, 0.15);
            position: relative;
        }

        #qr-reader video {
            width: 100% !important;
            height: auto !important;
            display: block;
            border-radius: 16px;
        }

        /* Marco decorativo sobre el video */
        .scanner-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            border-radius: 18px;
            overflow: hidden;
        }

        .scanner-overlay .corner {
            position: absolute;
            width: 40px;
            height: 40px;
            border: 3px solid #a5b4fc;
            opacity: 0.8;
        }

        .scanner-overlay .corner.tl { top: 12px; left: 12px; border-right: none; border-bottom: none; border-radius: 12px 0 0 0; }
        .scanner-overlay .corner.tr { top: 12px; right: 12px; border-left: none; border-bottom: none; border-radius: 0 12px 0 0; }
        .scanner-overlay .corner.bl { bottom: 12px; left: 12px; border-right: none; border-top: none; border-radius: 0 0 0 12px; }
        .scanner-overlay .corner.br { bottom: 12px; right: 12px; border-left: none; border-top: none; border-radius: 0 0 12px 0; }

        /* Línea de escaneo animada */
        .scan-line {
            position: absolute;
            left: 20px;
            right: 20px;
            height: 2px;
            background: linear-gradient(90deg, transparent, #a5b4fc, transparent);
            box-shadow: 0 0 10px #a5b4fc;
            animation: scanMove 2s ease-in-out infinite;
            opacity: 0.6;
        }

        @keyframes scanMove {
            0%, 100% { top: 20%; }
            50% { top: 80%; }
        }

        /* RESULTADOS */
        #resultados {
            padding: 20px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            text-align: center;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            transition: all 0.3s;
            margin-bottom: 16px;
        }

        #resultados .result-icon {
            font-size: 36px;
            margin-bottom: 8px;
            display: block;
        }

        #resultados.success {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.15) 0%, rgba(22, 163, 74, 0.15) 100%);
            border-color: rgba(34, 197, 94, 0.3);
            color: #4ade80;
        }

        #resultados.error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(220, 38, 38, 0.15) 100%);
            border-color: rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        #resultados.info {
            background: linear-gradient(135deg, rgba(96, 165, 250, 0.15) 0%, rgba(59, 130, 246, 0.15) 100%);
            border-color: rgba(96, 165, 250, 0.3);
            color: #93c5fd;
        }

        .employee-info {
            background: rgba(255, 255, 255, 0.08);
            padding: 10px 16px;
            border-radius: 10px;
            margin-top: 10px;
            font-size: 13px;
            width: 100%;
        }

        .employee-info strong {
            color: #a5b4fc;
        }

        /* BOTONES */
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 15px 20px;
            border: none;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.3s;
            color: white;
            text-decoration: none;
            margin-bottom: 10px;
        }

        .btn:active { transform: scale(0.97); }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.8);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            box-shadow: 0 6px 24px rgba(102, 126, 234, 0.4);
            transform: translateY(-1px);
        }

        /* FOOTER */
        .footer {
            text-align: center;
            color: rgba(255, 255, 255, 0.3);
            font-size: 12px;
            margin-top: 16px;
        }

        /* ESTADO SCANNER INACTIVO */
        .scanner-placeholder {
            padding: 40px 20px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 18px;
            text-align: center;
            border: 2px dashed rgba(255, 255, 255, 0.1);
        }

        .scanner-placeholder .icon {
            font-size: 56px;
            margin-bottom: 12px;
            display: block;
            opacity: 0.6;
        }

        .scanner-placeholder p {
            color: rgba(255, 255, 255, 0.5);
            font-size: 14px;
        }

        /* RESPONSIVE */
        @media (max-width: 480px) {
            body { padding: 16px; }
            .container { padding: 24px 18px; border-radius: 20px; }
            .header .logo-icon { width: 56px; height: 56px; font-size: 26px; }
            .header h1 { font-size: 20px; }
            #resultados { padding: 16px; font-size: 14px; }
            .btn { padding: 14px; font-size: 14px; }
        }
    </style>
</head>
<body>

    <div class="container">

        <!-- HEADER -->
        <div class="header">
            <div class="logo-icon">🎯</div>
            <h1>Fichaje Inteligente</h1>
            <p>Escanea tu QR para registrar asistencia</p>
        </div>

        <!-- SESIÓN -->
        <?php if (!isset($_SESSION['worker_logged_in']) || $_SESSION['worker_logged_in'] !== true): ?>
            <div class="session-warning">
                <span class="icon">🔒</span>
                <p><strong>Sesión no iniciada</strong></p>
                <p style="font-size: 13px;">Debes iniciar sesión para escanear tu QR</p>
                <a href="login_worker.php">🔐 Iniciar Sesión</a>
            </div>
        <?php else: ?>
            <div class="user-badge">
                <span class="dot"></span>
                Escaneando como <strong><?php echo htmlspecialchars($_SESSION['worker_name'] ?? 'Trabajador'); ?></strong>
                (<?php echo htmlspecialchars($_SESSION['worker_number'] ?? ''); ?>)
            </div>
        <?php endif; ?>

        <!-- AVISO NAVEGADOR ANTIGUO -->
        <div id="aviso-navegador"></div>

        <!-- ESCÁNER -->
        <?php if (isset($_SESSION['worker_logged_in']) && $_SESSION['worker_logged_in'] === true): ?>
            <div class="scanner-wrapper">
                <div id="qr-reader"></div>
                <div class="scanner-overlay">
                    <div class="corner tl"></div>
                    <div class="corner tr"></div>
                    <div class="corner bl"></div>
                    <div class="corner br"></div>
                    <div class="scan-line"></div>
                </div>
            </div>
        <?php else: ?>
            <div class="scanner-placeholder">
                <span class="icon">📷</span>
                <p>Inicia sesión para usar el escáner</p>
            </div>
        <?php endif; ?>

        <!-- RESULTADOS -->
        <div id="resultados">
            <span class="result-icon">📷</span>
            Apunta la cámara al código QR
        </div>

        <!-- BOTONES -->
        <?php if (isset($_SESSION['worker_logged_in']) && $_SESSION['worker_logged_in'] === true): ?>
            <button class="btn btn-secondary" onclick="reiniciarEscanner()">
                🔄 Reiniciar Escáner
            </button>
        <?php endif; ?>

        <div class="footer">
            Control de Horas v2.0
        </div>

    </div>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
        // ============================================
        // DETECCIÓN DE CARACTERÍSTICAS
        // ============================================
        var soportaFetch = (typeof fetch === 'function' && typeof Promise !== 'undefined');
        var soportaJSON = (typeof JSON !== 'undefined' && typeof JSON.parse === 'function');
        var soportaXHR = (typeof XMLHttpRequest !== 'undefined');
        var navegadorAntiguo = !soportaFetch || !soportaJSON;

        if (navegadorAntiguo) {
            document.addEventListener('DOMContentLoaded', function() {
                var aviso = document.getElementById('aviso-navegador');
                aviso.className = 'navegador-antiguo';
                aviso.innerHTML = '⚠️ Navegador antiguo detectado. Se usará un método compatible.';
            });
        }

        // ============================================
        // PROCESAR RESPUESTA
        // ============================================
        function procesarRespuesta(data, resultadosDiv) {
            if (data.status === 'success') {
                var mensaje = '<span class="result-icon">✅</span>' + data.message;
                if (data.employee) {
                    mensaje += '<div class="employee-info">👤 <strong>' + data.employee + '</strong> (' + (data.employee_number || 'N/A') + ')</div>';
                }
                resultadosDiv.innerHTML = mensaje;
                resultadosDiv.className = 'success';
            } else {
                resultadosDiv.innerHTML = '<span class="result-icon">❌</span>' + data.message;
                resultadosDiv.className = 'error';
            }

            if (typeof html5QrCodeScanner !== 'undefined' && html5QrCodeScanner) {
                try { html5QrCodeScanner.clear(); } catch(e) {}
            }

            document.getElementById('qr-reader').innerHTML =
                '<div class="scanner-placeholder" style="padding:50px 20px;">' +
                '<span class="icon">✅</span>' +
                '<p style="font-size:16px; color:#4ade80; font-weight:600;">Escaneo completado</p>' +
                '<button onclick="reiniciarEscanner()" class="btn btn-primary" style="margin-top:16px; max-width:200px;">' +
                '🔄 Escanear otro</button></div>';
        }

        // ============================================
        // ENVÍO CON XHR
        // ============================================
        function enviarConXHR(url, resultadosDiv) {
            if (!soportaXHR) {
                resultadosDiv.innerHTML = '<span class="result-icon">❌</span>Navegador no soporta peticiones';
                resultadosDiv.className = 'error';
                return;
            }

            var xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);
            xhr.timeout = 15000;

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var data = JSON.parse(xhr.responseText);
                            procesarRespuesta(data, resultadosDiv);
                        } catch (e) {
                            resultadosDiv.innerHTML = '<span class="result-icon">❌</span>Respuesta no válida';
                            resultadosDiv.className = 'error';
                        }
                    } else if (xhr.status === 0) {
                        resultadosDiv.innerHTML = '<span class="result-icon">📡</span>Error de red';
                        resultadosDiv.className = 'error';
                    } else {
                        resultadosDiv.innerHTML = '<span class="result-icon">❌</span>Error del servidor (' + xhr.status + ')';
                        resultadosDiv.className = 'error';
                    }
                }
            };

            xhr.ontimeout = function() {
                resultadosDiv.innerHTML = '<span class="result-icon">⏱️</span>Tiempo agotado';
                resultadosDiv.className = 'error';
            };

            xhr.onerror = function() {
                resultadosDiv.innerHTML = '<span class="result-icon">📡</span>Error de conexión';
                resultadosDiv.className = 'error';
            };

            xhr.send();
        }

        // ============================================
        // ENVÍO CON FETCH
        // ============================================
        function enviarConFetch(url, resultadosDiv) {
            fetch(url)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    procesarRespuesta(data, resultadosDiv);
                })
                .catch(function(error) {
                    console.error('Fetch error:', error);
                    enviarConXHR(url, resultadosDiv);
                });
        }

        // ============================================
        // ESCÁNER
        // ============================================
        var html5QrCodeScanner;
        var escaneando = true;

        <?php if (!isset($_SESSION['worker_logged_in']) || $_SESSION['worker_logged_in'] !== true): ?>
            // No iniciar escáner si no está logueado
        <?php else: ?>

        function onScanSuccess(decodedText, decodedResult) {
            if (!escaneando) return;
            escaneando = false;

            var resultadosDiv = document.getElementById('resultados');
            resultadosDiv.innerHTML = '<span class="result-icon">⏳</span>Verificando tu QR...';
            resultadosDiv.className = 'info';

            var url = 'register_attendance.php?token=' + encodeURIComponent(decodedText);

            if (soportaFetch) {
                enviarConFetch(url, resultadosDiv);
            } else {
                enviarConXHR(url, resultadosDiv);
            }
        }

        function onScanError(errorMessage) {
            if (errorMessage && errorMessage.indexOf('Permission denied') !== -1) {
                document.getElementById('resultados').innerHTML = '<span class="result-icon">🚫</span>Permiso de cámara denegado';
                document.getElementById('resultados').className = 'error';
            }
        }

        function iniciarEscanner() {
            var readerDiv = document.getElementById('qr-reader');
            readerDiv.innerHTML = '';

            try {
                html5QrCodeScanner = new Html5QrcodeScanner(
                    "qr-reader",
                    {
                        fps: 15,
                        qrbox: { width: 250, height: 250 },
                        aspectRatio: 1.0
                    }
                );

                html5QrCodeScanner.render(onScanSuccess, onScanError);
                escaneando = true;
                document.getElementById('resultados').innerHTML = '<span class="result-icon">📷</span>Apunta la cámara al código QR';
                document.getElementById('resultados').className = 'info';
            } catch (e) {
                document.getElementById('resultados').innerHTML = '<span class="result-icon">❌</span>Error: ' + e.message;
                document.getElementById('resultados').className = 'error';
            }
        }

        function reiniciarEscanner() {
            if (html5QrCodeScanner) {
                try { html5QrCodeScanner.clear(); } catch(e) {}
            }
            iniciarEscanner();
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Html5QrcodeScanner === 'undefined') {
                document.getElementById('resultados').innerHTML = '<span class="result-icon">❌</span>No se pudo cargar el lector QR';
                document.getElementById('resultados').className = 'error';
                return;
            }
            iniciarEscanner();
        });

        <?php endif; ?>
    </script>

</body>
</html>