<?php
require_once __DIR__ . '/../db_connect.php';

try {
    $searchTerm = "";
    if (isset($_GET["search"]) && trim($_GET["search"]) !== "") {
        $searchTerm = "%" . $conn->real_escape_string($_GET["search"]) . "%";
        $sql = "SELECT * FROM employees WHERE firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR position LIKE ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    } else {
        $sql = "SELECT * FROM employees";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
    }
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $result = $stmt->get_result();
} catch (Exception $e) {
    die("Error fetching employees: " . $e->getMessage());
}
?>
