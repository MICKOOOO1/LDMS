<?php
header('Content-Type: application/json');
require_once "config.php";

$id = $_POST['id_number'] ?? '';

if ($id === '') {
    echo json_encode([
        'available' => false,
        'message' => 'ID cannot be empty.'
    ]);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM users WHERE user_id = ?");
$stmt->bind_param("s", $id);
$stmt->execute();
$stmt->store_result();

$isUsed = $stmt->num_rows > 0;

echo json_encode([
    'available' => !$isUsed,
    'message' => $isUsed ? 'This ID number is already used.' : 'ID number is available.'
]);

$stmt->close();
$conn->close();
exit;
?>
