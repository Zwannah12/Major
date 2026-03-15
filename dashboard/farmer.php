<?php
session_start();
require_once "../includes/db.php";
require_once "../includes/functions.php";

// Ensure user is logged in and is a farmer
check_user_role('farmer');

$farmer_id = $_SESSION['id'];

// Fetch User Info (including verification status)
$sql_user = "SELECT is_verified FROM users WHERE id = ?";
$is_verified = false;
if ($stmt_user = mysqli_prepare($link, $sql_user)) {
    mysqli_stmt_bind_param($stmt_user, "i", $farmer_id);
    mysqli_stmt_execute($stmt_user);
    mysqli_stmt_bind_result($stmt_user, $is_verified);
    mysqli_stmt_fetch($stmt_user);
    mysqli_stmt_close($stmt_user);
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
            $sql = "INSERT INTO crops (farmer_id, crop_name, quantity, price, location, harvest_date, image) VALUES (?, ?, ?, ?, ?, ?, ?)";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "isddsss", $farmer_id, $crop_name, $quantity, $price, $location, $harvest_date, $image_filename);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['success_message'] = "Crop listing added successfully.";
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
$sql_orders = "SELECT o.id as order_id, c.crop_name, o.quantity, o.total_price, o.status, u.name as buyer_name, o.created_at
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

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Dashboard - AgroSphere</title>
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
                    <div class="ms-auto">
                        <span class="badge bg-success-custom text-white p-2">
                            <i class="fas fa-user me-1"></i> Farmer: <?php echo htmlspecialchars($_SESSION["name"]); ?>
                        </span>
                    </div>
                </div>
            </nav>

            <div class="container-fluid p-4 animated-fade-in">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">My Farm Overview</h1>
                    <?php if ($is_verified): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCropModal">
                            <i class="fas fa-plus me-2"></i>Add New Crop
                        </button>
                    <?php else: ?>
                        <button class="btn btn-secondary" disabled title="Account pending verification">
                            <i class="fas fa-lock me-2"></i>Add New Crop
                        </button>
                    <?php endif; ?>
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
    <script src="../assets/js/main.js"></script>
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
    </script>
</body>
</html>