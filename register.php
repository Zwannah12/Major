<?php
// Include config file and functions
require_once "includes/db.php";
require_once "includes/functions.php";
require_once "includes/security.php";
require_once "includes/districts.php";

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get list of districts
$provinces = get_all_provinces();
$districts = get_all_districts();

// Redirect if user is already logged in
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    redirect_by_role($_SESSION["role"]);
}

// Define variables and initialize with empty values
$name = $email = $password = $confirm_password = $role = $phone = $province = $location = "";
$name_err = $email_err = $password_err = $confirm_password_err = $role_err = $password_strength_err = "";
$csrf_token = get_csrf_token();
$registration_success = false;

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (empty($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $email_err = "Security token validation failed. Please try again.";
        log_security_event($link, 'CSRF_VALIDATION_FAILED', 'Invalid CSRF token on registration attempt');
    } else {
        // Check rate limiting
        $rate_limit = check_rate_limit('registration', 3, 3600);
        
        if (!$rate_limit['allowed']) {
            $email_err = "Too many registration attempts from this IP. Please try again later.";
            log_security_event($link, 'RATE_LIMIT_EXCEEDED', 'Registration rate limit exceeded from IP: ' . get_client_ip(), 'warning');
        } else {
            $phone = sanitize_input($_POST["phone"] ?? "");
            $province = sanitize_input($_POST["province"] ?? "");
            $location = sanitize_input($_POST["location"] ?? "");

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
                $email_input = trim($_POST["email"]);
                
                // Validate email format
                if (!is_valid_email($email_input)) {
                    $email_err = "Please enter a valid email address.";
                } else if (email_exists($link, $email_input)) {
                    $email_err = "This email is already registered.";
                    log_security_event($link, 'REGISTRATION_DUPLICATE_EMAIL', 'Registration attempt with existing email: ' . $email_input, 'info');
                } else {
                    $email = sanitize_email($email_input);
                }
            }

            // Validate password strength
            if (empty(trim($_POST["password"]))) {
                $password_err = "Please enter a password.";
            } else {
                $password = trim($_POST["password"]);
                $pwd_strength = validate_password_strength($password);
                
                if (!$pwd_strength['is_valid']) {
                    $password_err = "Password must be at least 8 characters with uppercase, lowercase, numbers, and special characters.";
                    $password_strength_err = "Strength: " . ucfirst($pwd_strength['strength']);
                } else {
                    $password_strength_err = "Strength: <span style='color: green;'>" . ucfirst($pwd_strength['strength']) . "</span>";
                }
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
                if (!in_array($role, ['farmer', 'buyer', 'transporter'])) {
                    $role_err = "Invalid role selected.";
                }
            }

            // Check input errors before inserting in database
            if (empty($name_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err) && empty($role_err)) {

                // Prepare an insert statement
                $sql = "INSERT INTO users (name, email, password, role, phone, location, is_verified) 
                        VALUES (?, ?, ?, ?, ?, ?, TRUE)";

                if ($stmt = mysqli_prepare($link, $sql)) {
                    // Bind variables to the prepared statement as parameters
                    $param_password = hash_password($password);
                    mysqli_stmt_bind_param($stmt, "ssssss", $name, $email, $param_password, $role, $phone, $location);

                    // Attempt to execute the prepared statement
                    if (mysqli_stmt_execute($stmt)) {
                        $user_id = mysqli_insert_id($link);
                        
                        // Generate email verification token
                        $verification_token = generate_verification_token();
                        $expires_at = date('Y-m-d H:i:s', time() + 86400); // 24 hours
                        
                        $verify_sql = "INSERT INTO email_verifications (user_id, email, token, expires_at) 
                                     VALUES (?, ?, ?, ?)";
                        
                        if ($verify_stmt = mysqli_prepare($link, $verify_sql)) {
                            mysqli_stmt_bind_param($verify_stmt, "isss", $user_id, $email, $verification_token, $expires_at);
                            mysqli_stmt_execute($verify_stmt);
                            mysqli_stmt_close($verify_stmt);
                        }
                        
                        // Log registration
                        log_activity($link, $user_id, 'REGISTRATION_COMPLETED', 'User registered as ' . $role);
                        log_security_event($link, 'USER_REGISTERED', 'New user registration: ' . $email, 'info', $user_id);
                        
                        $registration_success = true;
                        
                        // In production: Send email with verification link
                        // $verification_link = "http://" . $_SERVER['HTTP_HOST'] . "/verify_email.php?token=" . $verification_token;
                        // send_verification_email($email, $name, $verification_link);
                    } else {
                        $email_err = "Something went wrong. Please try again later.";
                        log_security_event($link, 'REGISTRATION_ERROR', 'Database error during registration: ' . mysqli_error($link), 'error');
                    }

                    // Close statement
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }

    // Close connection
    if (!$registration_success) {
        mysqli_close($link);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - AgroSphere MarketLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=modern-ui-2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container register-container py-5">
        <div class="row mb-2">
            <div class="col-12 text-center">
                <a href="index.php" class="btn btn-link text-white text-decoration-none fw-bold">
                    <i class="fas fa-arrow-left me-2"></i> Back to Home
                </a>
            </div>
        </div>
        <div class="row justify-content-center w-100">
            <div class="col-md-10 col-lg-6">
                <div class="card shadow-lg border-0 rounded-4 overflow-hidden my-4">
                    <div class="card-header bg-success text-white text-center py-4">
                        <h3 class="mb-0"><i class="fas fa-leaf me-2"></i>AgroSphere</h3>
                        <p class="mb-0 small opacity-75">Join the Rwandan Agricultural Network</p>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <?php if ($registration_success): ?>
                            <div class="alert alert-success border-0 mb-4">
                                <h4 class="alert-heading"><i class="fas fa-check-circle"></i> Registration Successful!</h4>
                                <p class="mb-2">Welcome, <strong><?php echo htmlspecialchars($name); ?></strong>!</p>
                                <p class="mb-3">We've sent a verification email to <strong><?php echo htmlspecialchars($email); ?></strong></p>
                                <p class="mb-0">Please check your email and click the verification link to activate your account. Then you can <a href="login.php" class="fw-bold">login here</a>.</p>
                            </div>
                            <div class="text-center">
                                <a href="index.php" class="btn btn-success btn-lg fw-bold">Back to Home</a>
                            </div>
                        <?php else: ?>
                            <h2 class="text-center mb-4 fw-bold text-dark">Create Your Account</h2>
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                <!-- CSRF Token -->
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <label for="name" class="form-label fw-semibold">Full Name</label>
                                        <div class="input-group shadow-sm rounded">
                                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-user"></i></span>
                                            <input type="text" name="name" class="form-control border-start-0 ps-0 <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($name); ?>" placeholder="John Doe" required>
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
                                        <input type="password" name="password" id="password" class="form-control border-start-0 border-end-0 ps-0 <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" 
                                               placeholder="Min. 8 chars with uppercase, lowercase, numbers, and special chars" required onkeyup="checkPasswordStrength()">
                                        <button class="btn btn-outline-secondary bg-white border-start-0 text-muted" type="button" id="togglePassword">
                                            <i class="fas fa-eye" id="eyeIcon"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback d-block"><?php echo $password_err; ?></div>
                                    <small id="passwordStrength" class="d-block mt-2 text-muted">Password Strength: <span id="strengthLevel">-</span></small>
                                    <small class="d-block text-muted" style="font-size: 0.75rem;">
                                        Required: <span id="req1" style="color: red;"><i class="fas fa-times"></i> 8+ chars</span>,
                                        <span id="req2" style="color: red;"><i class="fas fa-times"></i> Uppercase</span>,
                                        <span id="req3" style="color: red;"><i class="fas fa-times"></i> Numbers</span>,
                                        <span id="req4" style="color: red;"><i class="fas fa-times"></i> Special</span>
                                    </small>
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
                                    <label for="province" class="form-label fw-semibold">Province</label>
                                    <div class="input-group shadow-sm rounded">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-map-marker-alt"></i></span>
                                        <select name="province" id="province" class="form-select border-start-0 ps-0">
                                            <option value="">Select your province...</option>
                                            <?php foreach ($provinces as $prov): ?>
                                                <option value="<?php echo htmlspecialchars($prov); ?>" <?php echo ($province == $prov || (!$province && get_district_province($location) == $prov)) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($prov); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label for="location" class="form-label fw-semibold">District</label>
                                    <div class="input-group shadow-sm rounded">
                                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-location-dot"></i></span>
                                        <select name="location" id="location" class="form-select border-start-0 ps-0">
                                            <option value="">Select a province first...</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 mb-4">
                                <button type="submit" class="btn btn-success btn-lg fw-bold shadow-sm py-3">Register Now <i class="fas fa-user-plus ms-2"></i></button>
                            </div>
                            <p class="text-center text-muted mb-0">Already have an account? <a href="login.php" class="text-success fw-bold text-decoration-none">Login here</a></p>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const provinceSelect = document.querySelector('#province');
        const districtSelect = document.querySelector('#location');
        const selectedDistrict = <?php echo json_encode($location); ?>;
        const provinceDistricts = <?php echo json_encode(get_province_district_data()); ?>;

        function renderDistrictOptions(districts, valueToSelect = '') {
            districtSelect.innerHTML = '<option value="">Select your district...</option>';
            districts.forEach(function(district) {
                const option = document.createElement('option');
                option.value = district;
                option.textContent = district;
                option.selected = district === valueToSelect;
                districtSelect.appendChild(option);
            });
        }

        function loadDistrictsForProvince(province, valueToSelect = '') {
            if (!province) {
                districtSelect.innerHTML = '<option value="">Select a province first...</option>';
                return;
            }

            districtSelect.innerHTML = '<option value="">Loading districts...</option>';
            fetch(`api/weather.php?province=${encodeURIComponent(province)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderDistrictOptions(data.districts, valueToSelect);
                    } else {
                        renderDistrictOptions(Object.keys(provinceDistricts[province] || {}), valueToSelect);
                    }
                })
                .catch(() => {
                    renderDistrictOptions(Object.keys(provinceDistricts[province] || {}), valueToSelect);
                });
        }

        provinceSelect.addEventListener('change', function() {
            loadDistrictsForProvince(this.value);
        });

        if (provinceSelect.value) {
            loadDistrictsForProvince(provinceSelect.value, selectedDistrict);
        }

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

        // Password Strength Checker
        function checkPasswordStrength() {
            const password = document.querySelector('#password').value;
            const strengthLevel = document.querySelector('#strengthLevel');
            const req1 = document.querySelector('#req1');
            const req2 = document.querySelector('#req2');
            const req3 = document.querySelector('#req3');
            const req4 = document.querySelector('#req4');
            
            let strength = 0;
            
            // Check length
            if (password.length >= 8) {
                req1.style.color = 'green';
                req1.innerHTML = '<i class="fas fa-check"></i> 8+ chars';
                strength++;
            } else {
                req1.style.color = 'red';
                req1.innerHTML = '<i class="fas fa-times"></i> 8+ chars';
            }
            
            // Check uppercase
            if (/[A-Z]/.test(password)) {
                req2.style.color = 'green';
                req2.innerHTML = '<i class="fas fa-check"></i> Uppercase';
                strength++;
            } else {
                req2.style.color = 'red';
                req2.innerHTML = '<i class="fas fa-times"></i> Uppercase';
            }
            
            // Check numbers
            if (/[0-9]/.test(password)) {
                req3.style.color = 'green';
                req3.innerHTML = '<i class="fas fa-check"></i> Numbers';
                strength++;
            } else {
                req3.style.color = 'red';
                req3.innerHTML = '<i class="fas fa-times"></i> Numbers';
            }
            
            // Check special characters
            if (/[!@#$%^&*()_+\-=\[\]{};:'"",.<>?\\/]/.test(password)) {
                req4.style.color = 'green';
                req4.innerHTML = '<i class="fas fa-check"></i> Special';
                strength++;
            } else {
                req4.style.color = 'red';
                req4.innerHTML = '<i class="fas fa-times"></i> Special';
            }
            
            // Display strength
            if (password === '') {
                strengthLevel.textContent = '-';
                strengthLevel.style.color = 'gray';
            } else if (strength <= 1) {
                strengthLevel.textContent = 'Weak';
                strengthLevel.style.color = 'red';
            } else if (strength <= 2) {
                strengthLevel.textContent = 'Fair';
                strengthLevel.style.color = 'orange';
            } else if (strength <= 3) {
                strengthLevel.textContent = 'Good';
                strengthLevel.style.color = 'blue';
            } else {
                strengthLevel.textContent = 'Strong';
                strengthLevel.style.color = 'green';
            }
        }
    </script>
    <script src="assets/js/main.js?v=modern-ui-2"></script>
</body>
</html>
