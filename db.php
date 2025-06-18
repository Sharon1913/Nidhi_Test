<?php
<<<<<<< HEAD
$servername = "localhost";
$username = "root";
$password = ""; // change if your MySQL has a password
=======
$servername = "mysql";
$username = "root";
$password = "my-secret-pw"; // change if your MySQL has a password
>>>>>>> origin/rel-code
$database = "tihan_project_management"; // your DB name

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>