<?php
// Email Verification Page
require_once "includes/db.php";
require_once "includes/functions.php";
require_once "includes/security.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$token = isset($_GET['token']) ? sanitize_input($_GET['token']) : '';
$message = '';
$error = '';
$verified = false;

if (!empty($token)) {
    // Check if token exists and is valid
    $sql = "SELECT ev.id, ev.user_id, ev.email, ev.verified, ev.expires_at, u.id as user_check
            FROM email_verifications ev
            LEFT JOIN users u ON ev.user_id = u.id
            WHERE ev.token = ? AND ev.verified = FALSE";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) == 1) {
            mysqli_stmt_bind_result($stmt, $ev_id, $user_id, $email, $verified_status, $expires_at, $user_check);
            mysqli_stmt_fetch($stmt);
            
            // Check if token is expired
            if (strtotime($expires_at) < time()) {
                $error = "This verification link has expired. <a href='resend_verification.php?email=" . urlencode($email) . "'>Request a new one</a>";
            } else {
                // Update verification status
                $update_sql = "UPDATE email_verifications SET verified = TRUE, verified_at = NOW() WHERE id = ?";
                if ($update_stmt = mysqli_prepare($link, $update_sql)) {
                    mysqli_stmt_bind_param($update_stmt, "i", $ev_id);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);
                    
                    // Update user email_verified_at
                    $user_update = "UPDATE users SET email_verified_at = NOW() WHERE id = ?";
                    if ($user_stmt = mysqli_prepare($link, $user_update)) {
                        mysqli_stmt_bind_param($user_stmt, "i", $user_id);
                        mysqli_stmt_execute($user_stmt);
                        mysqli_stmt_close($user_stmt);
                    }
                    
                    $verified = true;
                    $message = "Email verified successfully! You can now <a href='login.php'>login to your account</a>";
                    log_activity($link, $user_id, 'EMAIL_VERIFIED', 'User verified their email address');
                }
            }
        } else {
            $error = "Invalid verification link or email already verified.";
        }
        
        mysqli_stmt_close($stmt);
    }
} else {
    $error = "No verification token provided.";
}

mysqli_close($link);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - AgroSphere MarketLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=modern-ui-2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
                    <div class="card-header <?php echo $verified ? 'bg-success' : 'bg-danger'; ?> text-white text-center py-4">
                        <h3 class="mb-0">
                            <?php if ($verified): ?>
                                <i class="fas fa-check-circle me-2"></i>Success!
                            <?php else: ?>
                                <i class="fas fa-exclamation-circle me-2"></i>Verification
                            <?php endif; ?>
                        </h3>
                    </div>
                    <div class="card-body p-4 p-md-5 text-center">
                        <?php if ($verified): ?>
                            <div class="alert alert-success border-0 mb-4">
                                <i class="fas fa-check-circle fa-3x mb-3" style="color: #28a745;"></i>
                                <p class="mb-0"><?php echo $message; ?></p>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger border-0 mb-4">
                                <i class="fas fa-times-circle fa-3x mb-3" style="color: #dc3545;"></i>
                                <p class="mb-0"><?php echo $error; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <a href="index.php" class="btn btn-success btn-lg fw-bold shadow-sm">Back to Home</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
