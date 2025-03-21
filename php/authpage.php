<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'db_connect.php';

// Function to generate recovery codes
function generateRecoveryCodes($count = 10) {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $codes[] = bin2hex(random_bytes(10)); // 20 characters long
    }
    return $codes;
}

// Function to hash security answers
function hashSecurityAnswer($answer) {
    return password_hash(strtolower(trim($answer)), PASSWORD_DEFAULT);
}

// Default security questions
function getDefaultSecurityQuestions() {
    return [
        "What was the name of your first pet?",
        "In which city were you born?",
        "What was your childhood nickname?",
        "What is your mother's maiden name?",
        "What was the name of your first school?",
        "What is the make of your first car?",
        "What is your favorite book?",
        "What is the name of the street you grew up on?"
    ];
}

// Handle registration form submission
if (isset($_POST['register'])) {
    try {
        $username = $conn->real_escape_string($_POST['username']);
        $email = $conn->real_escape_string($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate input
        if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
            $error_message = "All fields are required";
            $_SESSION['show_form'] = 'registerForm';
        } else if ($password !== $confirm_password) {
            $error_message = "Passwords do not match";
            $_SESSION['show_form'] = 'registerForm';
        } else if (strlen($password) < 8) {
            $error_message = "Password must be at least 8 characters long";
            $_SESSION['show_form'] = 'registerForm';
        } else {
            // Check if username or email already exists
            $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
            $check_stmt = $conn->prepare($check_sql);
            
            if (!$check_stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $check_stmt->bind_param("ss", $username, $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_message = "Username or email already exists";
                $_SESSION['show_form'] = 'registerForm';
            } else {
                // Generate recovery codes
                $recovery_codes = generateRecoveryCodes();
                
                // Hash security answers
                $security_answers = array();
                foreach ($_POST['security_answers'] as $answer) {
                    $security_answers[] = hashSecurityAnswer($answer);
                }
                
                // Get selected security questions
                $questions = array_slice(getDefaultSecurityQuestions(), 0, 3);
                
                // Insert new user
                $sql = "INSERT INTO users (username, email, password, recovery_codes, security_questions, security_answers, roles) VALUES (?, ?, ?, ?, ?, ?, 'user')";
                $stmt = $conn->prepare($sql);
                
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $recovery_codes_json = json_encode($recovery_codes);
                $questions_json = json_encode($questions);
                $answers_json = json_encode($security_answers);
                
                $stmt->bind_param("ssssss", $username, $email, $hashed_password, $recovery_codes_json, $questions_json, $answers_json);
                
                if ($stmt->execute()) {
                    $_SESSION['recovery_codes'] = $recovery_codes;
                    $success_message = "Registration successful! Please save your recovery codes.";
                    $_SESSION['show_form'] = 'loginForm';
                } else {
                    throw new Exception("Registration failed");
                }
                
                $stmt->close();
            }
            $check_stmt->close();
        }
    } catch (Exception $e) {
        $error_message = "Registration error: " . $e->getMessage();
        $_SESSION['show_form'] = 'registerForm';
    }
}

// Handle forgot password form submission
if (isset($_POST['forgot_password'])) {
    try {
        $email = $conn->real_escape_string($_POST['email']);
        $recovery_code = isset($_POST['recovery_code']) ? $_POST['recovery_code'] : null;
        
        if (empty($recovery_code)) {
            $error_message = "Please enter a recovery code";
            $_SESSION['show_form'] = 'forgotPasswordForm';
        } else {
            // Debug: Log the email being searched
            error_log("Searching for email: " . $email);
            
            $sql = "SELECT id, email, recovery_codes FROM users WHERE email = ?";
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $stored_codes = json_decode($user['recovery_codes'], true);
                
                // Debug: Log the recovery code check
                error_log("Checking recovery code: " . $recovery_code);
                error_log("Stored codes: " . print_r($stored_codes, true));
                
                if ($recovery_code && in_array($recovery_code, $stored_codes)) {
                    // Generate password reset token
                    $reset_token = bin2hex(random_bytes(32));
                    $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Debug: Log the token being generated
                    error_log("Generated reset token: " . $reset_token);
                    error_log("Token expires: " . $reset_expires);
                    
                    // Update user with reset token
                    $update_sql = "UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    
                    if (!$update_stmt) {
                        throw new Exception("Prepare failed for token update: " . $conn->error);
                    }
                    
                    $update_stmt->bind_param("ssi", $reset_token, $reset_expires, $user['id']);
                    
                    if ($update_stmt->execute()) {
                        // Debug: Log successful token update
                        error_log("Successfully updated reset token for user ID: " . $user['id']);
                        
                        // Remove used recovery code
                        $stored_codes = array_diff($stored_codes, [$recovery_code]);
                        $update_codes_sql = "UPDATE users SET recovery_codes = ? WHERE id = ?";
                        $update_codes_stmt = $conn->prepare($update_codes_sql);
                        $new_codes_json = json_encode($stored_codes);
                        $update_codes_stmt->bind_param("si", $new_codes_json, $user['id']);
                        $update_codes_stmt->execute();
                        
                        // Debug: Log the redirect URL
                        $redirect_url = "reset_password.php?token=" . urlencode($reset_token);
                        error_log("Redirecting to: " . $redirect_url);
                        
                        header("Location: " . $redirect_url);
                        exit();
                    } else {
                        throw new Exception("Failed to update reset token: " . $update_stmt->error);
                    }
                } else {
                    $error_message = "Invalid recovery code. Please try again or use security questions.";
                    $_SESSION['show_form'] = 'forgotPasswordForm';
                }
            } else {
                $error_message = "Email not found";
                $_SESSION['show_form'] = 'forgotPasswordForm';
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        $error_message = "Password reset error: " . $e->getMessage();
        $_SESSION['show_form'] = 'forgotPasswordForm';
        // Debug: Log any errors
        error_log("Password reset error: " . $e->getMessage());
    }
}

// Handle security questions verification
if (isset($_POST['verify_security_questions'])) {
    try {
        $email = $conn->real_escape_string($_POST['email']);
        
        // Debug: Log the email being searched
        error_log("Verifying security questions for email: " . $email);
        
        $sql = "SELECT id, security_questions, security_answers FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $stored_answers = json_decode($user['security_answers'], true);
            $all_correct = true;
            
            // Debug: Log the answers being checked
            error_log("Checking security answers for user ID: " . $user['id']);
            
            // Verify each security answer
            foreach ($_POST['security_answers'] as $index => $answer) {
                $trimmed_answer = strtolower(trim($answer));
                // Debug: Log each answer verification
                error_log("Verifying answer " . ($index + 1) . ": " . $trimmed_answer);
                
                if (!password_verify($trimmed_answer, $stored_answers[$index])) {
                    $all_correct = false;
                    error_log("Answer " . ($index + 1) . " is incorrect");
                    break;
                }
            }
            
            if ($all_correct) {
                // Generate password reset token
                $reset_token = bin2hex(random_bytes(32));
                $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Debug: Log the token being generated
                error_log("Generated reset token: " . $reset_token);
                error_log("Token expires: " . $reset_expires);
                
                // Update user with reset token
                $update_sql = "UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                
                if (!$update_stmt) {
                    throw new Exception("Prepare failed for token update: " . $conn->error);
                }
                
                $update_stmt->bind_param("ssi", $reset_token, $reset_expires, $user['id']);
                
                if ($update_stmt->execute()) {
                    // Debug: Log successful token update
                    error_log("Successfully updated reset token for user ID: " . $user['id']);
                    
                    $redirect_url = "reset_password.php?token=" . urlencode($reset_token);
                    error_log("Redirecting to: " . $redirect_url);
                    
                    header("Location: " . $redirect_url);
                    exit();
                } else {
                    throw new Exception("Failed to update reset token: " . $update_stmt->error);
                }
            } else {
                $error_message = "Incorrect answers to security questions. Please try again.";
                $_SESSION['show_form'] = 'securityQuestionsForm';
            }
        } else {
            $error_message = "Email not found";
            $_SESSION['show_form'] = 'securityQuestionsForm';
        }
        $stmt->close();
    } catch (Exception $e) {
        $error_message = "Security questions verification error: " . $e->getMessage();
        $_SESSION['show_form'] = 'securityQuestionsForm';
        // Debug: Log any errors
        error_log("Security questions verification error: " . $e->getMessage());
    }
}

// Handle login form submission
if (isset($_POST['login'])) {
    try {
        $username = $conn->real_escape_string($_POST['username']);
        $password = $_POST['password'];
        
        // Debug the input
        error_log("Login attempt for username: " . $username);
        
        $sql = "SELECT id, username, password, roles FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $username);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        error_log("Query result rows: " . $result->num_rows);
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            error_log("User data found: " . json_encode($user));
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['roles'];
                
                error_log("Session data set: " . json_encode($_SESSION));
                error_log("Role comparison: '" . $user['roles'] . "' === 'admin' is: " . ($user['roles'] === 'admin' ? 'true' : 'false'));
                
                if ($user['roles'] === 'admin') {
                    error_log("Redirecting admin to admin.php");
                    header("Location: admin.php");
                } else {
                    error_log("Redirecting user to index.php");
                    header("Location: index.php");
                }
                exit();
            } else {
                error_log("Password verification failed");
                $error_message = "Invalid username or password";
            }
        } else {
            error_log("No user found with username: " . $username);
            $error_message = "Invalid username or password";
        }
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $error_message = "Login error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/authpage.css">
    <title>Login</title>
</head>
<body>
    <div class="circle"></div>
    <!-- <div class="banner">
        <h1>Employee Sigmanagement System</h1>
    </div> -->
    <div class="login-container">
        <h2 id="formTitle">Login</h2>
        
        <?php if (isset($error_message)): ?>
            <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($success_message)): ?>
            <div class="success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php if (isset($_SESSION['recovery_codes'])): ?>
                <div class="recovery-codes">
                    <div class="recovery-codes-warning">
                        IMPORTANT: Save these recovery codes in a secure location. They will only be shown once!
                    </div>
                    <?php foreach ($_SESSION['recovery_codes'] as $code): ?>
                        <code><?php echo htmlspecialchars($code); ?></code>
                    <?php endforeach; ?>
                    <button class="btn" onclick="downloadRecoveryCodes()" style="margin-top: 10px;">Download Recovery Codes</button>
                </div>
                <?php unset($_SESSION['recovery_codes']); ?>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Login Form -->
        <form id="loginForm" method="post" class="<?php echo (!isset($_SESSION['show_form']) || $_SESSION['show_form'] === 'loginForm') ? 'active' : ''; ?>" style="<?php echo (!isset($_SESSION['show_form']) || $_SESSION['show_form'] === 'loginForm') ? 'display: block;' : 'display: none;'; ?>">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <button class="forgot-password" type="button" onclick="showForm('forgotPasswordForm')">Forgot Password?</button>
            </div>
            <button class="btn" type="submit" name="login">Login</button>
            <button class="register-btn" type="button" onclick="showForm('registerForm')">Don't have an account? | Register</button>
        </form>

        <!-- Register Form -->
        <form id="registerForm" method="post" style="<?php echo (isset($_SESSION['show_form']) && $_SESSION['show_form'] === 'registerForm') ? 'display: block;' : 'display: none;'; ?>">
            <div class="form-group">
                <label for="reg_username">Username</label>
                <input type="text" id="reg_username" name="username" required>
            </div>
            <div class="form-group">
                <label for="reg_email">Email</label>
                <input type="email" id="reg_email" name="email" required>
            </div>
            <div class="form-group">
                <label for="reg_password">Password</label>
                <input type="password" id="reg_password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <div id="security-questions">
                <h3>Security Questions</h3>
                <?php 
                $questions = array_slice(getDefaultSecurityQuestions(), 0, 3);
                foreach ($questions as $index => $question): 
                ?>
                    <div class="form-group">
                        <label><?php echo htmlspecialchars($question); ?></label>
                        <input type="text" name="security_answers[]" required>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="btn" type="submit" name="register">Register</button>
            <button class="btn" type="button" onclick="showForm('loginForm')">Back to Login</button>
        </form>

        <!-- Forgot Password Form -->
        <form id="forgotPasswordForm" method="post" style="<?php echo (isset($_SESSION['show_form']) && $_SESSION['show_form'] === 'forgotPasswordForm') ? 'display: block;' : 'display: none;'; ?>">
            <div class="form-group">
                <label for="forgot_email">Email</label>
                <input type="email" id="forgot_email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="recovery_code">Recovery Code</label>
                <input type="text" id="recovery_code" name="recovery_code" placeholder="Enter your recovery code" required>
            </div>
            <button class="btn" type="submit" name="forgot_password">Reset Password</button>
            <button class="btn" type="button" onclick="showForm('securityQuestionsForm')">Use Security Questions Instead</button>
            <button class="btn" type="button" onclick="showForm('loginForm')">Back to Login</button>
        </form>

        <!-- Security Questions Form -->
        <form id="securityQuestionsForm" method="post" style="<?php echo (isset($_SESSION['show_form']) && $_SESSION['show_form'] === 'securityQuestionsForm') ? 'display: block;' : 'display: none;'; ?>">
            <div class="form-group">
                <label for="sq_email">Email</label>
                <input type="email" id="sq_email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            <div id="securityQuestionsContainer">
                <!-- Will be populated by JavaScript -->
            </div>
            <button class="btn" type="submit" name="verify_security_questions">Verify Answers</button>
            <button class="btn" type="button" onclick="showForm('forgotPasswordForm')">Use Recovery Code Instead</button>
            <button class="btn" type="button" onclick="showForm('loginForm')">Back to Login</button>
        </form>
    </div>

    <script>
        function showForm(formId) {
            document.querySelectorAll('form').forEach(form => form.style.display = 'none');
            document.getElementById(formId).style.display = 'block';
            document.getElementById('formTitle').textContent = formId === 'loginForm' ? 'Login' : 
                                                             formId === 'registerForm' ? 'Register' : 
                                                             formId === 'forgotPasswordForm' ? 'Forgot Password' :
                                                             'Security Questions';
            
            // Store the current form in session
            fetch('set_form.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'form=' + formId
            });
            
            if (formId === 'securityQuestionsForm') {
                loadSecurityQuestions();
            }
        }

        function downloadRecoveryCodes() {
            const codes = Array.from(document.querySelectorAll('.recovery-codes code'))
                              .map(code => code.textContent.trim())
                              .join('\n');
            const blob = new Blob([codes], { type: 'text/plain' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'recovery-codes.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        function loadSecurityQuestions() {
            const email = document.getElementById('sq_email').value;
            if (!email) return;

            fetch('get_security_questions.php?email=' + encodeURIComponent(email))
                .then(response => response.json())
                .then(questions => {
                    const container = document.getElementById('securityQuestionsContainer');
                    container.innerHTML = '';
                    questions.forEach((question, index) => {
                        container.innerHTML += `
                            <div class="form-group">
                                <label>${question}</label>
                                <input type="text" name="security_answers[]" required>
                            </div>
                        `;
                    });
                })
                .catch(error => console.error('Error loading security questions:', error));
        }

        // Add event listener to email input in security questions form
        document.getElementById('sq_email').addEventListener('change', loadSecurityQuestions);
    </script>
</body>
</html>
