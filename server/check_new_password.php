<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

$response = [
    'valid' => false,
    'message' => 'Invalid request.'
];

$user_id = $_SESSION['change_password_user_id'] ?? '';
$new_password = $_POST['password'] ?? '';

if ($user_id === '' || $new_password === '') {
    $response['message'] = 'Missing data for password check.';
    echo json_encode($response);
    exit;
}

$stmt = $conn->prepare('SELECT password_hash FROM users WHERE user_id = ?');
$stmt->bind_param('s', $user_id);
$stmt->execute();
$stmt->bind_result($current_hash);

if ($stmt->fetch()) {
    if (password_verify($new_password, $current_hash)) {
        $response['valid'] = false;
        $response['message'] = "New Password can't be the current password.";
    } else {
        $response['valid'] = true;
        $response['message'] = '';
    }
} else {
    $response['message'] = 'Unable to verify current password.';
}

$stmt->close();

echo json_encode($response);
