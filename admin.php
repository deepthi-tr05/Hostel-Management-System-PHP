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
$ENCRYPTION_KEY = "YOUR_ENCRYPTION_KEY_MIN_32_CHARS"; // TODO: Must match register.php & dashboard.php

// ---------------- Google Maps Config ----------------
$GOOGLE_API_KEY = "YOUR_GOOGLE_MAPS_API_KEY"; // TODO: Get from https://console.cloud.google.com
$FIXED_ORIGIN_PIN = "570016";                 // Hostel pincode — change if needed

// ---------------- DATABASE ----------------
$conn = new mysqli("YOUR_DB_HOST", "YOUR_DB_USER", "YOUR_DB_PASSWORD", "YOUR_DB_NAME");
if ($conn->connect_error) die("DB Error: " . $conn->connect_error);

// Function to check if column exists
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
    return $result->num_rows > 0;
}

// Check and add status column if it doesn't exist
if (!columnExists($conn, 'register', 'status')) {
    $conn->query("ALTER TABLE register ADD COLUMN status ENUM('pending','approved','rejected') DEFAULT 'pending'");
}

// Check and add admin_notes column if it doesn't exist
if (!columnExists($conn, 'register', 'admin_notes')) {
    $conn->query("ALTER TABLE register ADD COLUMN admin_notes TEXT DEFAULT NULL");
}

// Create dashboard table if it doesn't exist
$dashboard_table_sql = "CREATE TABLE IF NOT EXISTS dashboard (
    id INT PRIMARY KEY AUTO_INCREMENT,
    original_id INT NOT NULL,
    student_name VARCHAR(500) NOT NULL,
    father_name VARCHAR(500) NOT NULL,
    mobile1 VARCHAR(20) NOT NULL,
    mobile2 VARCHAR(20),
    email VARCHAR(255) NOT NULL,
    country VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    district VARCHAR(100) NOT NULL,
    native_place VARCHAR(100) NOT NULL,
    admission VARCHAR(100) NOT NULL,
    degree VARCHAR(100) NOT NULL,
    pincode VARCHAR(20) NOT NULL,
    front_aadhaar VARCHAR(500),
    back_aadhaar VARCHAR(500),
    admin_notes TEXT,
    approved_by VARCHAR(100) DEFAULT 'admin',
    approval_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($dashboard_table_sql);

// ---------------- DECRYPT FUNCTION ----------------
function decryptData($data, $key) {
    if ($data === "" || $data === null) return $data;
    $method = "AES-256-CBC";
    $iv = substr(hash("sha256", $key), 0, 16);
    $dec = openssl_decrypt($data, $method, $key, 0, $iv);
    return ($dec === "" || $dec === false) ? $data : $dec;
}

// ---------------- ENCRYPT FUNCTION ----------------
function encryptData($data, $key) {
    $method = "AES-256-CBC";
    $iv = substr(hash("sha256", $key), 0, 16);
    return openssl_encrypt($data, $method, $key, 0, $iv);
}

// ---------------- Distance Fetch from Database ----------------
function getDistanceFromDB($conn, $destination_pin) {
    global $FIXED_ORIGIN_PIN;
    
    $origin_pin = $FIXED_ORIGIN_PIN; // "570016"
    $destination_pin = trim($destination_pin);
    
    // Check if distance table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'distance'");
    if ($table_check->num_rows === 0) {
        return null; // Table doesn't exist
    }
    
    // Query the distance table
    $stmt = $conn->prepare("SELECT distance FROM distance WHERE origin_pincode = ? AND destination_pincode = ?");
    $stmt->bind_param("ss", $origin_pin, $destination_pin);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['distance']; // Returns something like "895 km"
    }
    
    // If not found, try with just the first 6 digits (sometimes pincodes might have spaces)
    if (strlen($destination_pin) > 6) {
        $destination_pin_short = substr($destination_pin, 0, 6);
        $stmt = $conn->prepare("SELECT distance FROM distance WHERE origin_pincode = ? AND destination_pincode = ?");
        $stmt->bind_param("ss", $origin_pin, $destination_pin_short);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['distance'];
        }
    }
    
    $stmt->close();
    return null; // No distance found in database
}

// ---------------- Distance Color Function ----------------
function getDistanceColor($distance_str) {
    if ($distance_str === null) {
        return 'warning'; // yellow for not found
    }
    
    // Extract numeric value from string like "895 km"
    preg_match('/(\d+)/', $distance_str, $matches);
    if (empty($matches)) {
        return 'warning'; // if can't parse, show warning
    }
    
    $distance_num = (int)$matches[1];
    
    if ($distance_num <= 100) {
        return 'danger'; // red for <= 100 km
    } else {
        return 'success'; // green for > 100 km
    }
}

// ---------------- Distance Helper Function ----------------
function getDistanceBetweenPincodes($origin, $dest, $apiKey) {
    // sanitize
    $origin = trim($origin);
    $dest = trim($dest);
    if ($origin === "" || $dest === "") return null;

    // create cache dir
    $cacheDir = __DIR__ . DIRECTORY_SEPARATOR . "cache";
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }

    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . preg_replace('/[^0-9A-Za-z_\-\.]/','', $origin . "_" . $dest) . ".json";

    // use cache for up to 24 hours
    $cacheTtl = 24 * 3600;

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
        $json = file_get_contents($cacheFile);
        $data = json_decode($json, true);
        if ($data) return $data;
    }

    // if no API key, return null
    if (empty($apiKey)) {
        return null;
    }

    // Build Distance Matrix request
    $origin_q = urlencode($origin);
    $dest_q = urlencode($dest);

    $url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins={$origin_q}&destinations={$dest_q}&region=in&key=" . urlencode($apiKey);

    // call API with cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false || empty($resp)) {
        // store a small error cache to avoid repeated failing calls
        $errData = ['ok'=>false,'error'=>'no_response'];
        file_put_contents($cacheFile, json_encode($errData));
        return $errData;
    }

    $parsed = json_decode($resp, true);
    if (!$parsed || !isset($parsed['rows'][0]['elements'][0])) {
        $errData = ['ok'=>false,'error'=>'invalid_response','raw'=>$parsed];
        file_put_contents($cacheFile, json_encode($errData));
        return $errData;
    }

    $elem = $parsed['rows'][0]['elements'][0];
    if (isset($elem['status']) && $elem['status'] !== 'OK') {
        $errData = ['ok'=>false,'error'=> $elem['status'] ];
        file_put_contents($cacheFile, json_encode($errData));
        return $errData;
    }

    // collect distance and duration
    $distance_text = isset($elem['distance']['text']) ? $elem['distance']['text'] : null;
    $distance_m = isset($elem['distance']['value']) ? intval($elem['distance']['value']) : null; // meters
    $duration_text = isset($elem['duration']['text']) ? $elem['duration']['text'] : null;
    $duration_s = isset($elem['duration']['value']) ? intval($elem['duration']['value']) : null;

    $out = [
        'ok' => true,
        'distance_text' => $distance_text,
        'distance_m' => $distance_m,
        'duration_text' => $duration_text,
        'duration_s' => $duration_s,
        'fetched_at' => time()
    ];

    file_put_contents($cacheFile, json_encode($out));
    return $out;
}

// ---------------- APPROVAL/REJECTION HANDLER ----------------
if (isset($_POST['action']) && isset($_POST['id'])) {
    $id = $_POST['id'];
    $action = $_POST['action'];
    $notes = $_POST['notes'] ?? '';
    
    if (!ctype_digit((string)$id)) {
        header("Location: admin.php");
        exit();
    }
    
    if ($action === 'approve') {
        $status = 'approved';
        
        // Get student data from register table
        $stmt = $conn->prepare("SELECT * FROM register WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $student_data = $result->fetch_assoc();
        $stmt->close();
        
        if ($student_data) {
            // Insert into dashboard table
            $insert_stmt = $conn->prepare("
                INSERT INTO dashboard (
                    original_id, student_name, father_name, mobile1, mobile2, 
                    email, country, state, district, native_place, 
                    admission, degree, pincode, front_aadhaar, back_aadhaar, 
                    admin_notes, approved_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // FIXED: Use proper session check instead of null coalescing
            $approved_by = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'admin';
            
            $insert_stmt->bind_param(
                "issssssssssssssss",
                $student_data['id'],
                $student_data['student_name'],
                $student_data['father_name'],
                $student_data['mobile1'],
                $student_data['mobile2'],
                $student_data['email'],
                $student_data['country'],
                $student_data['state'],
                $student_data['district'],
                $student_data['native_place'],
                $student_data['admission'],
                $student_data['degree'],
                $student_data['pincode'],
                $student_data['front_aadhaar'],
                $student_data['back_aadhaar'],
                $notes,
                $approved_by
            );
            
            $insert_stmt->execute();
            $insert_stmt->close();
        }
        
    } elseif ($action === 'reject') {
        $status = 'rejected';
    } else {
        header("Location: admin.php");
        exit();
    }
    
    $stmt = $conn->prepare("UPDATE register SET status = ?, admin_notes = ? WHERE id = ?");
    $stmt->bind_param("ssi", $status, $notes, $id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Student record {$status} successfully!" . ($action === 'approve' ? ' Student moved to dashboard.' : '');
    } else {
        $_SESSION['error_message'] = "Error updating record: " . $stmt->error;
    }
    
    $stmt->close();
    
    header("Location: admin.php");
    exit();
}

/* --------------------------------------------------
   EXPORT TO EXCEL (CSV, Excel-friendly)
-------------------------------------------------- */
if (isset($_GET['export'])) {
    // Send headers so browser downloads file as CSV
    $filename = "student_data_" . date("Y-m-d_H-i-s") . ".csv";
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    // output stream
    $output = fopen('php://output', 'w');

    // UTF-8 BOM for Excel to recognize UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Header row
    fputcsv($output, [
        "ID",
        "Student Name",
        "Father Name",
        "Mobile1",
        "Mobile2",
        "Email",
        "Country",
        "State",
        "District",
        "Native Place",
        "Admission",
        "Degree",
        "Pincode",
        "Status",
        "Admin Notes",
        "Front Aadhaar File",
        "Back Aadhaar File",
        "Created At"
    ]);

    $stmt = $conn->prepare("SELECT * FROM register ORDER BY id ASC");
    $stmt->execute();
    $result = $stmt->get_result();

    while ($r = $result->fetch_assoc()) {
        // decrypt fields where needed
        $student = decryptData($r['student_name'], $ENCRYPTION_KEY);
        $father = decryptData($r['father_name'], $ENCRYPTION_KEY);
        $mobile1 = decryptData($r['mobile1'], $ENCRYPTION_KEY);
        $mobile2 = decryptData($r['mobile2'], $ENCRYPTION_KEY);
        $email = decryptData($r['email'], $ENCRYPTION_KEY);
        $country = decryptData($r['country'], $ENCRYPTION_KEY);
        $state = decryptData($r['state'], $ENCRYPTION_KEY);
        $district = decryptData($r['district'], $ENCRYPTION_KEY);
        $native_place = decryptData($r['native_place'], $ENCRYPTION_KEY);
        $admission = decryptData($r['admission'], $ENCRYPTION_KEY);
        $degree = decryptData($r['degree'], $ENCRYPTION_KEY);
        $pincode = decryptData($r['pincode'], $ENCRYPTION_KEY);

        // write CSV row
        fputcsv($output, [
            $r['id'],
            $student,
            $father,
            $mobile1,
            $mobile2,
            $email,
            $country,
            $state,
            $district,
            $native_place,
            $admission,
            $degree,
            $pincode,
            $r['status'],
            $r['admin_notes'],
            $r['front_aadhaar'],
            $r['back_aadhaar'],
            $r['created_at']
        ]);
    }

    $stmt->close();
    fclose($output);
    exit;
}

/* --------------------------------------------------
   DELETE RECORD (prepared)
-------------------------------------------------- */
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    if (!ctype_digit((string)$id)) {
        header("Location: admin.php");
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM register WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Record deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Error deleting record: " . $stmt->error;
    }
    
    $stmt->close();

    header("Location: admin.php");
    exit();
}

/* --------------------------------------------------
   UPDATE RECORD (prepared)
-------------------------------------------------- */
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    if (!ctype_digit((string)$id)) {
        header("Location: admin.php");
        exit();
    }

    // encrypt fields before storing
    $sname = encryptData($_POST['student_name'], $ENCRYPTION_KEY);
    $fname = encryptData($_POST['father_name'], $ENCRYPTION_KEY);
    $m1 = encryptData($_POST['mobile1'], $ENCRYPTION_KEY);
    $m2 = encryptData($_POST['mobile2'], $ENCRYPTION_KEY);
    $email = encryptData($_POST['email'], $ENCRYPTION_KEY);
    $country = encryptData($_POST['country'], $ENCRYPTION_KEY);
    $state = encryptData($_POST['state'], $ENCRYPTION_KEY);
    $district = encryptData($_POST['district'], $ENCRYPTION_KEY);
    $native = encryptData($_POST['native_place'], $ENCRYPTION_KEY);
    $admission = encryptData($_POST['admission'], $ENCRYPTION_KEY);
    $degree = encryptData($_POST['degree'], $ENCRYPTION_KEY);
    $pincode = encryptData($_POST['pincode'], $ENCRYPTION_KEY);

    $stmt = $conn->prepare("UPDATE register SET student_name=?, father_name=?, mobile1=?, mobile2=?, email=?, country=?, state=?, district=?, native_place=?, admission=?, degree=?, pincode=? WHERE id=?");
    $stmt->bind_param("ssssssssssssi", $sname, $fname, $m1, $m2, $email, $country, $state, $district, $native, $admission, $degree, $pincode, $id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Record updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating record: " . $stmt->error;
    }
    
    $stmt->close();

    header("Location: admin.php");
    exit();
}

/* --------------------------------------------------
   LOGIN PAGE - LIGHT SKY BLUE THEME
-------------------------------------------------- */
if (!isset($_SESSION['admin_logged_in'])) {
    $msg = "";
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_POST['username'], $_POST['password']) && $_POST['username'] === $ADMIN_USER && $_POST['password'] === $ADMIN_PASS) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $_POST['username']; // FIXED: Store username in session
            header("Location: admin.php");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        min-height: 100vh;
        padding: 20px;
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
        gap: 12px;
        animation: fadeIn 0.8s ease 0.2s both;
    }

    .header h2 i {
        color: var(--primary-light);
        animation: bounce 2s ease-in-out infinite;
    }

    .form-group {
        margin-bottom: 20px;
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
            padding: 25px 20px;
        }
        
        .header h2 {
            font-size: 1.3rem;
        }
        
        input {
            padding: 12px 12px 12px 45px;
            font-size: 14px;
        }
        
        .btn {
            padding: 12px;
            font-size: 15px;
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

// Get unique states and admission types for filters
$states_result = $conn->query("SELECT DISTINCT state FROM register");
$states = [];
while ($row = $states_result->fetch_assoc()) {
    $state = decryptData($row['state'], $ENCRYPTION_KEY);
    if (!empty($state)) {
        $states[] = $state;
    }
}
$states = array_unique($states);
sort($states);

$admission_result = $conn->query("SELECT DISTINCT admission FROM register");
$admissions = [];
while ($row = $admission_result->fetch_assoc()) {
    $admission = decryptData($row['admission'], $ENCRYPTION_KEY);
    if (!empty($admission)) {
        $admissions[] = $admission;
    }
}
$admissions = array_unique($admissions);
sort($admissions);

// Check if edit form is requested
$edit_id = null;
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    if (ctype_digit((string)$edit_id)) {
        $stmt = $conn->prepare("SELECT * FROM register WHERE id = ?");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_data = $result->fetch_assoc();
        $stmt->close();
        
        if (!$edit_data) {
            header("Location: admin.php");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Admin Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
  font-size: 14px;
  overflow-x: hidden;
  width: 100%;
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
  padding: 10px;
  width: 100%;
}

.card {
  background: var(--bg-card);
  border-radius: var(--radius);
  box-shadow: var(--shadow-xl);
  border: 1px solid var(--border-color);
  overflow: hidden;
  position: relative;
  margin: 0;
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
  padding: 15px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 15px;
  border-bottom: 1px solid var(--border-color);
}

.card-title h2 {
  color: var(--text-primary);
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 5px;
  display: flex;
  align-items: center;
  gap: 10px;
  line-height: 1.2;
}

.card-sub {
  color: var(--text-secondary);
  font-size: 13px;
  font-weight: 500;
  line-height: 1.3;
}

.controls {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 10px;
  flex-wrap: wrap;
  width: 100%;
}

.input-sm {
  display: flex;
  align-items: center;
  background: var(--bg-primary);
  padding: 8px 12px;
  border-radius: 10px;
  border: 2px solid var(--border-color);
  transition: var(--transition);
  width: 100%;
  margin-bottom: 5px;
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
  width: 100%;
  min-width: 0;
}

.input-sm input::placeholder {
  color: var(--text-muted);
}

.btn {
  padding: 10px 15px;
  border: none;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: var(--transition);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 40px;
  width: 100%;
  justify-content: center;
  margin-bottom: 5px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.btn:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg);
}

.btn-export {
  background: var(--primary);
  color: white;
}

.btn-logout {
  background: var(--danger);
  color: white;
  padding: 10px 15px;
  border-radius: 10px;
  text-decoration: none;
  font-weight: 600;
  transition: var(--transition);
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 40px;
  width: 100%;
  justify-content: center;
  margin-top: 5px;
}

.btn-logout:hover {
  background: #dc2626;
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg);
}

/* Dashboard Button */
.btn-dashboard {
  background: var(--success);
  color: white;
  padding: 10px 15px;
  border-radius: 10px;
  text-decoration: none;
  font-weight: 600;
  transition: var(--transition);
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 40px;
  width: 100%;
  justify-content: center;
}

.btn-dashboard:hover {
  background: #059669;
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg);
}

/* Map Button Styles */
.btn-map-main {
  background: #2563eb;
  color: white;
  padding: 8px 12px;
  font-size: 13px;
  height: 40px;
  border-radius: 8px;
  width: 100%;
}

.filter-controls {
  padding: 15px;
  background: var(--bg-secondary);
  border-bottom: 1px solid var(--border-color);
  display: flex;
  flex-direction: column;
  gap: 10px;
  flex-wrap: wrap;
  align-items: flex-start;
}

.filter-btn {
  background: var(--bg-card);
  border: 2px solid var(--border-color);
  padding: 10px 15px;
  border-radius: 10px;
  cursor: pointer;
  font-size: 13px;
  transition: var(--transition);
  display: flex;
  align-items: center;
  gap: 8px;
  height: 40px;
  font-weight: 600;
  color: var(--text-secondary);
  width: 100%;
  justify-content: space-between;
}

.filter-btn:hover {
  border-color: var(--primary);
  color: var(--primary);
  transform: translateY(-2px);
  box-shadow: var(--shadow);
}

.filter-dropdown {
  position: relative;
  display: block;
  width: 100%;
}

.filter-options {
  display: none;
  position: absolute;
  background: var(--bg-card);
  min-width: 100%;
  max-height: 250px;
  overflow-y: auto;
  box-shadow: var(--shadow-xl);
  border-radius: var(--radius);
  border: 2px solid var(--border-color);
  z-index: 100;
  margin-top: 5px;
  left: 0;
  right: 0;
}

.filter-options.show {
  display: block;
}

.filter-option {
  padding: 12px 15px;
  cursor: pointer;
  transition: var(--transition);
  border-bottom: 1px solid var(--border-color);
  font-weight: 500;
  color: var(--text-primary);
  font-size: 13px;
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
  padding: 0 10px;
  -webkit-overflow-scrolling: touch;
  margin: 10px 0;
}

.table {
  width: 100%;
  border-collapse: collapse;
  margin: 10px 0;
  min-width: 1000px;
}

.table th {
  background: var(--bg-secondary);
  color: var(--text-primary);
  padding: 12px 8px;
  text-align: left;
  font-weight: 600;
  border-bottom: 2px solid var(--border-color);
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  white-space: nowrap;
}

.table td {
  padding: 10px 8px;
  border-bottom: 1px solid var(--border-color);
  font-size: 12px;
  font-weight: 500;
  color: var(--text-primary);
  white-space: nowrap;
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
  font-size: 12px;
}

.cell-muted {
  color: var(--text-muted);
  font-style: italic;
  font-size: 11px;
}

.btn-edit {
  background: var(--warning);
  color: white;
  padding: 6px 10px;
  font-size: 11px;
  height: 32px;
  border-radius: 8px;
  min-width: 60px;
  margin: 2px;
}

.btn-delete {
  background: var(--danger);
  color: white;
  padding: 6px 10px;
  font-size: 11px;
  height: 32px;
  border-radius: 8px;
  min-width: 70px;
  margin: 2px;
}

.btn-view {
  background: var(--primary);
  color: white;
  padding: 6px 10px;
  font-size: 11px;
  height: 32px;
  border-radius: 8px;
  min-width: 60px;
  margin: 2px;
}

.btn-approve {
  background: var(--success);
  color: white;
  padding: 6px 10px;
  font-size: 11px;
  height: 32px;
  border-radius: 8px;
  min-width: 80px;
  margin: 2px;
}

.btn-reject {
  background: var(--danger);
  color: white;
  padding: 6px 10px;
  font-size: 11px;
  height: 32px;
  border-radius: 8px;
  min-width: 80px;
  margin: 2px;
}

.table-footer {
  padding: 15px;
  background: var(--bg-secondary);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 10px;
}

.page-info {
  color: var(--text-muted);
  font-size: 13px;
  font-weight: 600;
}

.page-btn {
  background: var(--primary);
  color: white;
  border: none;
  padding: 10px 15px;
  border-radius: 10px;
  cursor: pointer;
  transition: var(--transition);
  display: flex;
  align-items: center;
  gap: 10px;
  height: 40px;
  font-weight: 600;
  width: 100%;
  justify-content: center;
}

.page-btn:hover {
  background: var(--primary-dark);
  transform: translateY(-2px);
  box-shadow: var(--shadow);
}

/* Status Badges */
.status-badge {
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  display: inline-block;
  letter-spacing: 0.5px;
}

.status-pending {
  background: linear-gradient(135deg, #fef3c7, #f59e0b);
  color: #92400e;
}

.status-approved {
  background: linear-gradient(135deg, #d1fae5, #10b981);
  color: #065f46;
}

.status-rejected {
  background: linear-gradient(135deg, #fee2e2, #ef4444);
  color: #991b1b;
}

/* Edit Form Styles */
.edit-form-container {
  background: var(--bg-card);
  padding: 20px;
  border-radius: var(--radius);
  box-shadow: var(--shadow-xl);
  border: 2px solid var(--border-color);
  margin: 15px;
  position: relative;
}

.edit-form-container::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 4px;
  background: var(--gradient);
  border-radius: var(--radius) var(--radius) 0 0;
}

.edit-form-container h3 {
  color: var(--text-primary);
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 18px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr;
  gap: 15px;
  margin-bottom: 15px;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: var(--text-secondary);
  font-size: 13px;
}

.form-group input,
.form-group select {
  width: 100%;
  padding: 12px;
  border: 2px solid var(--border-color);
  border-radius: 10px;
  font-size: 14px;
  transition: var(--transition);
  background: var(--bg-primary);
  font-weight: 500;
  color: var(--text-primary);
}

.form-group input:focus,
.form-group select:focus {
  border-color: var(--primary-light);
  outline: none;
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
  background: white;
}

.form-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
  justify-content: flex-end;
  margin-top: 20px;
  padding-top: 20px;
  border-top: 2px solid var(--border-color);
}

.btn-cancel {
  background: var(--text-muted);
  color: white;
  padding: 12px;
  border-radius: 10px;
  width: 100%;
}

.btn-update {
  background: var(--primary);
  color: white;
  padding: 12px;
  border-radius: 10px;
  width: 100%;
}

/* Approval Modal */
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(3, 105, 161, 0.5);
  backdrop-filter: blur(5px);
  padding: 10px;
}

.modal-content {
  background-color: var(--bg-card);
  margin: 5% auto;
  padding: 20px;
  border-radius: var(--radius);
  width: 100%;
  max-width: 500px;
  box-shadow: var(--shadow-xl);
  border: 2px solid var(--border-color);
  position: relative;
}

.modal-content::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 4px;
  background: var(--gradient);
  border-radius: var(--radius) var(--radius) 0 0;
}

.close {
  color: var(--text-muted);
  float: right;
  font-size: 24px;
  font-weight: bold;
  cursor: pointer;
  line-height: 1;
  transition: var(--transition);
}

.close:hover {
  color: var(--danger);
  transform: scale(1.1);
}

.modal-buttons {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 20px;
  justify-content: flex-end;
}

.notes-textarea {
  width: 100%;
  padding: 12px;
  margin-top: 10px;
  border: 2px solid var(--border-color);
  border-radius: 10px;
  font-size: 13px;
  background: var(--bg-primary);
  transition: var(--transition);
  resize: vertical;
  min-height: 80px;
  font-family: inherit;
  font-weight: 500;
  color: var(--text-primary);
}

.notes-textarea:focus {
  border-color: var(--primary-light);
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
  outline: none;
  background: white;
}

/* Filter for status */
.filter-status {
  background: var(--bg-secondary);
  padding: 6px 12px;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 700;
  margin-left: 8px;
}

.active-filter {
  background: var(--primary);
  color: white;
  border-color: var(--primary);
}

/* Success/Error Messages */
.alert {
  padding: 15px;
  margin: 15px;
  border-radius: var(--radius);
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 10px;
  border-left: 4px solid;
  box-shadow: var(--shadow);
  font-size: 13px;
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

/* Distance styling */
.distance-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    display: inline-block;
    letter-spacing: 0.5px;
    text-align: center;
    min-width: 70px;
}

.distance-danger {
    background: linear-gradient(135deg, #fee2e2, #ef4444);
    color: #991b1b;
    box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);
}

.distance-success {
    background: linear-gradient(135deg, #d1fae5, #10b981);
    color: #065f46;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
}

.distance-warning {
    background: linear-gradient(135deg, #fef3c7, #f59e0b);
    color: #92400e;
    box-shadow: 0 2px 4px rgba(245, 158, 11, 0.2);
}

/* Distance summary */
.distance-summary {
    background: var(--bg-secondary);
    padding: 15px;
    border-radius: var(--radius);
    margin: 15px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    border: 1px solid var(--border-color);
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    font-size: 13px;
}

.summary-count {
    font-size: 18px;
    font-weight: 700;
    padding: 6px 12px;
    border-radius: 8px;
    min-width: 40px;
    text-align: center;
}

.count-danger { background: #fee2e2; color: #dc2626; }
.count-success { background: #d1fae5; color: #059669; }
.count-warning { background: #fef3c7; color: #d97706; }

/* Map button in table - REMOVED */
.btn-map {
    display: none;
}

/* Tablet Styles */
@media (min-width: 768px) {
  body {
    font-size: 15px;
  }
  
  .container {
    padding: 15px;
  }
  
  .card-header {
    flex-direction: row;
    align-items: center;
    padding: 20px;
  }
  
  .card-title h2 {
    font-size: 24px;
  }
  
  .controls {
    flex-direction: row;
    align-items: center;
    gap: 15px;
  }
  
  .btn {
    width: auto;
    margin-bottom: 0;
  }
  
  .btn-logout {
    width: auto;
    margin-top: 0;
  }
  
  .btn-dashboard {
    width: auto;
  }
  
  .btn-map-main {
    width: auto;
  }
  
  .input-sm {
    width: auto;
  }
  
  .input-sm input {
    width: 200px;
  }
  
  .filter-controls {
    flex-direction: row;
    padding: 20px;
    gap: 15px;
  }
  
  .filter-btn {
    width: auto;
  }
  
  .filter-dropdown {
    width: auto;
  }
  
  .filter-options {
    min-width: 220px;
  }
  
  .table-wrap {
    padding: 0 20px;
  }
  
  .table {
    min-width: 1100px;
  }
  
  .table th,
  .table td {
    padding: 14px 12px;
    font-size: 13px;
  }
  
  .table-footer {
    flex-direction: row;
    padding: 20px;
    align-items: center;
  }
  
  .page-btn {
    width: auto;
  }
  
  .edit-form-container {
    padding: 30px;
    margin: 20px;
  }
  
  .form-row {
    grid-template-columns: 1fr 1fr;
    gap: 20px;
  }
  
  .form-actions {
    flex-direction: row;
  }
  
  .btn-cancel,
  .btn-update {
    width: auto;
  }
  
  .modal-buttons {
    flex-direction: row;
  }
  
  .distance-summary {
    flex-direction: row;
    padding: 20px;
    margin: 20px;
    gap: 20px;
  }
  
  .alert {
    margin: 20px;
    padding: 18px;
  }
}

/* Desktop Styles */
@media (min-width: 1024px) {
  .container {
    padding: 20px;
  }
  
  .card-header {
    padding: 30px 40px;
  }
  
  .card-title h2 {
    font-size: 28px;
  }
  
  .card-sub {
    font-size: 16px;
  }
  
  .btn {
    font-size: 14px;
    padding: 12px 24px;
    height: 44px;
  }
  
  .filter-controls {
    padding: 25px 40px;
  }
  
  .filter-btn {
    padding: 12px 20px;
    height: 44px;
    font-size: 14px;
  }
  
  .table-wrap {
    padding: 0 40px;
  }
  
  .table {
    margin: 25px 0;
  }
  
  .table th {
    padding: 18px 15px;
    font-size: 14px;
  }
  
  .table td {
    padding: 16px 15px;
    font-size: 14px;
  }
  
  .btn-edit,
  .btn-delete,
  .btn-view,
  .btn-approve,
  .btn-reject {
    font-size: 12px;
    padding: 8px 16px;
    height: 36px;
  }
  
  .table-footer {
    padding: 25px 40px;
  }
  
  .edit-form-container {
    padding: 40px;
    margin: 30px 40px;
  }
  
  .edit-form-container h3 {
    font-size: 24px;
  }
  
  .form-row {
    gap: 25px;
    margin-bottom: 25px;
  }
  
  .form-group label {
    font-size: 14px;
  }
  
  .form-group input,
  .form-group select {
    padding: 14px 16px;
    font-size: 15px;
  }
  
  .alert {
    padding: 20px 25px;
    margin: 25px 40px;
    font-size: 14px;
  }
  
  .distance-badge {
    font-size: 12px;
    padding: 6px 12px;
    min-width: 80px;
  }
  
  .distance-summary {
    padding: 20px 30px;
    margin: 20px 40px;
  }
  
  .summary-item {
    font-size: 14px;
  }
  
  .summary-count {
    font-size: 20px;
    padding: 8px 16px;
  }
  
  .status-badge {
    padding: 6px 16px;
    font-size: 12px;
  }
}

/* Extra large screens */
@media (min-width: 1400px) {
  .container {
    padding: 20px;
  }
  
  .table {
    min-width: 1300px;
  }
}

/* Fix for very small screens */
@media (max-width: 360px) {
  .container {
    padding: 5px;
  }
  
  .card-header {
    padding: 12px;
  }
  
  .card-title h2 {
    font-size: 18px;
  }
  
  .card-sub {
    font-size: 12px;
  }
  
  .btn {
    font-size: 12px;
    padding: 8px 12px;
    height: 36px;
  }
  
  .table-wrap {
    padding: 0 5px;
  }
  
  .table th,
  .table td {
    padding: 8px 6px;
    font-size: 11px;
  }
  
  .distance-badge {
    font-size: 10px;
    padding: 3px 6px;
    min-width: 60px;
  }
  
  .btn-edit,
  .btn-delete,
  .btn-view,
  .btn-approve,
  .btn-reject {
    font-size: 10px;
    padding: 4px 8px;
    height: 28px;
    min-width: 50px;
  }
  
  .status-badge {
    font-size: 10px;
    padding: 3px 8px;
  }
  
  .edit-form-container {
    padding: 15px;
    margin: 10px;
  }
  
  .edit-form-container h3 {
    font-size: 16px;
  }
  
  .alert {
    padding: 12px;
    margin: 10px;
    font-size: 12px;
  }
  
  .modal-content {
    padding: 15px;
  }
}
</style>
</head>
<body>

<!-- Light Sky Blue Background Pattern -->
<div class="background-pattern"></div>

<!-- Floating Elements -->
<div class="floating-elements" id="floatingElements"></div>

<!-- Approval Modal -->
<div id="approvalModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal()">&times;</span>
    <h3><i class="fas fa-user-check"></i> Student Approval</h3>
    <p>Student: <strong id="modalStudentName"></strong></p>
    <p>ID: <strong id="modalStudentId"></strong></p>
    
    <form id="approvalForm" method="POST">
      <input type="hidden" name="id" id="modalStudentIdInput">
      <input type="hidden" name="action" id="modalAction">
      
      <div class="form-group">
        <label for="adminNotes">Admin Notes (Optional):</label>
        <textarea class="notes-textarea" name="notes" id="adminNotes" placeholder="Add any notes or reasons for approval/rejection..."></textarea>
      </div>
      
      <div class="modal-buttons">
        <button type="button" class="btn btn-cancel" onclick="closeModal()">
          <i class="fas fa-times"></i> Cancel
        </button>
        <button type="submit" class="btn btn-approve" id="modalSubmitBtn">
          <i class="fas fa-check"></i> Confirm
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Map Modal -->
<div id="mapModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeMapModal()">&times;</span>
    <h3><i class="fas fa-map-marker-alt"></i> Google Maps Directions</h3>
    <p>Enter destination pincode:</p>
    <input type="text" id="destinationPincode" placeholder="Enter pincode..." style="width: 100%; padding: 12px; margin: 10px 0; border: 2px solid var(--border-color); border-radius: 8px;">
    <div class="modal-buttons">
      <button type="button" class="btn btn-cancel" onclick="closeMapModal()">
        <i class="fas fa-times"></i> Cancel
      </button>
      <button type="button" class="btn btn-map-main" onclick="openGoogleMaps()">
        <i class="fas fa-map"></i> Open Maps
      </button>
    </div>
  </div>
</div>

<div class="container">
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <h2><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h2>
        <div class="card-sub">Manage registered students — Origin PIN: <strong><?php echo $FIXED_ORIGIN_PIN; ?></strong></div>
      </div>

      <div class="controls">
        <div class="input-sm">
          <i class="fas fa-search" style="color: var(--text-muted); margin-right: 10px;"></i>
          <input placeholder="Search by ID or Name..." id="quickSearch" onkeyup="filterTable()">
        </div>
        
        <!-- Dashboard Button -->
        <a href="dashboard1.php" class="btn-dashboard">
          <i class="fas fa-table"></i> View Dashboard
        </a>
        
        <!-- Map Button -->
        <button class="btn btn-map-main" onclick="openMapModal()">
          <i class="fas fa-map-marker-alt"></i> Map
        </button>
        
        <a href="admin.php?export=1" title="Export to Excel">
          <button class="btn-export"><i class="fas fa-download"></i> Export to Excel</button>
        </a>
      </div>

      <a href="admin.php?logout=true" class="btn-logout">
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

    <!-- Distance Summary -->
    <?php
    // Get distance statistics
    $danger_count = 0;
    $success_count = 0;
    $warning_count = 0;
    
    // We'll count in the while loop below
    ?>
    <div class="distance-summary" id="distanceSummary" style="display: none;">
        <div class="summary-item">
            <span class="summary-count count-danger" id="dangerCount">0</span>
            <span>Near (≤ 100 km)</span>
        </div>
        <div class="summary-item">
            <span class="summary-count count-success" id="successCount">0</span>
            <span>Far (> 100 km)</span>
        </div>
        <div class="summary-item">
            <span class="summary-count count-warning" id="warningCount">0</span>
            <span>Not Found</span>
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

      <!-- Status Filter -->
      <div class="filter-dropdown">
        <button class="filter-btn" id="statusFilterBtn" onclick="toggleFilter('status')">
          <i class="fas fa-filter"></i> Status
        </button>
        <div class="filter-options" id="statusFilter">
          <div class="filter-option" onclick="filterByStatus('all')">All Status</div>
          <div class="filter-option" onclick="filterByStatus('pending')">Pending</div>
          <div class="filter-option" onclick="filterByStatus('approved')">Approved</div>
          <div class="filter-option" onclick="filterByStatus('rejected')">Rejected</div>
        </div>
      </div>

      <!-- Distance Filter -->
      <div class="filter-dropdown">
        <button class="filter-btn" id="distanceFilterBtn" onclick="toggleFilter('distance')">
          <i class="fas fa-road"></i> Distance
        </button>
        <div class="filter-options" id="distanceFilter">
          <div class="filter-option" onclick="filterByDistance('all')">All Distances</div>
          <div class="filter-option" onclick="filterByDistance('danger')">Near (≤ 100 km)</div>
          <div class="filter-option" onclick="filterByDistance('success')">Far (> 100 km)</div>
          <div class="filter-option" onclick="filterByDistance('warning')">Not Found</div>
        </div>
      </div>

      <button class="filter-btn" onclick="clearAllFilters()">
        <i class="fas fa-times"></i> Clear Filters
      </button>
    </div>

    <?php if ($edit_id && $edit_data): ?>
    <!-- Edit Form -->
    <div class="edit-form-container">
      <h3><i class="fas fa-edit"></i> Edit Student Record - ID: <?php echo htmlspecialchars($edit_id); ?></h3>
      <form method="POST">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_id); ?>">
        
        <div class="form-row">
          <div class="form-group">
            <label for="student_name">Student Name *</label>
            <input type="text" id="student_name" name="student_name" value="<?php echo htmlspecialchars(decryptData($edit_data['student_name'], $ENCRYPTION_KEY)); ?>" required>
          </div>
          
          <div class="form-group">
            <label for="father_name">Father Name *</label>
            <input type="text" id="father_name" name="father_name" value="<?php echo htmlspecialchars(decryptData($edit_data['father_name'], $ENCRYPTION_KEY)); ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="mobile1">Mobile 1 *</label>
            <input type="tel" id="mobile1" name="mobile1" value="<?php echo htmlspecialchars(decryptData($edit_data['mobile1'], $ENCRYPTION_KEY)); ?>" required>
          </div>
          
          <div class="form-group">
            <label for="mobile2">Mobile 2</label>
            <input type="tel" id="mobile2" name="mobile2" value="<?php echo htmlspecialchars(decryptData($edit_data['mobile2'], $ENCRYPTION_KEY)); ?>">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="email">Email *</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars(decryptData($edit_data['email'], $ENCRYPTION_KEY)); ?>" required>
          </div>
          
          <div class="form-group">
            <label for="country">Country *</label>
            <input type="text" id="country" name="country" value="<?php echo htmlspecialchars(decryptData($edit_data['country'], $ENCRYPTION_KEY)); ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="state">State *</label>
            <input type="text" id="state" name="state" value="<?php echo htmlspecialchars(decryptData($edit_data['state'], $ENCRYPTION_KEY)); ?>" required>
          </div>
          
          <div class="form-group">
            <label for="district">District *</label>
            <input type="text" id="district" name="district" value="<?php echo htmlspecialchars(decryptData($edit_data['district'], $ENCRYPTION_KEY)); ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="native_place">Native Place *</label>
            <input type="text" id="native_place" name="native_place" value="<?php echo htmlspecialchars(decryptData($edit_data['native_place'], $ENCRYPTION_KEY)); ?>" required>
          </div>
          
          <div class="form-group">
            <label for="admission">Admission *</label>
            <input type="text" id="admission" name="admission" value="<?php echo htmlspecialchars(decryptData($edit_data['admission'], $ENCRYPTION_KEY)); ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="degree">Degree *</label>
            <input type="text" id="degree" name="degree" value="<?php echo htmlspecialchars(decryptData($edit_data['degree'], $ENCRYPTION_KEY)); ?>" required>
          </div>
          
          <div class="form-group">
            <label for="pincode">Pincode *</label>
            <input type="text" id="pincode" name="pincode" value="<?php echo htmlspecialchars(decryptData($edit_data['pincode'], $ENCRYPTION_KEY)); ?>" required>
          </div>
        </div>

        <div class="form-actions">
          <a href="admin.php" class="btn btn-cancel">
            <i class="fas fa-times"></i> Cancel
          </a>
          <button type="submit" name="update" class="btn btn-update">
            <i class="fas fa-save"></i> Update Record
          </button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <div class="table-wrap">
      <table class="table" role="table" aria-label="Student registrations">
        <thead>
          <tr>
            <th class="id-col">ID</th>
            <th>Date</th>
            <th>Student</th>
            <th>Status</th>
            <th>Mobile1</th>
            <th>Mobile2</th>
            <th>State</th>
            <th>District</th>
            <th>Admission</th>
            <th>Degree</th>
            <th>Pincode</th>
            <th>Distance</th>
            <th>Front Aadhaar</th>
            <th>Back Aadhaar</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>

<?php
/* --------------------------------------------------
   Show table rows (list)
-------------------------------------------------- */
$rows_stmt = $conn->prepare("SELECT * FROM register ORDER BY id DESC");
$rows_stmt->execute();
$rows = $rows_stmt->get_result();

while ($row = $rows->fetch_assoc()) {
    // decrypt then escape for safe HTML output
    $id_raw = $row['id'];
    $id = htmlspecialchars($row['id']);
    $student = htmlspecialchars(decryptData($row['student_name'],$ENCRYPTION_KEY));
    $m1 = htmlspecialchars(decryptData($row['mobile1'],$ENCRYPTION_KEY));
    $m2 = htmlspecialchars(decryptData($row['mobile2'],$ENCRYPTION_KEY));
    $state = htmlspecialchars(decryptData($row['state'],$ENCRYPTION_KEY));
    $district = htmlspecialchars(decryptData($row['district'],$ENCRYPTION_KEY));
    $admission = htmlspecialchars(decryptData($row['admission'],$ENCRYPTION_KEY));
    $degree = htmlspecialchars(decryptData($row['degree'],$ENCRYPTION_KEY));
    $pincode_plain = decryptData($row['pincode'],$ENCRYPTION_KEY);
    $pincode = htmlspecialchars($pincode_plain);
    $front_aadhaar = $row['front_aadhaar'];
    $back_aadhaar = $row['back_aadhaar'];
    $status = $row['status'];
    $admin_notes = $row['admin_notes'];
    $created_date = date("d-m-Y", strtotime($row['created_at']));
    $created_date_raw = $row['created_at'];
    
    // Get distance from database
    $distance = getDistanceFromDB($conn, $pincode_plain);
    
    // Get distance color
    $distance_color = getDistanceColor($distance);
    
    // Count for statistics
    if ($distance_color === 'danger') {
        $danger_count++;
    } elseif ($distance_color === 'success') {
        $success_count++;
    } elseif ($distance_color === 'warning') {
        $warning_count++;
    }

    // Map URL for this student (still available for other uses if needed)
    $mapUrl = "https://www.google.com/maps/dir/" . urlencode($FIXED_ORIGIN_PIN) . "/" . urlencode($pincode_plain);

    echo "<tr data-date='{$created_date_raw}' data-state='{$state}' data-admission='{$admission}' data-status='{$status}' data-distance='{$distance_color}'>";
    echo "<td class='id-col' style='text-align:center; font-weight: 700; color: var(--primary);'>{$id}</td>";
    echo "<td style='white-space:nowrap; font-weight: 600;'>{$created_date}</td>";
    echo "<td style='font-weight: 600; color: var(--text-primary);'>{$student}</td>";
    
    // Status column with badge - color changes based on status
    $status_class = "status-" . $status;
    echo "<td><span class='status-badge {$status_class}'>{$status}</span></td>";
    
    echo "<td style='font-weight: 500;'>{$m1}</td>";
    echo "<td style='font-weight: 500;'>{$m2}</td>";
    echo "<td style='font-weight: 600; color: var(--primary);'>{$state}</td>";
    echo "<td style='font-weight: 500;'>{$district}</td>";
    echo "<td style='font-weight: 600; color: var(--secondary);'>{$admission}</td>";
    echo "<td style='font-weight: 500;'>{$degree}</td>";
    echo "<td style='font-weight: 600; color: var(--primary);'>{$pincode}</td>";
    
    // Distance column with color coding
    if ($distance !== null) {
        $distance_class = "distance-" . $distance_color;
        echo "<td style='text-align: center;'>
                <span class='distance-badge {$distance_class}'>
                  <i class='fas fa-road'></i> {$distance}
                </span>
              </td>";
    } else {
        echo "<td style='text-align: center;'>
                <span class='distance-badge distance-warning'>
                  <i class='fas fa-exclamation-triangle'></i> Not found
                </span>
              </td>";
    }

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

    // actions: edit, delete, approve/reject (MAP BUTTON REMOVED)
    $editUrl = "admin.php?edit=" . urlencode($row['id']);
    $delUrl = "admin.php?delete=" . urlencode($row['id']);
    
    echo "<td style='white-space:nowrap;'>
            <a href='{$editUrl}'><button class='btn btn-edit'><i class='fas fa-edit'></i> Edit</button></a>
            <a href='{$delUrl}' onclick=\"return confirm('Delete this record?');\"><button class='btn btn-delete'><i class='fas fa-trash'></i> Delete</button></a>";
    
    // Approval/Rejection buttons (only show for pending status)
    if ($status === 'pending') {
        echo "<button class='btn btn-approve' onclick=\"openApprovalModal('{$id}', '{$student}', 'approve')\" style='margin-top: 5px;'>
                <i class='fas fa-check'></i> Approve
              </button>
              <button class='btn btn-reject' onclick=\"openApprovalModal('{$id}', '{$student}', 'reject')\" style='margin-top: 5px;'>
                <i class='fas fa-times'></i> Reject
              </button>";
    } else {
        // Show current status for non-pending records with appropriate colors
        $action_text = $status === 'approved' ? 'Approved' : 'Rejected';
        $action_class = $status === 'approved' ? 'btn-approve' : 'btn-reject';
        echo "<button class='btn {$action_class}' style='margin-top: 5px; background: " . ($status === 'approved' ? 'var(--success)' : 'var(--danger)') . "; opacity: 0.7;' disabled>
                <i class='fas fa-" . ($status === 'approved' ? 'check' : 'times') . "'></i> {$action_text}
              </button>";
    }
    
    echo "</td>";
    echo "</tr>";
}

$rows_stmt->close();
$conn->close();
?>

        </tbody>
      </table>
    </div>

    <div class="table-footer">
      <div class="page-info">Showing all records</div>
      <div class="pager">
        <button class="page-btn" onclick="location.href='admin.php'"><i class="fas fa-sync-alt"></i> Refresh</button>
      </div>
    </div>
  </div>
</div>

<!-- FRONT-END SCRIPT -->
<script>
let currentFilters = {
    state: 'all',
    admission: 'all',
    status: 'all',
    distance: 'all'
};

// Store original button content
const originalButtons = {
    state: '<i class="fas fa-map-marker-alt"></i> State',
    admission: '<i class="fas fa-graduation-cap"></i> Admission',
    status: '<i class="fas fa-filter"></i> Status',
    distance: '<i class="fas fa-road"></i> Distance'
};

// Initialize distance counts
let distanceCounts = {
    danger: <?php echo $danger_count; ?>,
    success: <?php echo $success_count; ?>,
    warning: <?php echo $warning_count; ?>
};

// Update summary on page load
document.addEventListener('DOMContentLoaded', function() {
    updateDistanceSummary();
    
    // Show summary if there are any distances
    if (distanceCounts.danger > 0 || distanceCounts.success > 0 || distanceCounts.warning > 0) {
        document.getElementById('distanceSummary').style.display = 'flex';
    }
    
    // Create floating elements
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
});

// Update distance summary
function updateDistanceSummary() {
    document.getElementById('dangerCount').textContent = distanceCounts.danger;
    document.getElementById('successCount').textContent = distanceCounts.success;
    document.getElementById('warningCount').textContent = distanceCounts.warning;
}

// Approval Modal Functions
function openApprovalModal(studentId, studentName, action) {
    document.getElementById('modalStudentId').textContent = studentId;
    document.getElementById('modalStudentName').textContent = studentName;
    document.getElementById('modalStudentIdInput').value = studentId;
    document.getElementById('modalAction').value = action;
    document.getElementById('adminNotes').value = '';
    
    const modal = document.getElementById('approvalModal');
    const submitBtn = document.getElementById('modalSubmitBtn');
    
    if (action === 'approve') {
        submitBtn.innerHTML = '<i class="fas fa-check"></i> Approve Student';
        submitBtn.className = 'btn btn-approve';
    } else {
        submitBtn.innerHTML = '<i class="fas fa-times"></i> Reject Student';
        submitBtn.className = 'btn btn-reject';
    }
    
    modal.style.display = 'block';
}

function closeModal() {
    document.getElementById('approvalModal').style.display = 'none';
}

// Map Modal Functions
function openMapModal() {
    document.getElementById('mapModal').style.display = 'block';
    document.getElementById('destinationPincode').value = '';
    document.getElementById('destinationPincode').focus();
}

function closeMapModal() {
    document.getElementById('mapModal').style.display = 'none';
}

function openGoogleMaps() {
    const destination = document.getElementById('destinationPincode').value.trim();
    if (destination) {
        const origin = "<?php echo $FIXED_ORIGIN_PIN; ?>";
        const url = `https://www.google.com/maps/dir/${origin}/${destination}`;
        window.open(url, '_blank');
        closeMapModal();
    } else {
        alert('Please enter a destination pincode');
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    const approvalModal = document.getElementById('approvalModal');
    const mapModal = document.getElementById('mapModal');
    
    if (event.target === approvalModal) {
        closeModal();
    }
    if (event.target === mapModal) {
        closeMapModal();
    }
}

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

function filterByStatus(status) {
    currentFilters.status = status;
    applyFilters();
    updateFilterButton('status', status);
    document.getElementById('statusFilter').classList.remove('show');
}

function filterByDistance(distance) {
    currentFilters.distance = distance;
    applyFilters();
    updateFilterButton('distance', distance);
    document.getElementById('distanceFilter').classList.remove('show');
}

function updateFilterButton(type, value) {
    const buttonId = type + 'FilterBtn';
    const button = document.getElementById(buttonId);
    
    if (value !== 'all') {
        button.classList.add('active-filter');
        let displayValue = value;
        
        // Format display value
        if (type === 'distance') {
            if (value === 'danger') displayValue = 'Near (≤ 100 km)';
            else if (value === 'success') displayValue = 'Far (> 100 km)';
            else if (value === 'warning') displayValue = 'Not Found';
        }
        
        button.innerHTML = `<i class="fas fa-${getFilterIcon(type)}"></i> ${getFilterLabel(type)}: ${displayValue}`;
    } else {
        button.classList.remove('active-filter');
        button.innerHTML = originalButtons[type];
    }
}

function getFilterIcon(type) {
    const icons = {
        state: 'map-marker-alt',
        admission: 'graduation-cap',
        status: 'filter',
        distance: 'road'
    };
    return icons[type] || 'filter';
}

function getFilterLabel(type) {
    const labels = {
        state: 'State',
        admission: 'Admission',
        status: 'Status',
        distance: 'Distance'
    };
    return labels[type] || type;
}

function applyFilters() {
    const rows = document.querySelectorAll(".table tbody tr");
    let visibleCount = 0;
    
    // Reset counts for filtered view
    let filteredCounts = {
        danger: 0,
        success: 0,
        warning: 0
    };

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

        // Status filter
        if (currentFilters.status !== 'all') {
            const rowStatus = row.getAttribute('data-status');
            showRow = showRow && (rowStatus === currentFilters.status);
        }

        // Distance filter
        if (currentFilters.distance !== 'all') {
            const rowDistance = row.getAttribute('data-distance');
            showRow = showRow && (rowDistance === currentFilters.distance);
        }

        if (showRow) {
            row.style.display = "";
            visibleCount++;
            
            // Count for filtered view
            const distanceType = row.getAttribute('data-distance');
            if (distanceType && filteredCounts.hasOwnProperty(distanceType)) {
                filteredCounts[distanceType]++;
            }
        } else {
            row.style.display = "none";
        }
    });

    // Update summary with filtered counts
    document.getElementById('dangerCount').textContent = filteredCounts.danger;
    document.getElementById('successCount').textContent = filteredCounts.success;
    document.getElementById('warningCount').textContent = filteredCounts.warning;
    
    document.querySelector('.page-info').textContent = `Showing ${visibleCount} records`;
}

// Clear all filters function
function clearAllFilters() {
    currentFilters = {
        state: 'all',
        admission: 'all',
        status: 'all',
        distance: 'all'
    };
    
    // Reset all filter buttons to original state
    document.getElementById('stateFilterBtn').innerHTML = originalButtons.state;
    document.getElementById('stateFilterBtn').classList.remove('active-filter');
    
    document.getElementById('admissionFilterBtn').innerHTML = originalButtons.admission;
    document.getElementById('admissionFilterBtn').classList.remove('active-filter');
    
    document.getElementById('statusFilterBtn').innerHTML = originalButtons.status;
    document.getElementById('statusFilterBtn').classList.remove('active-filter');
    
    document.getElementById('distanceFilterBtn').innerHTML = originalButtons.distance;
    document.getElementById('distanceFilterBtn').classList.remove('active-filter');
    
    // Reset all rows to visible
    const rows = document.querySelectorAll(".table tbody tr");
    rows.forEach(row => {
        row.style.display = "";
    });
    
    // Reset search input
    document.getElementById('quickSearch').value = '';
    
    // Reset summary to total counts
    document.getElementById('dangerCount').textContent = distanceCounts.danger;
    document.getElementById('successCount').textContent = distanceCounts.success;
    document.getElementById('warningCount').textContent = distanceCounts.warning;
    
    document.querySelector('.page-info').textContent = "Showing all records";
    
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
    let filteredCounts = {
        danger: 0,
        success: 0,
        warning: 0
    };
    
    rows.forEach(row => {
        const id = row.cells[0].textContent.toLowerCase();
        const name = row.cells[2].textContent.toLowerCase();
        
        if (id.includes(filter) || name.includes(filter)) {
            row.style.display = "";
            visibleCount++;
            
            // Count for filtered view
            const distanceType = row.getAttribute('data-distance');
            if (distanceType && filteredCounts.hasOwnProperty(distanceType)) {
                filteredCounts[distanceType]++;
            }
        } else {
            row.style.display = "none";
        }
    });
    
    // Update summary with filtered counts
    document.getElementById('dangerCount').textContent = filteredCounts.danger;
    document.getElementById('successCount').textContent = filteredCounts.success;
    document.getElementById('warningCount').textContent = filteredCounts.warning;
    
    document.querySelector('.page-info').textContent = `Showing ${visibleCount} records`;
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.matches('.filter-btn')) {
        const dropdowns = document.querySelectorAll('.filter-options');
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    }
});

// Enter key support for map modal
document.getElementById('destinationPincode').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        openGoogleMaps();
    }
});
</script>

</body>
</html>