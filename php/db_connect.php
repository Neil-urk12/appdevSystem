<?php
$host = "localhost";
$username = "root";
$password = "@Mirkingwapa1112";
$database = "employee_db";

try {
    $conn = new mysqli($host, $username, $password, $database);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}
?>
