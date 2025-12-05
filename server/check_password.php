<?php
if (isset($_POST['password'])) {
    $password = $_POST['password'];

    $length = strlen($password) >= 8;
    $upper  = preg_match('@[A-Z]@', $password);
    $lower  = preg_match('@[a-z]@', $password);
    $number = preg_match('@[0-9]@', $password);
    $special= preg_match('@[^\w]@', $password);

    if ($length && $upper && $lower && $number && $special) {
        echo "strong";
    } else {
        echo "weak";
    }
}
?>
