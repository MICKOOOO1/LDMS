<?php
session_start();

// Retrieve session variables or set defaults
$id_number = $_SESSION['id_number'] ?? null;
$first_name = $_SESSION['first_name'] ?? null;
$alerts = $_SESSION['alerts'] ?? [];
$selected_questions = $_SESSION['selected_questions'] ?? [];

// Clear session variables
session_unset();
if ($id_number !== null) {
    $_SESSION['id_number'] = $id_number;
    $_SESSION['first_name'] = $first_name;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Questions - CSUCC Ladies Dormitory Management System</title>
    <link rel="stylesheet" href="../css/security_question.css">
    <link href="https://cdn.boxicons.com/fonts/basic/boxicons.min.css" rel="stylesheet">
</head>
<body>
<header class="user-auth">
    <a href="index.php" class="logo">
        <img src="../assets/imgs/logo.png" alt="CSUCC Logo">
        CSUCC LDMS
    </a>
    <nav>
        <a href="index.php" class="login-btn-modal">Home</a>
        <a href="login.php" class="login-btn-modal">Login</a>
    </nav>
</header>

<?php if (!empty($alerts)): ?>
<div class="alert-box">
    <?php foreach ($alerts as $alert): ?>
        <div class="alert <?= htmlspecialchars($alert['type']); ?>">
            <i class='bx <?= $alert['type'] === 'success' ? 'bxs-check-circle' : 'bxs-x-circle'; ?>'></i>
            <span><?= htmlspecialchars($alert['message']); ?></span>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="auth-modal show slide expanded">
    <div class="form-box security-questions">
        <h2>Set Your Authentication Questions</h2>
        <p class="subtitle">Please select and answer 3 security questions to protect your account if ever you forget your password</p>
        
        <form id="securityForm" action="../server/auth_process.php" method="POST" novalidate>
            <!-- Hidden input to store ID number -->
            <input type="hidden" name="id_number" value="<?= htmlspecialchars($id_number ?? ''); ?>">
            
            <!-- Question 1 Selection and Answer -->
            <div class="question-group">
                <label for="question_1">Question 1 <span class="asterisk">*</span></label>
                <select name="question_1" id="question_1" required class="question-select">
                    <option value="">-- Select a question --</option>
                    <option value="mothers_maiden_name">What is your mother's maiden name?</option>
                    <option value="first_school">What was the name of your first school?</option>
                    <option value="first_pet">What is the name of your first pet?</option>
                    <option value="birth_city">In which city were you born?</option>
                    <option value="favorite_media">What is your favorite book, movie, or song?</option>
                    <option value="childhood_friend">What was the name of your childhood best friend?</option>
                </select>
                <small class="input-message"></small>
            </div>

            <div class="answer-group input-box">
                <label for="answer_1">Answer <span class="asterisk">*</span></label>
                <input type="password" name="answer_1" id="answer_1" placeholder="Enter your answer" required>
                <small class="input-message"></small>
                <i class='bx bx-eye-slash'></i>
            </div>

            <!-- Question 2 Selection and Answer -->
            <div class="question-group">
                <label for="question_2">Question 2 <span class="asterisk">*</span></label>
                <select name="question_2" id="question_2" required class="question-select">
                    <option value="">-- Select a question --</option>
                    <option value="mothers_maiden_name">What is your mother's maiden name?</option>
                    <option value="first_school">What was the name of your first school?</option>
                    <option value="first_pet">What is the name of your first pet?</option>
                    <option value="birth_city">In which city were you born?</option>
                    <option value="favorite_media">What is your favorite book, movie, or song?</option>
                    <option value="childhood_friend">What was the name of your childhood best friend?</option>
                </select>
                <small class="input-message"></small>
            </div>

            <div class="answer-group input-box">
                <label for="answer_2">Answer <span class="asterisk">*</span></label>
                <input type="password" name="answer_2" id="answer_2" placeholder="Enter your answer" required>
                <small class="input-message"></small>
                <i class='bx bx-eye-slash'></i>
            </div>

            <!-- Question 3 Selection and Answer -->
            <div class="question-group">
                <label for="question_3">Question 3 <span class="asterisk">*</span></label>
                <select name="question_3" id="question_3" required class="question-select">
                    <option value="">-- Select a question --</option>
                    <option value="mothers_maiden_name">What is your mother's maiden name?</option>
                    <option value="first_school">What was the name of your first school?</option>
                    <option value="first_pet">What is the name of your first pet?</option>
                    <option value="birth_city">In which city were you born?</option>
                    <option value="favorite_media">What is your favorite book, movie, or song?</option>
                    <option value="childhood_friend">What was the name of your childhood best friend?</option>
                </select>
                <small class="input-message"></small>
            </div>

            <div class="answer-group input-box">
                <label for="answer_3">Answer <span class="asterisk">*</span></label>
                <input type="password" name="answer_3" id="answer_3" placeholder="Enter your answer" required>
                <small class="input-message"></small>
                <i class='bx bx-eye-slash'></i>
            </div>

            <!-- Action Buttons -->
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="window.location.href='register.php'">Back</button>
                <button type="submit" name="save_questions_btn" class="btn btn-primary">Register</button>
            </div>
        </form>
    </div>
</div>

<footer>
    <p>&copy; 2025 Caraga State University Cabadbaran Campus. All rights reserved.</p>
</footer>
<script src="../javascript/security.js"></script>
<script src="../javascript/script.js"></script>
</body>
</html>
