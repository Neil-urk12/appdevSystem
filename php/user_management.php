<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: authpage.php");
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

// Handle Create User
if (isset($_POST['create'])) {
    try {
        $username = $conn->real_escape_string($_POST['username']);
        $password = $_POST['password'];
        $role = $conn->real_escape_string($_POST['role']);
        
        $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("sss", $username, $password, $role);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
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
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $error_message = "Error deleting user: " . $e->getMessage();
    }
}

// Handle Update User
if (isset($_POST['update'])) {
    try {
        $id = (int)$_POST['id'];
        $username = $conn->real_escape_string($_POST['username']);
        $role = $conn->real_escape_string($_POST['role']);
        
        // If password is provided, update it too
        if (!empty($_POST['password'])) {
            $password = $_POST['password'];
            $sql = "UPDATE users SET username=?, password=?, role=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $username, $password, $role, $id);
        } else {
            $sql = "UPDATE users SET username=?, role=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $username, $role, $id);
        }
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $error_message = "Error updating user: " . $e->getMessage();
    }
}

// Fetch Users
try {
    $sql = "SELECT * FROM users ORDER BY created_at DESC";
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
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
        }
        .nav-links a {
            margin-right: 15px;
            text-decoration: none;
        }
        .back-btn {
            color: #4CAF50;
            padding: 8px 16px;
        }
        .nav-links a:hover {
            text-decoration: underline;
        }
        .logout-btn {
            color: #dc3545;
            padding: 8px 16px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-links">
            <a href="index.php" class="back-btn">← Back to Employee Management</a>
            <a href="authpage.php?logout=1" class="logout-btn">Logout</a>
        </div>
        
        <div class="table-heading">
            <h2>User Management</h2>
            <button type="button" class="btn" onclick="openModal()">Add User</button>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

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
                                <th>Role</th>
                                <th>Created At</th>
                                <th>Updated At</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td class="role-<?php echo $row['role']; ?>">
                                    <?php echo htmlspecialchars(ucfirst($row['role'])); ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($row['updated_at']); ?></td>
                                <td style="text-align: center;">
                                    <button type="button" onclick="editUser(
                                        <?php echo $row['id']; ?>, 
                                        '<?php echo addslashes($row['username']); ?>',
                                        '<?php echo addslashes($row['role']); ?>'
                                    )" class="btn btn-warning">Edit</button>
                                    
                                    <?php if ($row['id'] !== $_SESSION['user_id']): ?>
                                        <a href="?delete=<?php echo $row['id']; ?>" 
                                           onclick="return confirm('Are you sure you want to delete this user?')">
                                            <button type="button" class="btn btn-danger">Delete</button>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No users found.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Create User Modal -->
        <div id="createModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal()">&times;</span>
                <h3>Add New User</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
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
                <form method="POST">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="form-group">
                        <label for="edit_username">Username</label>
                        <input type="text" id="edit_username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_password">New Password (leave blank to keep current)</label>
                        <input type="password" id="edit_password" name="password">
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
        function editUser(id, username, role) {
            document.getElementById('editModal').style.display = 'flex';
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_password').value = ''; // Clear password field
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
            var createModal = document.getElementById('createModal');
            var editModal = document.getElementById('editModal');
            if (event.target == createModal) {
                closeModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>
