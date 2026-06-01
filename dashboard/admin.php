<?php
session_start();
require_once "../includes/db.php";
require_once "../includes/functions.php";

// Ensure user is logged in and is an admin
check_user_role('admin');

$admin_id = $_SESSION['id'];
ensure_public_messages_table($link);

// Variables for success/error messages
$message = '';
$message_type = '';

// Handle User Management Actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_action'])) {
    $user_action = sanitize_input($_POST['user_action']);
    $user_id_target = sanitize_input($_POST['user_id_target']);

    if ($user_action == 'delete_user') {
        $sql = "DELETE FROM users WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $user_id_target);
            if (mysqli_stmt_execute($stmt)) {
                $message = "User deleted successfully.";
                $message_type = "success";
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($user_action == 'update_role') {
        $new_role = sanitize_input($_POST['new_role']);
        $sql = "UPDATE users SET role = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "si", $new_role, $user_id_target);
            if (mysqli_stmt_execute($stmt)) {
                $message = "User role updated successfully.";
                $message_type = "success";
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($user_action == 'toggle_verify') {
        $new_status = (int)$_POST['new_status'];
        $sql = "UPDATE users SET is_verified = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ii", $new_status, $user_id_target);
            if (mysqli_stmt_execute($stmt)) {
                $status_text = $new_status ? "verified" : "unverified";
                $message = "User marked as $status_text.";
                $message_type = "success";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Handle Public Contact Messages
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['public_message_action'])) {
    $public_message_action = sanitize_input($_POST['public_message_action']);
    $public_message_id = (int)$_POST['public_message_id'];

    if ($public_message_action == 'mark_read') {
        $sql = "UPDATE public_messages SET status = 'read' WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $public_message_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Public message marked as read.";
                $message_type = "success";
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($public_message_action == 'delete') {
        $sql = "DELETE FROM public_messages WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $public_message_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Public message deleted successfully.";
                $message_type = "success";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Handle Announcements
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['announcement_action'])) {
    $announcement_action = sanitize_input($_POST['announcement_action']);

    if ($announcement_action === 'publish') {
        $title = sanitize_input($_POST['title']);
        $msg = sanitize_input($_POST['message']);
        
        // Get target audience from checkboxes
        $target_audience = [];
        if (isset($_POST['target_farmer'])) $target_audience[] = 'farmer';
        if (isset($_POST['target_buyer'])) $target_audience[] = 'buyer';
        if (isset($_POST['target_transporter'])) $target_audience[] = 'transporter';
        if (isset($_POST['target_public'])) $target_audience[] = 'public';
        
        // If no audience selected, default to all
        if (empty($target_audience)) {
            $target_audience = ['farmer', 'buyer', 'transporter', 'public'];
        }
        
        $target_str = implode(',', $target_audience);
        
        $sql = "INSERT INTO announcements (title, message, target_audience) VALUES (?, ?, ?)";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "sss", $title, $msg, $target_str);
            if (mysqli_stmt_execute($stmt)) {
                $target_label = ucwords(implode(', ', $target_audience));
                $message = "Announcement published to: " . $target_label . ".";
                $message_type = "success";
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($announcement_action === 'delete') {
        $announcement_id = (int)$_POST['announcement_id'];

        $sql = "DELETE FROM announcements WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $announcement_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Announcement deleted successfully.";
                $message_type = "success";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Handle Crop Approval
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['crop_approval'])) {
    $crop_id = (int)$_POST['crop_id'];
    $new_status = sanitize_input($_POST['new_status']);
    
    // Get farmer_id and crop_name for notification
    $farmer_id_notif = 0;
    $crop_name_notif = "";
    $sql_get = "SELECT farmer_id, crop_name FROM crops WHERE id = ?";
    if($stmt_get = mysqli_prepare($link, $sql_get)) {
        mysqli_stmt_bind_param($stmt_get, "i", $crop_id);
        mysqli_stmt_execute($stmt_get);
        mysqli_stmt_bind_result($stmt_get, $farmer_id_notif, $crop_name_notif);
        mysqli_stmt_fetch($stmt_get);
        mysqli_stmt_close($stmt_get);
    }

    $sql = "UPDATE crops SET status = ? WHERE id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "si", $new_status, $crop_id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Crop listing status updated to $new_status.";
            $message_type = "success";
            
            // Trigger Notification
            $notif_msg = ($new_status == 'approved') ? "Your crop listing '$crop_name_notif' has been approved!" : "Your crop listing '$crop_name_notif' was rejected.";
            create_notification($link, $farmer_id_notif, $notif_msg, "farmer.php#myCrops");
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle Order & Message Actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['order_action'])) {
    $order_action = sanitize_input($_POST['order_action']);
    
    if ($order_action == 'assign_transporter') {
        $order_id_target = (int)$_POST['order_id_target'];
        $transporter_id_assign = (int)$_POST['transporter_id_assign'];

        if ($transporter_id_assign <= 0) {
            $message = "Please choose a transporter before assigning the order.";
            $message_type = "warning";
        } else {
            mysqli_begin_transaction($link);
            try {
                $sql = "UPDATE orders SET transporter_id = ? WHERE id = ?";
                if ($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "ii", $transporter_id_assign, $order_id_target);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }

                $delivery_id = null;
                $sql_existing_delivery = "SELECT id FROM deliveries WHERE order_id = ? LIMIT 1";
                if ($stmt_existing_delivery = mysqli_prepare($link, $sql_existing_delivery)) {
                    mysqli_stmt_bind_param($stmt_existing_delivery, "i", $order_id_target);
                    mysqli_stmt_execute($stmt_existing_delivery);
                    mysqli_stmt_bind_result($stmt_existing_delivery, $delivery_id);
                    mysqli_stmt_fetch($stmt_existing_delivery);
                    mysqli_stmt_close($stmt_existing_delivery);
                }

                if ($delivery_id) {
                    $sql_delivery = "UPDATE deliveries SET transporter_id = ?, status = 'pending', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                    if ($stmt_delivery = mysqli_prepare($link, $sql_delivery)) {
                        mysqli_stmt_bind_param($stmt_delivery, "ii", $transporter_id_assign, $delivery_id);
                        mysqli_stmt_execute($stmt_delivery);
                        mysqli_stmt_close($stmt_delivery);
                    }
                } else {
                    $sql_delivery = "INSERT INTO deliveries (order_id, transporter_id, status) VALUES (?, ?, 'pending')";
                    if ($stmt_delivery = mysqli_prepare($link, $sql_delivery)) {
                        mysqli_stmt_bind_param($stmt_delivery, "ii", $order_id_target, $transporter_id_assign);
                        mysqli_stmt_execute($stmt_delivery);
                        mysqli_stmt_close($stmt_delivery);
                    }
                }

                mysqli_commit($link);
                $message = "Transporter assigned and delivery request created successfully.";
                $message_type = "success";

                create_notification($link, $transporter_id_assign, "New delivery job assigned! Order #$order_id_target", "transporter.php#deliveryRequests");
            } catch (Exception $e) {
                mysqli_rollback($link);
                $message = "Error assigning transporter. Please try again.";
                $message_type = "danger";
            }
        }
    } elseif ($order_action == 'confirm_payment') {
        $order_id_target = sanitize_input($_POST['order_id_target']);
        $sql = "UPDATE orders SET payment_status = 'paid' WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $order_id_target);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Payment confirmed successfully.";
                $message_type = "success";
                
                // Notify Buyer
                $buyer_id_notif = 0;
                $sql_b = "SELECT buyer_id FROM orders WHERE id = ?";
                if($stmt_b = mysqli_prepare($link, $sql_b)) {
                    mysqli_stmt_bind_param($stmt_b, "i", $order_id_target);
                    mysqli_stmt_execute($stmt_b);
                    mysqli_stmt_bind_result($stmt_b, $buyer_id_notif);
                    mysqli_stmt_fetch($stmt_b);
                    mysqli_stmt_close($stmt_b);
                }
                create_notification($link, $buyer_id_notif, "Payment for order #$order_id_target has been confirmed!", "buyer.php#myOrders");
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($order_action == 'reply_message') {
        $msg_id = sanitize_input($_POST['msg_id']);
        $reply_text = sanitize_input($_POST['reply_text']);
        $sql = "UPDATE messages SET admin_reply = ?, status = 'replied', replied_at = CURRENT_TIMESTAMP WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "si", $reply_text, $msg_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Reply sent successfully.";
                $message_type = "success";
                
                // Notify User
                $user_id_notif = 0;
                $sql_u = "SELECT user_id FROM messages WHERE id = ?";
                if($stmt_u = mysqli_prepare($link, $sql_u)) {
                    mysqli_stmt_bind_param($stmt_u, "i", $msg_id);
                    mysqli_stmt_execute($stmt_u);
                    mysqli_stmt_bind_result($stmt_u, $user_id_notif);
                    mysqli_stmt_fetch($stmt_u);
                    mysqli_stmt_close($stmt_u);
                }
                create_notification($link, $user_id_notif, "Admin has replied to your support message.", "contact_admin.php");
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Data for Charts
// Fetch All Data
$verified_transporters = [];
$sql_vt = "SELECT id, name FROM users WHERE role = 'transporter' AND is_verified = TRUE";
if ($result_vt = mysqli_query($link, $sql_vt)) {
    while ($row = mysqli_fetch_assoc($result_vt)) { $verified_transporters[] = $row; }
}

$all_users = [];
$sql_users = "SELECT id, name, email, role, is_verified FROM users ORDER BY created_at DESC";
if ($result_users = mysqli_query($link, $sql_users)) {
    while ($row = mysqli_fetch_assoc($result_users)) { $all_users[] = $row; }
}

$all_orders = [];
$sql_orders = "SELECT o.id as order_id, c.crop_name, o.quantity, o.total_price, o.status, o.payment_status, u_buyer.name as buyer_name, u_farmer.name as farmer_name, u_transporter.name as transporter_name 
               FROM orders o 
               JOIN crops c ON o.crop_id = c.id 
               JOIN users u_buyer ON o.buyer_id = u_buyer.id 
               JOIN users u_farmer ON c.farmer_id = u_farmer.id 
               LEFT JOIN users u_transporter ON o.transporter_id = u_transporter.id 
               ORDER BY o.created_at DESC";
if ($result_orders = mysqli_query($link, $sql_orders)) {
    while ($row = mysqli_fetch_assoc($result_orders)) { $all_orders[] = $row; }
}

$all_messages = [];
$sql_msg = "SELECT m.*, u.name as user_name, u.role as user_role FROM messages m JOIN users u ON m.user_id = u.id ORDER BY m.created_at DESC";
if ($result_msg = mysqli_query($link, $sql_msg)) {
    while ($row = mysqli_fetch_assoc($result_msg)) { $all_messages[] = $row; }
}

$public_messages = [];
$sql_public_msg = "SELECT * FROM public_messages ORDER BY created_at DESC";
if ($result_public_msg = mysqli_query($link, $sql_public_msg)) {
    while ($row = mysqli_fetch_assoc($result_public_msg)) { $public_messages[] = $row; }
}

$all_announcements = [];
$sql_announcements = "SELECT id, title, message, target_audience, created_at FROM announcements ORDER BY created_at DESC";
if ($result_announcements = mysqli_query($link, $sql_announcements)) {
    while ($row = mysqli_fetch_assoc($result_announcements)) { $all_announcements[] = $row; }
}

$pending_crops = [];
// Check if the status column exists in the crops table to avoid crashing
$check_column = mysqli_query($link, "SHOW COLUMNS FROM crops LIKE 'status'");
$status_exists = mysqli_num_rows($check_column) > 0;

if ($status_exists) {
    $sql_pc = "SELECT c.*, u.name as farmer_name FROM crops c JOIN users u ON c.farmer_id = u.id WHERE c.status = 'pending' ORDER BY c.created_at DESC";
    if ($result_pc = mysqli_query($link, $sql_pc)) {
        while ($row = mysqli_fetch_assoc($result_pc)) { $pending_crops[] = $row; }
    }
} else {
    // If status column is missing, we treat it as no pending crops and don't crash
    $pending_crops = [];
}

// Counts
function get_count($link, $sql) {
    $result = mysqli_query($link, $sql);
    return ($result) ? mysqli_fetch_assoc($result)['count'] : 0;
}
$total_farmers = get_count($link, "SELECT COUNT(*) AS count FROM users WHERE role = 'farmer'");
$total_buyers = get_count($link, "SELECT COUNT(*) AS count FROM users WHERE role = 'buyer'");
$total_orders = get_count($link, "SELECT COUNT(*) AS count FROM orders");
$unread_msg_count = get_count($link, "SELECT COUNT(*) AS count FROM messages WHERE status = 'unread'");
$unread_public_msg_count = get_count($link, "SELECT COUNT(*) AS count FROM public_messages WHERE status = 'unread'");

// Notifications
$unread_notifications = [];
$sql_notif = "SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC";
if ($stmt_notif = mysqli_prepare($link, $sql_notif)) {
    mysqli_stmt_bind_param($stmt_notif, "i", $admin_id);
    mysqli_stmt_execute($stmt_notif);
    $res_notif = mysqli_stmt_get_result($stmt_notif);
    while ($row = mysqli_fetch_assoc($res_notif)) { $unread_notifications[] = $row; }
    mysqli_stmt_close($stmt_notif);
}

// Mark All as Read
if (isset($_GET['mark_all_read'])) {
    $sql_mr = "UPDATE notifications SET is_read = TRUE WHERE user_id = ?";
    if ($stmt_mr = mysqli_prepare($link, $sql_mr)) {
        mysqli_stmt_bind_param($stmt_mr, "i", $admin_id);
        mysqli_stmt_execute($stmt_mr);
        mysqli_stmt_close($stmt_mr);
        header("location: admin.php");
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
    <title>Admin Dashboard - AgroSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=modern-ui-2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div id="wrapper">
        <!-- Sidebar -->
        <div id="sidebar-wrapper">
            <div class="sidebar-heading"><i class="fas fa-leaf me-2"></i>AgroSphere</div>
            <div class="list-group list-group-flush mt-3">
                <a href="#overview" class="list-group-item list-group-item-action"><i class="fas fa-tachometer-alt"></i> Overview</a>
                <a href="#pendingCrops" class="list-group-item list-group-item-action">
                    <i class="fas fa-seedling"></i> Crop Approvals
                    <?php if(!empty($pending_crops)): ?><span class="badge bg-warning rounded-pill ms-1 text-dark"><?php echo count($pending_crops); ?></span><?php endif; ?>
                </a>
                <a href="#manageUsers" class="list-group-item list-group-item-action"><i class="fas fa-users"></i> Users</a>
                <a href="#monitorOrders" class="list-group-item list-group-item-action"><i class="fas fa-shopping-basket"></i> Orders</a>
                <a href="#userMessages" class="list-group-item list-group-item-action">
                    <i class="fas fa-envelope"></i> Messages 
                    <?php if($unread_msg_count > 0): ?><span class="badge bg-danger rounded-pill ms-1"><?php echo $unread_msg_count; ?></span><?php endif; ?>
                </a>
                <a href="#publicMessages" class="list-group-item list-group-item-action">
                    <i class="fas fa-inbox"></i> Public Messages
                    <?php if($unread_public_msg_count > 0): ?><span class="badge bg-danger rounded-pill ms-1"><?php echo $unread_public_msg_count; ?></span><?php endif; ?>
                </a>
                <a href="#announcements" class="list-group-item list-group-item-action"><i class="fas fa-bullhorn"></i> Announcements</a>
                <a href="../logout.php" class="list-group-item list-group-item-action text-danger mt-5"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom sticky-top">
                <div class="container-fluid">
                    <button class="btn btn-outline-success" id="menu-toggle"><i class="fas fa-bars"></i></button>
                    <div class="ms-3 fw-bold text-success">ADMIN PANEL</div>
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
                    </div>
                </div>
            </nav>

            <div class="container-fluid p-4 animated-fade-in">
                <div class="dashboard-hero">
                    <div class="row align-items-end g-4">
                        <div class="col-lg-8">
                            <div class="dashboard-eyebrow">Admin command center</div>
                            <h1 class="dashboard-title">Monitor the marketplace and keep operations moving.</h1>
                            <p class="dashboard-subtitle">Review crop approvals, manage users, publish announcements, confirm payments, and respond to support messages.</p>
                            <div class="dashboard-chip-row">
                                <span class="dashboard-chip"><i class="fas fa-users"></i><strong><?php echo count($all_users); ?></strong> Users</span>
                                <span class="dashboard-chip"><i class="fas fa-seedling"></i><strong><?php echo count($pending_crops); ?></strong> Pending crops</span>
                                <span class="dashboard-chip"><i class="fas fa-shopping-basket"></i><strong><?php echo count($all_orders); ?></strong> Orders</span>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="dashboard-action-panel ms-lg-auto">
                                <div class="small opacity-75 mb-2">Needs attention</div>
                                <div class="h5 fw-bold mb-3"><?php echo $unread_msg_count + $unread_public_msg_count; ?> unread support messages</div>
                                <a href="#publicMessages" class="btn btn-warning w-100 fw-bold">
                                    <i class="fas fa-inbox me-2"></i>Review Messages
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?> shadow-sm"><?php echo $message; ?></div><?php endif; ?>

                <!-- Stats -->
                <div class="row mb-4" id="overview">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card p-3 h-100 border-start border-4 border-success">
                            <div class="text-muted small fw-bold text-uppercase">Total Farmers</div>
                            <div class="h3 mb-0"><?php echo $total_farmers; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card p-3 h-100 border-start border-4 border-primary">
                            <div class="text-muted small fw-bold text-uppercase">Total Buyers</div>
                            <div class="h3 mb-0"><?php echo $total_buyers; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card p-3 h-100 border-start border-4 border-warning">
                            <div class="text-muted small fw-bold text-uppercase">Total Orders</div>
                            <div class="h3 mb-0"><?php echo $total_orders; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card p-3 h-100 border-start border-4 border-danger">
                            <div class="text-muted small fw-bold text-uppercase">New Messages</div>
                            <div class="h3 mb-0"><?php echo $unread_msg_count; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Crop Approvals -->
                <div class="card shadow mb-4" id="pendingCrops">
                    <div class="card-header bg-warning text-dark fw-bold">Pending Crop Approvals</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Farmer</th>
                                        <th>Crop Details</th>
                                        <th>Location</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($pending_crops)): ?>
                                        <tr><td colspan="4" class="text-center py-4">No crops pending approval.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($pending_crops as $crop): ?>
                                            <tr>
                                                <td class="p-3 fw-bold"><?php echo htmlspecialchars($crop['farmer_name']); ?></td>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($crop['crop_name']); ?></div>
                                                    <div class="small text-muted">Price: RWF <?php echo number_format($crop['price']); ?> | Qty: <?php echo $crop['quantity']; ?></div>
                                                </td>
                                                <td><?php echo htmlspecialchars($crop['location']); ?></td>
                                                <td>
                                                    <form action="admin.php" method="post" class="d-inline">
                                                        <input type="hidden" name="crop_approval" value="1">
                                                        <input type="hidden" name="crop_id" value="<?php echo $crop['id']; ?>">
                                                        <input type="hidden" name="new_status" value="approved">
                                                        <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                                    </form>
                                                    <form action="admin.php" method="post" class="d-inline">
                                                        <input type="hidden" name="crop_approval" value="1">
                                                        <input type="hidden" name="crop_id" value="<?php echo $crop['id']; ?>">
                                                        <input type="hidden" name="new_status" value="rejected">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Platform Announcements -->
                <div class="card shadow mb-4" id="announcements">
                    <div class="card-header bg-primary text-white fw-bold">Platform-wide Announcements</div>
                    <div class="card-body">
                        <form action="admin.php" method="post">
                            <input type="hidden" name="announcement_action" value="publish">
                            <div class="row g-3 mb-3">
                                <div class="col-md-3">
                                    <input type="text" name="title" class="form-control" placeholder="Announcement Title" required>
                                </div>
                                <div class="col-md-9">
                                    <textarea name="message" class="form-control" placeholder="Message content..." rows="2" required></textarea>
                                </div>
                            </div>
                            
                            <div class="row mb-3 p-3 bg-light rounded">
                                <label class="fw-bold mb-2">Send To:</label>
                                <div class="col-12">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="target_farmer" id="target_farmer" value="1" checked>
                                        <label class="form-check-label" for="target_farmer">
                                            <i class="fas fa-tractor me-1"></i>Farmers
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="target_buyer" id="target_buyer" value="1" checked>
                                        <label class="form-check-label" for="target_buyer">
                                            <i class="fas fa-shopping-basket me-1"></i>Buyers
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="target_transporter" id="target_transporter" value="1" checked>
                                        <label class="form-check-label" for="target_transporter">
                                            <i class="fas fa-truck me-1"></i>Transporters
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="target_public" id="target_public" value="1" checked>
                                        <label class="form-check-label" for="target_public">
                                            <i class="fas fa-globe me-1"></i>Public
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>Publish Announcement</button>
                                </div>
                            </div>
                        </form>

                        <hr class="my-4">

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Announcement</th>
                                        <th>Audience</th>
                                        <th>Published</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($all_announcements)): ?>
                                        <tr><td colspan="4" class="text-center py-4">No announcements published yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($all_announcements as $announcement): ?>
                                            <tr>
                                                <td class="p-3">
                                                    <div class="fw-bold"><?php echo htmlspecialchars($announcement['title']); ?></div>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($announcement['message']); ?></div>
                                                </td>
                                                <td><?php echo htmlspecialchars(ucwords(str_replace(',', ', ', $announcement['target_audience']))); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($announcement['created_at'])); ?></td>
                                                <td>
                                                    <form action="admin.php#announcements" method="post" onsubmit="return confirm('Delete this announcement?');">
                                                        <input type="hidden" name="announcement_action" value="delete">
                                                        <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-trash me-1"></i>Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Public Messages Section -->
                <div class="card shadow mb-4" id="publicMessages">
                    <div class="card-header bg-success text-white fw-bold">
                        Public Contact Messages
                        <?php if($unread_public_msg_count > 0): ?>
                            <span class="badge bg-light text-success ms-2"><?php echo $unread_public_msg_count; ?> unread</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Sender</th>
                                        <th>Subject & Message</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($public_messages)): ?>
                                        <tr><td colspan="4" class="text-center py-4">No public contact messages found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($public_messages as $public_msg): ?>
                                            <tr>
                                                <td style="width: 230px;">
                                                    <div class="fw-bold"><?php echo htmlspecialchars($public_msg['name']); ?></div>
                                                    <a class="small text-success text-decoration-none" href="mailto:<?php echo htmlspecialchars($public_msg['email']); ?>">
                                                        <?php echo htmlspecialchars($public_msg['email']); ?>
                                                    </a>
                                                    <div class="small text-muted"><?php echo date('M d, Y H:i', strtotime($public_msg['created_at'])); ?></div>
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-success mb-1"><?php echo htmlspecialchars($public_msg['subject']); ?></div>
                                                    <p class="small mb-0 text-dark"><?php echo nl2br(htmlspecialchars($public_msg['message'])); ?></p>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo ($public_msg['status'] == 'unread') ? 'bg-warning text-dark' : 'bg-primary'; ?>">
                                                        <?php echo ucfirst($public_msg['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <?php if($public_msg['status'] == 'unread'): ?>
                                                            <form action="admin.php#publicMessages" method="post">
                                                                <input type="hidden" name="public_message_action" value="mark_read">
                                                                <input type="hidden" name="public_message_id" value="<?php echo $public_msg['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-primary">Mark Read</button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <a href="mailto:<?php echo htmlspecialchars($public_msg['email']); ?>?subject=<?php echo rawurlencode('Re: ' . $public_msg['subject']); ?>" class="btn btn-sm btn-outline-success">
                                                            <i class="fas fa-reply me-1"></i>Reply
                                                        </a>
                                                        <form action="admin.php#publicMessages" method="post" onsubmit="return confirm('Delete this public message?');">
                                                            <input type="hidden" name="public_message_action" value="delete">
                                                            <input type="hidden" name="public_message_id" value="<?php echo $public_msg['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                <i class="fas fa-trash me-1"></i>Delete
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Messages Section -->
                <div class="card shadow mb-4" id="userMessages">
                    <div class="card-header bg-dark text-white fw-bold">User Inquiries & Messages</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>From</th>
                                        <th>Subject & Message</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($all_messages)): ?>
                                        <tr><td colspan="4" class="text-center py-4">No messages found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($all_messages as $msg): ?>
                                            <tr>
                                                <td style="width: 200px;">
                                                    <div class="fw-bold"><?php echo htmlspecialchars($msg['user_name']); ?></div>
                                                    <div class="small text-muted text-uppercase"><?php echo $msg['user_role']; ?></div>
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-success mb-1"><?php echo htmlspecialchars($msg['subject']); ?></div>
                                                    <p class="small mb-1 text-dark"><?php echo htmlspecialchars($msg['message']); ?></p>
                                                    <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($msg['created_at'])); ?></small>
                                                    <?php if(!empty($msg['admin_reply'])): ?>
                                                        <div class="mt-2 p-2 bg-light rounded border-start border-3 border-primary small">
                                                            <span class="fw-bold text-primary">Your Reply:</span> <?php echo htmlspecialchars($msg['admin_reply']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge <?php echo ($msg['status'] == 'replied') ? 'bg-primary' : 'bg-warning text-dark'; ?>"><?php echo ucfirst($msg['status']); ?></span></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#replyModal" data-id="<?php echo $msg['id']; ?>" data-user="<?php echo htmlspecialchars($msg['user_name']); ?>" data-subject="<?php echo htmlspecialchars($msg['subject']); ?>" data-reply="<?php echo htmlspecialchars($msg['admin_reply'] ?? ''); ?>"><i class="fas fa-reply"></i> <?php echo empty($msg['admin_reply']) ? 'Reply' : 'Edit Reply'; ?></button>
                                                        <button class="btn btn-sm btn-outline-danger delete-msg-admin" data-id="<?php echo $msg['id']; ?>"><i class="fas fa-trash"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Order Monitoring -->
                <div class="card shadow mb-4" id="monitorOrders">
                    <div class="card-header fw-bold">Order & Payment Monitoring</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Assignment</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_orders as $order): ?>
                                        <tr>
                                            <td class="p-3">
                                                <div class="fw-bold">#<?php echo $order['order_id']; ?> - <?php echo htmlspecialchars($order['crop_name']); ?></div>
                                                <div class="small text-success">RWF <?php echo number_format($order['total_price']); ?></div>
                                            </td>
                                            <td>
                                                <?php if (empty($order['transporter_name']) && $order['status'] == 'pending'): ?>
                                                    <form action="admin.php" method="post" class="input-group input-group-sm" style="width: 200px;">
                                                        <input type="hidden" name="order_action" value="assign_transporter">
                                                        <input type="hidden" name="order_id_target" value="<?php echo $order['order_id']; ?>">
                                                        <select name="transporter_id_assign" class="form-select">
                                                            <option value="">Assign...</option>
                                                            <?php foreach($verified_transporters as $vt): ?>
                                                                <option value="<?php echo $vt['id']; ?>"><?php echo htmlspecialchars($vt['name']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" class="btn btn-primary">Go</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="small text-muted"><i class="fas fa-truck me-1"></i><?php echo htmlspecialchars($order['transporter_name'] ?? 'Unassigned'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge bg-secondary"><?php echo ucfirst($order['status']); ?></span></td>
                                            <td><span class="badge bg-<?php echo ($order['payment_status'] == 'paid') ? 'success' : 'info'; ?>"><?php echo ucfirst(str_replace('_', ' ', $order['payment_status'])); ?></span></td>
                                            <td>
                                                <?php if ($order['payment_status'] == 'pending_confirmation'): ?>
                                                    <form action="admin.php" method="post"><input type="hidden" name="order_action" value="confirm_payment"><input type="hidden" name="order_id_target" value="<?php echo $order['order_id']; ?>"><button type="submit" class="btn btn-sm btn-success">Verify Pay</button></form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- User Management -->
                <div class="card shadow mb-4" id="manageUsers">
                    <div class="card-header fw-bold">User Management</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Action</th></tr></thead>
                                <tbody>
                                    <?php foreach($all_users as $u): ?>
                                    <tr>
                                        <td class="p-3">
                                            <div class="fw-bold"><?php echo htmlspecialchars($u['name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($u['email']); ?></div>
                                        </td>
                                        <td><?php echo ucfirst($u['role']); ?></td>
                                        <td><span class="badge bg-<?php echo $u['is_verified'] ? 'success':'warning'; ?>"><?php echo $u['is_verified']?'Verified':'Unverified'; ?></span></td>
                                        <td>
                                            <?php if($u['id'] != $admin_id): ?>
                                            <form action="admin.php" method="post"><input type="hidden" name="user_action" value="toggle_verify"><input type="hidden" name="user_id_target" value="<?php echo $u['id']; ?>"><input type="hidden" name="new_status" value="<?php echo $u['is_verified'] ? '0':'1'; ?>"><button type="submit" class="btn btn-sm btn-outline-success">Toggle Verify</button></form>
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

    <!-- Reply Modal -->
    <div class="modal fade" id="replyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Reply to <span id="replyToUser"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="admin.php" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="order_action" value="reply_message">
                        <input type="hidden" name="msg_id" id="replyMsgId">
                        <div class="mb-3"><label class="fw-bold small">Subject:</label><div id="replySubject" class="text-muted small"></div></div>
                        <div class="mb-3"><label class="form-label fw-bold">Your Reply</label><textarea name="reply_text" id="adminReplyText" class="form-control" rows="5" required placeholder="Type your response..."></textarea></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Send Reply</button></div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/main.js?v=modern-ui-2"></script>
    <script>
        $("#menu-toggle").click(function(e) { e.preventDefault(); $("#wrapper").toggleClass("toggled"); });
        
        var replyModal = document.getElementById('replyModal');
        replyModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            document.getElementById('replyToUser').textContent = button.getAttribute('data-user');
            document.getElementById('replyMsgId').value = button.getAttribute('data-id');
            document.getElementById('replySubject').textContent = button.getAttribute('data-subject');
            document.getElementById('adminReplyText').value = button.getAttribute('data-reply');
        });

        $('.delete-msg-admin').click(function() {
            const msgId = $(this).data('id');
            if (confirm('Delete this message for everyone?')) {
                $.ajax({
                    url: '../includes/message_actions.php',
                    method: 'POST',
                    data: { action: 'delete_admin_view', msg_id: msgId },
                    success: function() {
                        location.reload();
                    }
                });
            }
        });
    </script>
</body>
</html>
