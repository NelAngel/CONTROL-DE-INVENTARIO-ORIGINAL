<?php
require_once 'config.php';
if (!isAuthenticated()) { http_response_code(401); exit(); }

$id = $_GET['id'] ?? 0;
$pdo = getConnection();
$stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
$stmt->execute([$id]);
$producto = $stmt->fetch();

header('Content-Type: application/json');
echo json_encode($producto);