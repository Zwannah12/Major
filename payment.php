<?php
session_start();
require_once "includes/db.php";
require_once "includes/functions.php";

if (!is_logged_in() || !isset($_GET['order_id'])) {
    header("location: login.php");
    exit();
}

$order_id = (int)$_GET['order_id'];
$user_id = $_SESSION['id'];

// Handle Cancellation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'cancel_payment') {
    $sql = "UPDATE orders SET status = 'cancelled' WHERE id = ? AND buyer_id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header("location: dashboard/buyer.php?msg=payment_cancelled");
            exit();
        }
        mysqli_stmt_close($stmt);
    }
}

// State management for the payment page
$step = 'initiate'; // 'initiate', 'confirm', 'success'

// Handle Payment "Simulation"
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'simulate_payment') {
    $phone_number = sanitize_input($_POST['phone_number']);
    
    // In a real app, you'd call a payment gateway API here.
    // We will just simulate the next step.
    $sql = "UPDATE orders SET payment_status = 'pending_confirmation' WHERE id = ? AND buyer_id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $step = 'confirm';
    }
}

// Fetch order details to display on the page
$order_details = null;
$sql_order = "SELECT o.id, o.total_price, o.quantity, c.crop_name, u.name as farmer_name 
              FROM orders o 
              JOIN crops c ON o.crop_id = c.id
              JOIN users u ON c.farmer_id = u.id
              WHERE o.id = ? AND o.buyer_id = ?";
if ($stmt_order = mysqli_prepare($link, $sql_order)) {
    mysqli_stmt_bind_param($stmt_order, "ii", $order_id, $user_id);
    mysqli_stmt_execute($stmt_order);
    $result = mysqli_stmt_get_result($stmt_order);
    $order_details = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt_order);
}

if ($order_details === null) {
    die("Error: Order not found or you do not have permission to view it.");
}

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Payment - AgroSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=modern-ui-2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="text-center mb-4">
                    <a class="navbar-brand fw-bold text-success fs-2" href="index.php">
                        <i class="fas fa-leaf"></i> AGROSPHERE
                    </a>
                </div>

                <div class="card shadow-lg">
                    <div class="card-header bg-success text-white text-center p-4">
                        <h4 class="mb-0">Secure Payment</h4>
                    </div>
                    <div class="card-body p-5">
                        
                        <?php if ($step == 'initiate'): ?>
                        <!-- Step 1: Initiate Payment -->
                        <div class="text-center">
                            <i class="fas fa-mobile-alt fa-3x text-success mb-3"></i>
                            <h5 class="fw-bold">Pay with Mobile Money</h5>
                            <p class="text-muted">Enter your phone number to receive a payment prompt.</p>
                        </div>

                        <ul class="list-group list-group-flush my-4">
                            <li class="list-group-item d-flex justify-content-between"><span>Order ID:</span> <strong>#<?php echo $order_details['id']; ?></strong></li>
                            <li class="list-group-item d-flex justify-content-between"><span>Item:</span> <strong><?php echo htmlspecialchars($order_details['crop_name']); ?></strong></li>
                            <li class="list-group-item d-flex justify-content-between"><span>Quantity:</span> <strong><?php echo $order_details['quantity']; ?></strong></li>
                            <li class="list-group-item d-flex justify-content-between bg-light">
                                <span class="fw-bold">Total Amount:</span> 
                                <strong class="text-success fs-5">RWF <?php echo number_format($order_details['total_price']); ?></strong>
                            </li>
                        </ul>

                        <form action="payment.php?order_id=<?php echo $order_id; ?>" method="post">
                            <input type="hidden" name="action" value="simulate_payment">
                            <div class="mb-3">
                                <label for="phone_number" class="form-label">Your Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text">+250</span>
                                    <input type="tel" name="phone_number" class="form-control" placeholder="788 123 456" required>
                                </div>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg">Pay Now</button>
                            </div>
                        </form>
                        <form action="payment.php?order_id=<?php echo $order_id; ?>" method="post" class="mt-3">
                            <input type="hidden" name="action" value="cancel_payment">
                            <div class="d-grid">
                                <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to cancel this payment? This will cancel your order.')">Cancel Payment</button>
                            </div>
                        </form>

                        <?php elseif ($step == 'confirm'): ?>
                        <!-- Step 2: Confirmation Screen -->
                        <div class="text-center">
                            <div class="spinner-border text-success mb-3" role="status" style="width: 3rem; height: 3rem;">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <h5 class="fw-bold">Confirm Payment on Your Phone</h5>
                            <p class="text-muted">A payment prompt for <strong>RWF <?php echo number_format($order_details['total_price']); ?></strong> has been sent to your phone. Please enter your PIN to authorize the transaction.</p>
                            <div class="alert alert-info mt-4">
                                Once you approve, we will verify the payment and finalize your order. This may take a moment.
                            </div>
                            <a href="dashboard/buyer.php" class="btn btn-outline-secondary mt-3">
                                <i class="fas fa-arrow-left me-2"></i>Go to My Orders
                            </a>
                        </div>
                        <script>
                            // Optional: Redirect after a few seconds
                            setTimeout(function() {
                                window.location.href = 'dashboard/buyer.php';
                            }, 8000); // 8 seconds
                        </script>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js?v=modern-ui-2"></script>
</body>
</html>
