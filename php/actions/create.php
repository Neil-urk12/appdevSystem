<?php
require_once __DIR__ . '/../db_connect.php';

if (isset($_POST["create"])) {
    try {
        $errors = [];

        $firstname = trim($_POST["firstname"]);
        if (empty($firstname)) {
            $errors[] = "First name is required";
        } elseif (strlen($firstname) > 50) {
            $errors[] = "First name must be less than 50 characters";
        }

        $lastname = trim($_POST["lastname"]);
        if (strlen($lastname) > 50) {
            $errors[] = "Last name must be less than 50 characters";
        }

        $email = trim($_POST["email"]);
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        } elseif (strlen($email) > 100) {
            $errors[] = "Email must be less than 100 characters";
        }

        $checkEmail = "SELECT id FROM employees WHERE email = ?";
        $stmt = $conn->prepare($checkEmail);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Email already exists";
        }

        $position = trim($_POST["position"]);
        if (empty($position)) {
            $errors[] = "Position is required";
        } elseif (strlen($position) > 50) {
            $errors[] = "Position must be less than 50 characters";
        }

        if (empty($errors)) {
            $sql =
                "INSERT INTO employees (firstname, lastname, email, position, updated_at) VALUES (?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("ssss", $firstname, $lastname, $email, $position);

            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $stmt->close();
            $_SESSION["success_message"] = "Employee created successfully!";
            header("Location: ../index.php");
            exit();
        } else {
            $_SESSION["error_messages"] = $errors;
        }
    } catch (Exception $e) {
        $_SESSION["error_messages"] = [
            "Error creating employee: " . $e->getMessage(),
        ];
        die("Error creating employee: " . $e->getMessage());
    }
}
?>
