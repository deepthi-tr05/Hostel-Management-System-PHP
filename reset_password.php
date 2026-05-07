<?php
session_start();
include(__DIR__. '/db.php');

// ✅ Check Session instead of GET to prevent "Invalid Request"
if (!isset($_SESSION['reset_user_id'])) {
    header("Location: forgot_password.php");
    exit;
}

$user_id = $_SESSION['reset_user_id'];
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = $_POST['new_password'];

    if (empty($new_password)) {
        $message = "❌ Please enter a new password!";
    } else {
        // ✅ 1. Hash the password using BCRYPT
        $hashedPassword = password_hash($new_password, PASSWORD_BCRYPT);

        // ✅ 2. Update the 'password' column in the 'users' table
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hashedPassword, $user_id);

        if ($stmt->execute()) {
            // Success response for the AJAX fetch
            echo "SUCCESS";
            
            // ✅ 3. Clear session so the reset link expires
            unset($_SESSION['reset_user_id']);
            exit;
        } else {
            echo "❌ Database Error: " . $conn->error;
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Reset Password - Girls Hostel Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    /* Light Blue Color Scheme */
    --bg-primary: #f0f9ff;
    --bg-secondary: #e0f2fe;
    --bg-card: #ffffff;
    --primary: #0369a1;
    --primary-light: #0ea5e9;
    --primary-dark: #075985;
    --secondary: #38bdf8;
    --accent: #f59e0b;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    
    /* Text Colors */
    --text-primary: #0c4a6e;
    --text-secondary: #475569;
    --text-muted: #64748b;
    
    /* Border & Shadow */
    --border-color: #bae6fd;
    --shadow-color: rgba(3, 105, 161, 0.1);
    
    /* Effects */
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --shadow: 0 4px 6px -1px var(--shadow-color);
    --shadow-lg: 0 10px 15px -3px var(--shadow-color);
    --shadow-xl: 0 20px 25px -5px var(--shadow-color);
    --radius: 16px;
    --gradient: linear-gradient(135deg, var(--primary-light) 0%, var(--secondary) 100%);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
}

body {
    background: var(--bg-primary);
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 15px;
    overflow-x: hidden;
    font-size: 14px;
    width: 100%;
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
    animation: slideUp 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: 1px solid var(--border-color);
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
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    line-height: 1.2;
}

.header h2 i {
    color: var(--primary-light);
    font-size: 18px;
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
    margin-bottom: 8px;
}

.input-container i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    transition: var(--transition);
    z-index: 2;
    font-size: 16px;
}

input {
    width: 100%;
    padding: 14px 15px 14px 50px;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    font-size: 15px;
    background: var(--bg-primary);
    transition: var(--transition);
    color: var(--text-primary);
    height: 50px;
    -webkit-appearance: none;
    appearance: none;
}

input:focus {
    border-color: var(--primary-light);
    background: var(--bg-secondary);
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
    outline: none;
    transform: translateY(-1px);
}

input:focus + i {
    color: var(--primary-light);
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
    margin-top: 10px;
    box-shadow: var(--shadow);
    height: 50px;
    -webkit-tap-highlight-color: transparent;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
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
    animation: slideIn 0.5s ease both;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 500;
    border: 1px solid;
    font-size: 13px;
}

.alert.error {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fecaca;
    animation: shake 0.5s ease;
}

.alert.success {
    background: #f0fdf4;
    color: #16a34a;
    border-color: #bbf7d0;
}

.alert.warning {
    background: #fffbeb;
    color: #d97706;
    border-color: #fed7aa;
}

/* Password strength indicator */
.password-strength {
    height: 4px;
    border-radius: 2px;
    margin-top: 8px;
    background: #e2e8f0;
    overflow: hidden;
    position: relative;
}

.strength-bar {
    height: 100%;
    width: 0%;
    transition: all 0.3s ease;
    border-radius: 2px;
}

.strength-weak { background: #ef4444; width: 33%; }
.strength-medium { background: #f59e0b; width: 66%; }
.strength-strong { background: #10b981; width: 100%; }

.password-requirements {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 12px;
    text-align: left;
}

.password-requirements ul {
    list-style: none;
    padding-left: 0;
    margin: 8px 0;
}

.password-requirements li {
    margin: 4px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
}

.password-requirements .requirement-met {
    color: #10b981;
}

.password-requirements .requirement-not-met {
    color: var(--text-muted);
}

.password-requirements i {
    font-size: 6px;
}

/* Animations */
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

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
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

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
    40% { transform: translateY(-4px); }
    60% { transform: translateY(-2px); }
}

.loader {
    display: inline-block;
    width: 18px;
    height: 18px;
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Success Overlay */
.success-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(240, 249, 255, 0.95);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    animation: fadeIn 0.5s ease;
    padding: 15px;
}

.success-content {
    background: var(--bg-card);
    padding: 30px;
    border-radius: var(--radius);
    box-shadow: var(--shadow-xl);
    text-align: center;
    animation: slideUp 0.6s ease;
    border: 1px solid var(--border-color);
    width: 100%;
    max-width: 400px;
}

.success-content i {
    font-size: 2.5rem;
    color: var(--success);
    margin-bottom: 15px;
    animation: successCheck 0.8s ease;
}

.success-content h3 {
    color: var(--text-primary);
    margin-bottom: 10px;
    font-size: 1.3rem;
}

.success-content p {
    color: var(--text-secondary);
    font-size: 0.95rem;
    margin-bottom: 20px;
}

@keyframes successCheck {
    0% { transform: scale(0); opacity: 0; }
    50% { transform: scale(1.2); opacity: 1; }
    100% { transform: scale(1); opacity: 1; }
}

/* Light Blue Background Pattern */
.background-pattern {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: 
        radial-gradient(circle at 20% 80%, rgba(14, 165, 233, 0.05) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(56, 189, 248, 0.05) 0%, transparent 50%),
        radial-gradient(circle at 40% 40%, rgba(186, 230, 253, 0.08) 0%, transparent 50%);
    z-index: 1;
    pointer-events: none;
}

/* Floating Elements */
.floating-elements {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
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
    0%, 100% { 
        transform: translateY(0px) rotate(0deg) scale(1); 
    }
    33% { 
        transform: translateY(-15px) rotate(120deg) scale(1.1); 
    }
    66% { 
        transform: translateY(8px) rotate(240deg) scale(0.9); 
    }
}

/* Responsive Styles */
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
    
    .password-requirements li {
        font-size: 12px;
    }
    
    .success-content {
        padding: 35px;
    }
    
    .success-content h3 {
        font-size: 1.4rem;
    }
    
    .success-content i {
        font-size: 3rem;
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
    
    .password-requirements li {
        font-size: 10px;
        gap: 6px;
    }
    
    .success-content {
        padding: 25px 20px;
    }
    
    .success-content h3 {
        font-size: 1.2rem;
    }
    
    .success-content i {
        font-size: 2.2rem;
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
    
    .password-requirements {
        margin-top: 8px;
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

/* Touch device optimizations */
.touch-device .btn {
    min-height: 44px; /* Apple's minimum touch target */
}

.touch-device input {
    font-size: 16px;
}

/* Password toggle visibility (optional enhancement) */
.password-toggle {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    cursor: pointer;
    background: none;
    border: none;
    font-size: 16px;
    z-index: 3;
}

.password-toggle:hover {
    color: var(--primary-light);
}
</style>
</head>
<body>
<!-- Light Blue Background Pattern -->
<div class="background-pattern"></div>

<!-- Floating Elements -->
<div class="floating-elements" id="floatingElements"></div>

<div class="container">
    <div class="header">
        <h2><i class="fas fa-key"></i> Reset Password</h2>
        <p>Create a new password for your account</p>
    </div>

    <?php if (!empty($message)): ?>
        <?php 
        $alertClass = 'alert ';
        if (strpos($message, '❌') !== false) {
            $alertClass .= 'error';
        } elseif (strpos($message, '⚠') !== false) {
            $alertClass .= 'warning';
        } else {
            $alertClass .= 'success';
        }
        ?>
        <div class="<?php echo $alertClass; ?>">
            <?php 
            $icon = 'fas fa-exclamation-circle';
            if (strpos($message, '✅') !== false) $icon = 'fas fa-check-circle';
            elseif (strpos($message, '⚠') !== false) $icon = 'fas fa-exclamation-triangle';
            ?>
            <i class="<?php echo $icon; ?>"></i>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" id="resetForm">
        <div class="form-group">
            <div class="input-container">
                <i class="fas fa-lock"></i>
                <input type="password" name="new_password" id="new_password" placeholder="Enter new password" required autocomplete="new-password">
                <button type="button" class="password-toggle" id="togglePassword" aria-label="Show password">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            <div class="password-strength">
                <div class="strength-bar" id="strengthBar"></div>
            </div>
            <div class="password-requirements">
                <ul>
                    <li id="req-length" class="requirement-not-met">
                        <i class="fas fa-circle"></i>
                        At least 8 characters
                    </li>
                    <li id="req-uppercase" class="requirement-not-met">
                        <i class="fas fa-circle"></i>
                        One uppercase letter
                    </li>
                    <li id="req-lowercase" class="requirement-not-met">
                        <i class="fas fa-circle"></i>
                        One lowercase letter
                    </li>
                    <li id="req-number" class="requirement-not-met">
                        <i class="fas fa-circle"></i>
                        One number
                    </li>
                </ul>
            </div>
        </div>
        
        <button type="submit" class="btn" id="resetBtn">
            <i class="fas fa-sync-alt"></i> Reset Password
        </button>
    </form>
</div>

<!-- Success Overlay -->
<div class="success-overlay" id="successOverlay" style="display: none;">
    <div class="success-content">
        <i class="fas fa-check-circle"></i>
        <h3>Password Updated!</h3>
        <p>Redirecting to login page...</p>
        <div class="loader" style="margin-top: 20px; width: 24px; height: 24px;"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Create floating elements
    const floatingContainer = document.getElementById('floatingElements');
    const elementCount = window.innerWidth < 768 ? 4 : 6;
    
    for (let i = 0; i < elementCount; i++) {
        const element = document.createElement('div');
        element.classList.add('floating-element');
        
        const size = window.innerWidth < 768 ? 
            Math.random() * 40 + 30 : 
            Math.random() * 60 + 40;
        const posX = Math.random() * 100;
        const posY = Math.random() * 100;
        const delay = Math.random() * 5;
        const duration = Math.random() * 6 + 6;
        
        element.style.width = `${size}px`;
        element.style.height = `${size}px`;
        element.style.left = `${posX}%`;
        element.style.top = `${posY}%`;
        element.style.animationDelay = `${delay}s`;
        element.style.animationDuration = `${duration}s`;
        
        floatingContainer.appendChild(element);
    }
    
    // Password visibility toggle
    const passwordInput = document.getElementById('new_password');
    const toggleButton = document.getElementById('togglePassword');
    const toggleIcon = toggleButton.querySelector('i');
    
    toggleButton.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        toggleIcon.className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
        toggleButton.setAttribute('aria-label', type === 'password' ? 'Show password' : 'Hide password');
    });
    
    // Password strength indicator
    const strengthBar = document.getElementById('strengthBar');
    
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        // Check requirements
        const hasLength = password.length >= 8;
        const hasUppercase = /[A-Z]/.test(password);
        const hasLowercase = /[a-z]/.test(password);
        const hasNumber = /\d/.test(password);
        const hasSpecial = /[^a-zA-Z\d]/.test(password);
        
        // Update requirement indicators
        document.getElementById('req-length').className = hasLength ? 'requirement-met' : 'requirement-not-met';
        document.getElementById('req-uppercase').className = hasUppercase ? 'requirement-met' : 'requirement-not-met';
        document.getElementById('req-lowercase').className = hasLowercase ? 'requirement-met' : 'requirement-not-met';
        document.getElementById('req-number').className = hasNumber ? 'requirement-met' : 'requirement-not-met';
        
        // Calculate strength
        if (hasLength) strength += 1;
        if (hasUppercase && hasLowercase) strength += 1;
        if (hasNumber) strength += 1;
        if (hasSpecial) strength += 1;
        
        strengthBar.className = 'strength-bar';
        strengthBar.style.width = '0%';
        
        if (strength === 0) {
            strengthBar.style.width = '0%';
        } else if (strength === 1) {
            strengthBar.classList.add('strength-weak');
        } else if (strength === 2) {
            strengthBar.classList.add('strength-medium');
        } else if (strength >= 3) {
            strengthBar.classList.add('strength-strong');
        }
    });
    
    // Form submission handling
    const form = document.getElementById('resetForm');
    const submitBtn = document.getElementById('resetBtn');
    const successOverlay = document.getElementById('successOverlay');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const password = passwordInput.value;
        
        // Basic client-side validation
        if (password.length < 8) {
            showError('Password must be at least 8 characters long');
            return;
        }
        
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="loader"></span> Updating Password...';
        submitBtn.classList.add('loading');
        submitBtn.disabled = true;
        
        const formData = new FormData(form);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            if (data.trim() === "SUCCESS") {
                successOverlay.style.display = 'flex';
                
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 2000);
            } else {
                resetSubmitButton(originalText);
                showError(data.includes('Database Error') ? data : 'Error updating password. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            resetSubmitButton(originalText);
            showError('Network error. Please check your connection and try again.');
        });
    });
    
    function resetSubmitButton(originalText) {
        submitBtn.innerHTML = originalText;
        submitBtn.classList.remove('loading');
        submitBtn.disabled = false;
    }
    
    function showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert error';
        errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + message;
        
        const existingAlerts = document.querySelectorAll('.alert:not(.alert.success)');
        existingAlerts.forEach(alert => alert.remove());
        
        form.parentNode.insertBefore(errorDiv, form);
        
        form.style.animation = 'none';
        void form.offsetWidth; // trigger reflow
        form.style.animation = 'shake 0.5s ease';
        
        passwordInput.focus();
    }
    
    // Auto-focus password input
    setTimeout(() => {
        passwordInput.focus();
    }, 300);
    
    // Touch device optimizations
    if ('ontouchstart' in window) {
        document.body.classList.add('touch-device');
        
        // Improve touch targets
        submitBtn.style.minHeight = '44px';
        toggleButton.style.minWidth = '44px';
        toggleButton.style.minHeight = '44px';
        
        // Add touch feedback
        submitBtn.addEventListener('touchstart', function() {
            if (!this.disabled) {
                this.style.transform = 'scale(0.98)';
            }
        });
        
        submitBtn.addEventListener('touchend', function() {
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