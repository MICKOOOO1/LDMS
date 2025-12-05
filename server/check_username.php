<?php
header('Content-Type: application/json');
require_once "config.php";

$username = $_POST['username'] ?? '';

if ($username === '') {
    echo json_encode([
        'available' => false,
        'message' => 'Username cannot be empty.'
    ]);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();

$isTaken = $stmt->num_rows > 0;

echo json_encode([
    'available' => !$isTaken,
    'message' => $isTaken ? 'Username is already taken.' : 'Username is available.'
]);

$stmt->close();
$conn->close();
exit;
?>
