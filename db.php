<?php
$servername = "127.0.0.1";
$username = "root";
$password = "my-secret-pw"; // change if your MySQL has a different password
$database = "tihan_project_management"; // your DB name
$port = 3307; // reverse SSH tunnel port

$conn = new mysqli($servername, $username, $password, $database, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
