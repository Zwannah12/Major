<?php
// Include config file and functions
require_once "includes/db.php";
require_once "includes/functions.php";

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in, otherwise redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Check if the user is a buyer, or allow all roles to browse but only buyers to order
$user_role = $_SESSION["role"];
$buyer_id = ($user_role == 'buyer') ? $_SESSION['id'] : null;

// Handle Place Order
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'place_order') {
    if ($user_role !== 'buyer') {
        $_SESSION['error_message'] = "Only buyers can place orders.";
    } else {
        $crop_id = sanitize_input($_POST['crop_id']);
        $order_quantity = sanitize_input($_POST['order_quantity']);
        $crop_price = sanitize_input($_POST['crop_price']); // Get price from form or re-fetch

        if (empty($crop_id) || empty($order_quantity) || empty($crop_price) || !is_numeric($order_quantity) || $order_quantity <= 0) {
            $_SESSION['error_message'] = "Invalid order details.";
        } else {
            // Re-fetch crop details to prevent price tampering
            $sql_fetch_crop = "SELECT price, quantity FROM crops WHERE id = ?";
            if ($stmt_fetch_crop = mysqli_prepare($link, $sql_fetch_crop)) {
                mysqli_stmt_bind_param($stmt_fetch_crop, "i", $crop_id);
                mysqli_stmt_execute($stmt_fetch_crop);
                $result_fetch_crop = mysqli_stmt_get_result($stmt_fetch_crop);
                if ($row_crop = mysqli_fetch_assoc($result_fetch_crop)) {
                    $actual_price_per_unit = $row_crop['price'];
                    $available_quantity = $row_crop['quantity'];

                    if ($order_quantity > $available_quantity) {
                        $_SESSION['error_message'] = "Requested quantity exceeds available stock.";
                    } else {
                        $total_price = $order_quantity * $actual_price_per_unit;

                        $sql_insert_order = "INSERT INTO orders (buyer_id, crop_id, quantity, total_price, status) VALUES (?, ?, ?, ?, 'pending')";
                        if ($stmt_insert_order = mysqli_prepare($link, $sql_insert_order)) {
                            mysqli_stmt_bind_param($stmt_insert_order, "iidd", $buyer_id, $crop_id, $order_quantity, $total_price);
                            if (mysqli_stmt_execute($stmt_insert_order)) {
                                // Update crop quantity
                                $sql_update_crop = "UPDATE crops SET quantity = quantity - ? WHERE id = ?";
                                if ($stmt_update_crop = mysqli_prepare($link, $sql_update_crop)) {
                                    mysqli_stmt_bind_param($stmt_update_crop, "di", $order_quantity, $crop_id);
                                    mysqli_stmt_execute($stmt_update_crop);
                                    mysqli_stmt_close($stmt_update_crop);
                                }
                                $_SESSION['success_message'] = "Order placed successfully!";
                            } else {
                                $_SESSION['error_message'] = "Error placing order: " . mysqli_error($link);
                            }
                            mysqli_stmt_close($stmt_insert_order);
                        }
                    }
                } else {
                    $_SESSION['error_message'] = "Crop not found.";
                }
                mysqli_stmt_close($stmt_fetch_crop);
            }
        }
    }
    header("location: marketplace.php");
    exit();
}


// Pagination setup
$limit = 9; // Number of crops per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Build query for fetching crops
$sql_query = "SELECT c.id, c.crop_name, c.quantity, c.price, c.location, c.harvest_date, c.image, u.name as farmer_name 
              FROM crops c JOIN users u ON c.farmer_id = u.id WHERE 1=1";
$params = [];
$types = "";

// Apply search filter
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term = "%" . trim($_GET['search']) . "%";
    $sql_query .= " AND (c.crop_name LIKE ? OR c.location LIKE ? OR u.name LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

// Apply crop type filter (placeholder for now, as crop_type is not in DB schema directly)
if (isset($_GET['crop_type']) && !empty(trim($_GET['crop_type']))) {
    // This would ideally filter based on a 'category' column in the crops table
    // For now, we'll just filter by crop name for demonstration
    $crop_type_filter = "%" . trim($_GET['crop_type']) . "%";
    $sql_query .= " AND c.crop_name LIKE ?";
    $params[] = $crop_type_filter;
    $types .= "s";
}

// Apply location filter
if (isset($_GET['location']) && !empty(trim($_GET['location']))) {
    $location_filter = "%" . trim($_GET['location']) . "%";
    $sql_query .= " AND c.location LIKE ?";
    $params[] = $location_filter;
    $types .= "s";
}

// Order by
$sql_query .= " ORDER BY c.created_at DESC";

// Get total number of crops for pagination
$sql_count = "SELECT COUNT(*) FROM crops c JOIN users u ON c.farmer_id = u.id WHERE 1=1";
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $sql_count .= " AND (c.crop_name LIKE ? OR c.location LIKE ? OR u.name LIKE ?)";
}
if (isset($_GET['crop_type']) && !empty(trim($_GET['crop_type']))) {
    $sql_count .= " AND c.crop_name LIKE ?";
}
if (isset($_GET['location']) && !empty(trim($_GET['location']))) {
    $sql_count .= " AND c.location LIKE ?";
}

$stmt_count = mysqli_prepare($link, $sql_count);
if ($stmt_count) {
    if (!empty($params) && !empty($types)) {
        // We need to exclude the last two params (limit and offset) for the count query
        // But we haven't added them yet, so it's safe if we do count before limit/offset
        mysqli_stmt_bind_param($stmt_count, $types, ...$params);
    }
    mysqli_stmt_execute($stmt_count);
    mysqli_stmt_bind_result($stmt_count, $total_crops);
    mysqli_stmt_fetch($stmt_count);
    mysqli_stmt_close($stmt_count);
} else {
    $total_crops = 0;
    error_log("marketplace.php: mysqli_prepare for count failed: " . mysqli_error($link));
}
$total_pages = ceil($total_crops / $limit);


// Add limit and offset for pagination
$sql_query .= " LIMIT ? OFFSET ?";
$params_with_paging = array_merge($params, [$limit, $offset]);
$types_with_paging = $types . "ii";


$crops = [];
if ($stmt = mysqli_prepare($link, $sql_query)) {
    if (!empty($params_with_paging) && !empty($types_with_paging)) {
        mysqli_stmt_bind_param($stmt, $types_with_paging, ...$params_with_paging);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $crops[] = $row;
    }
    mysqli_stmt_close($stmt);
} else {
    $_SESSION['error_message'] = "Error fetching crops: " . mysqli_error($link);
    error_log("marketplace.php: mysqli_prepare for main query failed: " . mysqli_error($link));
}

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace - AgroSphere MarketLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-leaf text-success"></i> AgroSphere MarketLink
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="marketplace.php">Marketplace</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION["name"]); ?>
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="dashboard/<?php echo $_SESSION["role"]; ?>.php">Dashboard</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2 class="mb-4 text-center">AgroSphere Marketplace</h2>

        <?php
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . $_SESSION['success_message'] . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . $_SESSION['error_message'] . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            unset($_SESSION['error_message']);
        }
        ?>

        <div class="row mb-4">
            <div class="col-md-8 offset-md-2">
                <form action="marketplace.php" method="get" class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search for crops..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    <button class="btn btn-outline-success" type="submit"><i class="fas fa-search"></i> Search</button>
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        Filters
                    </div>
                    <div class="card-body">
                        <form action="marketplace.php" method="get">
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                            <div class="mb-3">
                                <label for="crop_type" class="form-label">Crop Type</label>
                                <select class="form-select" id="crop_type" name="crop_type">
                                    <option value="">All</option>
                                    <option value="Tomatoes" <?php echo (($_GET['crop_type'] ?? '') == 'Tomatoes') ? 'selected' : ''; ?>>Tomatoes</option>
                                    <option value="Potatoes" <?php echo (($_GET['crop_type'] ?? '') == 'Potatoes') ? 'selected' : ''; ?>>Potatoes</option>
                                    <option value="Cabbage" <?php echo (($_GET['crop_type'] ?? '') == 'Cabbage') ? 'selected' : ''; ?>>Cabbage</option>
                                    <option value="Carrots" <?php echo (($_GET['crop_type'] ?? '') == 'Carrots') ? 'selected' : ''; ?>>Carrots</option>
                                    <!-- Dynamic options from DB later -->
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" placeholder="e.g., Kigali" value="<?php echo htmlspecialchars($_GET['location'] ?? ''); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                            <a href="marketplace.php" class="btn btn-secondary w-100 mt-2">Clear Filters</a>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-9">
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php if (!empty($crops)): ?>
                        <?php foreach ($crops as $crop): ?>
                            <div class="col">
                                <div class="card h-100 shadow-sm crop-card">
                                    <img src="assets/images/crops/<?php echo htmlspecialchars($crop['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($crop['crop_name']); ?>" onerror="this.onerror=null;this.src='assets/images/default_crop.jpg';">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($crop['crop_name']); ?></h5>
                                        <p class="card-text text-muted">Farmer: <?php echo htmlspecialchars($crop['farmer_name']); ?></p>
                                        <p class="card-text"><strong>Price: RWF <?php echo htmlspecialchars(number_format($crop['price'], 2)); ?> / unit</strong></p>
                                        <p class="card-text"><small class="text-success">Available: <?php echo htmlspecialchars($crop['quantity']); ?> units</small></p>
                                        <p class="card-text"><small class="text-info">Location: <?php echo htmlspecialchars($crop['location']); ?></small></p>
                                        <p class="card-text"><small class="text-secondary">Harvest: <?php echo htmlspecialchars($crop['harvest_date']); ?></small></p>
                                        <?php if ($user_role === 'buyer' && $crop['quantity'] > 0): ?>
                                            <button type="button" class="btn btn-success btn-sm w-100 mt-2" data-bs-toggle="modal" data-bs-target="#placeOrderModal"
                                                data-crop_id="<?php echo $crop['id']; ?>"
                                                data-crop_name="<?php echo htmlspecialchars($crop['crop_name']); ?>"
                                                data-crop_price="<?php echo htmlspecialchars($crop['price']); ?>"
                                                data-available_quantity="<?php echo htmlspecialchars($crop['quantity']); ?>">
                                                <i class="fas fa-shopping-cart"></i> Place Order
                                            </button>
                                        <?php elseif ($crop['quantity'] <= 0): ?>
                                            <button type="button" class="btn btn-secondary btn-sm w-100 mt-2" disabled>Out of Stock</button>
                                        <?php else: ?>
                                            <p class="text-center text-muted mt-2">Login as a Buyer to place orders.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center" role="alert">
                                No crops found matching your criteria.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="text-center mt-4">
                    <!-- Pagination -->
                    <nav aria-label="Page navigation example">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php if($page <= 1){ echo 'disabled'; } ?>">
                                <a class="page-link" href="<?php echo '?page='.($page-1). '&search=' . htmlspecialchars($_GET['search'] ?? '') . '&crop_type=' . htmlspecialchars($_GET['crop_type'] ?? '') . '&location=' . htmlspecialchars($_GET['location'] ?? ''); ?>">Previous</a>
                            </li>
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php if($page == $i){ echo 'active'; } ?>">
                                    <a class="page-link" href="<?php echo '?page='.$i. '&search=' . htmlspecialchars($_GET['search'] ?? '') . '&crop_type=' . htmlspecialchars($_GET['crop_type'] ?? '') . '&location=' . htmlspecialchars($_GET['location'] ?? ''); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php if($page >= $total_pages){ echo 'disabled'; } ?>">
                                <a class="page-link" href="<?php echo '?page='.($page+1). '&search=' . htmlspecialchars($_GET['search'] ?? '') . '&crop_type=' . htmlspecialchars($_GET['crop_type'] ?? '') . '&location=' . htmlspecialchars($_GET['location'] ?? ''); ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Place Order Modal -->
    <div class="modal fade" id="placeOrderModal" tabindex="-1" aria-labelledby="placeOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="placeOrderModalLabel">Place Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="marketplace.php" method="post">
                        <input type="hidden" name="action" value="place_order">
                        <input type="hidden" name="crop_id" id="order_crop_id">
                        <input type="hidden" name="crop_price" id="order_crop_price">
                        <p>You are ordering: <strong id="order_crop_name"></strong></p>
                        <p>Price per unit: RWF <span id="order_price_display"></span></p>
                        <p>Available quantity: <span id="order_available_quantity"></span> units</p>
                        <div class="mb-3">
                            <label for="order_quantity" class="form-label">Quantity to Order</label>
                            <input type="number" name="order_quantity" id="order_quantity" class="form-control" min="1" required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Confirm Order</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p>&copy; 2026 AgroSphere MarketLink. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        // Populate Place Order Modal
        var placeOrderModal = document.getElementById('placeOrderModal');
        placeOrderModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; // Button that triggered the modal
            var crop_id = button.getAttribute('data-crop_id');
            var crop_name = button.getAttribute('data-crop_name');
            var crop_price = button.getAttribute('data-crop_price');
            var available_quantity = button.getAttribute('data-available_quantity');

            var modalTitle = placeOrderModal.querySelector('#placeOrderModalLabel');
            var orderCropIdInput = placeOrderModal.querySelector('#order_crop_id');
            var orderCropPriceInput = placeOrderModal.querySelector('#order_crop_price');
            var orderCropNameDisplay = placeOrderModal.querySelector('#order_crop_name');
            var orderPriceDisplay = placeOrderModal.querySelector('#order_price_display');
            var orderAvailableQuantityDisplay = placeOrderModal.querySelector('#order_available_quantity');
            var orderQuantityInput = placeOrderModal.querySelector('#order_quantity');

            modalTitle.textContent = 'Place Order for ' + crop_name;
            orderCropIdInput.value = crop_id;
            orderCropPriceInput.value = crop_price;
            orderCropNameDisplay.textContent = crop_name;
            orderPriceDisplay.textContent = parseFloat(crop_price).toLocaleString('en-RW', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            orderAvailableQuantityDisplay.textContent = available_quantity;
            orderQuantityInput.max = available_quantity; // Set max for input
            orderQuantityInput.value = 1; // Default to 1
        });
    </script>
</body>
</html>