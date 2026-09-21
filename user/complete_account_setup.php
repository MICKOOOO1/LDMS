<?php
session_start();
require '../server/config.php';

$setupUserId = (int)($_SESSION['activation_setup_user_id'] ?? 0);
if (!$setupUserId) {
    $_SESSION['alerts'] = [['type' => 'error', 'message' => 'Setup session expired. Please log in again.']];
    header('Location: login.php');
    exit;
}

$alerts = $_SESSION['alerts'] ?? [];
unset($_SESSION['alerts']);

$stmt = $conn->prepare("SELECT user_id, first_name, middle_name, last_name, extension_name, dob, age, sex, email, username, purok_street, barangay, city_municipality, province, country, zip_code FROM users WHERE id = ? AND account_status = 'approved'");
$stmt->bind_param("i", $setupUserId);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$account) {
    unset($_SESSION['activation_setup_user_id']);
    $_SESSION['alerts'] = [['type' => 'error', 'message' => 'Account setup is unavailable. Please log in again.']];
    header('Location: login.php');
    exit;
}

function setup_display_value(array $account, string $field): string {
    $placeholderAddress = [
        'purok_street' => 'Campus',
        'barangay' => 'CSUCC',
        'city_municipality' => 'Cabadbaran',
        'province' => 'Agusan Del Norte',
        'country' => 'Philippines',
        'zip_code' => '8605',
    ];

    if (isset($placeholderAddress[$field]) && ($account[$field] ?? '') === $placeholderAddress[$field]) {
        return '';
    }

    return $account[$field] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Account Setup - CSUCC LDMS</title>
    <link rel="stylesheet" href="../css/home.css">
    <link href="https://cdn.boxicons.com/fonts/basic/boxicons.min.css" rel="stylesheet">
</head>
<body class="user-account-page">
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

<main class="user-account-main">
    <section class="user-account-card setup-card">
        <?php if (!empty($alerts)): ?>
            <div class="user-alerts" role="status" aria-live="polite">
                <?php foreach ($alerts as $alert): ?>
                    <div class="user-alert <?= htmlspecialchars($alert['type']); ?>">
                        <span><?= htmlspecialchars($alert['message']); ?></span>
                        <button type="button" aria-label="Close notification" onclick="this.parentElement.remove()">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="user-account-heading">
            <div>
                <p class="eyebrow">Account Activation</p>
                <h1>Complete Your Account</h1>
                <p>Your activation code was verified. Review your details and set security questions for forgot-password recovery.</p>
            </div>
        </div>

        <form action="../server/auth_process.php" method="POST" id="completeSetupForm">
            <div class="setup-step setup-step-active" data-step="1">
                <div class="setup-section-title">
                    <h2>Page 1: Account Details</h2>
                    <p>Review and complete your account information.</p>
                </div>
                <div class="user-account-grid">
                    <label><span class="field-label">First Name <span class="asterisk">*</span></span><input type="text" name="first_name" value="<?= htmlspecialchars($account['first_name']); ?>" required><small class="input-message"></small></label>
                    <label>Middle Name<input type="text" name="middle_name" value="<?= htmlspecialchars($account['middle_name'] ?? ''); ?>"></label>
                    <label><span class="field-label">Last Name <span class="asterisk">*</span></span><input type="text" name="last_name" value="<?= htmlspecialchars($account['last_name']); ?>" required><small class="input-message"></small></label>
                    <label>Extension<input type="text" name="extension_name" value="<?= htmlspecialchars($account['extension_name'] ?? ''); ?>"></label>
                    <label>ID Number<input type="text" value="<?= htmlspecialchars($account['user_id']); ?>" readonly></label>
                    <label><span class="field-label">Email <span class="asterisk">*</span></span><input type="email" name="email" value="<?= htmlspecialchars($account['email']); ?>" required><small class="input-message"></small></label>
                    <label><span class="field-label">Username <span class="asterisk">*</span></span><input type="text" name="username" value="<?= htmlspecialchars($account['username']); ?>" required readonly><small class="input-message"></small></label>
                    <label><span class="field-label">Date of Birth <span class="asterisk">*</span></span><input type="date" name="dob" value="<?= htmlspecialchars($account['dob']); ?>" required><small class="input-message"></small></label>
                    <label><span class="field-label">Age <span class="asterisk">*</span></span><input type="number" name="age" value="<?= (int)$account['age']; ?>" min="1" required readonly><small class="input-message"></small></label>
                    <label><span class="field-label">Sex <span class="asterisk">*</span></span>
                        <select name="sex" required>
                            <option value="female" <?= $account['sex'] === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="male" <?= $account['sex'] === 'male' ? 'selected' : ''; ?>>Male</option>
                        </select><small class="input-message"></small>
                    </label>
                    <label><span class="field-label">Purok/Street <span class="asterisk">*</span></span><input type="text" name="purok_street" value="<?= htmlspecialchars(setup_display_value($account, 'purok_street')); ?>" required><small class="input-message"></small></label>
                    <label><span class="field-label">Barangay <span class="asterisk">*</span></span><input type="text" name="barangay" value="<?= htmlspecialchars(setup_display_value($account, 'barangay')); ?>" required><small class="input-message"></small></label>
                    <label><span class="field-label">City/Municipality <span class="asterisk">*</span></span><input type="text" name="city_municipality" value="<?= htmlspecialchars(setup_display_value($account, 'city_municipality')); ?>" required><small class="input-message"></small></label>
                    <label><span class="field-label">Province <span class="asterisk">*</span></span><input type="text" name="province" value="<?= htmlspecialchars(setup_display_value($account, 'province')); ?>" required><small class="input-message"></small></label>
                    <label><span class="field-label">Country <span class="asterisk">*</span></span><input type="text" name="country" value="<?= htmlspecialchars(setup_display_value($account, 'country')); ?>" required><small class="input-message"></small></label>
                    <label><span class="field-label">Zip Code <span class="asterisk">*</span></span><input type="text" name="zip_code" value="<?= htmlspecialchars(setup_display_value($account, 'zip_code')); ?>" required><small class="input-message"></small></label>
                </div>
                <div class="profile-actions">
                    <button type="button" id="setupNextBtn">Next</button>
                </div>
            </div>

            <div class="setup-step" data-step="2">
                <div class="setup-security-box">
                    <h2>Set Your Authentication Questions</h2>
                    <p class="subtitle">Please select and answer 3 different security questions to protect your account if you forget your password.</p>

                    <div class="setup-security-form">
                        <div class="setup-q-group">
                            <label for="question_1">Question 1 <span class="asterisk">*</span></label>
                            <select name="question_1" id="question_1" required class="question-select">
                                <option value="">-- Select a question --</option>
                                <option value="first_pet">What was the name of your first pet?</option>
                                <option value="childhood_nickname">What was your childhood nickname?</option>
                                <option value="favorite_subject">What was your favorite subject in school?</option>
                            </select>
                            <small class="input-message"></small>
                        </div>

                        <div class="setup-a-group input-box">
                            <label for="answer_1">Answer <span class="asterisk">*</span></label>
                            <input type="password" name="answer_1" id="answer_1" placeholder="Enter your answer" required>
                            <small class="input-message"></small>
                            <i class='bx bx-eye-slash'></i>
                        </div>

                        <div class="setup-q-group">
                            <label for="question_2">Question 2 <span class="asterisk">*</span></label>
                            <select name="question_2" id="question_2" required class="question-select">
                                <option value="">-- Select a question --</option>
                                <option value="mothers_maiden_name">What is your mother's maiden name?</option>
                                <option value="dream_job">What was your dream job when you were a child?</option>
                                <option value="first_travel">What was the first place you traveled to?</option>
                            </select>
                            <small class="input-message"></small>
                        </div>

                        <div class="setup-a-group input-box">
                            <label for="answer_2">Answer <span class="asterisk">*</span></label>
                            <input type="password" name="answer_2" id="answer_2" placeholder="Enter your answer" required>
                            <small class="input-message"></small>
                            <i class='bx bx-eye-slash'></i>
                        </div>

                        <div class="setup-q-group">
                            <label for="question_3">Question 3 <span class="asterisk">*</span></label>
                            <select name="question_3" id="question_3" required class="question-select">
                                <option value="">-- Select a question --</option>
                                <option value="first_school">What was the name of your first school?</option>
                                <option value="childhood_friend">What was the name of your childhood best friend?</option>
                                <option value="birth_city">In which city were you born?</option>
                            </select>
                            <small class="input-message"></small>
                        </div>

                        <div class="setup-a-group input-box">
                            <label for="answer_3">Answer <span class="asterisk">*</span></label>
                            <input type="password" name="answer_3" id="answer_3" placeholder="Enter your answer" required>
                            <small class="input-message"></small>
                            <i class='bx bx-eye-slash'></i>
                        </div>

                        <div class="setup-form-actions">
                            <button type="button" class="setup-btn setup-btn-secondary" id="setupPrevBtn">Back</button>
                            <button type="submit" name="complete_account_setup_btn" class="setup-btn setup-btn-primary">Complete Setup</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </section>
</main>

<footer>
    <p>&copy; 2025 Caraga State University Cabadbaran Campus. All rights reserved.</p>
</footer>
<script>
    const setupForm = document.getElementById('completeSetupForm');
    const setupNextBtn = document.getElementById('setupNextBtn');
    const setupPrevBtn = document.getElementById('setupPrevBtn');
    const setupSteps = Array.from(document.querySelectorAll('.setup-step'));

    function showSetupStep(step) {
        setupSteps.forEach(section => {
            section.classList.toggle('setup-step-active', section.dataset.step === String(step));
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    setupNextBtn.addEventListener('click', () => {
        const firstStepInputs = setupSteps[0].querySelectorAll('input, select');
        for (const input of firstStepInputs) {
            if (input.required) validateSetupProfileField(input);
            if (!input.checkValidity()) {
                input.reportValidity();
                return;
            }
        }
        showSetupStep(2);
    });

    // Validate required account details while they are being completed.
    const setupProfileFields = Array.from(setupSteps[0].querySelectorAll('input[required], select[required]'));
    const setupProfileMessage = (field) => field.parentElement.querySelector('.input-message');
    const validateSetupProfileField = (field) => {
        const message = setupProfileMessage(field);
        if (!message) return field.checkValidity();

        let text = '';
        if (!field.value.trim()) {
            text = 'This field is required.';
        } else if (field.type === 'email' && !field.validity.valid) {
            text = 'Enter a valid email address.';
        } else if (field.name === 'age' && Number(field.value) < 18) {
            text = 'You must be at least 18 years old.';
        }

        message.textContent = text;
        message.classList.toggle('show', text !== '');
        field.setCustomValidity(text);
        return text === '';
    };

    setupProfileFields.forEach(field => {
        field.addEventListener('input', () => validateSetupProfileField(field));
        field.addEventListener('change', () => validateSetupProfileField(field));
        field.addEventListener('blur', () => validateSetupProfileField(field));
    });

    const setupDob = setupForm.querySelector('input[name="dob"]');
    const setupAge = setupForm.querySelector('input[name="age"]');
    const updateSetupAge = () => {
        if (!setupDob.value) {
            setupAge.value = '';
            validateSetupProfileField(setupDob);
            validateSetupProfileField(setupAge);
            return;
        }

        const birthDate = new Date(`${setupDob.value}T00:00:00`);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        if (today < new Date(today.getFullYear(), birthDate.getMonth(), birthDate.getDate())) age--;
        setupAge.value = age;
        validateSetupProfileField(setupDob);
        validateSetupProfileField(setupAge);
    };
    setupDob.addEventListener('input', updateSetupAge);
    setupDob.addEventListener('change', updateSetupAge);

    setupPrevBtn.addEventListener('click', () => showSetupStep(1));

    // ===== Security questions UX (mirrors security_question page) =====
    const setupQuestionSelects = Array.from(document.querySelectorAll('#completeSetupForm .question-select'));
    const setupAnswerIds = ['answer_1', 'answer_2', 'answer_3'];

    function setupMsgEl(inputEl) {
        if (!inputEl) return null;
        const next = inputEl.nextElementSibling;
        if (next && next.classList && next.classList.contains('input-message')) return next;
        return inputEl.parentElement ? inputEl.parentElement.querySelector('.input-message') : null;
    }

    function setupShowMsg(inputEl, message) {
        const msgEl = setupMsgEl(inputEl);
        if (msgEl) {
            msgEl.textContent = message;
            msgEl.classList.add('show');
        }
    }

    function setupClearMsg(inputEl) {
        const msgEl = setupMsgEl(inputEl);
        if (msgEl) {
            msgEl.textContent = '';
            msgEl.classList.remove('show');
        }
    }

    function updateSetupQuestionOptions() {
        const selected = setupQuestionSelects.map(s => s.value).filter(v => v !== '');
        setupQuestionSelects.forEach(current => {
            Array.from(current.options).forEach(option => {
                if (option.value === '') {
                    option.disabled = false;
                    return;
                }
                option.disabled = selected.includes(option.value) && current.value !== option.value;
            });
        });
    }

    function enforceSetupAnswer(select) {
        const m = (select.id || '').match(/question_(\d+)/);
        if (!m) return;
        const ans = document.getElementById('answer_' + m[1]);
        if (!ans) return;
        if (select.value === '') {
            ans.disabled = true;
            ans.value = '';
            setupShowMsg(ans, 'Please select a question first.');
        } else {
            ans.disabled = false;
            setupClearMsg(ans);
        }
    }

    setupQuestionSelects.forEach(select => {
        select.addEventListener('change', () => {
            updateSetupQuestionOptions();
            enforceSetupAnswer(select);
            if (select.value === '') {
                setupShowMsg(select, 'Please select a question.');
            } else {
                setupClearMsg(select);
            }
        });
    });
    updateSetupQuestionOptions();
    setupQuestionSelects.forEach(enforceSetupAnswer);

    // Eye toggle for answer fields
    document.querySelectorAll('.setup-a-group i.bx-eye, .setup-a-group i.bx-eye-slash').forEach(icon => {
        icon.addEventListener('click', () => {
            const box = icon.closest('.setup-a-group');
            const input = box ? box.querySelector('input[type="password"], input[type="text"]') : null;
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            icon.classList.toggle('bx-eye');
            icon.classList.toggle('bx-eye-slash');
        });
    });

    // Prevent double-submit on final setup
    setupForm.addEventListener('submit', () => {
        const btn = setupForm.querySelector('button[name="complete_account_setup_btn"]');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Submitting...';
        }
    });

    // Validate security step on final submit
    setupForm.addEventListener('submit', (e) => {
        const submitBtn = setupForm.querySelector('button[name="complete_account_setup_btn"]');
        const reenable = () => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Complete Setup';
            }
        };
        const questions = setupQuestionSelects.map(s => s.value);
        if (questions.includes('')) {
            e.preventDefault();
            reenable();
            showSetupStep(2);
            for (const s of setupQuestionSelects) {
                if (s.value === '') {
                    setupShowMsg(s, 'Please select a question.');
                    s.focus();
                    break;
                }
            }
            return false;
        }
        if (new Set(questions).size !== questions.length) {
            e.preventDefault();
            reenable();
            showSetupStep(2);
            alert('Please select different questions. Duplicate questions are not allowed.');
            return false;
        }
        return true;
    });
</script>
</body>
</html>
