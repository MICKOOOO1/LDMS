<?php
session_start();

// Set headers to prevent caching and ensure page stays
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

if (isset($_SESSION['user_id'])) {
    if (($_SESSION['role'] ?? '') === 'super_admin') {
        header('Location: ../super%20admin/dashboard.php');
    } elseif (($_SESSION['role'] ?? '') === 'admin') {
        header('Location: ../admin/dashboard.php');
    } else {
        header('Location: home.php');
    }
    exit;
}

// Retrieve session variables or set defaults
$name = $_SESSION['name'] ?? null;
$alerts = $_SESSION['alerts'] ?? [];
$active_form = $_SESSION['active_form'] ?? '';
$failed_attempts = $_SESSION['failed_attempts'] ?? 0;
$cred_failed_attempts = $_SESSION['cred_failed_attempts'] ?? 0;
$lockout_until = $_SESSION['lockout_until'] ?? null;
$lockout_remaining = ($lockout_until && time() < $lockout_until) ? ($lockout_until - time()) : 0;

// Clear session variables but preserve name if it exists
unset($_SESSION['alerts'], $_SESSION['active_form']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Login - CSUCC Ladies Dormitory Management System</title>
    <link rel="stylesheet" href="../css/login.css">
    <link href="https://cdn.boxicons.com/fonts/basic/boxicons.min.css" rel="stylesheet">
</head>
<body>
    <header class="user-auth">
        <a href="index.php" class="logo">
            <img src="../assets/imgs/logo.png" alt="CSUCC Logo">
            CSUCC LDMS
        </a>
        <nav>
            <a href="index.php" class="login-btn-modal" id="header-home-link">Home</a>
            <a href="register.php" class="login-btn-modal" id="header-register-link">Register</a>
        </nav>
    </header>

    <?php if (!empty($alerts)): ?>
        <div class="alert-box">
            <?php foreach ($alerts as $alert): ?>
                <span class="alert <?= htmlspecialchars($alert['type']); ?>"><i class='bx <?= $alert['type'] === 'success' ? 'bxs-check-circle' : 'bxs-x-circle'; ?>'></i> <?= htmlspecialchars($alert['message']); ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="auth-modal show">
        <div class="form-box login">
            <h2>Login</h2>
            <form action="../server/auth_process.php" method="POST">
                <div class="input-box full-width">
                    <label>Username</label>
                    <input type="text" name="name" placeholder="Username" required>
                    <i class='bx bxs-user'></i>
                </div>

                <div class="input-box full-width">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Password" required>
                    <i class='bx  bx-eye-slash'></i> 
                </div>
                <?php if ($cred_failed_attempts >= 2 && isset($_SESSION['last_failure_type']) && $_SESSION['last_failure_type'] === 'credential'): ?>
                <p class="forgot">Forgot Password? <a href="forgot_password.php">Reset Here</a></p>
                <?php endif; ?>

                <button type="submit" name="login_btn" class="btn" id="login-btn" <?= $lockout_remaining > 0 ? 'disabled' : '' ?>>Login</button>
                <?php if ($lockout_remaining > 0): ?>
                    <p id="lockout-timer">Try again in <?= (int)$lockout_remaining ?>s</p>
                <?php endif; ?>
                <p>Don't have an account? <a href="register.php" class="register-link" id="register-link" <?= $lockout_remaining > 0 ? 'aria-disabled="true"' : '' ?>>Register</a></p>
            </form>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 Caraga State University Cabadbaran Campus. All rights reserved.</p>
    </footer>

    <script>
    (function(){
        // ===== SIMPLE BACK BUTTON PREVENTION - ONLY PREVENT BACK, ALLOW ALL LINKS =====
        var loginPageUrl = location.href.split('?')[0].split('#')[0];
        
        // Function to add history entries
        function addHistoryEntries() {
            try {
                for (var i = 0; i < 10; i++) {
                    history.pushState({page: 'login'}, '', loginPageUrl);
                }
            } catch(e) {}
        }
        
        // Replace current history entry
        history.replaceState({page: 'login'}, '', loginPageUrl);
        addHistoryEntries();
        
        // ONLY prevent back button - don't interfere with anything else
        window.addEventListener('popstate', function(event) {
            // Immediately add history entries
            addHistoryEntries();
            // Force redirect back to login page
            window.location.replace(loginPageUrl);
            // Try to go forward
            try {
                setTimeout(function() {
                    history.go(1);
                    addHistoryEntries();
                }, 1);
            } catch(e) {}
        });
        
        // Refresh history on page load
        window.addEventListener('load', function() {
            addHistoryEntries();
        });
        
        // Refresh history on pageshow (browser cache)
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                addHistoryEntries();
            }
        });
        
        // Prevent on page load
        window.addEventListener('load', function() {
            floodHistory();
            forceStayOnLogin();
            // Double check
            setTimeout(function() {
                floodHistory();
                forceStayOnLogin();
            }, 100);
        });
        
        // Prevent on pageshow (handles browser back/forward cache)
        window.addEventListener('pageshow', function(event) {
            floodHistory();
            var currentUrl = location.href.split('?')[0].split('#')[0];
            if (event.persisted || currentUrl !== loginPageUrl) {
                window.location.replace(loginPageUrl);
                floodHistory();
            }
        });
        
        // Immediate execution on script load
        floodHistory();
        forceStayOnLogin();
        
        // Additional prevention on DOMContentLoaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                floodHistory();
                forceStayOnLogin();
            });
        } else {
            floodHistory();
            forceStayOnLogin();
        }
        
        // Block hash changes
        window.addEventListener('hashchange', function() {
            floodHistory();
            forceStayOnLogin();
        });
        
        // COMPLETE OVERRIDE of history methods
        var originalPushState = history.pushState;
        var originalReplaceState = history.replaceState;
        var originalGo = history.go;
        var originalBack = history.back;
        var originalForward = history.forward;
        
        history.pushState = function() {
            originalPushState.apply(history, arguments);
            var currentUrl = location.href.split('?')[0].split('#')[0];
            if (currentUrl !== loginPageUrl) {
                window.location.replace(loginPageUrl);
                floodHistory();
            }
        };
        
        history.replaceState = function() {
            originalReplaceState.apply(history, arguments);
            var currentUrl = location.href.split('?')[0].split('#')[0];
            if (currentUrl !== loginPageUrl) {
                window.location.replace(loginPageUrl);
                floodHistory();
            }
        };
        
        history.back = function() {
            floodHistory();
            forceStayOnLogin();
            // Try to go forward instead
            try {
                setTimeout(function() {
                    if (history.length > 0) {
                        history.go(1);
                    }
                }, 1);
            } catch(e) {}
        };
        
        history.forward = function() {
            originalForward.apply(history, arguments);
            setTimeout(function() {
                var currentUrl = location.href.split('?')[0].split('#')[0];
                if (currentUrl !== loginPageUrl) {
                    window.location.replace(loginPageUrl);
                    floodHistory();
                }
            }, 10);
        };
        
        history.go = function(delta) {
            if (delta < 0) {
                // Trying to go back - ABSOLUTELY PREVENT IT
                floodHistory();
                forceStayOnLogin();
                // Try to go forward instead
                try {
                    setTimeout(function() {
                        if (history.length > 0) {
                            history.go(1);
                        }
                        floodHistory();
                    }, 1);
                } catch(e) {}
            } else {
                originalGo.apply(history, arguments);
                setTimeout(function() {
                    var currentUrl = location.href.split('?')[0].split('#')[0];
                    if (currentUrl !== loginPageUrl) {
                        window.location.replace(loginPageUrl);
                        floodHistory();
                    }
                }, 10);
            }
        };
        
        
        var remaining = <?= (int)$lockout_remaining ?>;
        var loginBtn = document.getElementById('login-btn');
        var regLink = document.getElementById('register-link');
        var headerReg = document.getElementById('header-register-link');
        var headerHome = document.getElementById('header-home-link');
        function setDisabled(disabled){
            if (loginBtn) loginBtn.disabled = disabled;
            if (regLink) {
                if (disabled) { regLink.classList.add('disabled'); regLink.addEventListener('click', function(e){ e.preventDefault(); }); }
                else { regLink.classList.remove('disabled'); }
            }
            if (headerReg) {
                if (disabled) { headerReg.classList.add('disabled'); headerReg.addEventListener('click', function(e){ e.preventDefault(); }); }
                else { headerReg.classList.remove('disabled'); }
            }
            if (headerHome) {
                if (disabled) { headerHome.classList.add('disabled'); headerHome.addEventListener('click', function(e){ e.preventDefault(); }); }
                else { headerHome.classList.remove('disabled'); }
            }
        }
        var el = document.getElementById('lockout-timer');
        if (remaining > 0) {
            setDisabled(true);
            function tick(){
                if (el) el.textContent = 'Try again in ' + remaining + 's';
                remaining--;
                if (remaining >= 0) setTimeout(tick, 1000);
                else {
                    setDisabled(false);
                }
            }
            tick();
        }
    })();
    </script>
    <script src="../javascript/script.js"></script>
</body>
</html>
