<?php
require_once "config.php";

$email = $_POST['email'] ?? '';

if ($email === '') {
    echo json_encode([
        'available' => false,
        'message' => 'Email cannot be empty.'
    ]);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

$isTaken = $stmt->num_rows > 0;

echo json_encode([
    'available' => !$isTaken,
    'message' => $isTaken ? 'Email is already registered.' : 'Email is available.'
]);

$stmt->close();
$conn->close();
?>
