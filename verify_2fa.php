<?php
// Verify 2FA Page
require_once "includes/db.php";
require_once "includes/functions.php";
require_once "includes/security.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if pending 2FA
if (!isset($_SESSION['pending_2fa']) || !isset($_SESSION['pending_user_id'])) {
    header("location: login.php");
    exit();
}

$code_err = $verify_err = "";
$user_id = $_SESSION['pending_user_id'];
$email = $_SESSION['pending_email'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (empty($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $verify_err = "Security token validation failed. Please try again.";
    } else {
        if (empty(trim($_POST["code"]))) {
            $code_err = "Please enter the 2FA code.";
        } else {
            $code = sanitize_input($_POST["code"]);
            
            // Verify 2FA code
            if (verify_2fa_token($link, $user_id, $code)) {
                // 2FA verified successfully
                session_regenerate_id(true);
                
                // Get user data
                $sql = "SELECT id, name, email, role FROM users WHERE id = ?";
                if ($stmt = mysqli_prepare($link, $sql)) {
                    mysqli_stmt_bind_param($stmt, "i", $user_id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_bind_result($stmt, $id, $name, $email, $role);
                    mysqli_stmt_fetch($stmt);
                    mysqli_stmt_close($stmt);
                    
                    $_SESSION["loggedin"] = true;
                    $_SESSION["id"] = $id;
                    $_SESSION["name"] = $name;
                    $_SESSION["email"] = $email;
                    $_SESSION["role"] = $role;
                    
                    // Clear pending 2FA
                    unset($_SESSION['pending_2fa']);
                    unset($_SESSION['pending_user_id']);
                    unset($_SESSION['pending_email']);
                    
                    update_last_login($link, $id);
                    
                    log_activity($link, $id, '2FA_VERIFIED', '2FA authentication successful');
                    redirect_by_role($role);
                }
            } else {
                $verify_err = "Invalid or expired 2FA code. Please try again.";
                log_security_event($link, '2FA_VERIFICATION_FAILED', 'Invalid 2FA code for user: ' . $email, 'warning', $user_id);
            }
        }
    }
}

$csrf_token = get_csrf_token();
mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2FA Verification - AgroSphere MarketLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=modern-ui-2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container login-container py-5">
        <div class="row justify-content-center w-100">
            <div class="col-md-8 col-lg-5">
                <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
                    <div class="card-header bg-success text-white text-center py-4">
                        <h3 class="mb-0"><i class="fas fa-lock me-2"></i>Security Verification</h3>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <h4 class="text-center mb-3 fw-bold text-dark">Enter Your 2FA Code</h4>
                        <p class="text-center text-muted mb-4">A 6-digit code has been sent to your registered phone number.</p>
                        
                        <?php
                        if (!empty($verify_err)) {
                            echo '<div class="alert alert-danger border-0 shadow-sm mb-4">' . $verify_err . '</div>';
                        }
                        ?>
                        
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            
                            <div class="mb-4">
                                <label for="code" class="form-label fw-semibold">2FA Code</label>
                                <input type="text" name="code" id="code" class="form-control form-control-lg text-center <?php echo (!empty($code_err)) ? 'is-invalid' : ''; ?>" 
                                       placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autofocus>
                                <div class="invalid-feedback d-block"><?php echo $code_err; ?></div>
                            </div>
                            
                            <div class="d-grid gap-2 mb-4">
                                <button type="submit" class="btn btn-success btn-lg fw-bold shadow-sm py-3">Verify <i class="fas fa-check ms-2"></i></button>
                            </div>
                            
                            <p class="text-center text-muted mb-0">
                                <a href="login.php" class="text-success fw-bold text-decoration-none">Back to Login</a>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
