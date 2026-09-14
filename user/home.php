<?php
session_start();

// Set headers to prevent caching and ensure page stays
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

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
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Login & Registration</title>
    <link rel="stylesheet" href="../css/home.css">
    <link href="https://cdn.boxicons.com/fonts/basic/boxicons.min.css" rel="stylesheet">
</head>
<body>

<header class="user-auth">
    <a href="index.php" class="logo">
        <img src="../assets/imgs/logo.png" alt="CSUCC Logo">
        CSUCC LDMS
    </a>
    <nav>
        <?php if (!empty($name)): ?>
            <span class="hello">Hello, <?= htmlspecialchars($name); ?></span>
            <a href="logs.php" class="login-btn-modal">Logs</a>
            <a href="logout.php" class="login-btn-modal">Logout</a>
        <?php else: ?>
            <a href="login.php" class="login-btn-modal">Login</a>
            <a href="register.php" class="login-btn-modal">Register</a>
        <?php endif; ?>
    </nav>
</header>

<section class="hero">
    <div class="hero-container">
        <?php if (!empty($name)): ?>
            <h1>Welcome to the CSUCC Ladies Dormitory</h1>
        <?php endif; ?>
        <p>This platform is designed to provide students with a convenient and secure way to manage dormitory information,
            stay updated with announcements, and access essential services.
            Whether you're checking room availability, monitoring your records, or receiving updates from the dorm administration,
            this system aims to make your stay safe, organized, and comfortable.</p>
    </div>
</section>

<div class="auth-modal <?= $active_form === 'register' ? 'show slide expanded' : ($active_form === 'login' ? 'show' : ''); ?>">
    <button type="button" class="close-btn-modal">
        <i class='bx bx-x'></i>
    </button>
</div>
<footer>
    <p>&copy; 2025 Caraga State University Cabadbaran Campus. All rights reserved.</p>
</footer>


<script>
// ===== SIMPLE BACK BUTTON PREVENTION - ONLY PREVENT BACK, ALLOW ALL LINKS =====
(function(){
    var dashboardUrl = location.href.split('?')[0].split('#')[0];
    
    // Function to add history entries
    function addHistoryEntries() {
        try {
            for (var i = 0; i < 10; i++) {
                history.pushState({page: 'dashboard'}, '', dashboardUrl);
            }
        } catch(e) {}
    }
    
    // Replace current history entry
    history.replaceState({page: 'dashboard'}, '', dashboardUrl);
    addHistoryEntries();
    
    // ONLY prevent back button - don't interfere with anything else
    window.addEventListener('popstate', function(event) {
        // Immediately add history entries
        addHistoryEntries();
        // Force redirect back to dashboard
        window.location.replace(dashboardUrl);
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
        forceStayOnDashboard();
        // Double check
        setTimeout(function() {
            floodHistory();
            forceStayOnDashboard();
        }, 100);
    });
    
    // Prevent on pageshow (handles browser back/forward cache)
    window.addEventListener('pageshow', function(event) {
        floodHistory();
        var currentUrl = location.href.split('?')[0].split('#')[0];
        if (event.persisted || currentUrl !== dashboardUrl) {
            window.location.replace(dashboardUrl);
            floodHistory();
        }
    });
    
    // Immediate execution on script load
    floodHistory();
    forceStayOnDashboard();
    
    // Additional prevention on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            floodHistory();
            forceStayOnDashboard();
        });
    } else {
        floodHistory();
        forceStayOnDashboard();
    }
    
    // Block hash changes
    window.addEventListener('hashchange', function() {
        floodHistory();
        forceStayOnDashboard();
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
        if (currentUrl !== dashboardUrl) {
            window.location.replace(dashboardUrl);
            floodHistory();
        }
    };
    
    history.replaceState = function() {
        originalReplaceState.apply(history, arguments);
        var currentUrl = location.href.split('?')[0].split('#')[0];
        if (currentUrl !== dashboardUrl) {
            window.location.replace(dashboardUrl);
            floodHistory();
        }
    };
    
    history.back = function() {
        floodHistory();
        forceStayOnDashboard();
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
            if (currentUrl !== dashboardUrl) {
                window.location.replace(dashboardUrl);
                floodHistory();
            }
        }, 10);
    };
    
    history.go = function(delta) {
        if (delta < 0) {
            // Trying to go back - ABSOLUTELY PREVENT IT
            floodHistory();
            forceStayOnDashboard();
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
                if (currentUrl !== dashboardUrl) {
                    window.location.replace(dashboardUrl);
                    floodHistory();
                }
            }, 10);
        }
    };
})();
</script>
<script src="../javascript/script.js"></script>
</body>
</html>
