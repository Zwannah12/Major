<?php
session_start();
require_once "includes/db.php";
require_once "includes/functions.php";

if (!is_logged_in()) {
    header("location: login.php");
    exit();
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

if (!$user_id) {
    header("location: dashboard/" . $_SESSION['role'] . ".php");
    exit();
}

// Fetch user data
$name = $email = $phone = $location = $bio = $profile_image = $role = "";
$sql_user = "SELECT name, email, phone, location, bio, profile_image, role FROM users WHERE id = ?";
if ($stmt_user = mysqli_prepare($link, $sql_user)) {
    mysqli_stmt_bind_param($stmt_user, "i", $user_id);
    mysqli_stmt_execute($stmt_user);
    mysqli_stmt_bind_result($stmt_user, $name, $email, $phone, $location, $bio, $profile_image, $role);
    if (!mysqli_stmt_fetch($stmt_user)) {
        die("User not found.");
    }
    mysqli_stmt_close($stmt_user);
}

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($name); ?>'s Profile - AgroSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=modern-ui-2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold text-success" href="index.php"><i class="fas fa-leaf"></i> AGROSPHERE</a>
            <div class="ms-auto">
                <a href="chat.php?user_id=<?php echo $user_id; ?>" class="btn btn-success btn-sm"><i class="fas fa-comments me-1"></i> Send Message</a>
                <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm ms-2">Back</a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm border-0 overflow-hidden" style="border-radius: 20px;">
                    <div class="card-header bg-success p-4 text-center">
                        <img src="assets/images/avatars/<?php echo htmlspecialchars($profile_image); ?>" class="rounded-circle border border-4 border-white shadow" style="width: 150px; height: 150px; object-fit: cover; margin-bottom: -75px; background: white;">
                    </div>
                    <div class="card-body pt-5 mt-4 text-center">
                        <h2 class="fw-bold mt-2 mb-0"><?php echo htmlspecialchars($name); ?></h2>
                        <span class="badge bg-light text-success text-uppercase p-2 mb-3 mt-1"><?php echo $role; ?></span>
                        
                        <div class="row text-center mt-4">
                            <?php if($location): ?>
                            <div class="col-sm-4 mb-3">
                                <div class="text-muted small">Location</div>
                                <div class="fw-bold"><i class="fas fa-map-marker-alt me-1 text-danger"></i> <?php echo htmlspecialchars($location); ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if($phone): ?>
                            <div class="col-sm-4 mb-3">
                                <div class="text-muted small">Phone</div>
                                <div class="fw-bold"><i class="fas fa-phone me-1 text-success"></i> <?php echo htmlspecialchars($phone); ?></div>
                            </div>
                            <?php endif; ?>
                            <div class="col-sm-4 mb-3">
                                <div class="text-muted small">Member Since</div>
                                <div class="fw-bold"><i class="fas fa-calendar-alt me-1 text-primary"></i> Joined</div>
                            </div>
                        </div>

                        <hr class="my-4 mx-5 opacity-10">

                        <div class="px-lg-5">
                            <h5 class="fw-bold mb-3">About <?php echo explode(' ', $name)[0]; ?></h5>
                            <p class="text-muted">
                                <?php echo !empty($bio) ? nl2br(htmlspecialchars($bio)) : "This user hasn't added a bio yet."; ?>
                            </p>
                        </div>
                        
                        <div class="mt-5 mb-3">
                            <a href="chat.php?user_id=<?php echo $user_id; ?>" class="btn btn-success px-5 py-2 rounded-pill fw-bold shadow-sm">
                                <i class="fas fa-envelope me-2"></i>Contact Now
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js?v=modern-ui-2"></script>
</body>
</html>
