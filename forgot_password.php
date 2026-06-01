<?php
require_once "includes/db.php";
require_once "includes/functions.php";

$email = "";
$email_err = $success_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } else {
        $email = sanitize_input($_POST["email"]);
    }

    if (empty($email_err)) {
        $sql = "SELECT id FROM users WHERE email = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    // Generate 6-digit code
                    $reset_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expiry = date("Y-m-d H:i:s", strtotime("+1 hour"));

                    $update_sql = "UPDATE users SET reset_token = ?, token_expiry = ? WHERE email = ?";
                    if ($update_stmt = mysqli_prepare($link, $update_sql)) {
                        mysqli_stmt_bind_param($update_stmt, "sss", $reset_code, $expiry, $email);
                        if (mysqli_stmt_execute($update_stmt)) {
                            // In a real app, send email here. For now, simulate.
                            session_start();
                            $_SESSION['reset_email'] = $email;
                            $_SESSION['temp_reset_code'] = $reset_code; // SIMULATION ONLY
                            header("location: reset_password.php");
                            exit();
                        }
                    }
                } else {
                    $email_err = "No account found with that email.";
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
    mysqli_close($link);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - AgroSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=modern-ui-2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-lg border-0 rounded-4">
                    <div class="card-header bg-success text-white text-center py-4">
                        <h3 class="mb-0">Forgot Password</h3>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <p class="text-muted text-center mb-4">Enter your email address and we'll send you a 6-digit code to reset your password.</p>
                        
                        <?php if(!empty($email_err)): ?>
                            <div class="alert alert-danger"><?php echo $email_err; ?></div>
                        <?php endif; ?>

                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="mb-4">
                                <label for="email" class="form-label fw-semibold">Email Address</label>
                                <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>" required>
                            </div>
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-success btn-lg">Send Reset Code</button>
                            </div>
                            <div class="text-center">
                                <a href="login.php" class="text-decoration-none">Back to Login</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js?v=modern-ui-2"></script>
</body>
</html>
