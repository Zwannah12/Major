<?php
session_start();
require_once "../includes/db.php";
require_once "../includes/functions.php";

// Ensure user is logged in and is a buyer
check_user_role('buyer');

$buyer_id = $_SESSION['id'];

// Fetch Buyer's Orders
$my_orders = [];
$sql_orders = "SELECT o.id as order_id, c.crop_name, o.quantity, o.total_price, o.status, u.name as farmer_name, o.created_at
               FROM orders o
               JOIN crops c ON o.crop_id = c.id
               JOIN users u ON c.farmer_id = u.id
               WHERE o.buyer_id = ? ORDER BY o.created_at DESC";
if ($stmt_orders = mysqli_prepare($link, $sql_orders)) {
    mysqli_stmt_bind_param($stmt_orders, "i", $buyer_id);
    if (mysqli_stmt_execute($stmt_orders)) {
        $result_orders = mysqli_stmt_get_result($stmt_orders);
        while ($row = mysqli_fetch_assoc($result_orders)) {
            $my_orders[] = $row;
        }
    }
    mysqli_stmt_close($stmt_orders);
}

// Stats
$total_spent = 0;
foreach($my_orders as $o) { if($o['status'] == 'delivered') $total_spent += $o['total_price']; }
$active_orders = 0;
foreach($my_orders as $o) { if(in_array($o['status'], ['pending', 'accepted', 'shipped'])) $active_orders++; }

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buyer Dashboard - AgroSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div id="wrapper">
        <!-- Sidebar -->
        <div id="sidebar-wrapper">
            <div class="sidebar-heading">
                <i class="fas fa-leaf me-2"></i>AgroSphere
            </div>
            <div class="list-group list-group-flush mt-3">
                <a href="buyer.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="../marketplace.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-store"></i> Marketplace
                </a>
                <a href="#myOrders" class="list-group-item list-group-item-action">
                    <i class="fas fa-shopping-cart"></i> My Orders
                </a>
                <a href="../logout.php" class="list-group-item list-group-item-action text-danger mt-5">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom sticky-top">
                <div class="container-fluid">
                    <button class="btn btn-outline-success" id="menu-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="ms-3 fw-bold text-success d-none d-sm-block">BUYER PANEL</div>
                    <div class="ms-auto">
                        <span class="badge bg-success-custom text-white p-2">
                            <i class="fas fa-user me-1"></i> Buyer: <?php echo htmlspecialchars($_SESSION["name"]); ?>
                        </span>
                    </div>
                </div>
            </nav>

            <div class="container-fluid p-4 animated-fade-in">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">My Buying Activity</h1>
                    <a href="../marketplace.php" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Browse Crops
                    </a>
                </div>

                <?php
                if (isset($_SESSION['success_message'])) {
                    echo '<div class="alert alert-success shadow-sm">' . $_SESSION['success_message'] . '</div>';
                    unset($_SESSION['success_message']);
                }
                if (isset($_SESSION['error_message'])) {
                    echo '<div class="alert alert-danger shadow-sm">' . $_SESSION['error_message'] . '</div>';
                    unset($_SESSION['error_message']);
                }
                ?>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card h-100 py-2">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Orders</div>
                                        <div class="h4 mb-0 font-weight-bold"><?php echo count($my_orders); ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-shopping-bag fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card h-100 py-2 border-start-warning" style="border-left-color: var(--warning-color) !important;">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Active Orders</div>
                                        <div class="h4 mb-0 font-weight-bold"><?php echo $active_orders; ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-truck fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card h-100 py-2 border-start-success" style="border-left-color: var(--success-color) !important;">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Spent</div>
                                        <div class="h4 mb-0 font-weight-bold">RWF <?php echo number_format($total_spent); ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-wallet fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- My Orders Table -->
                <div class="card shadow mb-4" id="myOrders">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-shopping-cart me-2"></i>Order History</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($my_orders)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-shopping-basket fa-3x text-gray-200 mb-3"></i>
                                <p class="text-muted">You haven't placed any orders yet.</p>
                                <a href="../marketplace.php" class="btn btn-outline-success">Start Shopping</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Crop</th>
                                            <th>Farmer</th>
                                            <th>Quantity</th>
                                            <th>Total Price</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($my_orders as $order): ?>
                                            <tr>
                                                <td>#<?php echo $order['order_id']; ?></td>
                                                <td><span class="fw-bold"><?php echo htmlspecialchars($order['crop_name']); ?></span></td>
                                                <td><?php echo htmlspecialchars($order['farmer_name']); ?></td>
                                                <td><?php echo htmlspecialchars($order['quantity']); ?></td>
                                                <td class="fw-bold">RWF <?php echo number_format($order['total_price'], 2); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php
                                                        switch($order['status']) {
                                                            case 'pending': echo 'warning text-dark'; break;
                                                            case 'accepted': echo 'info'; break;
                                                            case 'shipped': echo 'primary'; break;
                                                            case 'delivered': echo 'success'; break;
                                                            case 'cancelled': echo 'danger'; break;
                                                            default: echo 'secondary'; break;
                                                        }
                                                    ?>"><?php echo ucfirst($order['status']); ?></span>
                                                </td>
                                                <td class="small"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        $("#menu-toggle").click(function(e) { e.preventDefault(); $("#wrapper").toggleClass("toggled"); });
    </script>
</body>
</html>