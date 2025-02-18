<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

try {
    $conn = new mysqli('localhost', 'root', '@Mirkingwapa1112', 'employee_db');
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Handle registration form submission
if (isset($_POST['register'])) {
    try {
        $username = $conn->real_escape_string($_POST['username']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate password match
        if ($password !== $confirm_password) {
            $error_message = "Passwords do not match";
        } else {
            // Check if username already exists
            $check_sql = "SELECT id FROM users WHERE username = ?";
            $check_stmt = $conn->prepare($check_sql);
            
            if (!$check_stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = "Username already exists";
            } else {
                // Insert new user with default 'user' role
                $insert_sql = "INSERT INTO users (username, password, role) VALUES (?, ?, 'user')";
                $insert_stmt = $conn->prepare($insert_sql);
                
                if (!$insert_stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $insert_stmt->bind_param("ss", $username, $password);
                
                if ($insert_stmt->execute()) {
                    $success_message = "Registration successful! Please login.";
                } else {
                    throw new Exception("Registration failed: " . $insert_stmt->error);
                }
                
                $insert_stmt->close();
            }
            $check_stmt->close();
        }
    } catch (Exception $e) {
        $error_message = "Registration error: " . $e->getMessage();
    }
}

// Handle login form submission
if (isset($_POST['login'])) {
    try {
        $username = $conn->real_escape_string($_POST['username']);
        $password = $_POST['password'];
        
        $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $username);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            // For now, using direct comparison - you should implement password_hash() for new users
            if ($password === $user['password']) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: user_management.php");
                } else {
                    header("Location: index.php"); // Create this page for regular users
                }
                exit();
            }
        }
        
        $error_message = "Invalid username or password";
        $stmt->close();
        
    } catch (Exception $e) {
        $error_message = "Login error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
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
        .login-container {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
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
        input[type="text"],
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
        }
        button:hover {
           color: blue;
            border: 2px solid blue; 
            background-color: rgb(255, 255, 255);
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
        .auth-toggle {
            text-align: center;
            margin-top: 15px;
        }
        
        .auth-toggle a {
            color: blue;
            text-decoration: none;
        }
        
        .auth-toggle a:hover {
            text-decoration: underline;
        }
        
        .success {
            color: #4CAF50;
            margin-bottom: 15px;
        }
        
        #loginForm, #registerForm {
            display: none;
        }
        
        .active {
            display: block !important;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 id="formTitle">Login</h2>
        
        <?php if (isset($error_message)): ?>
            <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($success_message)): ?>
            <div class="success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <!-- Login Form -->
        <form id="loginForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="active">
            <div class="form-group">
                <label for="login_username">Username:</label>
                <input type="text" id="login_username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="login_password">Password:</label>
                <input type="password" id="login_password" name="password" required>
            </div>
            
            <button type="submit" name="login">Login</button>
            
            <div class="auth-toggle">
                Don't have an account? <a href="#" onclick="toggleForms('register'); return false;">Register</a>
            </div>
        </form>
        
        <!-- Registration Form -->
        <form id="registerForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label for="reg_username">Username:</label>
                <input type="text" id="reg_username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="reg_password">Password:</label>
                <input type="password" id="reg_password" name="password" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" name="register">Register</button>
            
            <div class="auth-toggle">
                Already have an account? <a href="#" onclick="toggleForms('login'); return false;">Login</a>
            </div>
        </form>
    </div>

    <script>
        function toggleForms(form) {
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            const formTitle = document.getElementById('formTitle');
            
            if (form === 'register') {
                loginForm.classList.remove('active');
                registerForm.classList.add('active');
                formTitle.textContent = 'Register';
            } else {
                registerForm.classList.remove('active');
                loginForm.classList.add('active');
                formTitle.textContent = 'Login';
            }
        }
        
        // Show the appropriate form based on the action
        <?php if (isset($_POST['register'])): ?>
            toggleForms('register');
        <?php endif; ?>
    </script>
</body>
</html>
