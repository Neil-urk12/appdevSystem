<?php
require_once __DIR__ . '/../db_connect.php';

if (isset($_GET["delete"])) {
    try {
        $id = (int) $_GET["delete"];
        $sql = "DELETE FROM employees WHERE id = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("i", $id);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $stmt->close();
        header("Location: ../index.php");
        exit();
    } catch (Exception $e) {
        die("Error deleting employee: " . $e->getMessage());
    }
}
?>
