<?php
// Include config file and functions
require_once "includes/db.php";
require_once "includes/functions.php";
error_log("login.php: db.php and functions.php included.");

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect if user is already logged in
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    error_log("login.php: User already logged in, role: " . $_SESSION["role"] . ". Redirecting.");
    redirect_by_role($_SESSION["role"]);
}

// Define variables and initialize with empty values
$email = $password = "";
$email_err = $password_err = $login_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log("login.php: Form submitted via POST.");

    // Check if email is empty
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter email.";
        error_log("login.php: Email field is empty.");
    } else {
        $email = sanitize_input($_POST["email"]);
        error_log("login.php: Email received: " . $email);
    }

    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
        error_log("login.php: Password field is empty.");
    } else {
        $password = trim($_POST["password"]);
        error_log("login.php: Password received (not logged for security).");
    }

    // Validate credentials
    if (empty($email_err) && empty($password_err)) {
        error_log("login.php: Email and password fields are valid. Preparing SQL statement.");
        // Prepare a select statement
        $sql = "SELECT id, name, email, password, role FROM users WHERE email = ?";

        if ($stmt = mysqli_prepare($link, $sql)) {
            error_log("login.php: SQL statement prepared successfully.");
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_email);

            // Set parameters
            $param_email = $email;

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                error_log("login.php: Prepared statement executed successfully.");
                // Store result
                mysqli_stmt_store_result($stmt);

                // Check if email exists, if yes then verify password
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    error_log("login.php: Email found in database.");
                    // Bind result variables
                    mysqli_stmt_bind_result($stmt, $id, $name, $email, $hashed_password, $role);
                    if (mysqli_stmt_fetch($stmt)) {
                        if (password_verify($password, $hashed_password)) {
                            error_log("login.php: Password verified successfully.");
                            // Password is correct, start a new session
                            session_start();

                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["name"] = $name;
                            $_SESSION["email"] = $email;
                            $_SESSION["role"] = $role;
                            error_log("login.php: Session variables set for user ID: " . $id . ", role: " . $role);

                            // Redirect user to appropriate dashboard page
                            error_log("login.php: Redirecting user with role: " . $role);
                            redirect_by_role($role);
                        } else {
                            error_log("login.php: Password verification failed for email: " . $email);
                            // Password is not valid, display a generic error message
                            $login_err = "Invalid email or password.";
                        }
                    }
                } else {
                    error_log("login.php: Email not found in database: " . $email);
                    // Email doesn't exist, display a generic error message
                    $login_err = "Invalid email or password.";
                }
            } else {
                error_log("login.php: ERROR: mysqli_stmt_execute failed: " . mysqli_stmt_error($stmt));
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        } else {
            error_log("login.php: ERROR: mysqli_prepare failed: " . mysqli_error($link));
        }
    }

    // Close connection
    mysqli_close($link);
    error_log("login.php: Database connection closed.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AgroSphere MarketLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container login-container py-5">
        <div class="row justify-content-center w-100">
            <div class="col-md-8 col-lg-5">
                <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
                    <div class="card-header bg-success text-white text-center py-4">
                        <h3 class="mb-0"><i class="fas fa-leaf me-2"></i>AgroSphere</h3>
                        <p class="mb-0 small opacity-75">Connecting Rwanda's Agriculture</p>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <h2 class="text-center mb-4 fw-bold text-dark">Welcome Back!</h2>
                        <?php
                        if (!empty($login_err)) {
                            echo '<div class="alert alert-danger border-0 shadow-sm mb-4">' . $login_err . '</div>';
                        }
                        ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="mb-4">
                                <label for="email" class="form-label fw-semibold">Email Address</label>
                                <div class="input-group shadow-sm rounded">
                                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-envelope"></i></span>
                                    <input type="email" name="email" class="form-control border-start-0 ps-0 <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>" placeholder="Enter your email" required>
                                </div>
                                <div class="invalid-feedback d-block"><?php echo $email_err; ?></div>
                            </div>
                            <div class="mb-4">
                                <label for="password" class="form-label fw-semibold">Password</label>
                                <div class="input-group shadow-sm rounded">
                                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="password" id="password" class="form-control border-start-0 border-end-0 ps-0 <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Enter your password" required>
                                    <button class="btn btn-outline-secondary bg-white border-start-0 text-muted" type="button" id="togglePassword">
                                        <i class="fas fa-eye" id="eyeIcon"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback d-block"><?php echo $password_err; ?></div>
                            </div>
                            <div class="d-grid gap-2 mb-4">
                                <button type="submit" class="btn btn-success btn-lg fw-bold shadow-sm py-3">Login <i class="fas fa-sign-in-alt ms-2"></i></button>
                            </div>
                            <p class="text-center text-muted mb-0">Don't have an account? <a href="register.php" class="text-success fw-bold text-decoration-none">Sign up now</a></p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        const eyeIcon = document.querySelector('#eyeIcon');

        togglePassword.addEventListener('click', function (e) {
            // toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            // toggle the eye / eye slash icon
            eyeIcon.classList.toggle('fa-eye');
            eyeIcon.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>