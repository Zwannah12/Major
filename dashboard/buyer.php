<?php
session_start();
require_once "../includes/db.php";
require_once "../includes/functions.php";

// Ensure user is logged in and is a buyer
check_user_role('buyer');

$buyer_id = $_SESSION['id'];

function get_order_progress_steps($order) {
    $is_paid = ($order['payment_status'] === 'paid');
    $has_transporter = !empty($order['transporter_id']);
    $delivery_status = $order['delivery_status'] ?? '';
    $order_status = $order['status'];

    return [
        [
            'label' => 'Order placed',
            'detail' => 'Sent to farmer',
            'icon' => 'fa-seedling',
            'done' => true,
            'active' => $order_status === 'pending' && !$is_paid,
        ],
        [
            'label' => 'Admin review',
            'detail' => $is_paid ? 'Payment confirmed' : 'Waiting for payment/admin',
            'icon' => 'fa-user-shield',
            'done' => $is_paid || $has_transporter || in_array($order_status, ['accepted', 'shipped', 'delivered']),
            'active' => !$is_paid && $order_status !== 'cancelled',
        ],
        [
            'label' => 'Transport assigned',
            'detail' => $has_transporter ? ($order['transporter_name'] ?: 'Transporter assigned') : 'Not assigned yet',
            'icon' => 'fa-truck',
            'done' => $has_transporter,
            'active' => $is_paid && !$has_transporter && $order_status !== 'cancelled',
        ],
        [
            'label' => 'On the way',
            'detail' => $delivery_status ? ucfirst(str_replace('_', ' ', $delivery_status)) : 'Waiting for pickup',
            'icon' => 'fa-route',
            'done' => in_array($delivery_status, ['in_progress', 'delivered']) || in_array($order_status, ['shipped', 'delivered']),
            'active' => $has_transporter && !in_array($delivery_status, ['delivered']) && $order_status !== 'delivered',
        ],
        [
            'label' => 'Delivered',
            'detail' => $order_status === 'delivered' ? 'Order received' : 'Final step',
            'icon' => 'fa-check-circle',
            'done' => $order_status === 'delivered' || $delivery_status === 'delivered',
            'active' => false,
        ],
    ];
}

function get_order_eta($order) {
    if ($order['status'] === 'cancelled') {
        return ['label' => 'Cancelled', 'class' => 'danger', 'detail' => 'This order is no longer active.'];
    }

    if ($order['status'] === 'delivered' || ($order['delivery_status'] ?? '') === 'delivered') {
        return ['label' => 'Delivered', 'class' => 'success', 'detail' => 'Your order has arrived.'];
    }

    if (($order['delivery_status'] ?? '') === 'in_progress' || $order['status'] === 'shipped') {
        return ['label' => 'Today or tomorrow', 'class' => 'primary', 'detail' => 'The transporter is moving the order.'];
    }

    if (!empty($order['transporter_id']) || $order['status'] === 'accepted') {
        return ['label' => '1-2 days', 'class' => 'info', 'detail' => 'Transport has been assigned or accepted.'];
    }

    if ($order['payment_status'] === 'paid') {
        return ['label' => '2-3 days', 'class' => 'warning', 'detail' => 'Admin needs to assign a transporter.'];
    }

    if ($order['payment_status'] === 'pending_confirmation') {
        return ['label' => '3-4 days', 'class' => 'warning', 'detail' => 'Payment is waiting for admin confirmation.'];
    }

    return ['label' => '3-5 days', 'class' => 'secondary', 'detail' => 'Complete payment to start fulfilment.'];
}

// Fetch Buyer's Orders
$my_orders = [];
$sql_orders = "SELECT o.id as order_id, c.crop_name, o.quantity, o.total_price, o.status, o.payment_status,
                      o.transporter_id, u.name as farmer_name, o.created_at, c.farmer_id,
                      t.name as transporter_name, d.id as delivery_id, d.status as delivery_status, d.updated_at as delivery_updated_at
               FROM orders o
               JOIN crops c ON o.crop_id = c.id
               JOIN users u ON c.farmer_id = u.id
               LEFT JOIN users t ON o.transporter_id = t.id
               LEFT JOIN deliveries d ON d.order_id = o.id
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
foreach($my_orders as $o) { if(!in_array($o['status'], ['delivered', 'cancelled'])) $active_orders++; }

// Notifications
$unread_notifications = [];
$sql_notif = "SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC";
if ($stmt_notif = mysqli_prepare($link, $sql_notif)) {
    mysqli_stmt_bind_param($stmt_notif, "i", $buyer_id);
    mysqli_stmt_execute($stmt_notif);
    $res_notif = mysqli_stmt_get_result($stmt_notif);
    while ($row = mysqli_fetch_assoc($res_notif)) { $unread_notifications[] = $row; }
    mysqli_stmt_close($stmt_notif);
}

// Fetch Latest Announcement (for buyers)
$latest_announcement = null;
// Try with target_audience column if it exists
$sql_check = "SHOW COLUMNS FROM announcements LIKE 'target_audience'";
if (mysqli_num_rows(mysqli_query($link, $sql_check)) > 0) {
    $sql_ann = "SELECT * FROM announcements WHERE FIND_IN_SET('buyer', target_audience) > 0 ORDER BY created_at DESC LIMIT 1";
} else {
    $sql_ann = "SELECT * FROM announcements ORDER BY created_at DESC LIMIT 1";
}
if ($res_ann = mysqli_query($link, $sql_ann)) {
    $latest_announcement = mysqli_fetch_assoc($res_ann);
}

// Mark All as Read
if (isset($_GET['mark_all_read'])) {
    $sql_mr = "UPDATE notifications SET is_read = TRUE WHERE user_id = ?";
    if ($stmt_mr = mysqli_prepare($link, $sql_mr)) {
        mysqli_stmt_bind_param($stmt_mr, "i", $buyer_id);
        mysqli_stmt_execute($stmt_mr);
        mysqli_stmt_close($stmt_mr);
        header("location: buyer.php");
        exit();
    }
}

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buyer Dashboard - AgroSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=modern-ui-2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .order-progress-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(110px, 1fr));
            gap: .75rem;
        }
        .order-progress-step {
            min-height: 112px;
            border: 1px solid var(--surface-border);
            border-radius: 8px;
            background: var(--surface-bg);
            padding: .85rem;
            position: relative;
        }
        .order-progress-step.done {
            border-color: rgba(40, 167, 69, .35);
            background: rgba(40, 167, 69, .08);
        }
        .order-progress-step.active {
            border-color: rgba(255, 193, 7, .55);
            background: rgba(255, 193, 7, .12);
        }
        .order-progress-icon {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: rgba(108, 117, 125, .12);
            color: #6c757d;
            margin-bottom: .65rem;
        }
        .order-progress-step.done .order-progress-icon {
            background: rgba(40, 167, 69, .16);
            color: #198754;
        }
        .order-progress-step.active .order-progress-icon {
            background: rgba(255, 193, 7, .22);
            color: #946200;
        }
        @media (max-width: 992px) {
            .order-progress-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 576px) {
            .order-progress-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
                <a href="../chat.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-comments"></i> Messages
                </a>
                <a href="../contact_admin.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-envelope"></i> Contact Support
                </a>
                <a href="../profile.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-user-cog"></i> Profile Settings
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
                    <div class="ms-auto d-flex align-items-center">
                        <div class="dropdown me-3">
                            <button class="btn btn-light position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown">
                                <i class="fas fa-bell text-success"></i>
                                <?php if(count($unread_notifications) > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?php echo count($unread_notifications); ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="width: 300px; max-height: 400px; overflow-y: auto;">
                                <li class="p-2 border-bottom d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">Notifications</span>
                                    <a href="?mark_all_read=1" class="small text-decoration-none">Mark all read</a>
                                </li>
                                <?php if(empty($unread_notifications)): ?>
                                    <li class="p-3 text-center text-muted small">No new notifications</li>
                                <?php else: ?>
                                    <?php foreach($unread_notifications as $n): ?>
                                        <li>
                                            <a class="dropdown-item p-3 border-bottom text-wrap" href="<?php echo $n['link']; ?>">
                                                <div class="small mb-1"><?php echo htmlspecialchars($n['message']); ?></div>
                                                <div class="smaller text-muted"><?php echo date('M d, H:i', strtotime($n['created_at'])); ?></div>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <span class="badge bg-success-custom text-white p-2">
                            <i class="fas fa-user me-1"></i> Buyer: <?php echo htmlspecialchars($_SESSION["name"]); ?>
                        </span>
                    </div>
                </div>
            </nav>

            <div class="container-fluid p-4 animated-fade-in">
                <div class="dashboard-hero">
                    <div class="row align-items-end g-4">
                        <div class="col-lg-8">
                            <div class="dashboard-eyebrow">Buyer workspace</div>
                            <h1 class="dashboard-title">Source fresh crops with order visibility.</h1>
                            <p class="dashboard-subtitle">Follow every purchase from marketplace discovery through delivery, payment, and farmer communication.</p>
                            <div class="dashboard-chip-row">
                                <span class="dashboard-chip"><i class="fas fa-shopping-bag"></i><strong><?php echo count($my_orders); ?></strong> Orders</span>
                                <span class="dashboard-chip"><i class="fas fa-truck"></i><strong><?php echo $active_orders; ?></strong> Active</span>
                                <span class="dashboard-chip"><i class="fas fa-coins"></i>RWF <?php echo number_format($total_spent); ?> spent</span>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="dashboard-action-panel ms-lg-auto">
                                <div class="small opacity-75 mb-2">Next best action</div>
                                <div class="h5 fw-bold mb-3">Browse available harvests</div>
                                <a href="../marketplace.php" class="btn btn-warning w-100 fw-bold">
                                    <i class="fas fa-search me-2"></i>Browse Crops
                                </a>
                            </div>
                        </div>
                    </div>
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

                <!-- Platform Announcement -->
                <?php if ($latest_announcement): ?>
                    <div class="card shadow-sm border-0 mb-4 bg-primary text-white overflow-hidden">
                        <div class="card-body p-4 position-relative">
                            <div style="position: absolute; right: -20px; top: -20px; font-size: 100px; opacity: 0.1; transform: rotate(15deg);">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <h5 class="fw-bold mb-1"><i class="fas fa-bullhorn me-2"></i><?php echo htmlspecialchars($latest_announcement['title']); ?></h5>
                            <p class="mb-0 opacity-90"><?php echo htmlspecialchars($latest_announcement['message']); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

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
                            <div class="d-flex flex-column gap-4">
                                <?php foreach ($my_orders as $order): ?>
                                    <?php
                                        $steps = get_order_progress_steps($order);
                                        $eta = get_order_eta($order);
                                    ?>
                                    <div class="border rounded p-3 p-md-4 bg-white">
                                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                                            <div>
                                                <div class="small text-muted mb-1">Order #<?php echo $order['order_id']; ?> placed <?php echo date('M d, Y', strtotime($order['created_at'])); ?></div>
                                                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($order['crop_name']); ?></h5>
                                                <div class="text-muted small">
                                                    Farmer: <?php echo htmlspecialchars($order['farmer_name']); ?>
                                                    <?php if (!empty($order['transporter_name'])): ?>
                                                        <span class="mx-2">|</span> Transporter: <?php echo htmlspecialchars($order['transporter_name']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="text-md-end">
                                                <span class="badge bg-<?php
                                                    switch($order['status']) {
                                                        case 'pending': echo 'warning text-dark'; break;
                                                        case 'accepted': echo 'info'; break;
                                                        case 'shipped': echo 'primary'; break;
                                                        case 'delivered': echo 'success'; break;
                                                        case 'cancelled': echo 'danger'; break;
                                                        default: echo 'secondary'; break;
                                                    }
                                                ?> mb-2"><?php echo ucfirst($order['status']); ?></span>
                                                <div class="fw-bold">RWF <?php echo number_format($order['total_price'], 2); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars($order['quantity']); ?> units</div>
                                            </div>
                                        </div>

                                        <div class="row g-3 mb-3">
                                            <div class="col-md-4">
                                                <div class="p-3 rounded bg-light h-100">
                                                    <div class="small text-muted">Payment</div>
                                                    <div class="fw-bold"><?php echo ucfirst(str_replace('_', ' ', $order['payment_status'])); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="p-3 rounded bg-light h-100">
                                                    <div class="small text-muted">Estimated delivery</div>
                                                    <div class="fw-bold text-<?php echo $eta['class']; ?>"><?php echo $eta['label']; ?></div>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($eta['detail']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="p-3 rounded bg-light h-100">
                                                    <div class="small text-muted">Last delivery update</div>
                                                    <div class="fw-bold">
                                                        <?php echo !empty($order['delivery_updated_at']) ? date('M d, Y H:i', strtotime($order['delivery_updated_at'])) : 'Not started'; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="order-progress-grid mb-3">
                                            <?php foreach ($steps as $step): ?>
                                                <div class="order-progress-step <?php echo $step['done'] ? 'done' : ''; ?> <?php echo $step['active'] ? 'active' : ''; ?>">
                                                    <div class="order-progress-icon"><i class="fas <?php echo $step['icon']; ?>"></i></div>
                                                    <div class="fw-bold small"><?php echo htmlspecialchars($step['label']); ?></div>
                                                    <div class="text-muted small"><?php echo htmlspecialchars($step['detail']); ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="../chat.php?user_id=<?php echo $order['farmer_id']; ?>" class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-comments me-1"></i>Chat with Farmer
                                            </a>
                                            <?php if (!empty($order['transporter_id'])): ?>
                                                <a href="../chat.php?user_id=<?php echo $order['transporter_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-truck me-1"></i>Chat with Transporter
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!empty($order['delivery_id'])): ?>
                                                <span class="btn btn-sm btn-light disabled">Delivery #<?php echo $order['delivery_id']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/main.js?v=modern-ui-2"></script>
    <script>
        $("#menu-toggle").click(function(e) { e.preventDefault(); $("#wrapper").toggleClass("toggled"); });
    </script>
</body>
</html>
