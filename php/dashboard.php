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

// Get statistics
$totalEmployees = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
$activeUsers = $conn->query("SELECT COUNT(*) as active FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['active'];
$departments = $conn->query("SELECT COUNT(DISTINCT roles) as dept_count FROM users")->fetch_assoc()['dept_count'];
$recentActivities = $conn->query("SELECT COUNT(*) as recent FROM users WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['recent'];

// Get monthly user registration data for chart
$monthlyData = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as count
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");

$labels = [];
$data = [];
while ($row = $monthlyData->fetch_assoc()) {
    $labels[] = date('M Y', strtotime($row['month'] . '-01'));
    $data[] = $row['count'];
}

// Check if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($isAjax) {
?>
<section class="content">
    <div class="container-fluid">
        <!-- Small boxes (Stat box) -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <!-- small box -->
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo $totalEmployees; ?></h3>
                        <p>Total Employees</p>
                    </div>
                    <div class="icon">
                        <i class="ion ion-bag"></i>
                    </div>
                    <a href="#" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <!-- ./col -->
            <div class="col-lg-3 col-6">
                <!-- small box -->
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo $activeUsers; ?></h3>
                        <p>Active Users (Last 30 days)</p>
                    </div>
                    <div class="icon">
                        <i class="ion ion-stats-bars"></i>
                    </div>
                    <a href="#" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <!-- ./col -->
            <div class="col-lg-3 col-6">
                <!-- small box -->
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo $departments; ?></h3>
                        <p>Departments</p>
                    </div>
                    <div class="icon">
                        <i class="ion ion-person-add"></i>
                    </div>
                    <a href="#" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <!-- ./col -->
            <div class="col-lg-3 col-6">
                <!-- small box -->
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo $recentActivities; ?></h3>
                        <p>Recent Activities (7 days)</p>
                    </div>
                    <div class="icon">
                        <i class="ion ion-pie-graph"></i>
                    </div>
                    <a href="#" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <!-- ./col -->
        </div>
        <!-- /.row -->

        <!-- Main row -->
        <div class="row">
            <!-- Left col -->
            <section class="col-lg-7 connectedSortable">
                <!-- Custom tabs (Charts with tabs)-->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-pie mr-1"></i>
                            Monthly User Registration
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="tab-content p-0">
                            <div class="chart tab-pane active" id="revenue-chart" style="position: relative; height: 300px;">
                                <canvas id="revenue-chart-canvas" height="300" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- right col -->
            <section class="col-lg-5 connectedSortable">
                <!-- Role Distribution card -->
                <div class="card bg-gradient-primary">
                    <div class="card-header border-0">
                        <h3 class="card-title">
                            <i class="fas fa-users mr-1"></i>
                            Role Distribution
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php
                        $roleStats = $conn->query("
                            SELECT roles, COUNT(*) as count 
                            FROM users 
                            GROUP BY roles
                        ");
                        while ($role = $roleStats->fetch_assoc()) {
                            $percentage = ($role['count'] / $totalEmployees) * 100;
                            ?>
                            <div class="progress-group">
                                <?php echo ucfirst($role['roles']); ?>
                                <span class="float-right"><?php echo $role['count']; ?></span>
                                <div class="progress progress-sm">
                                    <div class="progress-bar bg-primary" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </section>
        </div>
    </div>
</section>

<script>
// Initialize chart data
var ctx = document.getElementById('revenue-chart-canvas').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($labels); ?>,
        datasets: [{
            label: 'New Users',
            data: <?php echo json_encode($data); ?>,
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>
<?php
    exit();
}
?>
