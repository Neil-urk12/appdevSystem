<?php
session_start();
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once __DIR__ . '/actions/logout.php';

// Check if user is not logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: authpage.php");
    exit();
}

require_once 'db_connect.php';



require_once __DIR__ . '/actions/list.php';
?>
<?php
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
<?php
require_once __DIR__ . '/actions/create.php';
require_once __DIR__ . '/actions/update.php';
require_once __DIR__ . '/actions/delete.php';
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <div style="position: absolute; bottom: 10px; left: 10px;">
        <form method="post" action="actions/logout.php">
            <button type="submit" name="logout" class="btn">Logout</button>
        </form>
    </div>
    <div class="container">

        <div class="table-heading">
            <h2>Employee Management</h2>
            <button type="button" class="btn" onclick="openModal()">Add Employee</button>
        </div>

        <form id="searchForm" method="GET">
            <input type="text" name="search" placeholder="Search employees..." value="<?php echo isset($_GET["search"]) ? htmlspecialchars($_GET["search"]) : ""; ?>">
            <button type="submit" class="btn">Search</button>
            <?php if (isset($_GET["search"]) && $_GET["search"] != "") : ?>
                <a href="index.php"><button type="button" class="reset-btn" style="background-color: #dc3545; color: #fff; opacity: 0.9;">Reset</button></a>
            <?php endif; ?>
        </form>

        <div class="card">
            <div class="card-header">
                Employee List
            </div>
            <div class="table-responsive">
                <?php if ($result->num_rows > 0) : ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Email</th>
                                <th>Position</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row["id"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["firstname"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["lastname"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["email"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["position"]); ?></td>
                                    <td style="text-align: center;">
                                        <button type="button" onclick="editEmployee(
                                        <?php echo $row["id"]; ?>,
                                        '<?php echo addslashes($row["firstname"]); ?>',
                                        '<?php echo addslashes($row["lastname"]); ?>',
                                        '<?php echo addslashes($row["email"]); ?>',
                                        '<?php echo addslashes($row["position"]); ?>'
                                    )" class="btn btn-warning">Edit</button>
                                        <a href="actions/delete.php?delete=<?php echo $row["id"]; ?>" onclick="return confirm('Are you sure you want to delete this employee?')">
                                            <button type="button" class="btn btn-danger">Delete</button>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <div class="empty-state" style="text-align: center; padding: 20px;">
                        <?php if (
                            isset($_GET["search"]) &&
                            $_GET["search"] != ""
                        ): ?>
                            <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" alt="No results" class="empty-state-icon" style="width: 100px; height: 100px;">
                            <h3>No matching results</h3>
                            <p>We couldn't find any employees matching your search criteria.</p>
                            <a href="index.php" class="btn">Clear Search</a>
                        <?php else : ?>
                            <img src="https://cdn-icons-png.flaticon.com/512/4076/4076478.png" alt="No employees" class="empty-state-icon" style="width: 100px; height: 100px;">
                            <h3>No employees yet</h3>
                            <p>Get started by adding your first employee!</p>
                            <button type="button" class="btn" onclick="openModal()">Add Employee</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="editModal">
            <div class="modal-content">
                <span class="close" onclick="closeEditModal()">&times;</span>
                <h3 class="edit-employee-heading">Edit Employee</h3>
                <form method="POST" action="actions/update.php">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="form-group">
                        <label for="edit_firstname">First Name</label>
                        <input type="text" name="firstname" id="edit_firstname" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_lastname">Last Name</label>
                        <input type="text" name="lastname" id="edit_lastname">
                    </div>
                    <div class="form-group">
                        <label for="edit_email">Email</label>
                        <input type="email" name="email" id="edit_email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_position">Position</label>
                        <input type="text" name="position" id="edit_position" required>
                    </div>
                    <button type="submit" name="update" class="btn">Update Employee</button>
                </form>
            </div>
        </div>
    </div>

    <div id="createModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3 class="add-employee-heading">Add New Employee</h3>
            <form method="POST" action="actions/create.php">
                <div class="form-group">
                    <label for="modal_firstname">First Name</label>
                    <input type="text" id="modal_firstname" name="firstname" placeholder="First Name" required>
                </div>
                <div class="form-group">
                    <label for="modal_lastname">Last Name</label>
                    <input type="text" id="modal_lastname" name="lastname" placeholder="Last Name">
                </div>
                <div class="form-group">
                    <label for="modal_email">Email</label>
                    <input type="email" id="modal_email" name="email" placeholder="Email" required>
                </div>
                <div class="form-group">
                    <label for="modal_position">Position</label>
                    <input type="text" id="modal_position" name="position" placeholder="Position" required>
                </div>
                <button type="submit" name="create" class="btn">Add Employee</button>
            </form>
        </div>
    </div>

    <script>
        function editEmployee(id, firstname, lastname, email, position) {
            document.getElementById('editModal').style.display = 'flex';
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_firstname').value = firstname;
            document.getElementById('edit_lastname').value = lastname;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_position').value = position;
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
