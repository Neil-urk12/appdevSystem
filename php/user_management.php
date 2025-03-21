<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: authpage.php");
    exit();
}

require_once 'db_connect.php';

// Handle Create User
if (isset($_POST['create'])) {
    try {
        $username = $conn->real_escape_string($_POST['username']);
        $email = $conn->real_escape_string($_POST['email']);
        $password = $_POST['password'];
        $role = $conn->real_escape_string($_POST['role']);
        
        // Validate input
        if (empty($username) || empty($email) || empty($password)) {
            throw new Exception("All fields are required");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }
        if (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters long");
        }

        // Check if username or email already exists
        $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $username, $email);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            throw new Exception("Username or email already exists");
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate recovery codes
        $recovery_codes = array();
        for ($i = 0; $i < 10; $i++) {
            $recovery_codes[] = bin2hex(random_bytes(10));
        }
        $recovery_codes_json = json_encode($recovery_codes);
        
        // Default security questions and empty answers
        $security_questions = json_encode(array_slice([
            "What was the name of your first pet?",
            "In what city were you born?",
            "What was your mother's maiden name?"
        ], 0, 3));
        $security_answers = json_encode(array_fill(0, 3, ''));
        
        $sql = "INSERT INTO users (username, email, password, role, recovery_codes, security_questions, security_answers) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("sssssss", $username, $email, $hashed_password, $role, $recovery_codes_json, $security_questions, $security_answers);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        $success_message = "User created successfully";
    } catch (Exception $e) {
        $error_message = "Error creating user: " . $e->getMessage();
    }
}

// Handle Delete User
if (isset($_GET['delete'])) {
    try {
        $id = (int)$_GET['delete'];
        
        // Prevent admin from deleting themselves
        if ($id === (int)$_SESSION['user_id']) {
            throw new Exception("Cannot delete your own account!");
        }
        
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        $success_message = "User deleted successfully";
    } catch (Exception $e) {
        $error_message = "Error deleting user: " . $e->getMessage();
    }
}

// Handle Update User
if (isset($_POST['update'])) {
    try {
        $id = (int)$_POST['id'];
        $username = $conn->real_escape_string($_POST['username']);
        $email = $conn->real_escape_string($_POST['email']);
        $role = $conn->real_escape_string($_POST['role']);
        
        // Validate input
        if (empty($username) || empty($email)) {
            throw new Exception("Username and email are required");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        // Check if username or email already exists for other users
        $check_sql = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ssi", $username, $email, $id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            throw new Exception("Username or email already exists");
        }
        
        // If password is provided, update it too
        if (!empty($_POST['password'])) {
            if (strlen($_POST['password']) < 8) {
                throw new Exception("Password must be at least 8 characters long");
            }
            $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql = "UPDATE users SET username=?, email=?, password=?, role=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $username, $email, $hashed_password, $role, $id);
        } else {
            $sql = "UPDATE users SET username=?, email=?, role=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $username, $email, $role, $id);
        }
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        $success_message = "User updated successfully";
    } catch (Exception $e) {
        $error_message = "Error updating user: " . $e->getMessage();
    }
}

// Fetch Users with Search
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
try {
    $searchTerm = "";
    if (isset($_GET["search"]) && trim($_GET["search"]) !== "") {
        $searchTerm = "%" . $conn->real_escape_string($_GET["search"]) . "%";
        $sql = "SELECT * FROM users WHERE username LIKE ? OR email LIKE ? OR roles LIKE ? ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
    } else {
        $sql = "SELECT * FROM users ORDER BY created_at DESC";
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
    die("Error fetching users: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <link rel="stylesheet" href="/css/styles.css">
    <style>
        .role-admin { color: #dc3545; font-weight: bold; }
        .role-user { color: #28a745; }
        .nav-links {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 15px 25px;
            border-radius: 8px;
        }
        .nav-links a {
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        .back-btn {
            color: #fff;
            background: rgba(76, 175, 80, 0.1);
        }
        .back-btn:hover {
            background: rgba(76, 175, 80, 0.2);
        }
        .logout-btn {
            color: #fff;
            background: rgba(220, 53, 69, 0.1);
        }
        .logout-btn:hover {
            background: rgba(220, 53, 69, 0.2);
        }
        .success-message {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.2);
            color: #28a745;
            padding: 12px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .error-message {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            color: #dc3545;
            padding: 12px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .modal {
            backdrop-filter: blur(5px);
        }
        .modal-content {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }
        .modal-content .form-group {
            margin-bottom: 20px;
        }
        .modal-content input[type="text"],
        .modal-content input[type="email"],
        .modal-content input[type="password"],
        .modal-content select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 1em;
        }
        .modal-content input:focus,
        .modal-content select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
            outline: none;
        }
        .password-requirements {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
        }
        .search-form {
            margin-bottom: 25px;
            display: flex;
            gap: 10px;
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 8px;
        }
        .search-form input[type="text"] {
            flex: 1;
            padding: 12px 15px;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 4px;
            font-size: 0.95em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="table-heading">
            <h2>User Management</h2>
            <button type="button" class="btn" onclick="openModal()">Add User</button>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form class="search-form" method="GET">
            <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn">Search</button>
            <?php if ($search): ?>
                <a href="?" class="btn btn-danger">Clear</a>
            <?php endif; ?>
        </form>

        <div class="card">
            <div class="card-header">
                User List
            </div>
            <div class="table-responsive">
                <?php if($result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td class="role-<?php echo $row['roles']; ?>">
                                    <?php echo htmlspecialchars(ucfirst($row['roles'])); ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                <td>
                                    <button type="button" onclick="editUser(
                                        <?php echo $row['id']; ?>, 
                                        '<?php echo addslashes($row['username']); ?>',
                                        '<?php echo addslashes($row['email']); ?>',
                                        '<?php echo addslashes($row['roles']); ?>'
                                    )" class="btn btn-warning btn-sm">Edit</button>
                                    
                                    <?php if ($row['id'] !== $_SESSION['user_id']): ?>
                                        <a href="?delete=<?php echo $row['id']; ?>" 
                                           onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                            <button type="button" class="btn btn-danger btn-sm">Delete</button>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center p-4">No users found.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Create User Modal -->
        <div id="createModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal()">&times;</span>
                <h3>Add New User</h3>
                <form method="POST" onsubmit="return validateForm(this)">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                        <div class="password-requirements">
                            Password must be at least 8 characters long
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <button type="submit" name="create" class="btn">Add User</button>
                </form>
            </div>
        </div>

        <!-- Edit User Modal -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeEditModal()">&times;</span>
                <h3>Edit User</h3>
                <form method="POST" onsubmit="return validateForm(this)">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="form-group">
                        <label for="edit_username">Username</label>
                        <input type="text" id="edit_username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_email">Email</label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_password">New Password (leave blank to keep current)</label>
                        <input type="password" id="edit_password" name="password">
                        <div class="password-requirements">
                            Password must be at least 8 characters long
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_role">Role</label>
                        <select id="edit_role" name="role" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <button type="submit" name="update" class="btn">Update User</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function validateForm(form) {
            const password = form.password.value;
            if (password && password.length < 8) {
                alert('Password must be at least 8 characters long');
                return false;
            }
            return true;
        }

        function editUser(id, username, email, role) {
            document.getElementById('editModal').style.display = 'flex';
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_password').value = '';
        }

        function openModal() {
            document.getElementById('createModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('createModal').style.display = 'none';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
