<?php
session_start();
include "db.php";

// AES encryption settings (Must match your registration/login settings)
$encryption_key = "YOUR_ENCRYPTION_KEY_MIN_32_CHARS";
$cipher = "AES-256-CBC";

/**
 * Decrypts data stored in the database
 */
function decryptData($encrypted, $key, $cipher) {
    if (empty($encrypted)) return "";
    $data = base64_decode($encrypted);
    if ($data === false) return "";
    $ivlen = openssl_cipher_iv_length($cipher);
    if (strlen($data) < $ivlen) return "";
    $iv = substr($data, 0, $ivlen);
    $ciphertext = substr($data, $ivlen);
    return openssl_decrypt($ciphertext, $cipher, $key, 0, $iv);
}

// Check if email is being submitted via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    // Clear buffer to ensure only our response is sent
    if (ob_get_length()) ob_clean();

    $email_input = trim($_POST['email']);

    if (empty($email_input)) {
        echo "❌ Please enter your email address.";
        exit;
    } else {
        // We must fetch users and decrypt emails to find a match 
        // because AES with random IV produces different strings every time.
        $stmt = $conn->prepare("SELECT id, email_id FROM users");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $found = false;

        while ($row = $result->fetch_assoc()) {
            $dbEmail = decryptData($row['email_id'], $encryption_key, $cipher);
            
            if (strtolower($dbEmail) === strtolower($email_input)) {
                $found = true;
                // Store in session so reset_password.php knows which user to update
                $_SESSION['reset_email'] = $dbEmail;
                $_SESSION['reset_user_id'] = $row['id'];
                echo "SUCCESS";
                exit;
            }
        }

        if (!$found) {
            echo "❌ Email not found! Please check your details or register.";
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Forgot Password - Girls Hostel Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #f0f9ff;
            --bg-secondary: #e0f2fe;
            --bg-card: #ffffff;
            --primary: #0369a1;
            --primary-light: #0ea5e9;
            --primary-dark: #075985;
            --secondary: #38bdf8;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --text-primary: #0c4a6e;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --border-color: #bae6fd;
            --shadow-xl: 0 20px 25px -5px rgba(3, 105, 161, 0.1);
            --radius: 16px;
            --gradient: linear-gradient(135deg, var(--primary-light) 0%, var(--secondary) 100%);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Segoe UI', system-ui, sans-serif; 
        }
        
        body { 
            background: var(--bg-primary); 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            padding: 15px;
            width: 100%;
            overflow-x: hidden;
            font-size: 14px;
        }

        .background-pattern { 
            position: fixed; 
            inset: 0; 
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(14, 165, 233, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(56, 189, 248, 0.05) 0%, transparent 50%);
            z-index: 1; 
            pointer-events: none; 
        }
        
        .floating-elements { 
            position: fixed; 
            inset: 0; 
            pointer-events: none; 
            z-index: 0; 
        }
        
        .floating-element { 
            position: absolute; 
            background: rgba(14, 165, 233, 0.08); 
            border-radius: 50%; 
            animation: float 8s ease-in-out infinite; 
        }

        @keyframes float { 
            0%, 100% { transform: translateY(0); } 
            50% { transform: translateY(-15px); } 
        }

        .container {
            background: var(--bg-card);
            padding: 30px 25px;
            border-radius: var(--radius);
            width: 100%;
            max-width: 420px;
            box-shadow: var(--shadow-xl);
            position: relative;
            z-index: 2;
            border: 1px solid var(--border-color);
            animation: slideUp 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes slideUp { 
            from { 
                opacity: 0; 
                transform: translateY(30px) scale(0.95); 
            } 
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1); 
            } 
        }

        .container::before { 
            content: ''; 
            position: absolute; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 4px; 
            background: var(--gradient); 
            border-radius: var(--radius) var(--radius) 0 0; 
        }

        .header { 
            text-align: center; 
            margin-bottom: 25px; 
        }
        
        .header h2 { 
            color: var(--text-primary); 
            font-size: 1.5rem; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 10px; 
            line-height: 1.2;
        }
        
        .header p { 
            color: var(--text-secondary); 
            font-size: 0.9rem; 
            margin-top: 8px; 
            line-height: 1.4;
        }

        .form-group { 
            margin-bottom: 20px; 
        }
        
        .input-container { 
            position: relative; 
        }
        
        .input-container i { 
            position: absolute; 
            left: 15px; 
            top: 50%; 
            transform: translateY(-50%); 
            color: var(--text-muted); 
            z-index: 2; 
            font-size: 16px;
        }

        input {
            width: 100%;
            padding: 14px 15px 14px 50px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            background: var(--bg-primary);
            transition: var(--transition);
            font-size: 15px;
            color: var(--text-primary);
            height: 50px;
            -webkit-appearance: none;
            appearance: none;
        }

        input:focus { 
            border-color: var(--primary-light); 
            background: var(--bg-secondary); 
            outline: none; 
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15); 
            transform: translateY(-1px);
        }
        
        input::placeholder {
            color: var(--text-muted);
            font-weight: 400;
        }

        .btn {
            width: 100%; 
            background: var(--gradient); 
            color: white; 
            padding: 14px; 
            border: none; 
            border-radius: 10px;
            font-size: 15px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: var(--transition);
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 10px;
            height: 50px;
            -webkit-tap-highlight-color: transparent;
        }

        .btn:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 4px 12px rgba(3, 105, 161, 0.2); 
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn:disabled { 
            opacity: 0.7; 
            cursor: not-allowed; 
            transform: none !important;
        }

        .alert { 
            padding: 14px; 
            border-radius: 10px; 
            margin-bottom: 20px; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            font-weight: 500; 
            border: 1px solid; 
            animation: slideIn 0.5s ease; 
            font-size: 13px;
        }
        
        .alert.error { 
            background: #fef2f2; 
            color: #dc2626; 
            border-color: #fecaca; 
        }
        
        @keyframes slideIn { 
            from { 
                opacity: 0; 
                transform: translateX(-10px); 
            } 
            to { 
                opacity: 1; 
                transform: translateX(0); 
            } 
        }

        .back-link { 
            text-align: center; 
            margin-top: 20px; 
        }
        
        .back-link a { 
            color: var(--primary-light); 
            text-decoration: none; 
            font-weight: 500; 
            font-size: 13px; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            padding: 8px 12px;
            border-radius: 8px;
            transition: var(--transition);
        }
        
        .back-link a:hover {
            background: var(--bg-secondary);
            color: var(--primary);
        }

        .success-overlay { 
            position: fixed; 
            inset: 0; 
            background: rgba(240, 249, 255, 0.95); 
            display: none; 
            justify-content: center; 
            align-items: center; 
            z-index: 1000; 
            padding: 15px;
        }
        
        .success-content { 
            background: white; 
            padding: 30px; 
            border-radius: 16px; 
            text-align: center; 
            box-shadow: var(--shadow-xl); 
            border: 1px solid var(--border-color); 
            width: 100%;
            max-width: 400px;
            animation: slideUp 0.5s ease;
        }
        
        .success-content h3 {
            color: var(--text-primary);
            margin: 15px 0 10px;
            font-size: 1.3rem;
        }
        
        .success-content p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-bottom: 20px;
        }

        .loader { 
            width: 18px; 
            height: 18px; 
            border: 2px solid rgba(255,255,255,0.3); 
            border-radius: 50%; 
            border-top-color: white; 
            animation: spin 1s linear infinite; 
        }
        
        @keyframes spin { 
            to { 
                transform: rotate(360deg); 
            } 
        }
        
        /* Shake animation */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        /* Tablet Styles */
        @media (min-width: 768px) {
            body {
                padding: 20px;
                font-size: 15px;
            }
            
            .container {
                padding: 35px 30px;
            }
            
            .header h2 {
                font-size: 1.75rem;
            }
            
            .header p {
                font-size: 0.95rem;
            }
            
            input {
                padding: 15px 15px 15px 50px;
                font-size: 16px;
                height: 52px;
            }
            
            .btn {
                padding: 15px;
                font-size: 16px;
                height: 52px;
            }
            
            .alert {
                padding: 16px;
                font-size: 14px;
            }
            
            .back-link a {
                font-size: 14px;
            }
            
            .success-content {
                padding: 35px;
            }
            
            .success-content h3 {
                font-size: 1.4rem;
            }
        }

        /* Desktop Styles */
        @media (min-width: 1024px) {
            .container {
                padding: 40px 35px;
            }
            
            .header h2 {
                font-size: 1.8rem;
            }
        }

        /* Small Mobile Styles */
        @media (max-width: 360px) {
            body {
                padding: 10px;
            }
            
            .container {
                padding: 25px 20px;
            }
            
            .header h2 {
                font-size: 1.3rem;
            }
            
            .header p {
                font-size: 0.85rem;
            }
            
            input {
                padding: 12px 12px 12px 45px;
                font-size: 14px;
                height: 46px;
            }
            
            .input-container i {
                font-size: 14px;
                left: 12px;
            }
            
            .btn {
                padding: 12px;
                font-size: 14px;
                height: 46px;
                gap: 8px;
            }
            
            .alert {
                padding: 12px;
                font-size: 12px;
                gap: 10px;
            }
            
            .success-content {
                padding: 25px 20px;
            }
            
            .success-content h3 {
                font-size: 1.2rem;
            }
        }

        /* Landscape Mode */
        @media (max-height: 600px) and (orientation: landscape) {
            body {
                align-items: flex-start;
                padding-top: 20px;
                padding-bottom: 20px;
            }
            
            .container {
                max-height: 90vh;
                overflow-y: auto;
            }
            
            .header {
                margin-bottom: 20px;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
        }

        /* Fix for iOS input styling */
        @supports (-webkit-touch-callout: none) {
            input {
                font-size: 16px; /* Prevents iOS zoom on focus */
            }
        }

        /* Loading animation for button */
        .btn.loading {
            position: relative;
            color: transparent;
        }
        
        .btn.loading::after {
            content: "";
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        /* Accessibility improvements */
        input:focus-visible {
            outline: 2px solid var(--primary-light);
            outline-offset: 2px;
        }
        
        .btn:focus-visible {
            outline: 2px solid var(--primary-dark);
            outline-offset: 2px;
        }
        
        a:focus-visible {
            outline: 2px solid var(--primary-light);
            outline-offset: 2px;
            border-radius: 4px;
        }
    </style>
</head>
<body>

<div class="background-pattern"></div>
<div class="floating-elements" id="floatingElements"></div>

<div class="container">
    <div class="header">
        <h2><i class="fas fa-key"></i> Forgot Password</h2>
        <p>Enter your registered email to reset your password</p>
    </div>

    <div id="messageBox"></div>

    <form id="forgotForm">
        <div class="form-group">
            <div class="input-container">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" id="emailInput" placeholder="Enter your registered email" required autocomplete="email">
            </div>
        </div>
        
        <button type="submit" class="btn" id="verifyBtn">
            <i class="fas fa-shield-alt"></i> Verify Email
        </button>
    </form>

    <div class="back-link">
        <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
    </div>
</div>

<div class="success-overlay" id="successOverlay">
    <div class="success-content">
        <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--success); margin-bottom: 20px;"></i>
        <h3>Email Verified!</h3>
        <p>Redirecting to password reset page...</p>
        <div class="loader" style="margin: 20px auto; border-top-color: var(--primary); width: 24px; height: 24px;"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Create Floating Background Elements
    const floatingContainer = document.getElementById('floatingElements');
    const elementCount = window.innerWidth < 768 ? 4 : 6;
    
    for (let i = 0; i < elementCount; i++) {
        const el = document.createElement('div');
        el.className = 'floating-element';
        const size = window.innerWidth < 768 ? 
            Math.random() * 40 + 30 : 
            Math.random() * 60 + 40;
        el.style.width = el.style.height = `${size}px`;
        el.style.left = `${Math.random() * 100}%`;
        el.style.top = `${Math.random() * 100}%`;
        el.style.animationDelay = `${Math.random() * 5}s`;
        el.style.animationDuration = `${Math.random() * 6 + 6}s`;
        floatingContainer.appendChild(el);
    }

    // 2. Form Logic
    const form = document.getElementById('forgotForm');
    const btn = document.getElementById('verifyBtn');
    const msgBox = document.getElementById('messageBox');
    const overlay = document.getElementById('successOverlay');
    const emailInput = document.getElementById('emailInput');

    // Auto-focus email input on page load
    setTimeout(() => {
        emailInput.focus();
    }, 300);

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="loader"></span> Verifying...';
        btn.classList.add('loading');
        btn.disabled = true;
        msgBox.innerHTML = '';

        const formData = new FormData(form);

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            const result = data.trim();
            if (result === "SUCCESS") {
                overlay.style.display = 'flex';
                setTimeout(() => {
                    window.location.href = 'reset_password.php';
                }, 2000);
            } else {
                btn.innerHTML = originalText;
                btn.classList.remove('loading');
                btn.disabled = false;
                msgBox.innerHTML = `<div class="alert error"><i class="fas fa-exclamation-circle"></i> ${result}</div>`;
                
                // Shake animation
                form.style.animation = 'none';
                void form.offsetWidth; // trigger reflow
                form.style.animation = 'shake 0.5s ease';
                
                // Focus back on input
                emailInput.focus();
            }
        })
        .catch(err => {
            btn.innerHTML = originalText;
            btn.classList.remove('loading');
            btn.disabled = false;
            msgBox.innerHTML = `<div class="alert error"><i class="fas fa-exclamation-triangle"></i> Connection error. Please check your internet and try again.</div>`;
        });
    });

    // Touch device optimizations
    if ('ontouchstart' in window) {
        document.body.classList.add('touch-device');
        
        // Improve touch targets
        btn.style.minHeight = '44px'; // Apple's minimum touch target
        
        // Add touch feedback
        btn.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.98)';
        });
        
        btn.addEventListener('touchend', function() {
            this.style.transform = '';
        });
    }

    // Prevent form resubmission on page refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
});
</script>

</body>
</html>