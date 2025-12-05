<?php
session_start();

$name = $_SESSION['name'] ?? null;
$alerts = $_SESSION['alerts'] ?? [];
$active_form = $_SESSION['active_form'] ?? '';

unset($_SESSION['alerts'], $_SESSION['active_form']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CSUCC Ladies Dormitory Management System</title>
    <link rel="stylesheet" href="../css/register.css">
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
    <?php if (count($alerts) > 1): ?>
        <div class="alert error" style="margin-top: 10px;">
            <i class='bx bxs-info-circle'></i>
            <span><strong>Total errors: <?= count($alerts); ?></strong> - Please fix all errors above before proceeding.</span>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="auth-modal show slide expanded">
    <div class="form-box register">
        <h2>Create Account</h2>
<form id="registerForm" action="../server/auth_process.php" method="POST" novalidate>

    <!-- ============================
     PERSONAL INFORMATION
============================== -->
<h3 class="section-title">Personal Information</h3>

<!-- ID Number - full width -->
<div class="input-box">
    <label>ID Number <span class="asterisk">*</span></label>
    <input type="text" name="id_number" placeholder="xxxx-xxxx" required>
    <small class="input-message"></small>
</div>

<!-- First, Middle, Last Names -->  
<div class="input-box">
    <label>First Name <span class="asterisk">*</span></label>
    <input type="text" name="first_name" required>
    <small class="input-message"></small>
</div>

<div class="input-box">
    <label>Middle Name <span class="optional">(optional)</span></label>
    <input type="text" name="middle_name">
    <small class="input-message"></small>
</div>

<div class="input-box">
    <label>Last Name <span class="asterisk">*</span></label>
    <input type="text" name="last_name" required>
    <small class="input-message"></small>
</div>

<!-- Extension, DOB, Age -->
<div class="input-box">
    <label>Extension Name <span class="optional">(optional)</span></label>
    <select name="extension_name" id="extension_name">
        <option value="">--Select--</option>
        <option value="Sr">Sr</option>
        <option value="Jr">Jr</option>
        <option value="Others">Others</option>
    </select>
    <small class="input-message"></small>
</div>

<div class="input-box">
    <label>Date of Birth <span class="asterisk">*</span></label>
    <input type="date" name="dob" id="dob" required>
    <small class="input-message"></small>
</div>

<div class="input-box">
    <label>Age <span class="asterisk">*</span></label>
    <input type="number" name="age" id="age" readonly required>
    <small class="input-message"></small>
</div>

<!-- Sex, Email (center 2 fields inside 3-column grid) -->
<div class="input-box">
    <label>Sex <span class="asterisk">*</span></label>
    <select name="sex" required>
        <option value="">--Select--</option>
        <option value="male">Male</option>
        <option value="female">Female</option>
    </select>
    <small class="input-message"></small>
</div>

<div class="input-box">
    <label>Email Address <span class="asterisk">*</span></label>
    <input type="email" name="email" minlength="5" maxlength="70" required>
    <small class="input-message"></small>
</div>


<!-- ============================
     ADDRESS INFORMATION
============================== -->
<h3 class="section-title">Address Information</h3>

<div class="input-box">
    <label>Purok/Street <span class="asterisk">*</span></label>
    <input type="text" name="purok_street" required>
    <small class="input-message"></small>
</div>

<div class="input-box">
    <label>Barangay <span class="asterisk">*</span></label>
    <input type="text" name="barangay" required>
    <small class="input-message"></small>
</div>

<div class="input-box">
    <label>City/Municipality <span class="asterisk">*</span></label>
    <input type="text" name="city_municipality" required>
    <small class="input-message"></small>
</div>

<div class="input-box">
    <label>Province <span class="asterisk">*</span></label>
    <input type="text" name="province" required>
    <small class="input-message"></small>
</div>

<div class="input-box">
    <label>Country <span class="asterisk">*</span></label>
    <input type="text" name="country" required>
    <small class="input-message"></small>
</div>

<div class="input-box">
    <label>Zip Code <span class="asterisk">*</span></label>
    <input type="text" name="zip_code" required>
    <small class="input-message"></small>
</div>


<!-- ============================
     ACCOUNT INFORMATION
============================== -->
<h3 class="section-title">Account Information</h3>

<div class="input-box">
    <label>Username <span class="asterisk">*</span></label>
    <input type="text" name="username" autocomplete="off" required>
    <small class="input-message"></small>
    <i class='bx bxs-user'></i>
</div>

<div class="input-box">
    <label>Password <span class="asterisk">*</span></label>
    <input type="password" name="password" required>
    <small class="input-message"></small>
    <div class="strength-meter">
        <div class="strength-bar"></div>
    </div>
    <i class='bx bx-eye-slash'></i>
</div>

<div class="input-box">
    <label>Re-enter Password <span class="asterisk">*</span></label>
    <input type="password" name="re_password" required>
    <small class="input-message"></small>
    <i class='bx bx-eye-slash'></i>
</div>

<!-- Submit -->
<button type="submit" name="register_btn" value="1" class="btn">Next</button>
<p>Already have an account? <a href="login.php">Login</a></p>
</form>

    </div>
</div>

<footer>
    <p>&copy; 2025 Caraga State University Cabadbaran Campus. All rights reserved.</p>
</footer>

<script>
// Handle extension name dropdown - show Roman numerals in same dropdown when "Others" is selected
document.addEventListener('DOMContentLoaded', function() {
    var extensionSelect = document.getElementById('extension_name');
    
    if (extensionSelect) {
        // Store original options
        var originalOptions = [
            {value: '', text: '--Select--'},
            {value: 'Sr', text: 'Sr'},
            {value: 'Jr', text: 'Jr'},
            {value: 'Others', text: 'Others'}
        ];
        
        // Roman numeral options
        var romanOptions = [
            {value: '', text: '--Select Roman Numeral--'},
            {value: 'I', text: 'I'},
            {value: 'II', text: 'II'},
            {value: 'III', text: 'III'},
            {value: 'IV', text: 'IV'},
            {value: 'V', text: 'V'},
            {value: 'VI', text: 'VI'},
            {value: 'VII', text: 'VII'},
            {value: 'VIII', text: 'VIII'},
            {value: 'IX', text: 'IX'},
            {value: 'X', text: 'X'}
        ];
        
        var isShowingRomans = false;
        
        extensionSelect.addEventListener('change', function() {
            if (this.value === 'Others' && !isShowingRomans) {
                // User selected "Others" - change dropdown to show Roman numerals
                isShowingRomans = true;
                this.innerHTML = '';
                romanOptions.forEach(function(option) {
                    var opt = document.createElement('option');
                    opt.value = option.value;
                    opt.textContent = option.text;
                    this.appendChild(opt);
                }, this);
                // Reset to first option (--Select--)
                this.value = '';
            } else if (isShowingRomans && this.value === '') {
                // User selected --Select-- while showing Romans - go back to original options
                isShowingRomans = false;
                this.innerHTML = '';
                originalOptions.forEach(function(option) {
                    var opt = document.createElement('option');
                    opt.value = option.value;
                    opt.textContent = option.text;
                    this.appendChild(opt);
                }, this);
                this.value = '';
            }
            // If a Roman numeral is selected, keep it selected (value will be I, II, III, etc.)
        });
    }
});
</script>
<script src="../javascript/script.js"></script>
</body>
</html>
