<?php
// Include config file and functions
require_once "includes/db.php";
require_once "includes/functions.php";

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect if user is already logged in
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    redirect_by_role($_SESSION["role"]);
}

// Define variables and initialize with empty values
$name = $email = $password = $confirm_password = $role = $phone = $location = "";
$name_err = $email_err = $password_err = $confirm_password_err = $role_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate name
    if (empty(trim($_POST["name"]))) {
        $name_err = "Please enter your name.";
    } else {
        $name = sanitize_input($_POST["name"]);
    }

    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE email = ?";

        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_email);

            // Set parameters
            $param_email = trim($_POST["email"]);

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);

                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $email_err = "This email is already taken.";
                } else {
                    $email = sanitize_input($_POST["email"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }

    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }

    // Validate role
    if (empty(trim($_POST["role"]))) {
        $role_err = "Please select a role.";
    } else {
        $role = sanitize_input($_POST["role"]);
    }

    // Check input errors before inserting in database
    if (empty($name_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err) && empty($role_err)) {

        // Prepare an insert statement
        $sql = "INSERT INTO users (name, email, password, role, phone, location) VALUES (?, ?, ?, ?, ?, ?)";

        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ssssss", $param_name, $param_email, $param_password, $param_role, $param_phone, $param_location);

            // Set parameters
            $param_name = $name;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash
            $param_role = $role;
            $param_phone = sanitize_input($_POST["phone"]); // Phone is optional
            $param_location = sanitize_input($_POST["location"]); // Location is optional

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Redirect to login page
                header("location: login.php");
            } else {
                echo "Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }

    // Close connection
    mysqli_close($link);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - AgroSphere MarketLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container register-container py-5">
        <div class="row justify-content-center w-100">
            <div class="col-md-10 col-lg-6">
                <div class="card shadow-lg border-0 rounded-4 overflow-hidden my-4">
                    <div class="card-header bg-success text-white text-center py-4">
                        <h3 class="mb-0"><i class="fas fa-leaf me-2"></i>AgroSphere</h3>
                        <p class="mb-0 small opacity-75">Join the Rwandan Agricultural Network</p>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <h2 class="text-center mb-4 fw-bold text-dark">Create Your Account</h2>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="name" class="form-label fw-semibold">Full Name</label>
                                    <div class="input-group shadow-sm rounded">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-user"></i></span>
                                        <input type="text" name="name" class="form-control border-start-0 ps-0 <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $name; ?>" placeholder="John Doe" required>
                                    </div>
                                    <div class="invalid-feedback d-block"><?php echo $name_err; ?></div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label for="email" class="form-label fw-semibold">Email Address</label>
                                    <div class="input-group shadow-sm rounded">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-envelope"></i></span>
                                        <input type="email" name="email" class="form-control border-start-0 ps-0 <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>" placeholder="john@example.com" required>
                                    </div>
                                    <div class="invalid-feedback d-block"><?php echo $email_err; ?></div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="password" class="form-label fw-semibold">Password</label>
                                    <div class="input-group shadow-sm rounded">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-lock"></i></span>
                                        <input type="password" name="password" id="password" class="form-control border-start-0 border-end-0 ps-0 <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Min. 6 chars" required>
                                        <button class="btn btn-outline-secondary bg-white border-start-0 text-muted" type="button" id="togglePassword">
                                            <i class="fas fa-eye" id="eyeIcon"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback d-block"><?php echo $password_err; ?></div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label for="confirm_password" class="form-label fw-semibold">Confirm Password</label>
                                    <div class="input-group shadow-sm rounded">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-check-circle"></i></span>
                                        <input type="password" name="confirm_password" id="confirm_password" class="form-control border-start-0 border-end-0 ps-0 <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" placeholder="Repeat password" required>
                                        <button class="btn btn-outline-secondary bg-white border-start-0 text-muted" type="button" id="toggleConfirmPassword">
                                            <i class="fas fa-eye" id="eyeIconConfirm"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback d-block"><?php echo $confirm_password_err; ?></div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="role" class="form-label fw-semibold">I am a...</label>
                                <div class="input-group shadow-sm rounded">
                                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-briefcase"></i></span>
                                    <select name="role" class="form-select border-start-0 ps-0 <?php echo (!empty($role_err)) ? 'is-invalid' : ''; ?>" required>
                                        <option value="">Select your role</option>
                                        <option value="farmer" <?php echo ($role == 'farmer') ? 'selected' : ''; ?>>Farmer (Producer)</option>
                                        <option value="buyer" <?php echo ($role == 'buyer') ? 'selected' : ''; ?>>Buyer (Individual/Retailer)</option>
                                        <option value="transporter" <?php echo ($role == 'transporter') ? 'selected' : ''; ?>>Transporter (Logistics)</option>
                                    </select>
                                </div>
                                <div class="invalid-feedback d-block"><?php echo $role_err; ?></div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="phone" class="form-label fw-semibold">Phone (Optional)</label>
                                    <div class="input-group shadow-sm rounded">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-phone"></i></span>
                                        <input type="text" name="phone" class="form-control border-start-0 ps-0" value="<?php echo $phone; ?>" placeholder="+250 788 000 000">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label for="location" class="form-label fw-semibold">Location (Optional)</label>
                                    <div class="input-group shadow-sm rounded">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-map-marker-alt"></i></span>
                                        <input type="text" name="location" class="form-control border-start-0 ps-0" value="<?php echo $location; ?>" placeholder="e.g., Kigali, Rwanda">
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 mb-4">
                                <button type="submit" class="btn btn-success btn-lg fw-bold shadow-sm py-3">Register Now <i class="fas fa-user-plus ms-2"></i></button>
                            </div>
                            <p class="text-center text-muted mb-0">Already have an account? <a href="login.php" class="text-success fw-bold text-decoration-none">Login here</a></p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password Toggle
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        const eyeIcon = document.querySelector('#eyeIcon');

        togglePassword.addEventListener('click', function (e) {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            eyeIcon.classList.toggle('fa-eye');
            eyeIcon.classList.toggle('fa-eye-slash');
        });

        // Confirm Password Toggle
        const toggleConfirmPassword = document.querySelector('#toggleConfirmPassword');
        const confirmPassword = document.querySelector('#confirm_password');
        const eyeIconConfirm = document.querySelector('#eyeIconConfirm');

        toggleConfirmPassword.addEventListener('click', function (e) {
            const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPassword.setAttribute('type', type);
            eyeIconConfirm.classList.toggle('fa-eye');
            eyeIconConfirm.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>