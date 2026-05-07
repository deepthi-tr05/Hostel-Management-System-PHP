<?php
$servername = "YOUR_DB_HOST";   
$username   = "YOUR_DB_USER";        
$password   = "YOUR_DB_PASSWORD";           
$dbname     = "YOUR_DB_NAME";   

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>