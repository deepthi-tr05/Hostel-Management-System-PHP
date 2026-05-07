<?php
session_start();
include "db.php"; 

$error = "";

// AES encryption settings (must match your registration)
$encryption_key = "YOUR_ENCRYPTION_KEY_MIN_32_CHARS";
$cipher = "AES-256-CBC";

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

// Check if it's an AJAX/Post request
if (isset($_POST['login'])) {
    // Clear any previous output to ensure clean response for AJAX
    if (ob_get_length()) ob_clean();

    $email_input = trim($_POST['username']);
    $password_input = trim($_POST['password']);

    if (empty($email_input) || empty($password_input)) {
        echo "❌ Email and password are required!";
        exit;
    } else {
        // Fetch all users to find the matching decrypted email
        $stmt = $conn->prepare("SELECT id, email_id, password FROM users");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $found = false;
        $emailExists = false;

        while ($row = $result->fetch_assoc()) {
            $dbEmail = decryptData($row['email_id'], $encryption_key, $cipher);
            
            if ($dbEmail === $email_input) {
                $emailExists = true;
                // Verify against the hashed password stored in DB
                if (password_verify($password_input, $row['password'])) {
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['username'] = $dbEmail;
                    $_SESSION['logged_in'] = true;
                    $_SESSION['login_time'] = time();
                    
                    $found = true;
                    echo "SUCCESS";
                    exit;
                }
                break; // Email found, no need to check other users
            }
        }

        if (!$emailExists) {
            echo "❌ No user found with this email!";
        } else if (!$found) {
            echo "❌ Invalid password!";
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Girls Hostel Registration Portal - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #f0f9ff; --bg-secondary: #e0f2fe; --bg-card: #ffffff;
            --primary: #0369a1; --primary-light: #0ea5e9; --primary-dark: #075985;
            --secondary: #38bdf8; --accent: #f59e0b; --success: #10b981;
            --danger: #ef4444; --warning: #f59e0b; --text-primary: #0c4a6e;
            --text-secondary: #475569; --text-muted: #64748b; --border-color: #bae6fd;
            --shadow-color: rgba(3, 105, 161, 0.1); --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --shadow: 0 4px 6px -1px var(--shadow-color); --shadow-lg: 0 10px 15px -3px var(--shadow-color);
            --shadow-xl: 0 20px 25px -5px var(--shadow-color); --radius: 16px;
            --gradient: linear-gradient(135deg, var(--primary-light) 0%, var(--secondary) 100%);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { display: flex; justify-content: center; align-items: center; height: 100vh; height: calc(var(--vh, 1vh) * 100); overflow: hidden; background-color: var(--bg-primary); }

        /* Reuse your existing CSS here */
        .container { padding: 40px 35px; border-radius: var(--radius); width: 420px; box-shadow: var(--shadow-xl); position: relative; z-index: 2; animation: slideUp 0.8s ease; border: 1px solid var(--border-color); background: white; }
        .container::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: var(--gradient); border-radius: var(--radius) var(--radius) 0 0; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h2 { color: var(--text-primary); font-size: 1.5rem; display: flex; align-items: center; justify-content: center; gap: 12px; }
        .form-group { margin-bottom: 25px; }
        .input-container { position: relative; }
        .input-container i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); transition: var(--transition); z-index: 2; }
        input { width: 100%; padding: 14px 15px 14px 50px; border: 2px solid var(--border-color); border-radius: 10px; background: var(--bg-primary); transition: var(--transition); }
        input:focus { border-color: var(--primary-light); background: var(--bg-secondary); outline: none; }
        .btn { width: 100%; background: var(--gradient); color: white; padding: 14px; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .register-btn { background: var(--primary-dark); color: white; padding: 14px; border-radius: 10px; display: flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none; margin-top: 15px; }
        .alert { padding: 16px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; border: 1px solid; animation: slideIn 0.5s ease; }
        .alert.error { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        .note { background: #fffbeb; padding: 16px; font-size: 13px; margin-top: 20px; border-left: 4px solid var(--warning); border-radius: 10px; color: #92400e; }
        .background-pattern { position: fixed; inset: 0; background-image: radial-gradient(circle at 20% 80%, rgba(14, 165, 233, 0.05) 0%, transparent 50%); z-index: 1; pointer-events: none; }
        .floating-elements { position: fixed; inset: 0; pointer-events: none; z-index: 0; }
        .floating-element { position: absolute; background: rgba(14, 165, 233, 0.08); border-radius: 50%; animation: float 8s infinite; }
        .redirect-overlay { position: fixed; inset: 0; background: rgba(240, 249, 255, 0.95); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .loader { width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: white; animation: spin 1s linear infinite; }

        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-20px); } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }

        /* Responsive Mobile View */
        @media only screen and (max-width: 768px) {
            body {
                height: 100%;
                min-height: 100vh;
                min-height: calc(var(--vh, 1vh) * 100);
                overflow-y: auto;
                padding: 20px;
                display: flex;
                align-items: flex-start;
                background-color: var(--bg-primary);
            }
            
            .container {
                width: 100%;
                max-width: 100%;
                padding: 25px 20px;
                margin: 0;
                border-radius: var(--radius);
                box-shadow: var(--shadow);
                animation: slideUp 0.5s ease;
                border: 1px solid var(--border-color);
            }
            
            .header h2 {
                font-size: 1.3rem;
                flex-direction: column;
                gap: 8px;
                text-align: center;
            }
            
            .header h2 i {
                font-size: 1.8rem;
                margin-bottom: 5px;
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            input {
                padding: 16px 15px 16px 50px;
                font-size: 16px;
                border-radius: 12px;
            }
            
            .input-container i {
                left: 18px;
                font-size: 1.1rem;
            }
            
            .btn, .register-btn {
                padding: 16px;
                font-size: 16px;
                border-radius: 12px;
                margin-top: 10px;
            }
            
            .btn i, .register-btn i {
                font-size: 1.1rem;
            }
            
            .note {
                padding: 14px;
                font-size: 12.5px;
                margin-top: 25px;
                line-height: 1.5;
            }
            
            .note ul {
                padding-left: 20px;
                margin-top: 8px;
            }
            
            .note li {
                margin-bottom: 5px;
            }
            
            .alert {
                padding: 14px;
                font-size: 14px;
                flex-direction: column;
                text-align: center;
                gap: 8px;
            }
            
            .alert i {
                font-size: 1.5rem;
            }
            
            .redirect-overlay {
                padding: 20px;
            }
            
            .redirect-overlay > div {
                width: 90%;
                max-width: 320px;
                padding: 30px 20px;
                margin: 0 auto;
            }
            
            .floating-elements {
                display: none;
            }
            
            .background-pattern {
                opacity: 0.5;
            }
            
            @media only screen and (max-width: 480px) {
                body {
                    padding: 15px;
                }
                
                .container {
                    padding: 22px 18px;
                }
                
                .header h2 {
                    font-size: 1.2rem;
                }
                
                input {
                    padding: 15px 15px 15px 50px;
                }
                
                .btn, .register-btn {
                    padding: 15px;
                }
                
                a[href="forgot_password.php"] {
                    font-size: 13.5px;
                    display: block;
                    margin-top: 10px;
                }
            }
            
            @media only screen and (max-height: 600px) and (orientation: landscape) {
                body {
                    padding: 15px;
                    align-items: flex-start;
                    min-height: auto;
                }
                
                .container {
                    max-width: 500px;
                    margin: 20px auto;
                }
                
                .header {
                    margin-bottom: 20px;
                }
                
                .form-group {
                    margin-bottom: 15px;
                }
                
                .note {
                    margin-top: 15px;
                    padding: 12px;
                }
            }
        }
        
        @media only screen and (min-width: 769px) and (max-width: 1024px) {
            .container {
                width: 380px;
            }
        }
    </style>
</head>
<body>

<div class="background-pattern"></div>
<div class="floating-elements" id="floatingElements"></div>

<div class="container">
    <div class="header">
        <h2><i class="fas fa-home"></i> Smt. Rukminiyamma Girls Hostel</h2>
    </div>

    <div id="errorDisplay"></div>

    <form method="POST" id="loginForm">
        <div class="form-group">
            <div class="input-container">
                <i class="fas fa-envelope"></i>
                <input type="text" name="username" placeholder="Enter your email" required>
            </div>
        </div>
        
        <div class="form-group">
            <div class="input-container">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>
        </div>
        
        <button type="submit" name="login" class="btn" id="loginBtn">
            <i class="fas fa-sign-in-alt"></i> Login
        </button>
    </form>
    
    <div style="text-align: center; margin: 15px 0;">
        <a href="forgot_password.php" style="color:var(--primary-light); text-decoration:none; font-size:14px;">Forgot Password?</a>
    </div>
    
    <a href="new-register.php" class="register-btn">
        <i class="fas fa-user-plus"></i> New user? Click here to register
    </a>
    
    <div class="note">
        <b>Kindly Note:</b>
        <ul>
            <li>Hostel available for students residing outside 100 KMs.</li>
            <li>Fill details carefully with correct information.</li>
        </ul>
    </div>
</div>

<div class="redirect-overlay" id="redirectOverlay">
    <div style="text-align: center; background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
        <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--success);"></i>
        <h3>Login Successful!</h3>
        <p>Redirecting to registration page...</p>
        <div class="loader" style="margin-top: 20px; border-top-color: var(--primary);"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Decorative Background
    const floatingContainer = document.getElementById('floatingElements');
    for (let i = 0; i < 6; i++) {
        const el = document.createElement('div');
        el.className = 'floating-element';
        const size = Math.random() * 80 + 40;
        el.style.width = el.style.height = `${size}px`;
        el.style.left = `${Math.random() * 100}%`;
        el.style.top = `${Math.random() * 100}%`;
        el.style.animationDelay = `${Math.random() * 5}s`;
        floatingContainer.appendChild(el);
    }

    // 2. Form Submission Handling
    const form = document.getElementById('loginForm');
    const submitBtn = document.getElementById('loginBtn');
    const errorDisplay = document.getElementById('errorDisplay');
    const redirectOverlay = document.getElementById('redirectOverlay');

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const originalBtnHtml = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="loader"></span> Authenticating...';
        submitBtn.disabled = true;
        errorDisplay.innerHTML = ''; 

        const formData = new FormData(form);
        formData.append('login', '1');

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            const result = data.trim();
            
            if (result === "SUCCESS") {
                redirectOverlay.style.display = 'flex';
                setTimeout(() => { window.location.href = 'register.php'; }, 1500);
            } else {
                // Show Error
                submitBtn.innerHTML = originalBtnHtml;
                submitBtn.disabled = false;
                
                errorDisplay.innerHTML = `
                    <div class="alert error">
                        <i class="fas fa-exclamation-circle"></i> ${result}
                    </div>`;
                
                form.style.animation = 'shake 0.5s ease';
                setTimeout(() => { form.style.animation = ''; }, 500);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            submitBtn.innerHTML = originalBtnHtml;
            submitBtn.disabled = false;
            errorDisplay.innerHTML = '<div class="alert error">Connection error. Try again.</div>';
        });
    });

    // Mobile-specific optimizations
    function detectMobile() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }
    
    if (detectMobile()) {
        // Add touch feedback for mobile
        const buttons = document.querySelectorAll('.btn, .register-btn');
        buttons.forEach(button => {
            button.addEventListener('touchstart', function() {
                this.style.opacity = '0.8';
            });
            button.addEventListener('touchend', function() {
                this.style.opacity = '1';
            });
        });
        
        // Prevent double-tap zoom on buttons
        buttons.forEach(button => {
            button.addEventListener('touchstart', function(e) {
                if (e.touches.length > 1) {
                    e.preventDefault();
                }
            }, { passive: false });
        });
        
        // Adjust viewport height for mobile browsers
        function setVH() {
            let vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        }
        
        setVH();
        window.addEventListener('resize', setVH);
        window.addEventListener('orientationchange', setVH);
    }
});
</script>
</body>
</html>