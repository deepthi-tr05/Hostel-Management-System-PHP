<?php
session_start(); // ADD THIS LINE AT THE TOP

$conn = new mysqli("YOUR_DB_HOST", "YOUR_DB_USER", "YOUR_DB_PASSWORD", "YOUR_DB_NAME");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$key = "YOUR_ENCRYPTION_KEY_MIN_32_CHARS";

function encryptData($data, $key) {
    if ($data === null || $data === "") return "";
    $method = "AES-256-CBC";
    $iv = substr(hash("sha256", $key), 0, 16);
    return openssl_encrypt($data, $method, $key, 0, $iv);
}

function decryptData($data, $key) {
    if ($data === null || $data === "") return "";
    $method = "AES-256-CBC";
    $iv = substr(hash("sha256", $key), 0, 16);
    return openssl_decrypt($data, $method, $key, 0, $iv);
}

function post($name) { return isset($_POST[$name]) ? trim($_POST[$name]) : ''; }

function jsAlertBack($msg) {
    echo "<script>alert(" . json_encode($msg) . "); window.history.back();</script>";
    exit();
}

// Check if showing success message
$showSuccess = false;
$studentData = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // RAW INPUTS
    $student_name_raw = post('student_name');
    $father_name_raw  = post('father_name');
    $mobile1_raw      = post('mobile1');
    $mobile2_raw      = post('mobile2');
    $email_raw        = post('email');
    $country_raw      = post('country');
    $state_raw        = post('state');
    $district_raw     = post('district');
    $native_place_raw = post('native_place');
    $admission_raw    = post('admission');
    $degree_raw       = post('degree');
    $pincode_raw      = post('pincode');

    // VALIDATION
    if (!preg_match('/^[0-9]{10}$/', $mobile1_raw)) jsAlertBack('Mobile Number 1 must be exactly 10 digits');
    if (!empty($mobile2_raw) && !preg_match('/^[0-9]{10}$/', $mobile2_raw)) jsAlertBack('Mobile Number 2 must be exactly 10 digits');
    if (!preg_match('/^[0-9]{6}$/', $pincode_raw)) jsAlertBack('Pincode must be exactly 6 digits');

    // ENCRYPT FOR CHECKING DUPLICATES
    $mobile1_enc = encryptData($mobile1_raw, $key);
    $mobile2_enc = $mobile2_raw !== '' ? encryptData($mobile2_raw, $key) : '';
    $email_enc   = encryptData($email_raw, $key);

    // DUPLICATE CHECK
    $stmt = $conn->prepare("SELECT id FROM register WHERE mobile1=? LIMIT 1");
    $stmt->bind_param("s",$mobile1_enc); $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows>0) jsAlertBack('❌ Mobile Number 1 already registered');
    $stmt->close();

    $stmt = $conn->prepare("SELECT id FROM register WHERE email=? LIMIT 1");
    $stmt->bind_param("s",$email_enc); $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows>0) jsAlertBack('❌ Email already registered');
    $stmt->close();

    if ($mobile2_enc!=="") {
        $stmt = $conn->prepare("SELECT id FROM register WHERE mobile2=? LIMIT 1");
        $stmt->bind_param("s",$mobile2_enc); $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows>0) jsAlertBack('❌ Mobile Number 2 already registered');
        $stmt->close();
    }

    // ENCRYPT ALL DATA FOR STORAGE
    $student_name = encryptData($student_name_raw,$key);
    $father_name  = encryptData($father_name_raw,$key);
    $mobile1      = $mobile1_enc;
    $mobile2      = $mobile2_enc;
    $email        = $email_enc;
    $country      = encryptData($country_raw,$key);
    $state        = encryptData($state_raw,$key);
    $district     = encryptData($district_raw,$key);
    $native_place = encryptData($native_place_raw,$key);
    $admission    = encryptData($admission_raw,$key);
    $degree       = encryptData($degree_raw,$key);
    $pincode      = encryptData($pincode_raw,$key);

    // FILE UPLOAD
    $folder = "uploads/";
    if (!is_dir($folder)) mkdir($folder,0777,true);
    $front_aadhaar = "";
    $back_aadhaar = "";

    // Handle Front Aadhaar file upload
    if (!empty($_FILES["front_aadhaar"]["name"])) {
        $front_aadhaar = $folder.time()."_front_".basename($_FILES["front_aadhaar"]["name"]);
        move_uploaded_file($_FILES["front_aadhaar"]["tmp_name"],$front_aadhaar);
    }

    // Handle Back Aadhaar file upload
    if (!empty($_FILES["back_aadhaar"]["name"])) {
        $back_aadhaar = $folder.time()."_back_".basename($_FILES["back_aadhaar"]["name"]);
        move_uploaded_file($_FILES["back_aadhaar"]["tmp_name"],$back_aadhaar);
    }

    // INSERT QUERY - UPDATED FOR BOTH AADHAAR FILES
    $sql = "INSERT INTO register 
        (student_name,father_name,mobile1,mobile2,email,country,state,district,
        native_place,admission,degree,pincode,front_aadhaar,back_aadhaar,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssssssss",
        $student_name,$father_name,$mobile1,$mobile2,$email,
        $country,$state,$district,$native_place,$admission,
        $degree,$pincode,$front_aadhaar,$back_aadhaar
    );

    if ($stmt->execute()) {
        $registration_id = $stmt->insert_id; // GET THE REGISTRATION ID
        
        // Store student data for display and PDF
        $studentData = [
            'id' => $registration_id,
            'student_name' => $student_name_raw,
            'father_name' => $father_name_raw,
            'mobile1' => $mobile1_raw,
            'mobile2' => $mobile2_raw,
            'email' => $email_raw,
            'country' => $country_raw,
            'state' => $state_raw,
            'district' => $district_raw,
            'native_place' => $native_place_raw,
            'admission' => $admission_raw,
            'degree' => $degree_raw,
            'pincode' => $pincode_raw,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $_SESSION['student_data'] = $studentData;
        $_SESSION['last_registration_id'] = $registration_id;
        $showSuccess = true;
        
        // Don't exit, continue to show the page with success message
    } else {
        echo "<script>alert('Error during registration: " . $conn->error . "');</script>";
    }
}

// Get student data from session if available
if (isset($_SESSION['student_data']) && $showSuccess) {
    $studentData = $_SESSION['student_data'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Girls Hostel Registration Portal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ALL YOUR EXISTING CSS STAYS EXACTLY THE SAME */
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
    padding: 20px;
}

.container {
    background: var(--bg-card);
    padding: 35px 30px;
    border-radius: var(--radius);
    width: 100%;
    max-width: 800px;
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
    font-size: 1.8rem;
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

.form-section {
    animation: slideIn 0.6s ease 0.4s both;
}

.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
}

.form-row .col {
    flex: 1;
}

.form-group {
    margin-bottom: 20px;
    animation: slideIn 0.5s ease both;
}

.form-group:nth-child(1) { animation-delay: 0.5s; }
.form-group:nth-child(2) { animation-delay: 0.6s; }
.form-group:nth-child(3) { animation-delay: 0.7s; }
.form-group:nth-child(4) { animation-delay: 0.8s; }

label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--text-primary);
    transition: var(--transition);
    font-size: 14px;
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
    transition: var(--transition);
    z-index: 2;
    font-size: 16px;
}

input, select {
    width: 100%;
    padding: 14px 15px 14px 50px;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    font-size: 15px;
    background: var(--bg-primary);
    transition: var(--transition);
    color: var(--text-primary);
}

input:focus, select:focus {
    border-color: var(--primary-light);
    background: var(--bg-secondary);
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
    outline: none;
    transform: translateY(-1px);
}

input:focus + i, select:focus + i {
    color: var(--primary-light);
}

.btn {
    width: 100%;
    background: var(--gradient);
    color: white;
    padding: 16px;
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
    margin-top: 20px;
    box-shadow: var(--shadow);
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* SUCCESS MESSAGE STYLES */
.success-container {
    display: <?php echo $showSuccess ? 'block' : 'none'; ?>;
    animation: fadeIn 0.6s ease;
}

.success-header {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--border-color);
}

.success-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--success), #34d399);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    color: white;
    font-size: 40px;
    animation: bounce 1s ease;
}

.success-header h2 {
    color: var(--text-primary);
    margin-bottom: 10px;
    font-size: 28px;
}

.registration-id {
    background: linear-gradient(135deg, var(--primary-light), var(--primary));
    padding: 12px 25px;
    border-radius: 50px;
    display: inline-block;
    margin-top: 10px;
    font-weight: bold;
    color: white;
    font-size: 18px;
    box-shadow: 0 4px 6px rgba(14, 165, 233, 0.3);
}

.details-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.details-section {
    background: var(--bg-primary);
    padding: 20px;
    border-radius: 12px;
    border-left: 4px solid var(--primary);
    transition: transform 0.3s ease;
}

.details-section:hover {
    transform: translateY(-5px);
}

.details-section h3 {
    color: var(--primary);
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 18px;
}

.detail-row {
    display: flex;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(14, 165, 233, 0.1);
}

.detail-label {
    font-weight: 600;
    color: var(--text-primary);
    min-width: 150px;
    font-size: 14px;
}

.detail-value {
    color: #333;
    font-size: 14px;
}

.action-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 40px;
    flex-wrap: wrap;
}

.pdf-btn {
    padding: 16px 35px;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.3s ease;
    text-decoration: none;
    min-width: 250px;
    justify-content: center;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    box-shadow: 0 6px 12px rgba(245, 158, 11, 0.3);
}

.pdf-btn:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 10px 20px rgba(245, 158, 11, 0.4);
    background: linear-gradient(135deg, #d97706, #b45309);
}

.new-reg-btn {
    padding: 16px 35px;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.3s ease;
    text-decoration: none;
    min-width: 250px;
    justify-content: center;
    background: white;
    color: var(--text-primary);
    border: 2px solid var(--primary-light);
    box-shadow: 0 4px 6px rgba(14, 165, 233, 0.1);
}

.new-reg-btn:hover {
    background: var(--bg-primary);
    transform: translateY(-3px);
}

.note-box {
    background: linear-gradient(135deg, #fff3cd, #ffeaa7);
    border-left: 4px solid #f59e0b;
    padding: 20px;
    border-radius: 12px;
    margin-top: 40px;
}

.note-box h4 {
    color: #92400e;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 18px;
}

.note-box p {
    margin-bottom: 10px;
    color: #92400e;
    font-size: 14px;
}

/* FORM CONTAINER - Hide when success is shown */
.form-container {
    display: <?php echo $showSuccess ? 'none' : 'block'; ?>;
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

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
    40% { transform: translateY(-15px); }
    60% { transform: translateY(-7px); }
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

/* File input styling */
input[type="file"] {
    padding: 12px 15px 12px 50px;
    background: var(--bg-primary);
    border: 2px dashed var(--border-color);
    transition: var(--transition);
}

input[type="file"]:hover {
    border-color: var(--primary-light);
    background: var(--bg-secondary);
}

input[type="file"]::file-selector-button {
    background: var(--primary);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    margin-right: 10px;
    cursor: pointer;
    transition: var(--transition);
}

input[type="file"]::file-selector-button:hover {
    background: var(--primary-dark);
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

/* Custom select styling */
select {
    appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%230369a1' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 15px center;
    background-size: 16px;
}

/* Responsive */
@media(max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .container {
        padding: 25px 20px;
        margin: 10px;
    }
    
    .header h2 {
        font-size: 1.5rem;
    }
    
    input, select {
        padding: 12px 12px 12px 45px;
    }
    
    .details-container {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
        align-items: center;
    }
    
    .pdf-btn, .new-reg-btn {
        width: 100%;
        max-width: 300px;
    }
}

/* Datalist styling */
datalist {
    border-radius: 10px;
    background: white;
    box-shadow: var(--shadow);
}
</style>

<script>
// SOUTH INDIA DISTRICT DATA
const southIndiaDistricts = {
   "Karnataka": ["Mysuru","Bengaluru Urban","Bengaluru Rural","Mandya","Hassan","Tumakuru",
      "Chikkamagaluru","Chamarajanagar","Kodagu","Udupi","Dakshina Kannada",
      "Shivamogga","Davangere","Bellary","Raichur","Bidar","Kalaburagi",
      "Koppal","Vijayapura","Bagalkot"],
   "Kerala": ["Thiruvananthapuram","Kollam","Pathanamthitta","Alappuzha","Kottayam",
      "Idukki","Ernakulam","Thrissur","Palakkad","Malappuram","Kozhikode",
      "Wayanad","Kannur","Kasaragod"],
   "Tamil Nadu": ["Chennai","Coimbatore","Madurai","Salem","Tiruchirappalli","Erode",
      "Tirunelveli","Vellore","Thoothukudi","Dindigul","Krishnagiri",
      "Kanchipuram","Namakkal","Nilgiris"],
   "Andhra Pradesh": ["Visakhapatnam","Vijayawada","Guntur","Nellore","Kurnool","Kadapa",
      "Anantapur","Srikakulam","Prakasam","Krishna","Chittoor"],
   "Telangana": ["Hyderabad","Warangal","Nizamabad","Karimnagar","Khammam",
      "Mahabubnagar","Nalgonda","Adilabad","Medak","Rangareddy"],
   "Puducherry": ["Puducherry","Karaikal","Mahe","Yanam"],
   "Lakshadweep": ["Agatti","Amini","Andrott","Bitra","Chetlat","Kadmat",
      "Kalpeni","Kavaratti","Minicoy"]
};

// POPULATE STATES
function populateStates() {
  const country = document.getElementById("country").value;
  const list = document.getElementById("stateList");
  list.innerHTML = "";

  if (country === "India") {
    Object.keys(southIndiaDistricts).forEach(state => {
      let op = document.createElement("option");
      op.value = state;
      list.appendChild(op);
    });
  }
  populateDistricts();
}

// POPULATE DISTRICTS
function populateDistricts() {
  const state = document.getElementById("state").value;
  const list = document.getElementById("districtList");
  list.innerHTML = "";

  if (southIndiaDistricts[state]) {
    southIndiaDistricts[state].forEach(dist => {
      let op = document.createElement("option");
      op.value = dist;
      list.appendChild(op);
    });
  }
}

// ENFORCE NUMBERS ONLY
function allowOnlyNumbers(el, maxLen) {
    el.value = el.value.replace(/[^0-9]/g, "");
    if (maxLen) el.value = el.value.slice(0, maxLen);
}

// PDF Generation Function (No libraries needed)
function generateRegistrationPDF() {
    // Get student data from PHP
    const studentData = <?php echo json_encode($studentData); ?>;
    
    // Create HTML content for the PDF
    const htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                @page {
                    size: A4;
                    margin: 20px;
                }
                body {
                    font-family: 'Segoe UI', Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    padding: 20px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                    padding-bottom: 15px;
                    border-bottom: 2px solid #0369a1;
                }
                .header h1 {
                    color: #0369a1;
                    margin: 0;
                    font-size: 24px;
                }
                .header h2 {
                    color: #0ea5e9;
                    margin: 10px 0;
                    font-size: 18px;
                }
                .section {
                    margin-bottom: 20px;
                    page-break-inside: avoid;
                }
                .section-title {
                    background: #f0f9ff;
                    color: #0369a1;
                    padding: 10px 15px;
                    border-radius: 6px;
                    margin-bottom: 15px;
                    font-weight: bold;
                    border-left: 4px solid #0ea5e9;
                }
                .info-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 15px;
                }
                .info-item {
                    margin-bottom: 10px;
                }
                .label {
                    font-weight: bold;
                    color: #0c4a6e;
                    display: inline-block;
                    min-width: 180px;
                }
                .value {
                    color: #333;
                }
                .watermark {
                    position: fixed;
                    top: 40%;
                    left: 25%;
                    transform: rotate(-45deg);
                    font-size: 60px;
                    color: rgba(3, 105, 161, 0.1);
                    z-index: -1;
                    pointer-events: none;
                }
                .footer {
                    margin-top: 40px;
                    padding-top: 15px;
                    border-top: 1px solid #ddd;
                    text-align: center;
                    font-size: 12px;
                    color: #666;
                }
                .signature {
                    margin-top: 50px;
                    text-align: right;
                }
                .signature-line {
                    border-top: 1px solid #333;
                    width: 250px;
                    margin-left: auto;
                    margin-top: 60px;
                }
                .stamp {
                    border: 2px solid #0369a1;
                    padding: 15px 25px;
                    display: inline-block;
                    margin-top: 20px;
                    border-radius: 8px;
                    font-weight: bold;
                    color: #0369a1;
                    text-align: center;
                    background: #f0f9ff;
                }
                .notes {
                    padding: 15px;
                    background: #fff3cd;
                    border-radius: 6px;
                    border-left: 4px solid #f59e0b;
                    margin: 20px 0;
                }
            </style>
        </head>
        <body>
            <div class="watermark">REGISTERED</div>
            
            <div class="header">
                <h1>GIRLS HOSTEL REGISTRATION CONFIRMATION</h1>
                <h2>Registration ID: STU${String(studentData.id).padStart(5, '0')}</h2>
                <p>Generated on: ${new Date().toLocaleDateString('en-IN', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                })}</p>
            </div>
            
            <div class="section">
                <div class="section-title">STUDENT PERSONAL INFORMATION</div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Student Name:</span>
                        <span class="value">${studentData.student_name}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Father's Name:</span>
                        <span class="value">${studentData.father_name}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Primary Mobile:</span>
                        <span class="value">${studentData.mobile1}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Secondary Mobile:</span>
                        <span class="value">${studentData.mobile2 || 'N/A'}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Email Address:</span>
                        <span class="value">${studentData.email}</span>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">ADDRESS DETAILS</div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Country:</span>
                        <span class="value">${studentData.country}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">State:</span>
                        <span class="value">${studentData.state}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">District:</span>
                        <span class="value">${studentData.district}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Native Place:</span>
                        <span class="value">${studentData.native_place}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Pincode:</span>
                        <span class="value">${studentData.pincode}</span>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">ACADEMIC INFORMATION</div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Mode of Admission:</span>
                        <span class="value">${studentData.admission}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Degree Admitted:</span>
                        <span class="value">${studentData.degree}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Registration Date:</span>
                        <span class="value">${studentData.created_at}</span>
                    </div>
                </div>
            </div>
            
            <div class="notes">
                <strong>Important Notes:</strong>
                <p>1. This is an official registration confirmation document.</p>
                <p>2. Please keep this document safe for future reference.</p>
                <p>3. Present this document along with original certificates during hostel admission.</p>
                <p>4. For any queries, contact the hostel administration office.</p>
            </div>
            
            <div class="signature">
                <div class="signature-line"></div>
                <p style="margin-top: 10px; margin-right: 50px;">Authorized Signature</p>
                <div class="stamp">
                    HOSTEL ADMINISTRATION<br>
                    REGISTRATION CONFIRMED
                </div>
            </div>
            
            <div class="footer">
                <p>Document ID: STU${String(studentData.id).padStart(5, '0')}</p>
                <p>This document is electronically generated and valid without signature.</p>
            </div>
        </body>
        </html>
    `;
    
    // Open in new window for printing/saving as PDF
    const printWindow = window.open('', '_blank');
    printWindow.document.write(htmlContent);
    printWindow.document.close();
    
    // Add print/PDF save functionality
    setTimeout(() => {
        printWindow.print();
    }, 500);
}

// Start new registration
function startNewRegistration() {
    // Clear the session and reload the page
    window.location.href = window.location.pathname;
}

// Add loading animation to form submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    if (form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        
        form.addEventListener('submit', function() {
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="loader"></span> Processing Registration...';
            submitBtn.disabled = true;
        });
    }
    
    // Create floating elements
    const floatingContainer = document.createElement('div');
    floatingContainer.className = 'floating-elements';
    document.body.appendChild(floatingContainer);
    
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
    
    // Add background pattern
    const backgroundPattern = document.createElement('div');
    backgroundPattern.className = 'background-pattern';
    document.body.insertBefore(backgroundPattern, document.body.firstChild);
    
    // Auto-scroll to top if showing success message
    <?php if ($showSuccess): ?>
    window.scrollTo(0, 0);
    <?php endif; ?>
});
</script>
</head>

<body>
<!-- Light Blue Background Pattern -->
<div class="background-pattern"></div>

<!-- Floating Elements -->
<div class="floating-elements" id="floatingElements"></div>

<div class="container">
    <!-- SUCCESS MESSAGE SECTION -->
    <div class="success-container" id="successContainer">
        <div class="success-header">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h2>🎉 Registration Successful!</h2>
            <p>Your hostel registration has been completed successfully</p>
            <div class="registration-id">
                <i class="fas fa-id-badge"></i> Registration ID: STU<?php echo isset($studentData['id']) ? str_pad($studentData['id'], 5, '0', STR_PAD_LEFT) : ''; ?>
            </div>
        </div>
        
        <div class="details-container">
            <div class="details-section">
                <h3><i class="fas fa-user-circle"></i> Personal Information</h3>
                <div class="detail-row">
                    <div class="detail-label">Student Name:</div>
                    <div class="detail-value"><?php echo isset($studentData['student_name']) ? htmlspecialchars($studentData['student_name']) : ''; ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Father's Name:</div>
                    <div class="detail-value"><?php echo isset($studentData['father_name']) ? htmlspecialchars($studentData['father_name']) : ''; ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Mobile 1:</div>
                    <div class="detail-value"><?php echo isset($studentData['mobile1']) ? htmlspecialchars($studentData['mobile1']) : ''; ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Mobile 2:</div>
                    <div class="detail-value"><?php echo isset($studentData['mobile2']) && !empty($studentData['mobile2']) ? htmlspecialchars($studentData['mobile2']) : 'N/A'; ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Email:</div>
                    <div class="detail-value"><?php echo isset($studentData['email']) ? htmlspecialchars($studentData['email']) : ''; ?></div>
                </div>
            </div>
            
            <div class="details-section">
                <h3><i class="fas fa-map-marked-alt"></i> Address Details</h3>
                <div class="detail-row">
                    <div class="detail-label">Country:</div>
                    <div class="detail-value"><?php echo isset($studentData['country']) ? htmlspecialchars($studentData['country']) : ''; ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">State:</div>
                    <div class="detail-value"><?php echo isset($studentData['state']) ? htmlspecialchars($studentData['state']) : ''; ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">District:</div>
                    <div class="detail-value"><?php echo isset($studentData['district']) ? htmlspecialchars($studentData['district']) : ''; ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Native Place:</div>
                    <div class="detail-value"><?php echo isset($studentData['native_place']) ? htmlspecialchars($studentData['native_place']) : ''; ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Pincode:</div>
                    <div class="detail-value"><?php echo isset($studentData['pincode']) ? htmlspecialchars($studentData['pincode']) : ''; ?></div>
                </div>
            </div>
            
            <div class="details-section">
                <h3><i class="fas fa-graduation-cap"></i> Academic Details</h3>
                <div class="detail-row">
                    <div class="detail-label">Admission Mode:</div>
                    <div class="detail-value"><?php echo isset($studentData['admission']) ? htmlspecialchars($studentData['admission']) : ''; ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Degree:</div>
                    <div class="detail-value"><?php echo isset($studentData['degree']) ? htmlspecialchars($studentData['degree']) : ''; ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Registration Date:</div>
                    <div class="detail-value"><?php echo isset($studentData['created_at']) ? htmlspecialchars($studentData['created_at']) : ''; ?></div>
                </div>
            </div>
        </div>
        
        <div class="action-buttons">
            <button onclick="generateRegistrationPDF()" class="pdf-btn">
                <i class="fas fa-file-pdf"></i> Download Registration PDF
            </button>
            <button onclick="startNewRegistration()" class="new-reg-btn">
                <i class="fas fa-user-plus"></i> Register Another Student
            </button>
        </div>
        
        <div class="note-box">
            <h4><i class="fas fa-lightbulb"></i> Important Information</h4>
            <p>✅ Please download and save your registration confirmation PDF</p>
            <p>✅ Present this document during hostel admission along with original documents</p>
            <p>✅ For any queries, contact hostel administration office</p>
            <p>✅ Keep your Registration ID safe for future reference</p>
        </div>
    </div>
    
    <!-- REGISTRATION FORM SECTION -->
    <div class="form-container" id="formContainer">
        <div class="header">
            <h2><i class="fas fa-user-graduate"></i> Student Registration</h2>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-section">
                <div class="form-row">
                    <div class="col">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Student Name</label>
                            <div class="input-container">
                                <i class="fas fa-user"></i>
                                <input type="text" name="student_name" required />
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label><i class="fas fa-user-friends"></i> Father Name</label>
                            <div class="input-container">
                                <i class="fas fa-user-friends"></i>
                                <input type="text" name="father_name" required />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="col">
                        <div class="form-group">
                            <label><i class="fas fa-mobile-alt"></i> Phone Number 1</label>
                            <div class="input-container">
                                <i class="fas fa-mobile-alt"></i>
                                <input type="text" name="mobile1" minlength="10" maxlength="10" required oninput="allowOnlyNumbers(this,10)" />
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label><i class="fas fa-mobile-alt"></i> Phone Number 2</label>
                            <div class="input-container">
                                <i class="fas fa-mobile-alt"></i>
                                <input type="text" name="mobile2" minlength="10" maxlength="10" oninput="allowOnlyNumbers(this,10)" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <div class="input-container">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" required />
                    </div>
                </div>

                <div class="form-row">
                    <div class="col">
                        <div class="form-group">
                            <label><i class="fas fa-globe"></i> Country</label>
                            <div class="input-container">
                                <i class="fas fa-globe"></i>
                                <select id="country" name="country" onchange="populateStates()" required>
                                    <option value="">-- Select Country --</option>
                                    <option value="India">India</option>
                                    <option value="United States">United States</option>
                                    <option value="United Kingdom">United Kingdom</option>
                                    <option value="Australia">Australia</option>
                                    <option value="Canada">Canada</option>
                                    <option value="Nepal">Nepal</option>
                                    <option value="Bangladesh">Bangladesh</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> State</label>
                            <div class="input-container">
                                <i class="fas fa-map-marker-alt"></i>
                                <input list="stateList" id="state" name="state" onchange="populateDistricts()" required />
                                <datalist id="stateList"></datalist>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="col">
                        <div class="form-group">
                            <label><i class="fas fa-map"></i> District</label>
                            <div class="input-container">
                                <i class="fas fa-map"></i>
                                <input list="districtList" id="district" name="district" required />
                                <datalist id="districtList"></datalist>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label><i class="fas fa-home"></i> Native Place</label>
                            <div class="input-container">
                                <i class="fas fa-home"></i>
                                <input type="text" name="native_place" required />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="col">
                        <div class="form-group">
                            <label><i class="fas fa-graduation-cap"></i> Mode of Admission</label>
                            <div class="input-container">
                                <i class="fas fa-graduation-cap"></i>
                                <select name="admission" required>
                                    <option value="KCET">KCET</option>
                                    <option value="Management">Management</option>
                                    <option value="COMEDK">COMEDK</option>
                                    <option value="DCET">DCET</option>
                                    <option value="NATA">NATA</option>
                                    <option value="PGCET">PGCET</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label><i class="fas fa-user-graduate"></i> Degree Admitted</label>
                            <div class="input-container">
                                <i class="fas fa-user-graduate"></i>
                                <select name="degree" required>
                                    <option value="BE">Bachelor of Engineering</option>
                                    <option value="MBA">MBA</option>
                                    <option value="BCA">BCA</option>
                                    <option value="Architecture">Architecture</option>
                                    <option value="Mtech">Mtech</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="col">
                        <div class="form-group">
                            <label><i class="fas fa-map-pin"></i> Pin Code</label>
                            <div class="input-container">
                                <i class="fas fa-map-pin"></i>
                                <input type="text" name="pincode" minlength="6" maxlength="6" required oninput="allowOnlyNumbers(this,6)" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- File Uploads Section - Front and Back Aadhaar -->
                <div class="form-row">
                    <div class="col">
                        <div class="form-group">
                            <label><i class="fas fa-id-card"></i> Upload Front Aadhaar Card</label>
                            <div class="input-container">
                                <i class="fas fa-id-card"></i>
                                <input type="file" name="front_aadhaar" required />
                            </div>
                        </div>
                    </div>
                    
                    <div class="col">
                        <div class="form-group">
                            <label><i class="fas fa-id-card"></i> Upload Back Aadhaar Card</label>
                            <div class="input-container">
                                <i class="fas fa-id-card"></i>
                                <input type="file" name="back_aadhaar" required />
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn">
                    <i class="fas fa-paper-plane"></i> Submit Registration
                </button>
            </div>
        </form>
    </div>
</div>
</body>
</html>

<?php
// Clear session data after displaying
if ($showSuccess) {
    unset($_SESSION['student_data']);
    unset($_SESSION['last_registration_id']);
}
?>