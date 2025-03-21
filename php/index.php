<?php
session_start();
error_reporting(E_ALL);
ini_set("display_errors", 1);

// Check if user is not logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: authpage.php");
    exit();
}

// Handle logout
if (isset($_POST["logout"])) {
    session_destroy();
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
    <!-- Bootstrap 4 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Custom CSS -->
    <!-- <link rel="stylesheet" href="/css/styles.css"> -->
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
        <div class="container">
            <span class="navbar-brand">Employee Management System</span>
            <div class="ml-auto">
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="user_management.php" class="btn btn-info mr-2">Manage Users</a>
                <?php endif; ?>
                <form method="post" class="d-inline">
                    <button type="submit" name="logout" class="btn btn-danger">Logout</button>
                </form>
            </div>
        </div>
    </nav>
    <div class="container">

        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="card-title mb-0">Employee List</h2>
                    <button type="button" class="btn btn-primary" onclick="openModal()">
                        <i class="fas fa-plus"></i> Add Employee
                    </button>
                </div>

                <form id="searchForm" method="GET" class="mb-4">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search employees..."
                            value="<?php echo isset($_GET["search"]) ? htmlspecialchars($_GET["search"]) : ""; ?>">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <?php if (isset($_GET["search"]) && $_GET["search"] != "") : ?>
                                <a href="index.php" class="btn btn-danger">Reset</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

        <div class="card">
            <div class="card-header">
                Employee List
            </div>
            <div class="table-responsive">
                <?php if ($result->num_rows > 0) : ?>
                    <table id="employeeTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="5%">ID</th>
                                <th width="20%">First Name</th>
                                <th width="20%">Last Name</th>
                                <th width="25%">Email</th>
                                <th width="15%">Position</th>
                                <th width="15%" style="text-align: center;">Actions</th>
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
                                        <div class="btn-group">
                                            <button type="button" onclick="editEmployee(
                                                <?php echo $row["id"]; ?>,
                                                '<?php echo addslashes($row["firstname"]); ?>',
                                                '<?php echo addslashes($row["lastname"]); ?>',
                                                '<?php echo addslashes($row["email"]); ?>',
                                                '<?php echo addslashes($row["position"]); ?>'
                                            )" class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="actions/delete.php?delete=<?php echo $row["id"]; ?>"
                                               onclick="return confirm('Are you sure you want to delete this employee?')"
                                               class="btn btn-sm btn-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
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

        <div class="modal fade" id="editModal" tabindex="-1" role="dialog">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Employee</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form method="POST" action="actions/update.php">
                        <div class="modal-body">
                            <input type="hidden" name="id" id="edit_id">
                            <div class="form-group">
                                <label for="edit_firstname">First Name</label>
                                <input type="text" class="form-control" name="firstname" id="edit_firstname" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_lastname">Last Name</label>
                                <input type="text" class="form-control" name="lastname" id="edit_lastname">
                            </div>
                            <div class="form-group">
                                <label for="edit_email">Email</label>
                                <input type="email" class="form-control" name="email" id="edit_email" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_position">Position</label>
                                <input type="text" class="form-control" name="position" id="edit_position" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" name="update" class="btn btn-primary">Update Employee</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createModal" tabindex="-1" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Employee</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="actions/create.php">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="modal_firstname">First Name</label>
                            <input type="text" class="form-control" id="modal_firstname" name="firstname" required>
                        </div>
                        <div class="form-group">
                            <label for="modal_lastname">Last Name</label>
                            <input type="text" class="form-control" id="modal_lastname" name="lastname">
                        </div>
                        <div class="form-group">
                            <label for="modal_email">Email</label>
                            <input type="email" class="form-control" id="modal_email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="modal_position">Position</label>
                            <input type="text" class="form-control" id="modal_position" name="position" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" name="create" class="btn btn-primary">Add Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editEmployee(id, firstname, lastname, email, position) {
            $('#edit_id').val(id);
            $('#edit_firstname').val(firstname);
            $('#edit_lastname').val(lastname);
            $('#edit_email').val(email);
            $('#edit_position').val(position);
            $('#editModal').modal('show');
        }

        function openModal() {
            $('#createModal').modal('show');
        }

        $(document).ready(function() {
            // Reset form when modal is hidden
            $('#createModal, #editModal').on('hidden.bs.modal', function() {
                $(this).find('form').trigger('reset');
            });

            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 4 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#employeeTable').DataTable({
                "paging": true,
                "lengthChange": false,
                "searching": false, // Using custom search
                "ordering": true,
                "info": true,
                "autoWidth": false,
                "pageLength": 10
            });
        });
    </script>
</body>

</html>
