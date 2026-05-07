<?php
session_start();
include "db.php"; 

$message = "";
$messageType = ""; 

// Encryption settings for Email storage
$encryption_key = "YOUR_ENCRYPTION_KEY_MIN_32_CHARS"; 
$cipher = "AES-256-CBC";

function encryptData($data, $key, $cipher) {
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $ciphertext = openssl_encrypt($data, $cipher, $key, 0, $iv);
    return base64_encode($iv . $ciphertext);
}

if (isset($_POST['register'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $cpass = $_POST['confirm_password'];

    if ($password !== $cpass) {
        $message = "❌ Passwords do not match!";
        $messageType = "error";
    } elseif (strlen($password) < 6) {
        $message = "❌ Password must be at least 6 characters!";
        $messageType = "error";
    } else {
        $email_hash = hash('sha256', strtolower($email));

        $check = $conn->prepare("SELECT email_hash FROM users WHERE email_hash = ? LIMIT 1");
        $check->bind_param("s", $email_hash);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $message = "⚠ This email is already registered!";
            $messageType = "warning";
        } else {
            $encryptedEmail = encryptData($email, $encryption_key, $cipher);
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $conn->prepare("INSERT INTO users (email_id, email_hash, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $encryptedEmail, $email_hash, $hashedPassword);

            if ($stmt->execute()) {
                $message = "✅ Registered successfully! <a href='login.php' style='color:inherit; font-weight:bold;'>Login here</a>";
                $messageType = "success";
            } else {
                $message = "❌ Database error: " . $conn->error;
                $messageType = "error";
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Hostel Registration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #0ea5e9;
            --dark-blue: #075985;
            --bg-light: #e0f2fe;
            --text-main: #0c4a6e;
            --text-secondary: #475569;
            --border-color: #e2e8f0;
            --success-bg: #f0fdf4;
            --error-bg: #fee2e2;
            --warning-bg: #fffbeb;
            --radius: 12px;
            --transition: all 0.3s ease;
            --shadow: 0 10px 25px rgba(0,0,0,0.05);
            --shadow-lg: 0 20px 40px rgba(0,0,0,0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-light);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 15px;
            position: relative;
            overflow-x: hidden;
            font-size: 14px;
        }

        .circle {
            position: absolute;
            background: rgba(14, 165, 233, 0.1);
            border-radius: 50%;
            z-index: 0;
        }
        
        .c1 {
            width: 150px;
            height: 150px;
            top: -50px;
            left: -30px;
        }
        
        .c2 {
            width: 120px;
            height: 120px;
            bottom: 30px;
            right: -30px;
        }

        .container {
            background: white;
            width: 100%;
            max-width: 400px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            z-index: 10;
            overflow: hidden;
            border-top: 4px solid var(--primary-blue);
            position: relative;
        }

        .form-content {
            padding: 30px 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
            color: var(--text-main);
        }
        
        .header h2 {
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            line-height: 1.2;
        }
        
        .header i {
            color: var(--primary-blue);
            font-size: 18px;
        }

        .input-group {
            position: relative;
            margin-bottom: 16px;
            width: 100%;
        }
        
        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
            z-index: 2;
        }
        
        input {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background-color: #f8fafc;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-main);
            transition: var(--transition);
            -webkit-appearance: none;
            appearance: none;
            height: 48px;
        }
        
        input:focus {
            outline: none;
            border-color: var(--primary-blue);
            background-color: white;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
        }
        
        input::placeholder {
            color: var(--text-secondary);
            font-weight: 400;
        }

        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            font-size: 14px;
            height: 48px;
            -webkit-tap-highlight-color: transparent;
        }
        
        .btn-register {
            background-color: var(--primary-blue);
            margin-bottom: 12px;
        }
        
        .btn-register:hover {
            background-color: #0284c7;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
        }
        
        .btn-login {
            background-color: var(--dark-blue);
        }
        
        .btn-login:hover {
            background-color: #0c4a6e;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(7, 89, 133, 0.3);
        }

        .alert {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            text-align: center;
            font-weight: 500;
            border: 1px solid;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert.error {
            background: var(--error-bg);
            color: #b91c1c;
            border-color: #fecaca;
        }
        
        .alert.warning {
            background: var(--warning-bg);
            color: #d97706;
            border-color: #fed7aa;
        }
        
        .alert.success {
            background: var(--success-bg);
            color: #166534;
            border-color: #bbf7d0;
        }
        
        .alert.success a {
            color: inherit;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin-left: 5px;
        }
        
        .alert.success a:hover {
            text-decoration: underline;
        }

        /* Tablet Styles */
        @media (min-width: 768px) {
            body {
                padding: 20px;
                font-size: 15px;
            }
            
            .container {
                max-width: 420px;
            }
            
            .form-content {
                padding: 35px 30px;
            }
            
            .header h2 {
                font-size: 22px;
            }
            
            .header i {
                font-size: 20px;
            }
            
            input {
                padding: 15px 15px 15px 50px;
                font-size: 15px;
                height: 52px;
            }
            
            .input-group i {
                font-size: 16px;
                left: 18px;
            }
            
            .btn {
                padding: 15px;
                font-size: 15px;
                height: 52px;
            }
            
            .alert {
                padding: 16px;
                font-size: 14px;
            }
            
            .c1 {
                width: 250px;
                height: 250px;
                top: -80px;
                left: -50px;
            }
            
            .c2 {
                width: 180px;
                height: 180px;
                bottom: 50px;
                right: -50px;
            }
        }

        /* Desktop Styles */
        @media (min-width: 1024px) {
            .container {
                max-width: 450px;
            }
            
            .form-content {
                padding: 40px 35px;
            }
            
            .header h2 {
                font-size: 24px;
            }
            
            .c1 {
                width: 300px;
                height: 300px;
                top: -100px;
                left: -50px;
            }
            
            .c2 {
                width: 200px;
                height: 200px;
                bottom: 50px;
                right: -50px;
            }
        }

        /* Small Mobile Styles */
        @media (max-width: 360px) {
            .form-content {
                padding: 25px 15px;
            }
            
            .header h2 {
                font-size: 18px;
            }
            
            input {
                padding: 12px 12px 12px 40px;
                font-size: 13px;
                height: 44px;
            }
            
            .input-group i {
                font-size: 13px;
                left: 12px;
            }
            
            .btn {
                padding: 12px;
                font-size: 13px;
                height: 44px;
            }
            
            .alert {
                padding: 12px;
                font-size: 12px;
            }
            
            .c1 {
                width: 120px;
                height: 120px;
                top: -30px;
                left: -20px;
            }
            
            .c2 {
                width: 100px;
                height: 100px;
                bottom: 20px;
                right: -20px;
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
            
            .form-content {
                padding: 20px;
            }
            
            .header {
                margin-bottom: 15px;
            }
            
            .input-group {
                margin-bottom: 12px;
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
        
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Password strength indicator */
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
            color: var(--text-secondary);
            display: none;
        }
        
        .password-strength.visible {
            display: block;
        }

        /* Accessibility improvements */
        input:focus-visible {
            outline: 2px solid var(--primary-blue);
            outline-offset: 2px;
        }
        
        .btn:focus-visible {
            outline: 2px solid var(--dark-blue);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <div class="circle c1"></div>
    <div class="circle c2"></div>

    <div class="container">
        <div class="form-content">
            <div class="header">
                <h2><i class="fas fa-user-plus"></i> Create Account</h2>
            </div>

            <?php if($message): ?>
                <div class="alert <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="registerForm">
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="Enter your email" required autocomplete="email">
                </div>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" placeholder="Enter your password" required autocomplete="new-password">
                </div>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="confirm_password" placeholder="Confirm your password" required autocomplete="new-password">
                </div>
                <button type="submit" name="register" class="btn btn-register" id="submitBtn">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>

            <a href="login.php" class="btn btn-login">
                <i class="fas fa-sign-in-alt"></i> Already registered? Login here
            </a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            const submitBtn = document.getElementById('submitBtn');
            const passwordInput = document.getElementById('password');
            
            // Prevent form resubmission on page refresh
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }
            
            // Form submission handling
            form.addEventListener('submit', function() {
                // Add loading state to button
                submitBtn.classList.add('loading');
                submitBtn.innerHTML = '';
            });
            
            // Password strength indicator (optional enhancement)
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = '';
                let color = '';
                
                if (password.length === 0) {
                    strength = '';
                } else if (password.length < 6) {
                    strength = 'Weak - Password must be at least 6 characters';
                    color = '#ef4444';
                } else if (password.length < 8) {
                    strength = 'Fair';
                    color = '#f59e0b';
                } else if (/[A-Z]/.test(password) && /[0-9]/.test(password) && /[^A-Za-z0-9]/.test(password)) {
                    strength = 'Strong';
                    color = '#10b981';
                } else {
                    strength = 'Good';
                    color = '#3b82f6';
                }
                
                // You can add a strength indicator div if needed
                let strengthIndicator = document.getElementById('passwordStrength');
                if (!strengthIndicator) {
                    strengthIndicator = document.createElement('div');
                    strengthIndicator.id = 'passwordStrength';
                    strengthIndicator.className = 'password-strength';
                    passwordInput.parentNode.appendChild(strengthIndicator);
                }
                
                if (strength) {
                    strengthIndicator.textContent = strength;
                    strengthIndicator.style.color = color;
                    strengthIndicator.classList.add('visible');
                } else {
                    strengthIndicator.classList.remove('visible');
                }
            });
            
            // Auto-focus first input on page load
            const firstInput = form.querySelector('input');
            if (firstInput) {
                setTimeout(() => {
                    firstInput.focus();
                }, 100);
            }
            
            // Touch device optimizations
            if ('ontouchstart' in window) {
                // Add touch-specific classes
                document.body.classList.add('touch-device');
                
                // Improve button touch targets
                const buttons = document.querySelectorAll('.btn');
                buttons.forEach(btn => {
                    btn.style.minHeight = '44px'; // Apple's minimum touch target size
                });
            }
        });
    </script>
</body>
</html>