<?php
session_start();
require_once "../includes/db.php";
require_once "../includes/functions.php";

// Ensure user is logged in and is a transporter
check_user_role('transporter');

$transporter_id = $_SESSION['id'];

// Fetch User Info (including verification status)
$sql_user = "SELECT is_verified FROM users WHERE id = ?";
$is_verified = false;
if ($stmt_user = mysqli_prepare($link, $sql_user)) {
    mysqli_stmt_bind_param($stmt_user, "i", $transporter_id);
    mysqli_stmt_execute($stmt_user);
    mysqli_stmt_bind_result($stmt_user, $is_verified);
    mysqli_stmt_fetch($stmt_user);
    mysqli_stmt_close($stmt_user);
}

// Handle Accept Delivery Request
if (isset($_GET['action']) && $_GET['action'] == 'accept_delivery' && isset($_GET['order_id'])) {
    if (!$is_verified) {
        $_SESSION['error_message'] = "Your account must be verified to accept deliveries.";
        header("location: transporter.php");
        exit();
    }
    $order_id_to_accept = sanitize_input($_GET['order_id']);
    mysqli_begin_transaction($link);
    try {
        $sql_update_order = "UPDATE orders SET transporter_id = ?, status = 'accepted' WHERE id = ? AND status = 'pending'";
        if ($stmt_update_order = mysqli_prepare($link, $sql_update_order)) {
            mysqli_stmt_bind_param($stmt_update_order, "ii", $transporter_id, $order_id_to_accept);
            mysqli_stmt_execute($stmt_update_order);
            mysqli_stmt_close($stmt_update_order);
        }
        $sql_insert_delivery = "INSERT INTO deliveries (order_id, transporter_id, status) VALUES (?, ?, 'in_progress')";
        if ($stmt_insert_delivery = mysqli_prepare($link, $sql_insert_delivery)) {
            mysqli_stmt_bind_param($stmt_insert_delivery, "ii", $order_id_to_accept, $transporter_id);
            mysqli_stmt_execute($stmt_insert_delivery);
            mysqli_stmt_close($stmt_insert_delivery);
        }
        mysqli_commit($link);
        $_SESSION['success_message'] = "Delivery request accepted!";
    } catch (Exception $e) {
        mysqli_rollback($link);
        $_SESSION['error_message'] = "Failed to accept delivery.";
    }
    header("location: transporter.php");
    exit();
}

// Handle Update Delivery Status
if (isset($_POST['action']) && $_POST['action'] == 'update_delivery_status') {
    $delivery_id = sanitize_input($_POST['delivery_id']);
    $new_status = sanitize_input($_POST['new_status']);
    $order_id_for_status = sanitize_input($_POST['order_id_for_status']);

    $sql_update_delivery = "UPDATE deliveries SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND transporter_id = ?";
    if ($stmt_update_delivery = mysqli_prepare($link, $sql_update_delivery)) {
        mysqli_stmt_bind_param($stmt_update_delivery, "sii", $new_status, $delivery_id, $transporter_id);
        if (mysqli_stmt_execute($stmt_update_delivery)) {
            $sql_update_order_status = "UPDATE orders SET status = ? WHERE id = ?";
            if ($stmt_update_order_status = mysqli_prepare($link, $sql_update_order_status)) {
                mysqli_stmt_bind_param($stmt_update_order_status, "si", $new_status, $order_id_for_status);
                mysqli_stmt_execute($stmt_update_order_status);
                mysqli_stmt_close($stmt_update_order_status);
            }
            $_SESSION['success_message'] = "Status updated to " . ucfirst($new_status);
        }
        mysqli_stmt_close($stmt_update_delivery);
    }
    header("location: transporter.php");
    exit();
}

// Fetch Available Delivery Requests (Assigned by Admin, pending acceptance)
$delivery_requests = [];
$sql_requests = "SELECT o.id as order_id, c.crop_name, o.quantity, u_buyer.name as buyer_name, u_farmer.name as farmer_name, o.created_at, c.location as crop_location
                 FROM orders o
                 JOIN crops c ON o.crop_id = c.id
                 JOIN users u_buyer ON o.buyer_id = u_buyer.id
                 JOIN users u_farmer ON c.farmer_id = u_farmer.id
                 WHERE o.transporter_id = ? AND o.status = 'pending' ORDER BY o.created_at ASC";
if ($stmt_requests = mysqli_prepare($link, $sql_requests)) {
    mysqli_stmt_bind_param($stmt_requests, "i", $transporter_id);
    if (mysqli_stmt_execute($stmt_requests)) {
        $result_requests = mysqli_stmt_get_result($stmt_requests);
        while ($row = mysqli_fetch_assoc($result_requests)) {
            $delivery_requests[] = $row;
        }
    }
    mysqli_stmt_close($stmt_requests);
}

// Fetch My Deliveries (In progress or completed)
$my_deliveries = [];
$sql_my_deliveries = "SELECT d.id as delivery_id, d.status as delivery_status, d.updated_at,
                      o.id as order_id, c.crop_name, c.location as crop_location,
                      u_buyer.name as buyer_name, u_farmer.name as farmer_name
                      FROM deliveries d
                      JOIN orders o ON d.order_id = o.id
                      JOIN crops c ON o.crop_id = c.id
                      JOIN users u_buyer ON o.buyer_id = u_buyer.id
                      JOIN users u_farmer ON c.farmer_id = u_farmer.id
                      WHERE d.transporter_id = ? ORDER BY d.updated_at DESC";
if ($stmt_my_deliveries = mysqli_prepare($link, $sql_my_deliveries)) {
    mysqli_stmt_bind_param($stmt_my_deliveries, "i", $transporter_id);
    if (mysqli_stmt_execute($stmt_my_deliveries)) {
        $result_my_deliveries = mysqli_stmt_get_result($stmt_my_deliveries);
        while ($row = mysqli_fetch_assoc($result_my_deliveries)) {
            $my_deliveries[] = $row;
        }
    }
    mysqli_stmt_close($stmt_my_deliveries);
}

// Stats
$completed_count = 0;
foreach($my_deliveries as $d) { if($d['delivery_status'] == 'delivered') $completed_count++; }
$active_delivery_count = 0;
foreach($my_deliveries as $d) { if($d['delivery_status'] == 'in_progress') $active_delivery_count++; }

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transporter Dashboard - AgroSphere</title>
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
                <a href="transporter.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="#deliveryRequests" class="list-group-item list-group-item-action">
                    <i class="fas fa-shipping-fast"></i> New Requests
                </a>
                <a href="#myDeliveries" class="list-group-item list-group-item-action">
                    <i class="fas fa-truck-loading"></i> My Deliveries
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
                    <div class="ms-3 fw-bold text-success d-none d-sm-block">TRANSPORTER PANEL</div>
                    <div class="ms-auto">
                        <span class="badge bg-success-custom text-white p-2">
                            <i class="fas fa-truck me-1"></i> Transporter: <?php echo htmlspecialchars($_SESSION["name"]); ?>
                        </span>
                    </div>
                </div>
            </nav>

            <div class="container-fluid p-4 animated-fade-in">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Logistics Overview</h1>
                    <div class="text-muted small"><?php echo date('F d, Y'); ?></div>
                </div>

                <?php if (!$is_verified): ?>
                    <div class="alert alert-warning shadow-sm mb-4 border-start-warning border-4">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                            <div>
                                <h5 class="alert-heading fw-bold mb-1">Account Pending Verification</h5>
                                <p class="mb-0">Your account is currently waiting for admin confirmation. You cannot accept new delivery requests until an admin verifies your profile. Please contact support at <strong>info@agrosphere.com</strong> if you have any questions.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

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
                        <div class="card stat-card h-100 py-2 border-start-info" style="border-left-color: var(--info-color) !important;">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Available Requests</div>
                                        <div class="h4 mb-0 font-weight-bold"><?php echo count($delivery_requests); ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-map-marked-alt fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card h-100 py-2 border-start-warning" style="border-left-color: var(--warning-color) !important;">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">In Progress</div>
                                        <div class="h4 mb-0 font-weight-bold"><?php echo $active_delivery_count; ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-shipping-fast fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card h-100 py-2 border-start-success" style="border-left-color: var(--success-color) !important;">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Completed</div>
                                        <div class="h4 mb-0 font-weight-bold"><?php echo $completed_count; ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-check-circle fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Available Requests -->
                <div class="card shadow mb-4" id="deliveryRequests">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-bell me-2"></i>New Delivery Assignments</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($delivery_requests)): ?>
                            <div class="text-center py-4">No new orders have been assigned to you yet.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order</th>
                                            <th>Crop</th>
                                            <th>Route</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($delivery_requests as $request): ?>
                                            <tr>
                                                <td>#<?php echo $request['order_id']; ?></td>
                                                <td><?php echo htmlspecialchars($request['crop_name']); ?> (<?php echo $request['quantity']; ?>)</td>
                                                <td>
                                                    <div class="small">From: <span class="fw-bold text-success"><?php echo htmlspecialchars($request['farmer_name']); ?></span></div>
                                                    <div class="small">To: <span class="fw-bold text-primary"><?php echo htmlspecialchars($request['buyer_name']); ?></span></div>
                                                    <div class="small text-muted"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($request['crop_location']); ?></div>
                                                </td>
                                                <td class="small"><?php echo date('M d, H:i', strtotime($request['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($is_verified): ?>
                                                        <a href="transporter.php?action=accept_delivery&order_id=<?php echo $request['order_id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Accept this delivery assignment?');">
                                                            <i class="fas fa-check me-1"></i> Accept Job
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-secondary" disabled title="Account pending verification">Accept Job</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- My Deliveries -->
                <div class="card shadow mb-4" id="myDeliveries">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-truck me-2"></i>My Active Deliveries</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Delivery</th>
                                        <th>Details</th>
                                        <th>Status</th>
                                        <th>Update</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($my_deliveries as $delivery): ?>
                                        <tr>
                                            <td>#D-<?php echo $delivery['delivery_id']; ?></td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($delivery['crop_name']); ?></div>
                                                <div class="small">To: <?php echo htmlspecialchars($delivery['buyer_name']); ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php
                                                    switch($delivery['delivery_status']) {
                                                        case 'in_progress': echo 'info'; break;
                                                        case 'delivered': echo 'success'; break;
                                                        default: echo 'secondary'; break;
                                                    }
                                                ?>"><?php echo ucfirst($delivery['delivery_status']); ?></span>
                                            </td>
                                            <td>
                                                <?php if ($delivery['delivery_status'] == 'in_progress'): ?>
                                                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#updateStatusModal"
                                                        data-delivery_id="<?php echo $delivery['delivery_id']; ?>"
                                                        data-order_id="<?php echo $delivery['order_id']; ?>">
                                                        Update Status
                                                    </button>
                                                <?php endif; ?>
                                            </td>
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

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">Update Delivery Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="transporter.php" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_delivery_status">
                        <input type="hidden" name="delivery_id" id="modal_delivery_id">
                        <input type="hidden" name="order_id_for_status" id="modal_order_id">
                        <div class="mb-3">
                            <label class="form-label">New Status</label>
                            <select name="new_status" class="form-select">
                                <option value="in_progress">In Progress</option>
                                <option value="delivered">Delivered (Completed)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-warning">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
        $("#menu-toggle").click(function(e) { e.preventDefault(); $("#wrapper").toggleClass("toggled"); });
        var updateStatusModal = document.getElementById('updateStatusModal');
        updateStatusModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            document.getElementById('modal_delivery_id').value = button.getAttribute('data-delivery_id');
            document.getElementById('modal_order_id').value = button.getAttribute('data-order_id');
        });
    </script>
</body>
</html>