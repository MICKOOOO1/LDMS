<?php

session_start();
require '../server/config.php';

if (!empty($_SESSION['login_log_id'])) {
	$stmt = $conn->prepare("UPDATE login_logs SET time_out = NOW() WHERE id = ? AND time_out IS NULL");
	$stmt->bind_param("i", $_SESSION['login_log_id']);
	$stmt->execute();
	$stmt->close();
}

session_unset();
session_destroy();
header('Location: index.php');
exit();

?>