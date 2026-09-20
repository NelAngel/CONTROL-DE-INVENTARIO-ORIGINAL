<?php
// generate_qr.php - Generar código QR para empleado
require_once 'config.php';
session_start();

if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT full_name, employee_number, qr_token FROM employees WHERE id = $id";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $employee = $result->fetch_assoc();
        $token = $employee['qr_token'];
        $name = $employee['full_name'];
        $number = $employee['employee_number'];
        
        // Usar API gratuita para generar QR
        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($token);
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>QR - <?php echo $name; ?></title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { 
                    font-family: Arial; 
                    text-align: center; 
                    padding: 40px; 
                    background: #f0f2f5;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    margin: 0;
                }
                .qr-card {
                    background: white;
                    padding: 40px;
                    border-radius: 16px;
                    max-width: 450px;
                    width: 100%;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
                }
                h2 { 
                    color: #333; 
                    margin-bottom: 5px;
                }
                .subtitle {
                    color: #666;
                    font-size: 14px;
                    margin-bottom: 20px;
                }
                .employee-info {
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 8px;
                    margin: 15px 0;
                }
                .employee-info p {
                    margin: 5px 0;
                    color: #333;
                }
                .employee-info strong {
                    color: #667eea;
                }
                img {
                    max-width: 100%;
                    border: 2px solid #e0e0e0;
                    border-radius: 8px;
                    padding: 10px;
                    background: white;
                }
                .token {
                    background: #f8f9fa;
                    padding: 10px;
                    border-radius: 4px;
                    font-family: monospace;
                    font-size: 12px;
                    margin: 10px 0;
                    word-break: break-all;
                }
                .btn {
                    display: inline-block;
                    padding: 12px 24px;
                    background: #007bff;
                    color: white;
                    text-decoration: none;
                    border-radius: 8px;
                    margin-top: 15px;
                    transition: background 0.3s;
                    border: none;
                    cursor: pointer;
                }
                .btn:hover { background: #0056b3; }
                .btn-success { background: #28a745; }
                .btn-success:hover { background: #218838; }
                .instructions {
                    text-align: left;
                    background: #fff3cd;
                    padding: 15px;
                    border-radius: 8px;
                    margin: 15px 0;
                    font-size: 14px;
                    color: #856404;
                }
                .footer {
                    margin-top: 20px;
                    font-size: 12px;
                    color: #999;
                }
                @media print {
                    .no-print { display: none; }
                    body { background: white; padding: 20px; }
                    .qr-card { box-shadow: none; border: 1px solid #ddd; }
                }
            </style>
        </head>
        <body>
            <div class="qr-card">
                <h2>📱 Código QR</h2>
                <p class="subtitle">Control de Horas - Fichaje</p>
                
                <div class="employee-info">
                    <p><strong>Empleado:</strong> <?php echo $name; ?></p>
                    <p><strong>Número:</strong> <?php echo $number; ?></p>
                </div>
                
                <img src="<?php echo $qr_url; ?>" alt="QR Code">
                
                <div class="token">
                    <strong>Token:</strong> <?php echo $token; ?>
                </div>
                
                <div class="instructions">
                    <strong>📌 Instrucciones:</strong><br>
                    1. Abre <strong>http://<?php echo $_SERVER['SERVER_ADDR']; ?>/tu_proyecto/</strong> desde tu móvil<br>
                    2. Haz clic en "Escanear QR"<br>
                    3. Apunta la cámara a este código<br>
                    4. ¡Registrará tu entrada o salida!
                </div>
                
                <div class="no-print">
                    <button onclick="window.print()" class="btn btn-success">🖨️ Imprimir QR</button>
                    <a href="admin.php" class="btn">⬅️ Volver al Panel</a>
                </div>
                
                <div class="footer">
                    Sistema de Control de Horas v2.0
                </div>
            </div>
        </body>
        </html>
        <?php
    } else {
        echo "Empleado no encontrado.";
    }
} else {
    echo "ID no especificado.";
}
$conn->close();
?>