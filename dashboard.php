<?php
session_start();
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit();
}

// ---------------- CONFIG ----------------
$ADMIN_USER = "YOUR_ADMIN_USERNAME";          // TODO: Set in production
$ADMIN_PASS = "YOUR_ADMIN_PASSWORD";          // TODO: Set in production
$ENCRYPTION_KEY = "YOUR_ENCRYPTION_KEY_MIN_32_CHARS"; // TODO: Must match admin.php & register.php

// ---------------- DATABASE ----------------
$conn = new mysqli("YOUR_DB_HOST", "YOUR_DB_USER", "YOUR_DB_PASSWORD", "YOUR_DB_NAME");
if ($conn->connect_error) die("DB Error: " . $conn->connect_error);

// ---------------- DECRYPT FUNCTION ----------------
function decryptData($data, $key) {
    if ($data === "" || $data === null) return $data;
    $method = "AES-256-CBC";
    $iv = substr(hash("sha256", $key), 0, 16);
    $dec = openssl_decrypt($data, $method, $key, 0, $iv);
    return ($dec === "" || $dec === false) ? $data : $dec;
}

/* --------------------------------------------------
   LOGIN PAGE - LIGHT SKY BLUE THEME
-------------------------------------------------- */
if (!isset($_SESSION['admin_logged_in'])) {
    $msg = "";
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_POST['username'], $_POST['password']) && $_POST['username'] === $ADMIN_USER && $_POST['password'] === $ADMIN_PASS) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $_POST['username'];
            header("Location: dashboard.php");
            exit();
        } else {
            $msg = "❌ Invalid Login";
        }
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset="utf-8">
    <title>Admin Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        /* Light Sky Blue Color Scheme */
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
        height: 100vh;
        overflow: hidden;
    }

    .container {
        background: var(--bg-card);
        padding: 40px 35px;
        border-radius: var(--radius);
        width: 420px;
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
        margin-bottom: 30px;
    }

    .header h2 {
        color: var(--text-primary);
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        animation: fadeIn 0.8s ease 0.2s both;
    }

    .header h2 i {
        color: var(--primary-light);
        animation: bounce 2s ease-in-out infinite;
    }

    .form-group {
        margin-bottom: 25px;
        animation: slideIn 0.6s ease 0.5s both;
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
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin-top: 10px;
        box-shadow: var(--shadow);
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    .alert {
        padding: 16px;
        border-radius: 10px;
        margin-bottom: 20px;
        animation: slideIn 0.5s ease both;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        font-weight: 500;
        border: 1px solid;
    }

    .alert.error {
        background: #fef2f2;
        color: #dc2626;
        border-color: #fecaca;
        animation: shake 0.5s ease;
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
            transform: translateX(-20px);
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
        40% { transform: translateY(-6px); }
        60% { transform: translateY(-3px); }
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
            transform: translateY(-20px) rotate(120deg) scale(1.1); 
        }
        66% { 
            transform: translateY(10px) rotate(240deg) scale(0.9); 
        }
    }

    @media (max-width: 480px) {
        .container {
            width: 90%;
            padding: 30px 25px;
            margin: 20px;
        }
        
        .header h2 {
            font-size: 1.3rem;
        }
        
        input {
            padding: 12px 12px 12px 45px;
        }
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
            <h2><i class="fas fa-lock"></i> Admin Login</h2>
        </div>

        <?php if(!empty($msg)): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <div class="input-container">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="Enter username" required>
                </div>
            </div>
            
            <div class="form-group">
                <div class="input-container">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Enter password" required>
                </div>
            </div>
            
            <button type="submit" class="btn">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Create floating elements
        const floatingContainer = document.getElementById('floatingElements');
        const elementCount = 6;
        
        for (let i = 0; i < elementCount; i++) {
            const element = document.createElement('div');
            element.classList.add('floating-element');
            
            const size = Math.random() * 100 + 50;
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
    });
    </script>
    </body>
    </html>
    <?php
    exit();
}

// Get unique states and admission types for filters from register table (only approved)
$states_result = $conn->query("SELECT DISTINCT state FROM register WHERE status = 'approved'");
$states = [];
while ($row = $states_result->fetch_assoc()) {
    $state = decryptData($row['state'], $ENCRYPTION_KEY);
    if (!empty($state)) {
        $states[] = $state;
    }
}
$states = array_unique($states);
sort($states);

// Get state distribution data for the chart
$state_distribution = [];
$state_count_result = $conn->query("SELECT state, COUNT(*) as count FROM register WHERE status = 'approved' GROUP BY state");
while ($row = $state_count_result->fetch_assoc()) {
    $state = decryptData($row['state'], $ENCRYPTION_KEY);
    if (!empty($state)) {
        $state_distribution[$state] = $row['count'];
    }
}

$admission_result = $conn->query("SELECT DISTINCT admission FROM register WHERE status = 'approved'");
$admissions = [];
while ($row = $admission_result->fetch_assoc()) {
    $admission = decryptData($row['admission'], $ENCRYPTION_KEY);
    if (!empty($admission)) {
        $admissions[] = $admission;
    }
}
$admissions = array_unique($admissions);
sort($admissions);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Approved Students Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- ===== Light Sky Blue Theme CSS ===== -->
<style>
:root{
  /* Light Sky Blue Color Scheme */
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
  color: var(--text-primary);
  line-height: 1.6;
  min-height: 100vh;
}

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
  z-index: -1;
}

.floating-elements {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: -1;
}

.container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 20px;
}

.card {
  background: var(--bg-card);
  border-radius: var(--radius);
  box-shadow: var(--shadow-xl);
  border: 1px solid var(--border-color);
  overflow: hidden;
  position: relative;
  margin-bottom: 30px;
}

.card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 4px;
  background: var(--gradient);
  border-radius: var(--radius) var(--radius) 0 0;
}

.card-header {
  background: var(--bg-secondary);
  padding: 30px 40px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 20px;
  border-bottom: 1px solid var(--border-color);
}

.card-title h2 {
  color: var(--text-primary);
  font-size: 28px;
  font-weight: 700;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.card-sub {
  color: var(--text-secondary);
  font-size: 16px;
  font-weight: 500;
}

.controls {
  display: flex;
  align-items: center;
  gap: 15px;
  flex-wrap: wrap;
}

.input-sm {
  display: flex;
  align-items: center;
  background: var(--bg-primary);
  padding: 10px 20px;
  border-radius: 10px;
  border: 2px solid var(--border-color);
  transition: var(--transition);
}

.input-sm:focus-within {
  border-color: var(--primary-light);
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
}

.input-sm input {
  background: transparent;
  border: none;
  color: var(--text-primary);
  font-size: 14px;
  outline: none;
  width: 200px;
}

.input-sm input::placeholder {
  color: var(--text-muted);
}

.btn {
  padding: 12px 24px;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: var(--transition);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 44px;
}

.btn:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg);
}

.btn-admin {
  background: var(--primary);
  color: white;
}

.btn-logout {
  background: var(--danger);
  color: white;
  padding: 12px 24px;
  border-radius: 10px;
  text-decoration: none;
  font-weight: 600;
  transition: var(--transition);
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 44px;
}

.btn-logout:hover {
  background: #dc2626;
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg);
}

.filter-controls {
  padding: 25px 40px;
  background: var(--bg-secondary);
  border-bottom: 1px solid var(--border-color);
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
  align-items: center;
}

.filter-btn {
  background: var(--bg-card);
  border: 2px solid var(--border-color);
  padding: 12px 20px;
  border-radius: 10px;
  cursor: pointer;
  font-size: 14px;
  transition: var(--transition);
  display: flex;
  align-items: center;
  gap: 10px;
  height: 44px;
  font-weight: 600;
  color: var(--text-secondary);
}

.filter-btn:hover {
  border-color: var(--primary);
  color: var(--primary);
  transform: translateY(-2px);
  box-shadow: var(--shadow);
}

.filter-dropdown {
  position: relative;
  display: inline-block;
}

.filter-options {
  display: none;
  position: absolute;
  background: var(--bg-card);
  min-width: 220px;
  max-height: 300px;
  overflow-y: auto;
  box-shadow: var(--shadow-xl);
  border-radius: var(--radius);
  border: 2px solid var(--border-color);
  z-index: 100;
  margin-top: 8px;
}

.filter-options.show {
  display: block;
}

.filter-option {
  padding: 14px 20px;
  cursor: pointer;
  transition: var(--transition);
  border-bottom: 1px solid var(--border-color);
  font-weight: 500;
  color: var(--text-primary);
}

.filter-option:last-child {
  border-bottom: none;
}

.filter-option:hover {
  background: var(--bg-secondary);
  color: var(--primary);
}

.table-wrap {
  overflow-x: auto;
  padding: 0 40px;
}

.table {
  width: 100%;
  border-collapse: collapse;
  margin: 25px 0;
}

.table th {
  background: var(--bg-secondary);
  color: var(--text-primary);
  padding: 18px 15px;
  text-align: left;
  font-weight: 600;
  border-bottom: 2px solid var(--border-color);
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.table td {
  padding: 16px 15px;
  border-bottom: 1px solid var(--border-color);
  font-size: 14px;
  font-weight: 500;
  color: var(--text-primary);
}

.table tbody tr {
  transition: var(--transition);
}

.table tbody tr:hover {
  background: var(--bg-secondary);
  transform: translateY(-2px);
  box-shadow: var(--shadow);
}

.id-col {
  font-weight: 700;
  color: var(--primary);
  text-align: center;
}

.cell-muted {
  color: var(--text-muted);
  font-style: italic;
}

.btn-view {
  background: var(--primary);
  color: white;
  padding: 8px 16px;
  font-size: 12px;
  height: 36px;
  border-radius: 8px;
}

.table-footer {
  padding: 25px 40px;
  background: var(--bg-secondary);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 15px;
}

.page-info {
  color: var(--text-muted);
  font-size: 14px;
  font-weight: 600;
}

.page-btn {
  background: var(--primary);
  color: white;
  border: none;
  padding: 12px 24px;
  border-radius: 10px;
  cursor: pointer;
  transition: var(--transition);
  display: flex;
  align-items: center;
  gap: 10px;
  height: 44px;
  font-weight: 600;
}

.page-btn:hover {
  background: var(--primary-dark);
  transform: translateY(-2px);
  box-shadow: var(--shadow);
}

/* Status Badges */
.status-badge {
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  display: inline-block;
  letter-spacing: 0.5px;
}

.status-approved {
  background: linear-gradient(135deg, #d1fae5, #10b981);
  color: #065f46;
}

/* Success/Error Messages */
.alert {
  padding: 20px 25px;
  margin: 25px 40px;
  border-radius: var(--radius);
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 12px;
  border-left: 6px solid;
  box-shadow: var(--shadow);
}

.alert-success {
  background: linear-gradient(135deg, #d1fae5, #ecfdf5);
  color: #065f46;
  border-left-color: #10b981;
}

.alert-error {
  background: linear-gradient(135deg, #fee2e2, #fef2f2);
  color: #991b1b;
  border-left-color: #ef4444;
}

/* Chart Section */
.chart-section {
  padding: 30px 40px;
  border-bottom: 1px solid var(--border-color);
}

.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
  flex-wrap: wrap;
  gap: 15px;
}

.chart-title {
  font-size: 22px;
  font-weight: 700;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 12px;
}

.chart-controls {
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
}

.chart-type-btn {
  background: var(--bg-card);
  border: 2px solid var(--border-color);
  padding: 10px 20px;
  border-radius: 10px;
  cursor: pointer;
  font-size: 14px;
  transition: var(--transition);
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  color: var(--text-secondary);
}

.chart-type-btn:hover {
  border-color: var(--primary);
  color: var(--primary);
}

.chart-type-btn.active {
  background: var(--primary);
  color: white;
  border-color: var(--primary);
}

.chart-container {
  position: relative;
  height: 400px;
  width: 100%;
}

/* Floating animation for elements */
@keyframes float {
  0%, 100% {
    transform: translateY(0px);
  }
  50% {
    transform: translateY(-10px);
  }
}

.floating-element {
  animation: float 6s ease-in-out infinite;
}

@media (max-width: 768px) {
  .card-header {
    flex-direction: column;
    align-items: flex-start;
    padding: 25px 30px;
  }
  
  .controls {
    width: 100%;
    justify-content: flex-start;
  }
  
  .table-wrap {
    padding: 0 20px;
  }
  
  .table {
    font-size: 12px;
  }
  
  .table th,
  .table td {
    padding: 12px 8px;
  }
  
  .chart-header {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .chart-container {
    height: 300px;
  }
}
</style>
</head>
<body>

<!-- Light Sky Blue Background Pattern -->
<div class="background-pattern"></div>

<!-- Floating Elements -->
<div class="floating-elements" id="floatingElements"></div>

<div class="container">
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <h2><i class="fas fa-table"></i> Approved Students Dashboard</h2>
        <div class="card-sub">View all approved student records from main database</div>
      </div>

      <div class="controls">
        <div class="input-sm">
          <i class="fas fa-search" style="color: var(--text-muted); margin-right: 10px;"></i>
          <input placeholder="Search by ID or Name..." id="quickSearch" onkeyup="filterTable()">
        </div>
        
        <!-- Admin Dashboard Button -->
        <a href="admin.php" class="btn btn-admin">
          <i class="fas fa-tachometer-alt"></i> Admin Dashboard
        </a>
      </div>

      <a href="dashboard.php?logout=true" class="btn-logout">
        <i class="fas fa-sign-out-alt"></i> Logout
      </a>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <!-- Chart Section -->
    <div class="chart-section">
      <div class="chart-header">
        <h3 class="chart-title">
          <i class="fas fa-chart-bar"></i> Students Distribution by State
        </h3>
        <div class="chart-controls">
          <button class="chart-type-btn active" onclick="changeChartType('bar')">
            <i class="fas fa-chart-bar"></i> Bar Chart
          </button>
          <button class="chart-type-btn" onclick="changeChartType('pie')">
            <i class="fas fa-chart-pie"></i> Pie Chart
          </button>
          <button class="chart-type-btn" onclick="changeChartType('doughnut')">
            <i class="fas fa-chart-pie"></i> Doughnut
          </button>
        </div>
      </div>
      <div class="chart-container">
        <canvas id="stateChart"></canvas>
      </div>
    </div>

    <!-- Filter Controls -->
    <div class="filter-controls">
      <!-- State Filter -->
      <div class="filter-dropdown">
        <button class="filter-btn" id="stateFilterBtn" onclick="toggleFilter('state')">
          <i class="fas fa-map-marker-alt"></i> State
        </button>
        <div class="filter-options" id="stateFilter">
          <div class="filter-option" onclick="filterByState('all')">All States</div>
          <?php foreach ($states as $state): ?>
            <div class="filter-option" onclick="filterByState('<?php echo htmlspecialchars($state); ?>')"><?php echo htmlspecialchars($state); ?></div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Admission Filter -->
      <div class="filter-dropdown">
        <button class="filter-btn" id="admissionFilterBtn" onclick="toggleFilter('admission')">
          <i class="fas fa-graduation-cap"></i> Admission
        </button>
        <div class="filter-options" id="admissionFilter">
          <div class="filter-option" onclick="filterByAdmission('all')">All Admissions</div>
          <?php foreach ($admissions as $admission): ?>
            <div class="filter-option" onclick="filterByAdmission('<?php echo htmlspecialchars($admission); ?>')"><?php echo htmlspecialchars($admission); ?></div>
          <?php endforeach; ?>
        </div>
      </div>

      <button class="filter-btn" onclick="clearAllFilters()">
        <i class="fas fa-times"></i> Clear Filters
      </button>
    </div>

    <div class="table-wrap">
      <table class="table" role="table" aria-label="Approved students">
        <thead>
          <tr>
            <th class="id-col">ID</th>
            <th>Date</th>
            <th>Student</th>
            <th>Status</th>
            <th>Mobile1</th>
            <th>Mobile2</th>
            <th>Email</th>
            <th>State</th>
            <th>District</th>
            <th>Admission</th>
            <th>Degree</th>
            <th>Pincode</th>
            <th>Admin Notes</th>
            <th>Front Aadhaar</th>
            <th>Back Aadhaar</th>
          </tr>
        </thead>
        <tbody>

<?php
/* --------------------------------------------------
   Show approved students from register table
-------------------------------------------------- */
$rows_stmt = $conn->prepare("SELECT * FROM register WHERE status = 'approved' ORDER BY created_at DESC");
$rows_stmt->execute();
$rows = $rows_stmt->get_result();

while ($row = $rows->fetch_assoc()) {
    // decrypt then escape for safe HTML output
    $id = htmlspecialchars($row['id']);
    $student = htmlspecialchars(decryptData($row['student_name'],$ENCRYPTION_KEY));
    $m1 = htmlspecialchars(decryptData($row['mobile1'],$ENCRYPTION_KEY));
    $m2 = htmlspecialchars(decryptData($row['mobile2'],$ENCRYPTION_KEY));
    $email = htmlspecialchars(decryptData($row['email'],$ENCRYPTION_KEY));
    $state = htmlspecialchars(decryptData($row['state'],$ENCRYPTION_KEY));
    $district = htmlspecialchars(decryptData($row['district'],$ENCRYPTION_KEY));
    $admission = htmlspecialchars(decryptData($row['admission'],$ENCRYPTION_KEY));
    $degree = htmlspecialchars(decryptData($row['degree'],$ENCRYPTION_KEY));
    $pincode = htmlspecialchars(decryptData($row['pincode'],$ENCRYPTION_KEY));
    $front_aadhaar = $row['front_aadhaar'];
    $back_aadhaar = $row['back_aadhaar'];
    $admin_notes = $row['admin_notes'];
    $status = $row['status'];
    $created_date = date("d-m-Y", strtotime($row['created_at']));

    echo "<tr data-state='{$state}' data-admission='{$admission}'>";
    echo "<td class='id-col' style='text-align:center; font-weight: 700; color: var(--primary);'>{$id}</td>";
    echo "<td style='white-space:nowrap; font-weight: 600;'>{$created_date}</td>";
    echo "<td style='font-weight: 600; color: var(--text-primary);'>{$student}</td>";
    echo "<td><span class='status-badge status-approved'>{$status}</span></td>";
    echo "<td style='font-weight: 500;'>{$m1}</td>";
    echo "<td style='font-weight: 500;'>{$m2}</td>";
    echo "<td style='font-weight: 500;'>{$email}</td>";
    echo "<td style='font-weight: 600; color: var(--primary);'>{$state}</td>";
    echo "<td style='font-weight: 500;'>{$district}</td>";
    echo "<td style='font-weight: 600; color: var(--secondary);'>{$admission}</td>";
    echo "<td style='font-weight: 500;'>{$degree}</td>";
    echo "<td style='font-weight: 600; color: var(--primary);'>{$pincode}</td>";
    echo "<td style='max-width: 200px; word-wrap: break-word;'>" . htmlspecialchars($admin_notes ?? 'No notes') . "</td>";

    // Front Aadhaar column
    if (!empty($front_aadhaar)) {
        $safeFrontAad = htmlspecialchars($front_aadhaar);
        echo "<td><a href='{$safeFrontAad}' target='_blank' rel='noopener'><button class='btn btn-view'><i class='fas fa-eye'></i> View</button></a></td>";
    } else {
        echo "<td class='cell-muted'>No File</td>";
    }

    // Back Aadhaar column
    if (!empty($back_aadhaar)) {
        $safeBackAad = htmlspecialchars($back_aadhaar);
        echo "<td><a href='{$safeBackAad}' target='_blank' rel='noopener'><button class='btn btn-view'><i class='fas fa-eye'></i> View</button></a></td>";
    } else {
        echo "<td class='cell-muted'>No File</td>";
    }
    
    echo "</tr>";
}

$rows_stmt->close();
$conn->close();
?>

        </tbody>
      </table>
    </div>

    <div class="table-footer">
      <div class="page-info">Showing all approved records</div>
      <div class="pager">
        <button class="page-btn" onclick="location.href='dashboard.php'"><i class="fas fa-sync-alt"></i> Refresh</button>
      </div>
    </div>
  </div>
</div>

<!-- FRONT-END SCRIPT -->
<script>
// State distribution data from PHP
const stateData = <?php echo json_encode($state_distribution); ?>;
let currentChart = null;

// Initialize the chart
function initChart(type = 'bar') {
    const ctx = document.getElementById('stateChart').getContext('2d');
    
    // Destroy previous chart if exists
    if (currentChart) {
        currentChart.destroy();
    }
    
    // Prepare data
    const labels = Object.keys(stateData);
    const data = Object.values(stateData);
    
    // Generate colors based on theme
    const backgroundColors = generateColors(labels.length);
    
    // Chart configuration
    const config = {
        type: type,
        data: {
            labels: labels,
            datasets: [{
                label: 'Number of Students',
                data: data,
                backgroundColor: backgroundColors,
                borderColor: backgroundColors.map(color => color.replace('0.7', '1')),
                borderWidth: 2,
                borderRadius: 8,
                hoverOffset: 15
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: {
                            size: 14,
                            family: "'Segoe UI', system-ui, -apple-system, sans-serif"
                        },
                        color: '#0c4a6e'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#0c4a6e',
                    bodyColor: '#475569',
                    borderColor: '#bae6fd',
                    borderWidth: 1,
                    cornerRadius: 8,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            return `${context.label}: ${context.parsed} students`;
                        }
                    }
                }
            },
            scales: type === 'bar' ? {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(186, 230, 253, 0.5)'
                    },
                    ticks: {
                        color: '#475569',
                        font: {
                            size: 12
                        }
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(186, 230, 253, 0.3)'
                    },
                    ticks: {
                        color: '#475569',
                        font: {
                            size: 12
                        }
                    }
                }
            } : {}
        }
    };
    
    // Create chart
    currentChart = new Chart(ctx, config);
}

// Generate colors based on theme
function generateColors(count) {
    const baseColors = [
        'rgba(3, 105, 161, 0.7)',
        'rgba(14, 165, 233, 0.7)',
        'rgba(56, 189, 248, 0.7)',
        'rgba(186, 230, 253, 0.7)',
        'rgba(245, 158, 11, 0.7)',
        'rgba(16, 185, 129, 0.7)',
        'rgba(239, 68, 68, 0.7)',
        'rgba(139, 92, 246, 0.7)',
        'rgba(236, 72, 153, 0.7)',
        'rgba(249, 115, 22, 0.7)'
    ];
    
    let colors = [];
    for (let i = 0; i < count; i++) {
        colors.push(baseColors[i % baseColors.length]);
    }
    return colors;
}

// Change chart type
function changeChartType(type) {
    // Update active button
    document.querySelectorAll('.chart-type-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Reinitialize chart with new type
    initChart(type);
}

let currentFilters = {
    state: 'all',
    admission: 'all'
};

// Store original button content
const originalButtons = {
    state: '<i class="fas fa-map-marker-alt"></i> State',
    admission: '<i class="fas fa-graduation-cap"></i> Admission'
};

// Filter Functions
function toggleFilter(type) {
    const dropdowns = document.querySelectorAll('.filter-options');
    dropdowns.forEach(dropdown => {
        if (dropdown.id !== type + 'Filter') {
            dropdown.classList.remove('show');
        }
    });
    
    const currentDropdown = document.getElementById(type + 'Filter');
    currentDropdown.classList.toggle('show');
}

function filterByState(state) {
    currentFilters.state = state;
    applyFilters();
    updateFilterButton('state', state);
    document.getElementById('stateFilter').classList.remove('show');
}

function filterByAdmission(admission) {
    currentFilters.admission = admission;
    applyFilters();
    updateFilterButton('admission', admission);
    document.getElementById('admissionFilter').classList.remove('show');
}

function updateFilterButton(type, value) {
    const buttonId = type + 'FilterBtn';
    const button = document.getElementById(buttonId);
    
    if (value !== 'all') {
        button.classList.add('active-filter');
        let displayValue = value;
        button.innerHTML = `<i class="fas fa-${getFilterIcon(type)}"></i> ${getFilterLabel(type)}: ${displayValue}`;
    } else {
        button.classList.remove('active-filter');
        button.innerHTML = originalButtons[type];
    }
}

function getFilterIcon(type) {
    const icons = {
        state: 'map-marker-alt',
        admission: 'graduation-cap'
    };
    return icons[type] || 'filter';
}

function getFilterLabel(type) {
    const labels = {
        state: 'State',
        admission: 'Admission'
    };
    return labels[type] || type;
}

function applyFilters() {
    const rows = document.querySelectorAll(".table tbody tr");
    let visibleCount = 0;

    rows.forEach(row => {
        let showRow = true;

        // State filter
        if (currentFilters.state !== 'all') {
            const rowState = row.getAttribute('data-state');
            showRow = showRow && (rowState === currentFilters.state);
        }

        // Admission filter
        if (currentFilters.admission !== 'all') {
            const rowAdmission = row.getAttribute('data-admission');
            showRow = showRow && (rowAdmission === currentFilters.admission);
        }

        if (showRow) {
            row.style.display = "";
            visibleCount++;
        } else {
            row.style.display = "none";
        }
    });

    document.querySelector('.page-info').textContent = `Showing ${visibleCount} approved records`;
}

// Clear all filters function
function clearAllFilters() {
    currentFilters = {
        state: 'all',
        admission: 'all'
    };
    
    // Reset all filter buttons to original state
    document.getElementById('stateFilterBtn').innerHTML = originalButtons.state;
    document.getElementById('stateFilterBtn').classList.remove('active-filter');
    
    document.getElementById('admissionFilterBtn').innerHTML = originalButtons.admission;
    document.getElementById('admissionFilterBtn').classList.remove('active-filter');
    
    // Reset all rows to visible
    const rows = document.querySelectorAll(".table tbody tr");
    rows.forEach(row => {
        row.style.display = "";
    });
    
    // Reset search input
    document.getElementById('quickSearch').value = '';
    
    document.querySelector('.page-info').textContent = "Showing all approved records";
    
    // Close any open dropdowns
    const dropdowns = document.querySelectorAll('.filter-options');
    dropdowns.forEach(dropdown => {
        dropdown.classList.remove('show');
    });
}

// Search function
function filterTable() {
    const input = document.getElementById("quickSearch");
    const filter = input.value.toLowerCase();
    const rows = document.querySelectorAll(".table tbody tr");
    
    let visibleCount = 0;
    
    rows.forEach(row => {
        const id = row.cells[0].textContent.toLowerCase();
        const name = row.cells[2].textContent.toLowerCase();
        const email = row.cells[6].textContent.toLowerCase();
        
        if (id.includes(filter) || name.includes(filter) || email.includes(filter)) {
            row.style.display = "";
            visibleCount++;
        } else {
            row.style.display = "none";
        }
    });
    
    document.querySelector('.page-info').textContent = `Showing ${visibleCount} approved records`;
}

// Create floating elements
document.addEventListener('DOMContentLoaded', function() {
    const floatingEl = document.getElementById('floatingElements');
    for (let i = 0; i < 20; i++) {
        const element = document.createElement('div');
        element.classList.add('floating-element');
        element.style.width = Math.random() * 100 + 50 + 'px';
        element.style.height = element.style.width;
        element.style.background = 'rgba(14, 165, 233, 0.08)';
        element.style.borderRadius = '50%';
        element.style.left = Math.random() * 100 + '%';
        element.style.top = Math.random() * 100 + '%';
        element.style.animationDelay = Math.random() * 5 + 's';
        element.style.animationDuration = Math.random() * 6 + 6 + 's';
        floatingEl.appendChild(element);
    }
    
    // Initialize the chart
    initChart();
});

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.matches('.filter-btn')) {
        const dropdowns = document.querySelectorAll('.filter-options');
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    }
});
</script>

</body>
</html>