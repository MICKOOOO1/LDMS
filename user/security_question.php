<?php
session_start();

// Retrieve session variables (kept intact so the registration data survives)
$pending = $_SESSION['pending_registration'] ?? null;
$id_number = $pending['user_id'] ?? ($_SESSION['id_number'] ?? null);
$first_name = $pending['first_name'] ?? ($_SESSION['first_name'] ?? null);
$alerts = $_SESSION['alerts'] ?? [];
$selected_questions = $_SESSION['selected_questions'] ?? [];

unset($_SESSION['alerts']);

// No staged registration (e.g. direct URL access or expired session) -> back to register.
if (!$pending) {
    $_SESSION['alerts'] = [['type' => 'error', 'message' => 'Please complete the registration form first.']];
    header('Location: register.php');
    exit;
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
        <p class="subtitle">Please select and answer 3 different security questions to protect your account if you forget your password.</p>
        
        <form id="securityForm" action="../server/auth_process.php" method="POST" novalidate>
            <!-- Hidden input to store ID number -->
            <input type="hidden" name="id_number" value="<?= htmlspecialchars($id_number ?? ''); ?>">
            
            <!-- Question 1 Selection and Answer -->
            <div class="question-group">
                <label for="question_1">Question 1 <span class="asterisk">*</span></label>
                <select name="question_1" id="question_1" required class="question-select">
                    <option value="">-- Select a question --</option>
                    <option value="first_pet" <?= ($selected_questions[0] ?? '') === 'first_pet' ? 'selected' : ''; ?>>What was the name of your first pet?</option>
                    <option value="childhood_nickname" <?= ($selected_questions[0] ?? '') === 'childhood_nickname' ? 'selected' : ''; ?>>What was your childhood nickname?</option>
                    <option value="favorite_subject" <?= ($selected_questions[0] ?? '') === 'favorite_subject' ? 'selected' : ''; ?>>What was your favorite subject in school?</option>
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
                    <option value="mothers_maiden_name" <?= ($selected_questions[1] ?? '') === 'mothers_maiden_name' ? 'selected' : ''; ?>>What is your mother's maiden name?</option>
                    <option value="dream_job" <?= ($selected_questions[1] ?? '') === 'dream_job' ? 'selected' : ''; ?>>What was your dream job when you were a child?</option>
                    <option value="first_travel" <?= ($selected_questions[1] ?? '') === 'first_travel' ? 'selected' : ''; ?>>What was the first place you traveled to?</option>
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
                    <option value="first_school" <?= ($selected_questions[2] ?? '') === 'first_school' ? 'selected' : ''; ?>>What was the name of your first school?</option>
                    <option value="childhood_friend" <?= ($selected_questions[2] ?? '') === 'childhood_friend' ? 'selected' : ''; ?>>What was the name of your childhood best friend?</option>
                    <option value="birth_city" <?= ($selected_questions[2] ?? '') === 'birth_city' ? 'selected' : ''; ?>>In which city were you born?</option>
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
<script>
// Prevent double-submit (double-click / double-tap) on final registration.
// Only locks the button when the form is actually valid, so validation
// errors from security.js still let the user fix and resubmit.
document.getElementById('securityForm').addEventListener('submit', function() {
    const btn = this.querySelector('button[name="save_questions_btn"]');
    if (!btn || btn.disabled) return;
    const val = (name) => (this.querySelector(`[name="${name}"]`).value || '').trim();
    const questions = [val('question_1'), val('question_2'), val('question_3')];
    const answers = [val('answer_1'), val('answer_2'), val('answer_3')];
    const valid = questions.every(Boolean)
        && new Set(questions).size === 3
        && answers.every((a) => a.length >= 3)
        && this.checkValidity();
    if (valid) {
        btn.disabled = true;
        btn.textContent = 'Submitting...';
    }
});
</script>
</body>
</html>
