<?php
require_once "includes/db.php";
require_once "includes/functions.php";

session_start();

if (!isset($_SESSION['reset_email'])) {
    header("location: forgot_password.php");
    exit();
}

$email = $_SESSION['reset_email'];
$code = $new_password = $confirm_password = "";
$code_err = $password_err = $confirm_password_err = "";
$success_msg = "";

// SIMULATION: Show the code if it's in session for easy testing
$simulation_code = isset($_SESSION['temp_reset_code']) ? $_SESSION['temp_reset_code'] : "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate code
    if (empty(trim($_POST["code"]))) {
        $code_err = "Please enter the 6-digit code.";
    } else {
        $code = sanitize_input($_POST["code"]);
    }

    // Validate new password
    if (empty(trim($_POST["new_password"]))) {
        $password_err = "Please enter a new password.";
    } elseif (strlen(trim($_POST["new_password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $new_password = trim($_POST["new_password"]);
    }

    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm the password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($new_password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }

    if (empty($code_err) && empty($password_err) && empty($confirm_password_err)) {
        // Check if code matches and is not expired
        $sql = "SELECT id FROM users WHERE email = ? AND reset_token = ? AND token_expiry > NOW()";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ss", $email, $code);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    // Code is valid, update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_sql = "UPDATE users SET password = ?, reset_token = NULL, token_expiry = NULL WHERE email = ?";
                    if ($update_stmt = mysqli_prepare($link, $update_sql)) {
                        mysqli_stmt_bind_param($update_stmt, "ss", $hashed_password, $email);
                        if (mysqli_stmt_execute($update_stmt)) {
                            unset($_SESSION['reset_email']);
                            unset($_SESSION['temp_reset_code']);
                            $success_msg = "Password reset successfully! You can now <a href='login.php'>Login</a>.";
                        }
                    }
                } else {
                    $code_err = "Invalid or expired code.";
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
    <title>Reset Password - AgroSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=modern-ui-2">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-lg border-0 rounded-4">
                    <div class="card-header bg-success text-white text-center py-4">
                        <h3 class="mb-0">Reset Password</h3>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <?php if($success_msg): ?>
                            <div class="alert alert-success"><?php echo $success_msg; ?></div>
                        <?php else: ?>
                            <?php if($simulation_code): ?>
                                <div class="alert alert-info py-2 small">
                                    <i class="fas fa-info-circle me-1"></i> <strong>Simulation:</strong> Your reset code is: <code><?php echo $simulation_code; ?></code>
                                </div>
                            <?php endif; ?>

                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">6-Digit Code</label>
                                    <input type="text" name="code" class="form-control <?php echo (!empty($code_err)) ? 'is-invalid' : ''; ?>" maxlength="6" placeholder="######" required>
                                    <div class="invalid-feedback"><?php echo $code_err; ?></div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">New Password</label>
                                    <input type="password" name="new_password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" required>
                                    <div class="invalid-feedback"><?php echo $password_err; ?></div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" required>
                                    <div class="invalid-feedback"><?php echo $confirm_password_err; ?></div>
                                </div>
                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-success btn-lg">Reset Password</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js?v=modern-ui-2"></script>
</body>
</html>
