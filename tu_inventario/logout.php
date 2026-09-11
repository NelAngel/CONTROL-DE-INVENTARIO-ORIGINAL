<?php
require_once 'config.php';
logAudit('LOGOUT', 'usuarios', $_SESSION['user_id'] ?? null);
session_destroy();
header('Location: login.php');
exit();