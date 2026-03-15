<?php
session_start();
require_once "../includes/db.php";
require_once "../includes/functions.php";

// Ensure user is logged in and is an admin
check_user_role('admin');

$admin_id = $_SESSION['id'];

// Variables for success/error messages
$message = '';
$message_type = '';

// Handle User Management Actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_action'])) {
    $user_action = sanitize_input($_POST['user_action']);
    $user_id_target = sanitize_input($_POST['user_id_target']);

    if ($user_action == 'delete_user') {
        // ... (existing delete logic)
    } elseif ($user_action == 'update_role') {
        // ... (existing role update logic)
    } elseif ($user_action == 'toggle_verify') {
        // ... (existing verification logic)
    }
}

// Handle Order Monitoring Actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['order_action'])) {
    $order_action = sanitize_input($_POST['order_action']);
    $order_id_target = sanitize_input($_POST['order_id_target']);

    if ($order_action == 'update_order_status') {
        $new_order_status = sanitize_input($_POST['new_order_status']);
        $sql = "UPDATE orders SET status = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "si", $new_order_status, $order_id_target);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Order status updated successfully.";
                $message_type = "success";
            } else {
                $message = "Error updating order status: " . mysqli_error($link);
                $message_type = "danger";
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($order_action == 'assign_transporter') {
        $transporter_id_assign = sanitize_input($_POST['transporter_id_assign']);
        $sql = "UPDATE orders SET transporter_id = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ii", $transporter_id_assign, $order_id_target);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Transporter assigned to order successfully.";
                $message_type = "success";
            } else {
                $message = "Error assigning transporter: " . mysqli_error($link);
                $message_type = "danger";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Fetch Verified Transporters for Assignment
$verified_transporters = [];
$sql_vt = "SELECT id, name FROM users WHERE role = 'transporter' AND is_verified = TRUE";
if ($result_vt = mysqli_query($link, $sql_vt)) {
    while ($row = mysqli_fetch_assoc($result_vt)) {
        $verified_transporters[] = $row;
    }
}

// ... (rest of the data fetching logic for users, crops, orders, stats, charts) ...

// Fetch All Users
$all_users = [];
$sql_users = "SELECT id, name, email, role, phone, location, created_at, is_verified FROM users ORDER BY created_at DESC";
if ($result_users = mysqli_query($link, $sql_users)) {
    while ($row = mysqli_fetch_assoc($result_users)) {
        $all_users[] = $row;
    }
    mysqli_free_result($result_users);
}

// Fetch All Orders
$all_orders = [];
$sql_orders = "SELECT o.id as order_id, c.crop_name, o.quantity, o.total_price, o.status, u_buyer.name as buyer_name, u_farmer.name as farmer_name, u_transporter.name as transporter_name, o.created_at
               FROM orders o
               JOIN crops c ON o.crop_id = c.id
               JOIN users u_buyer ON o.buyer_id = u_buyer.id
               JOIN users u_farmer ON c.farmer_id = u_farmer.id
               LEFT JOIN users u_transporter ON o.transporter_id = u_transporter.id
               ORDER BY o.created_at DESC";
if ($result_orders = mysqli_query($link, $sql_orders)) {
    while ($row = mysqli_fetch_assoc($result_orders)) {
        $all_orders[] = $row;
    }
    mysqli_free_result($result_orders);
}


// Fetch stats and chart data...
$total_farmers = 10; // Example
$total_buyers = 20; // Example
$total_orders = 30; // Example
$total_crops_listed = 40; // Example
$sales_labels = json_encode(['Jan', 'Feb', 'Mar']);
$sales_data = json_encode([100, 200, 150]);
$region_labels = json_encode(['East', 'West', 'North']);
$region_data = json_encode([50, 80, 60]);


mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - AgroSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div id="wrapper">
        <!-- Sidebar -->
        <div id="sidebar-wrapper">
            <div class="sidebar-heading">
                <i class="fas fa-leaf me-2"></i>AgroSphere
            </div>
            <div class="list-group list-group-flush mt-3">
                <a href="#overview" class="list-group-item list-group-item-action">Dashboard Overview</a>
                <a href="#manageUsers" class="list-group-item list-group-item-action">Manage Users</a>
                <a href="#monitorOrders" class="list-group-item list-group-item-action">Monitor Orders</a>
                <a href="../logout.php" class="list-group-item list-group-item-action text-danger mt-5">Logout</a>
            </div>
        </div>

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom sticky-top">
                <div class="container-fluid">
                    <button class="btn btn-outline-success" id="menu-toggle"><i class="fas fa-bars"></i></button>
                    <div class="ms-3 fw-bold text-success">ADMIN PANEL</div>
                    <div class="ms-auto">
                        <span class="badge bg-success-custom text-white p-2">
                            Admin: <?php echo htmlspecialchars($_SESSION["name"]); ?>
                        </span>
                    </div>
                </div>
            </nav>

            <div class="container-fluid p-4 animated-fade-in">
                <h1 class="h3 mb-4">Dashboard Overview</h1>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="row" id="overview">
                    <!-- ... (stats cards html) ... -->
                </div>

                <!-- Manage Users -->
                <div class="card shadow mb-4" id="manageUsers">
                    <div class="card-header">User Management</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                                            <td>
                                                <?php if ($user['is_verified']): ?>
                                                    <span class="badge bg-success">Verified</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Unverified</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($user['id'] != $admin_id): ?>
                                                    <form action="admin.php" method="post" class="d-inline">
                                                        <input type="hidden" name="user_action" value="toggle_verify">
                                                        <input type="hidden" name="user_id_target" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="new_status" value="<?php echo $user['is_verified'] ? '0' : '1'; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                                            <?php echo $user['is_verified'] ? 'Revoke' : 'Verify'; ?>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Monitor Orders -->
                <div class="card shadow mb-4" id="monitorOrders">
                    <div class="card-header">Order Monitoring</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Details</th>
                                        <th>Assignment</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo htmlspecialchars($order['order_id']); ?></td>
                                            <td><?php echo htmlspecialchars($order['crop_name']); ?></td>
                                            <td>
                                                <?php if (empty($order['transporter_name']) && $order['status'] == 'pending'): ?>
                                                    <form action="admin.php" method="post" class="input-group input-group-sm">
                                                        <input type="hidden" name="order_action" value="assign_transporter">
                                                        <input type="hidden" name="order_id_target" value="<?php echo $order['order_id']; ?>">
                                                        <select name="transporter_id_assign" class="form-select">
                                                            <option value="">Assign to...</option>
                                                            <?php foreach($verified_transporters as $transporter): ?>
                                                                <option value="<?php echo $transporter['id']; ?>"><?php echo htmlspecialchars($transporter['name']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" class="btn btn-primary">Assign</button>
                                                    </form>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($order['transporter_name'] ?? 'N/A'); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($order['status']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/jquery-3.6.0.min.js"></script>
    <script>
        $("#menu-toggle").click(function(e) {
            e.preventDefault();
            $("#wrapper").toggleClass("toggled");
        });
    </script>
</body>
</html>