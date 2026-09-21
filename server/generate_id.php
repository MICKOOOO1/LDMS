<?php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'admin_helpers.php';

$role = $_POST['role'] ?? 'user';

if (!in_array($role, ['user', 'admin', 'super_admin'], true)) {
    $role = 'user';
}

$id = generate_next_id($conn, $role);

echo json_encode(['id' => $id]);
$conn->close();