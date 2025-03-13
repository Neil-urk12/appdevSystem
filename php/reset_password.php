<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';

$error_message = null;
$success_message = null;
$token_valid = false;

// Verify reset token and handle password reset
if (isset($_GET['token'])) {
    try {
        $token = $conn->real_escape_string($_GET['token']);
        
        // Debug: Log the token being verified
        error_log("Verifying reset token: " . $token);
        
        // Check if token exists and is not expired
        $sql = "SELECT id, reset_expires FROM users WHERE reset_token = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Debug: Log the query results
        error_log("Token query results: " . $result->num_rows . " rows found");
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $expires = strtotime($user['reset_expires']);
            $now = time();
            
            // Debug: Log expiration check
            error_log("Token expires: " . date('Y-m-d H:i:s', $expires));
            error_log("Current time: " . date('Y-m-d H:i:s', $now));
            
            if ($now > $expires) {
                $error_message = "Reset token has expired. Please request a new password reset.";
                error_log("Token expired");
            } else {
                // Token is valid
                $token_valid = true;
            }
        } else {
            $error_message = "Invalid or expired reset token. Please request a new password reset.";
            error_log("Token not found in database");
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
        error_log("Token verification error: " . $e->getMessage());
    }
}

// Handle password reset form submission
if (isset($_POST['reset_password']) && isset($_GET['token'])) {
    try {
        $token = $conn->real_escape_string($_GET['token']);
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Debug: Log password reset attempt
        error_log("Password reset attempt with token: " . $token);
        
        // Validate password
        if (strlen($new_password) < 8) {
            $error_message = "Password must be at least 8 characters long";
        } else if ($new_password !== $confirm_password) {
            $error_message = "Passwords do not match";
        } else {
            // Get user by token
            $sql = "SELECT id FROM users WHERE reset_token = ?";
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // Debug: Log user lookup results
            error_log("User lookup results: " . $result->num_rows . " rows found");
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Update password and clear reset token
                $update_sql = "UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                
                if (!$update_stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt->bind_param("si", $hashed_password, $user['id']);
                
                if ($update_stmt->execute()) {
                    $success_message = "Password has been successfully reset. You can now login with your new password.";
                    error_log("Password successfully reset for user ID: " . $user['id']);
                } else {
                    throw new Exception("Failed to update password");
                }
                
                $update_stmt->close();
            } else {
                $error_message = "Invalid or expired reset token. Please request a new password reset.";
                error_log("Token not found or expired during password reset");
            }
            
            $stmt->close();
        }
    } catch (Exception $e) {
        $error_message = "Password reset error: " . $e->getMessage();
        error_log("Password reset error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .reset-container {
            background-color: white;
            padding: 20px;
            border-radius: 24px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: blue;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            margin-bottom: 10px;
        }
        button:hover {
            color: blue;
            border: 1px solid blue;
            background-color: white;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
        .success {
            color: green;
            margin-bottom: 15px;
        }
        a {
            color: blue;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <h2>Reset Password</h2>
        
        <?php if (isset($error_message)): ?>
            <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php if ($error_message === "Invalid or expired reset token. Please request a new password reset."): ?>
                <p><a href="authpage.php">Return to Login</a></p>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (isset($success_message)): ?>
            <div class="success">
                <?php echo htmlspecialchars($success_message); ?>
                <p><a href="authpage.php">Return to Login</a></p>
            </div>
        <?php elseif ($token_valid || (!isset($error_message))): ?>
            <form method="post">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" name="reset_password">Reset Password</button>
                <a href="authpage.php" style="display: block; text-align: center; margin-top: 10px;">Cancel</a>
            </form>
        <?php else: ?>
            <p><a href="authpage.php">Return to Login</a></p>
        <?php endif; ?>
    </div>
</body>
</html>
