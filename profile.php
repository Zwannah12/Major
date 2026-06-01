<?php
session_start();
require_once "includes/db.php";
require_once "includes/functions.php";
require_once "includes/security.php";

// Ensure user is logged in, but allow any role
if (!is_logged_in()) {
    header("location: login.php");
    exit();
}

$user_id = $_SESSION['id'];
$user_role = $_SESSION['role'];
$csrf_token = get_csrf_token();

// Define dashboard path based on role
$dashboard_path = "dashboard/{$user_role}.php";

// Variables for form
$name = $email = $phone = $location = $bio = $profile_image = "";
$name_err = $email_err = $image_err = "";

// Handle Profile Update Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    // Validate CSRF token
    if (empty($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $name_err = "Security token validation failed. Please try again.";
    } else {
        // Validate inputs
        $name = sanitize_input($_POST['name']);
        $email = sanitize_input($_POST['email']);
        $phone = sanitize_input($_POST['phone']);
        $location = sanitize_input($_POST['location']);
        $bio = sanitize_input($_POST['bio']);
        $current_image = sanitize_input($_POST['current_image']);

    // Handle image upload
    $upload_dir = 'assets/images/avatars/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $image_filename = $current_image;
    if (isset($_FILES["profile_image"]) && $_FILES["profile_image"]["error"] == 0) {
        // Validation logic for image
        $image_filename = uniqid('avatar_', true) . "." . pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION);
        if (!move_uploaded_file($_FILES["profile_image"]["tmp_name"], $upload_dir . $image_filename)) {
            $image_err = "Failed to upload image.";
            $image_filename = $current_image; // Revert to old image
        }
    }

    if (empty($image_err)) {
        $sql = "UPDATE users SET name = ?, email = ?, phone = ?, location = ?, bio = ?, profile_image = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssssssi", $name, $email, $phone, $location, $bio, $image_filename, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "Profile updated successfully!";
                // Update session variables
                $_SESSION['name'] = $name;
                $_SESSION['profile_image'] = $image_filename;
                header("location: profile.php");
                exit();
            }
        }
    }
    }
}

// Fetch current user data
$sql_user = "SELECT name, email, phone, location, bio, profile_image FROM users WHERE id = ?";
if ($stmt_user = mysqli_prepare($link, $sql_user)) {
    mysqli_stmt_bind_param($stmt_user, "i", $user_id);
    mysqli_stmt_execute($stmt_user);
    mysqli_stmt_bind_result($stmt_user, $name, $email, $phone, $location, $bio, $profile_image);
    mysqli_stmt_fetch($stmt_user);
    mysqli_stmt_close($stmt_user);
}

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - AgroSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=modern-ui-2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div id="wrapper">
        <!-- Sidebar -->
        <div id="sidebar-wrapper">
            <div class="sidebar-heading"><i class="fas fa-leaf me-2"></i>AgroSphere</div>
            <div class="list-group list-group-flush mt-3">
                <a href="<?php echo $dashboard_path; ?>" class="list-group-item list-group-item-action">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="profile.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-user-cog"></i> Profile Settings
                </a>
                <a href="logout.php" class="list-group-item list-group-item-action text-danger mt-5">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom sticky-top">
                <div class="container-fluid">
                    <button class="btn btn-outline-success" id="menu-toggle"><i class="fas fa-bars"></i></button>
                    <div class="ms-3 fw-bold text-success text-uppercase"><?php echo $user_role; ?> PANEL</div>
                </div>
            </nav>

            <div class="container-fluid p-4 animated-fade-in">
                <h1 class="h3 mb-4">My Profile Settings</h1>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success shadow-sm"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
                <?php endif; ?>

                <div class="card shadow">
                    <div class="card-body p-lg-5">
                        <form action="profile.php" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_profile">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
                            <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($profile_image); ?>">
                            
                            <div class="row">
                                <div class="col-md-4 text-center">
                                    <img src="assets/images/avatars/<?php echo htmlspecialchars($profile_image); ?>" class="img-fluid rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                                    <label for="profile_image" class="btn btn-sm btn-outline-primary">Change Picture</label>
                                    <input type="file" name="profile_image" id="profile_image" class="d-none" accept="image/*">
                                    <?php if($image_err): ?><div class="small text-danger mt-2"><?php echo $image_err; ?></div><?php endif; ?>
                                </div>

                                <div class="col-md-8">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Full Name</label>
                                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($name); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Email Address</label>
                                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Phone</label>
                                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Location</label>
                                            <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($location ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">My Bio</label>
                                        <textarea name="bio" class="form-control" rows="4" placeholder="Tell us a little about yourself or your business..."><?php echo htmlspecialchars($bio ?? ''); ?></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success float-end"><i class="fas fa-save me-2"></i>Save Changes</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/main.js?v=modern-ui-2"></script>
    <script>
        $("#menu-toggle").click(function(e) { e.preventDefault(); $("#wrapper").toggleClass("toggled"); });
    </script>
</body>
</html>
