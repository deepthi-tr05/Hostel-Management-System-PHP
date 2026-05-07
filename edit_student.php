<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin.php");
    exit();
}

// ---------------- CONFIG ----------------
$ENCRYPTION_KEY = "YOUR_ENCRYPTION_KEY_MIN_32_CHARS";

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

// ---------------- ENCRYPT FUNCTION ----------------
function encryptData($data, $key) {
    $method = "AES-256-CBC";
    $iv = substr(hash("sha256", $key), 0, 16);
    return openssl_encrypt($data, $method, $key, 0, $iv);
}

// Check if student ID is provided
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header("Location: admin.php");
    exit();
}

$student_id = $_GET['id'];

// Fetch student data
$stmt = $conn->prepare("SELECT * FROM register WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student_data = $result->fetch_assoc();
$stmt->close();

if (!$student_data) {
    header("Location: admin.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
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
        $success_message = "Student record updated successfully!";
    } else {
        $error_message = "Error updating record: " . $stmt->error;
    }
    
    $stmt->close();
    
    // Refresh student data after update
    $stmt = $conn->prepare("SELECT * FROM register WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student_data = $result->fetch_assoc();
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Edit Student - Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{
  --primary: #0ea5e9;
  --primary-light: #7dd3fc;
  --primary-dark: #0369a1;
  --secondary: #64748b;
  --success: #10b981;
  --danger: #ef4444;
  --warning: #f59e0b;
  --bg-primary: #f8fafc;
  --bg-secondary: #f1f5f9;
  --bg-card: #ffffff;
  --text-primary: #1e293b;
  --text-secondary: #475569;
  --text-muted: #64748b;
  --border-color: #e2e8f0;
  --shadow: 0 1px 3px rgba(0,0,0,0.1);
  --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
  --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1);
  --radius: 8px;
  --transition: all 0.3s ease;
  --gradient: linear-gradient(135deg, #0ea5e9 0%, #3b82f6 100%);
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background: var(--bg-primary);
  color: var(--text-primary);
  line-height: 1.6;
}

.background-pattern {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: 
    radial-gradient(circle at 25% 25%, rgba(14, 165, 233, 0.1) 0%, transparent 50%),
    radial-gradient(circle at 75% 75%, rgba(59, 130, 246, 0.1) 0%, transparent 50%);
  z-index: -1;
}

.container {
  max-width: 1000px;
  margin: 0 auto;
  padding: 20px;
}

.card {
  background: var(--bg-card);
  border-radius: var(--radius);
  box-shadow: var(--shadow-lg);
  border: 1px solid var(--border-color);
  overflow: hidden;
}

.card-header {
  background: var(--gradient);
  color: white;
  padding: 25px 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 20px;
}

.card-title h2 {
  font-size: 24px;
  font-weight: 700;
  margin-bottom: 5px;
}

.card-sub {
  font-size: 14px;
  opacity: 0.9;
}

.btn {
  padding: 8px 16px;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: var(--transition);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 38px;
}

.btn:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg);
}

.btn-back {
  background: rgba(255,255,255,0.2);
  color: white;
  backdrop-filter: blur(10px);
}

.btn-back:hover {
  background: rgba(255,255,255,0.3);
}

.edit-form-container {
  padding: 30px;
}

.edit-form-container h3 {
  color: var(--primary);
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 20px;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: var(--text-secondary);
}

.form-group input,
.form-group select {
  width: 100%;
  padding: 10px 12px;
  border: 2px solid var(--border-color);
  border-radius: 6px;
  font-size: 14px;
  transition: var(--transition);
  background: var(--bg-primary);
}

.form-group input:focus,
.form-group select:focus {
  border-color: var(--primary);
  outline: none;
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

.form-actions {
  display: flex;
  gap: 15px;
  justify-content: flex-end;
  margin-top: 25px;
  padding-top: 20px;
  border-top: 1px solid var(--border-color);
}

.btn-cancel {
  background: var(--text-muted);
  color: white;
}

.btn-update {
  background: var(--primary);
  color: white;
}

.alert {
  padding: 12px 16px;
  border-radius: var(--radius);
  margin-bottom: 20px;
  font-weight: 600;
}

.alert-success {
  background: #d1fae5;
  color: #065f46;
  border: 1px solid #10b981;
}

.alert-error {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #ef4444;
}

@media (max-width: 768px) {
  .form-row {
    grid-template-columns: 1fr;
  }
  
  .form-actions {
    flex-direction: column;
  }
  
  .card-header {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
</head>
<body>

<div class="background-pattern"></div>

<div class="container">
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <h2><i class="fas fa-edit"></i> Edit Student Record</h2>
        <div class="card-sub">Update student information</div>
      </div>
      
      <a href="admin.php" class="btn btn-back">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
      </a>
    </div>

    <div class="edit-form-container">
      <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
          <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
      <?php endif; ?>
      
      <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
          <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
      <?php endif; ?>
      
      <h3><i class="fas fa-user-graduate"></i> Student ID: <?php echo htmlspecialchars($student_id); ?></h3>
      
      <form method="POST">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($student_id); ?>">
        
        <div class="form-row">
          <div class="form-group">
            <label for="student_name">Student Name *</label>
            <input type="text" id="student_name" name="student_name" 
                   value="<?php echo htmlspecialchars(decryptData($student_data['student_name'], $ENCRYPTION_KEY)); ?>" required>
          </div>
          
          <div class="form-group">
            <label for="father_name">Father Name *</label>
            <input type="text" id="father_name" name="father_name" 
                   value="<?php echo htmlspecialchars(decryptData($student_data['father_name'], $ENCRYPTION_KEY)); ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="mobile1">Mobile 1 *</label>
            <input type="tel" id="mobile1" name="mobile1" 
                   value="<?php echo htmlspecialchars(decryptData($student_data['mobile1'], $ENCRYPTION_KEY)); ?>" required>
          </div>
          
          <div class="form-group">
            <label for="mobile2">Mobile 2</label>
            <input type="tel" id="mobile2" name="mobile2" 
                   value="<?php echo htmlspecialchars(decryptData($student_data['mobile2'], $ENCRYPTION_KEY)); ?>">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="email">Email *</label>
            <input type="email" id="email" name="email" 
                   value="<?php echo htmlspecialchars(decryptData($student_data['email'], $ENCRYPTION_KEY)); ?>" required>
          </div>
          
          <div class="form-group">
            <label for="country">Country *</label>
            <input type="text" id="country" name="country" 
                   value="<?php echo htmlspecialchars(decryptData($student_data['country'], $ENCRYPTION_KEY)); ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="state">State *</label>
            <input type="text" id="state" name="state" 
                   value="<?php echo htmlspecialchars(decryptData($student_data['state'], $ENCRYPTION_KEY)); ?>" required>
          </div>
          
          <div class="form-group">
            <label for="district">District *</label>
            <input type="text" id="district" name="district" 
                   value="<?php echo htmlspecialchars(decryptData($student_data['district'], $ENCRYPTION_KEY)); ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="native_place">Native Place *</label>
            <input type="text" id="native_place" name="native_place" 
                   value="<?php echo htmlspecialchars(decryptData($student_data['native_place'], $ENCRYPTION_KEY)); ?>" required>
          </div>
          
          <div class="form-group">
            <label for="admission">Admission *</label>
            <input type="text" id="admission" name="admission" 
                   value="<?php echo htmlspecialchars(decryptData($student_data['admission'], $ENCRYPTION_KEY)); ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="degree">Degree *</label>
            <input type="text" id="degree" name="degree" 
                   value="<?php echo htmlspecialchars(decryptData($student_data['degree'], $ENCRYPTION_KEY)); ?>" required>
          </div>
          
          <div class="form-group">
            <label for="pincode">Pincode *</label>
            <input type="text" id="pincode" name="pincode" 
                   value="<?php echo htmlspecialchars(decryptData($student_data['pincode'], $ENCRYPTION_KEY)); ?>" required>
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
  </div>
</div>

</body>
</html>