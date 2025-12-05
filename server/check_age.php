<?php
header('Content-Type: application/json');

$dob = $_POST['dob'] ?? '';

if ($dob === '') {
    echo json_encode([
        'valid' => false,
        'message' => 'Date of birth cannot be empty.'
    ]);
    exit;
}

try {
    $birthdate = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birthdate)->y;
} catch (Throwable $e) {
    echo json_encode([
        'valid' => false,
        'message' => 'Invalid date format.'
    ]);
    exit;
}

if ($age < 18) {
    echo json_encode([
        'valid' => false,
        'age' => $age,
        'message' => 'You must be at least 18 years old.'
    ]);
    exit;
}

echo json_encode([
    'valid' => true,
    'age' => $age,
    'message' => 'Valid age.'
]);
exit;
?>
