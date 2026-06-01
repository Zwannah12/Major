<?php
session_start();
require_once "../includes/db.php";
require_once "../includes/functions.php";
require_once "../includes/districts.php";

// Ensure user is logged in and is a farmer
check_user_role('farmer');

$farmer_id = $_SESSION['id'];
$districts = get_all_districts();
$unique_districts = array_unique($districts);
sort($unique_districts);
$province_districts = get_province_district_data();

// Fetch User Info (including verification status and location)
$sql_user = "SELECT is_verified, location FROM users WHERE id = ?";
$is_verified = false;
$farmer_location = "Nyarugenge"; // Default location
if ($stmt_user = mysqli_prepare($link, $sql_user)) {
    mysqli_stmt_bind_param($stmt_user, "i", $farmer_id);
    mysqli_stmt_execute($stmt_user);
    mysqli_stmt_bind_result($stmt_user, $is_verified, $farmer_location);
    mysqli_stmt_fetch($stmt_user);
    mysqli_stmt_close($stmt_user);
}
if (!in_array($farmer_location, $unique_districts, true)) {
    $farmer_location = "Nyarugenge";
}

// Variables for crop form
$crop_name = $quantity = $price = $location = $harvest_date = "";
$crop_name_err = $quantity_err = $price_err = $image_err = "";
$upload_dir = '../assets/images/crops/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle Add/Edit Crop Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    if (!$is_verified && $action == 'add') {
        $_SESSION['error_message'] = "Your account must be verified to list new crops.";
        header("location: farmer.php");
        exit();
    }

    if (empty(trim($_POST["crop_name"]))) {
        $crop_name_err = "Please enter crop name.";
    } else {
        $crop_name = sanitize_input($_POST["crop_name"]);
    }

    if (empty(trim($_POST["quantity"]))) {
        $quantity_err = "Please enter quantity.";
    } elseif (!is_numeric($_POST["quantity"]) || $_POST["quantity"] <= 0) {
        $quantity_err = "Quantity must be a positive number.";
    } else {
        $quantity = sanitize_input($_POST["quantity"]);
    }

    if (empty(trim($_POST["price"]))) {
        $price_err = "Please enter price.";
    } elseif (!is_numeric($_POST["price"]) || $_POST["price"] <= 0) {
        $price_err = "Price must be a positive number.";
    } else {
        $price = sanitize_input($_POST["price"]);
    }

    $location = sanitize_input($_POST["location"]);
    $harvest_date = sanitize_input($_POST["harvest_date"]);
    $image_filename = 'default_crop.jpg';

    if (isset($_FILES["crop_image"]) && $_FILES["crop_image"]["error"] == 0) {
        $allowed_ext = array("jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png");
        $file_name = $_FILES["crop_image"]["name"];
        $file_type = $_FILES["crop_image"]["type"];
        $file_size = $_FILES["crop_image"]["size"];

        $ext = pathinfo($file_name, PATHINFO_EXTENSION);
        if (!array_key_exists($ext, $allowed_ext)) {
            $image_err = "Error: Please select a valid file format (JPG, JPEG, PNG, GIF).";
        }

        $maxsize = 5 * 1024 * 1024;
        if ($file_size > $maxsize) {
            $image_err = "Error: File size is larger than the allowed limit (5MB).";
        }

        if (in_array($file_type, $allowed_ext) && empty($image_err)) {
            $image_filename = uniqid('crop_', true) . "." . $ext;
            if (move_uploaded_file($_FILES["crop_image"]["tmp_name"], $upload_dir . $image_filename)) {
            } else {
                $image_err = "File upload failed. Please try again.";
            }
        }
    } elseif ($action == 'edit' && isset($_POST['current_image'])) {
        $image_filename = $_POST['current_image'];
    }

    if (empty($crop_name_err) && empty($quantity_err) && empty($price_err) && empty($image_err)) {
        if ($action == 'add') {
            $sql = "INSERT INTO crops (farmer_id, crop_name, quantity, price, location, harvest_date, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "isddsss", $farmer_id, $crop_name, $quantity, $price, $location, $harvest_date, $image_filename);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['success_message'] = "Crop listing added successfully and is pending admin approval.";
                    
                    // Notify Admins
                    $sql_admins = "SELECT id FROM users WHERE role = 'admin'";
                    if ($res_admins = mysqli_query($link, $sql_admins)) {
                        while ($admin = mysqli_fetch_assoc($res_admins)) {
                            create_notification($link, $admin['id'], "New crop listing '$crop_name' submitted by " . $_SESSION['name'], "admin.php#pendingCrops");
                        }
                    }
                    
                    header("location: farmer.php");
                    exit();
                } else {
                    $_SESSION['error_message'] = "Error adding crop: " . mysqli_error($link);
                }
                mysqli_stmt_close($stmt);
            }
        } elseif ($action == 'edit' && isset($_POST['crop_id'])) {
            $crop_id = sanitize_input($_POST['crop_id']);
            $sql = "UPDATE crops SET crop_name = ?, quantity = ?, price = ?, location = ?, harvest_date = ?, image = ? WHERE id = ? AND farmer_id = ?";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "sddsssii", $crop_name, $quantity, $price, $location, $harvest_date, $image_filename, $crop_id, $farmer_id);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['success_message'] = "Crop listing updated successfully.";
                    header("location: farmer.php");
                    exit();
                } else {
                    $_SESSION['error_message'] = "Error updating crop: " . mysqli_error($link);
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// Handle Delete Crop
if (isset($_GET['delete_crop'])) {
    $crop_id_to_delete = sanitize_input($_GET['delete_crop']);
    $sql = "DELETE FROM crops WHERE id = ? AND farmer_id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $crop_id_to_delete, $farmer_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "Crop listing deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Error deleting crop: " . mysqli_error($link);
        }
        mysqli_stmt_close($stmt);
    }
    header("location: farmer.php");
    exit();
}

// Fetch Farmer's Crops
$my_crops = [];
$sql_crops = "SELECT id, crop_name, quantity, price, location, harvest_date, image FROM crops WHERE farmer_id = ? ORDER BY created_at DESC";
if ($stmt_crops = mysqli_prepare($link, $sql_crops)) {
    mysqli_stmt_bind_param($stmt_crops, "i", $farmer_id);
    if (mysqli_stmt_execute($stmt_crops)) {
        $result_crops = mysqli_stmt_get_result($stmt_crops);
        while ($row = mysqli_fetch_assoc($result_crops)) {
            $my_crops[] = $row;
        }
    }
    mysqli_stmt_close($stmt_crops);
}

// Fetch Farmer's Orders
$my_orders = [];
$sql_orders = "SELECT o.id as order_id, c.crop_name, o.quantity, o.total_price, o.status, u.name as buyer_name, o.created_at, o.buyer_id
               FROM orders o
               JOIN crops c ON o.crop_id = c.id
               JOIN users u ON o.buyer_id = u.id
               WHERE c.farmer_id = ? ORDER BY o.created_at DESC";
if ($stmt_orders = mysqli_prepare($link, $sql_orders)) {
    mysqli_stmt_bind_param($stmt_orders, "i", $farmer_id);
    if (mysqli_stmt_execute($stmt_orders)) {
        $result_orders = mysqli_stmt_get_result($stmt_orders);
        while ($row = mysqli_fetch_assoc($result_orders)) {
            $my_orders[] = $row;
        }
    }
    mysqli_stmt_close($stmt_orders);
}

// Stats
$total_revenue = 0;
foreach($my_orders as $o) { if($o['status'] == 'delivered') $total_revenue += $o['total_price']; }
$pending_count = 0;
foreach($my_orders as $o) { if($o['status'] == 'pending') $pending_count++; }

// Notifications
$unread_notifications = [];
$sql_notif = "SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC";
if ($stmt_notif = mysqli_prepare($link, $sql_notif)) {
    mysqli_stmt_bind_param($stmt_notif, "i", $farmer_id);
    mysqli_stmt_execute($stmt_notif);
    $res_notif = mysqli_stmt_get_result($stmt_notif);
    while ($row = mysqli_fetch_assoc($res_notif)) { $unread_notifications[] = $row; }
    mysqli_stmt_close($stmt_notif);
}

// Fetch Latest Announcement (for farmers)
$latest_announcement = null;
// Try with target_audience column if it exists
$sql_check = "SHOW COLUMNS FROM announcements LIKE 'target_audience'";
if (mysqli_num_rows(mysqli_query($link, $sql_check)) > 0) {
    $sql_ann = "SELECT * FROM announcements WHERE FIND_IN_SET('farmer', target_audience) > 0 ORDER BY created_at DESC LIMIT 1";
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
        mysqli_stmt_bind_param($stmt_mr, "i", $farmer_id);
        mysqli_stmt_execute($stmt_mr);
        mysqli_stmt_close($stmt_mr);
        header("location: farmer.php");
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
    <title>Farmer Dashboard - AgroSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=modern-ui-2">
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
                <a href="farmer.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="#myCrops" class="list-group-item list-group-item-action">
                    <i class="fas fa-seedling"></i> My Crops
                </a>
                <a href="#buyerOrders" class="list-group-item list-group-item-action">
                    <i class="fas fa-shopping-basket"></i> Orders
                </a>
                <a href="../marketplace.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-store"></i> Marketplace
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
                    <div class="ms-3 fw-bold text-success d-none d-sm-block">FARMER PANEL</div>
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
                            <i class="fas fa-user me-1"></i> Farmer: <?php echo htmlspecialchars($_SESSION["name"]); ?>
                        </span>
                    </div>
                </div>
            </nav>

            <div class="container-fluid p-4 animated-fade-in">
                <div class="dashboard-hero">
                    <div class="row align-items-end g-4">
                        <div class="col-lg-8">
                            <div class="dashboard-eyebrow">Farmer workspace</div>
                            <h1 class="dashboard-title">Manage harvests, orders, and weather in one place.</h1>
                            <p class="dashboard-subtitle">Track your active listings, monitor buyer demand, and publish new crops as soon as they are ready for market.</p>
                            <div class="dashboard-chip-row">
                                <span class="dashboard-chip"><i class="fas fa-seedling"></i><strong><?php echo count($my_crops); ?></strong> Listings</span>
                                <span class="dashboard-chip"><i class="fas fa-shopping-basket"></i><strong><?php echo count($my_orders); ?></strong> Orders</span>
                                <span class="dashboard-chip"><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($farmer_location); ?></span>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="dashboard-action-panel ms-lg-auto">
                                <div class="small opacity-75 mb-2">Account status</div>
                                <div class="h5 fw-bold mb-3"><?php echo $is_verified ? 'Verified seller' : 'Pending verification'; ?></div>
                                <?php if ($is_verified): ?>
                                    <button class="btn btn-warning w-100 fw-bold" data-bs-toggle="modal" data-bs-target="#addCropModal">
                                        <i class="fas fa-plus me-2"></i>Add New Crop
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-light w-100 fw-bold" disabled title="Account pending verification">
                                        <i class="fas fa-lock me-2"></i>Add New Crop
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!$is_verified): ?>
                    <div class="alert alert-warning shadow-sm mb-4 border-start-warning border-4">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                            <div>
                                <h5 class="alert-heading fw-bold mb-1">Account Pending Verification</h5>
                                <p class="mb-0">Your account is currently waiting for admin confirmation. You cannot add new crops until an admin verifies your profile. Please contact support at <strong>info@agrosphere.com</strong> if you have any questions.</p>
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

                <!-- Platform Announcement -->
                <?php if ($latest_announcement): ?>
                    <div class="card shadow-sm border-0 mb-4 bg-primary text-white overflow-hidden">
                        <div class="card-body p-4 position-relative">
                            <div style="position: absolute; right: -20px; top: -20px; font-size: 100px; opacity: 0.1; transform: rotate(15deg);">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h5 class="fw-bold mb-1"><i class="fas fa-bullhorn me-2"></i><?php echo htmlspecialchars($latest_announcement['title']); ?></h5>
                                    <p class="mb-0 opacity-90"><?php echo htmlspecialchars($latest_announcement['message']); ?></p>
                                </div>
                                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                    <small class="opacity-75">Published on <?php echo date('M d, Y', strtotime($latest_announcement['created_at'])); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Weather Section -->
                <div class="card shadow-sm border-0 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body p-4">
                        <div class="row align-items-center mb-3">
                            <div class="col-md-8">
                                <h5 class="text-white fw-bold mb-1">
                                    <i class="fas fa-location-dot me-2"></i>Weather for <span class="weather-location-name"><?php echo htmlspecialchars($farmer_location); ?></span>
                                </h5>
                            </div>
                            <div class="col-md-4">
                                <select id="district-selector" class="form-select form-select-sm bg-white text-dark" style="font-size: 0.85rem;">
                                    <option value="auto">Auto-detect Location</option>
                                    <?php foreach ($province_districts as $province => $district_group): ?>
                                        <optgroup label="<?php echo htmlspecialchars($province); ?>">
                                            <?php foreach ($district_group as $dist => $coords): ?>
                                                <option value="<?php echo htmlspecialchars($dist); ?>" <?php echo ($farmer_location == $dist) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($dist); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div id="weather-info" class="text-white">
                                    <div class="spinner-border spinner-border-sm text-white" role="status" style="width: 1.5rem; height: 1.5rem;">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <span class="ms-2">Detecting your location...</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div id="weather-icon" style="font-size: 3rem; text-align: center; color: white;">
                                    <i class="fas fa-sun"></i>
                                </div>
                            </div>
                        </div>
                        <small class="text-white opacity-75 d-block mt-2">Updates every 10 seconds - choose any Rwanda district or use GPS</small>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card h-100 py-2">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Active Listings</div>
                                        <div class="h4 mb-0 font-weight-bold"><?php echo count($my_crops); ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-seedling fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card h-100 py-2 border-start-warning" style="border-left-color: var(--warning-color) !important;">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Orders</div>
                                        <div class="h4 mb-0 font-weight-bold"><?php echo $pending_count; ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-clock fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card h-100 py-2 border-start-success" style="border-left-color: var(--success-color) !important;">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Revenue</div>
                                        <div class="h4 mb-0 font-weight-bold">RWF <?php echo number_format($total_revenue); ?></div>
                                    </div>
                                    <div class="col-auto"><i class="fas fa-money-bill-wave fa-2x text-gray-300"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- My Crops Table -->
                <div class="card shadow mb-4" id="myCrops">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-seedling me-2"></i>My Crop Listings</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Crop</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                        <th>Location</th>
                                        <th>Harvest Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($my_crops as $crop): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="../assets/images/crops/<?php echo htmlspecialchars($crop['image']); ?>" class="rounded me-3" width="40" height="40" style="object-fit: cover;">
                                                    <span class="fw-bold"><?php echo htmlspecialchars($crop['crop_name']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($crop['quantity']); ?></td>
                                            <td>RWF <?php echo number_format($crop['price'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($crop['location']); ?></td>
                                            <td><?php echo htmlspecialchars($crop['harvest_date']); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-info me-1" data-bs-toggle="modal" data-bs-target="#editCropModal"
                                                   data-id="<?php echo $crop['id']; ?>"
                                                   data-name="<?php echo htmlspecialchars($crop['crop_name']); ?>"
                                                   data-quantity="<?php echo htmlspecialchars($crop['quantity']); ?>"
                                                   data-price="<?php echo htmlspecialchars($crop['price']); ?>"
                                                   data-location="<?php echo htmlspecialchars($crop['location']); ?>"
                                                   data-harvest_date="<?php echo htmlspecialchars($crop['harvest_date']); ?>"
                                                   data-image="<?php echo htmlspecialchars($crop['image']); ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="farmer.php?delete_crop=<?php echo $crop['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this listing?');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Buyer Orders Table -->
                <div class="card shadow mb-4" id="buyerOrders">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-shopping-basket me-2"></i>Recent Orders</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Buyer</th>
                                        <th>Crop</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($my_orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['order_id']; ?></td>
                                            <td><?php echo htmlspecialchars($order['buyer_name']); ?></td>
                                            <td><?php echo htmlspecialchars($order['crop_name']); ?> (<?php echo $order['quantity']; ?>)</td>
                                            <td class="fw-bold">RWF <?php echo number_format($order['total_price']); ?></td>
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
                                                <a href="../chat.php?user_id=<?php echo $order['buyer_id']; ?>" class="ms-2 text-success" title="Chat with Buyer">
                                                    <i class="fas fa-comments"></i>
                                                </a>
                                            </td>
                                            <td class="small"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
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

    <!-- Add Crop Modal -->
    <div class="modal fade" id="addCropModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Crop Listing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="farmer.php" method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Crop Name</label>
                            <input type="text" name="crop_name" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Quantity</label>
                                <input type="number" name="quantity" step="0.01" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price (RWF)</label>
                                <input type="number" name="price" step="0.01" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($_SESSION['location'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Harvest Date</label>
                            <input type="date" name="harvest_date" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Crop Image</label>
                            <input type="file" name="crop_image" class="form-control" accept="image/*">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Add Crop</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Crop Modal -->
    <div class="modal fade" id="editCropModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">Edit Crop Listing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="farmer.php" method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="crop_id" id="edit_crop_id">
                        <input type="hidden" name="current_image" id="edit_current_image">
                        <div class="mb-3">
                            <label class="form-label">Crop Name</label>
                            <input type="text" name="crop_name" id="edit_crop_name" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Quantity</label>
                                <input type="number" name="quantity" id="edit_quantity" step="0.01" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price</label>
                                <input type="number" name="price" id="edit_price" step="0.01" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" id="edit_location" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Harvest Date</label>
                            <input type="date" name="harvest_date" id="edit_harvest_date" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Update Image (Optional)</label>
                            <input type="file" name="crop_image" class="form-control" accept="image/*">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/main.js?v=modern-ui-2"></script>
    <script>
        $("#menu-toggle").click(function(e) { e.preventDefault(); $("#wrapper").toggleClass("toggled"); });

        var editCropModal = document.getElementById('editCropModal');
        editCropModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            document.getElementById('edit_crop_id').value = button.getAttribute('data-id');
            document.getElementById('edit_crop_name').value = button.getAttribute('data-name');
            document.getElementById('edit_quantity').value = button.getAttribute('data-quantity');
            document.getElementById('edit_price').value = button.getAttribute('data-price');
            document.getElementById('edit_location').value = button.getAttribute('data-location');
            document.getElementById('edit_harvest_date').value = button.getAttribute('data-harvest_date');
            document.getElementById('edit_current_image').value = button.getAttribute('data-image');
        });

        // Weather Update Function with Geolocation or District Selection
        const rwandaDistricts = <?php echo json_encode(get_all_districts()); ?>;
        const farmerDistrict = <?php echo json_encode($farmer_location); ?>;
        let currentWeatherMode = rwandaDistricts.includes(farmerDistrict) ? farmerDistrict : 'auto';
        document.getElementById('district-selector').value = currentWeatherMode;
        
        function updateWeather() {
            if (currentWeatherMode === 'auto') {
                // Auto-detect via GPS
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            const lat = position.coords.latitude;
                            const lon = position.coords.longitude;
                            fetchWeatherByCoordinates(lat, lon);
                        },
                        function(error) {
                            console.error('Geolocation error:', error);
                            // Fallback to user's location from profile
                            const location = "<?php echo htmlspecialchars($farmer_location); ?>";
                            fetchWeatherByDistrict(location);
                        }
                    );
                } else {
                    // Fallback to user's location from profile
                    const location = "<?php echo htmlspecialchars($farmer_location); ?>";
                    fetchWeatherByDistrict(location);
                }
            } else {
                // Fetch weather for selected district
                fetchWeatherByDistrict(currentWeatherMode);
            }
        }
        
        function fetchWeatherByDistrict(district) {
            const apiUrl = `../api/weather.php?district=${encodeURIComponent(district)}`;
            
            fetch(apiUrl)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        updateWeatherDisplay(data.temperature, data.weather_code, data.humidity, data.wind_speed, district);
                    } else {
                        console.error('Weather API error:', data.error);
                        document.getElementById('weather-info').innerHTML = '<span class="text-warning">Unable to fetch weather data</span>';
                    }
                })
                .catch(error => {
                    console.error('Weather API error:', error);
                    document.getElementById('weather-info').innerHTML = '<span class="text-warning">Weather service unavailable</span>';
                });
        }
        
        function fetchWeatherByCoordinates(lat, lon) {
            const weatherUrl = `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current=temperature_2m,weather_code,wind_speed_10m,relative_humidity_2m`;
            
            // Also get reverse geocoding to show city name
            const geoUrl = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`;
            
            Promise.all([
                fetch(weatherUrl).then(r => r.json()),
                fetch(geoUrl).then(r => r.json())
            ])
            .then(([weatherData, geoData]) => {
                const current = weatherData.current;
                
                // Get city/location name from reverse geocoding
                let locationName = "Your Location";
                if (geoData.address) {
                    const city = geoData.address.city || geoData.address.town || geoData.address.village;
                    const county = geoData.address.county || geoData.address.state;
                    if (city) {
                        locationName = city;
                    } else if (county) {
                        locationName = county;
                    }
                }
                
                updateWeatherDisplay(current.temperature_2m, current.weather_code, current.relative_humidity_2m, current.wind_speed_10m, locationName);
            })
            .catch(error => {
                console.error('Weather API error:', error);
                document.getElementById('weather-info').innerHTML = '<span class="text-warning">Unable to fetch weather data</span>';
            });
        }
        
        function updateWeatherDisplay(temp, weatherCode, humidity, windSpeed, locationName) {
            // Map WMO weather codes to descriptions
            const weatherDescriptions = {
                0: 'Clear sky',
                1: 'Mainly clear',
                2: 'Partly cloudy',
                3: 'Overcast',
                45: 'Foggy',
                48: 'Foggy',
                51: 'Light drizzle',
                61: 'Slight rain',
                63: 'Moderate rain',
                65: 'Heavy rain',
                71: 'Slight snow',
                85: 'Showers',
                95: 'Thunderstorm'
            };
            
            const weatherDesc = weatherDescriptions[weatherCode] || 'Unknown';
            
            // Select appropriate icon
            let iconClass = 'fas fa-sun';
            if (weatherCode === 0) iconClass = 'fas fa-sun';
            else if (weatherCode === 1 || weatherCode === 2) iconClass = 'fas fa-cloud-sun';
            else if (weatherCode === 3) iconClass = 'fas fa-cloud';
            else if (weatherCode === 45 || weatherCode === 48) iconClass = 'fas fa-smog';
            else if (weatherCode >= 51 && weatherCode <= 67) iconClass = 'fas fa-cloud-rain';
            else if (weatherCode >= 71 && weatherCode <= 85) iconClass = 'fas fa-snowflake';
            else if (weatherCode >= 95) iconClass = 'fas fa-bolt';
            
            // Update location name in header
            document.querySelector('.weather-location-name').textContent = locationName;
            
            // Update weather display
            const weatherHtml = `
                <div><strong>${temp}&deg;C</strong> - ${weatherDesc}</div>
                <div class="small opacity-75">
                    <i class="fas fa-water-lower me-1"></i>Humidity: ${humidity}% | 
                    <i class="fas fa-wind me-1"></i>Wind: ${windSpeed.toFixed(1)} km/h
                </div>
            `;
            
            document.getElementById('weather-info').innerHTML = weatherHtml;
            document.getElementById('weather-icon').innerHTML = `<i class="${iconClass}"></i>`;
        }
        
        // Update weather on page load and then every 10 seconds
        updateWeather();
        setInterval(updateWeather, 10000);
        
        // District selector change event
        document.getElementById('district-selector').addEventListener('change', function(e) {
            const selected = e.target.value;
            if (selected === 'auto') {
                currentWeatherMode = 'auto';
            } else if (selected !== '') {
                currentWeatherMode = selected;
            }
            updateWeather();
        });
    </script>
</body>
</html>
