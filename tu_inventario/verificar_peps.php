<?php
require_once 'config.php';

echo "<h2>🔍 Verificación del Sistema PEPS</h2>";

$verificacion = verificarInstalacionPEPS();

if ($verificacion['instalado']) {
    echo "<p style='color: green;'>✅ Sistema PEPS instalado correctamente</p>";
    
    // Verificar lotes
    $pdo = getConnection();
    $lotes = $pdo->query("SELECT COUNT(*) as total FROM lotes")->fetch()['total'];
    $consumos = $pdo->query("SELECT COUNT(*) as total FROM consumo_lotes")->fetch()['total'];
    
    echo "<ul>";
    echo "<li>Lotes registrados: <strong>$lotes</strong></li>";
    echo "<li>Consumos registrados: <strong>$consumos</strong></li>";
    echo "<li>Valor del inventario PEPS: <strong>$" . number_format(getValorInventarioPEPS(), 2) . "</strong></li>";
    echo "<li>Ganancia total: <strong>$" . number_format(getGananciaTotalPEPS(), 2) . "</strong></li>";
    echo "</ul>";
    
} else {
    echo "<p style='color: red;'>❌ Faltan las siguientes tablas: " . implode(', ', $verificacion['faltantes']) . "</p>";
    echo "<p>Ejecuta el SQL de instalación PEPS</p>";
}
?>