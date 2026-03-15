<?php
// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to redirect user based on role
function redirect_by_role($role) {
    $current_dir = basename(getcwd());
    $prefix = ($current_dir == 'dashboard') ? '' : 'dashboard/';
    
    switch ($role) {
        case 'admin':
            header("location: {$prefix}admin.php");
            break;
        case 'farmer':
            header("location: {$prefix}farmer.php");
            break;
        case 'buyer':
            header("location: {$prefix}buyer.php");
            break;
        case 'transporter':
            header("location: {$prefix}transporter.php");
            break;
        default:
            $login_path = ($current_dir == 'dashboard') ? '../login.php' : 'login.php';
            header("location: $login_path");
            break;
    }
    exit();
}

// Function to check if user is logged in
function is_logged_in() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
}

// Function to check user role
function check_user_role($required_role) {
    if (!is_logged_in() || $_SESSION["role"] !== $required_role) {
        $current_dir = basename(getcwd());
        $login_path = ($current_dir == 'dashboard') ? '../login.php' : 'login.php';
        header("location: $login_path");
        exit();
    }
}

// Function to check multiple roles
function check_multiple_roles($allowed_roles) {
    if (!is_logged_in() || !in_array($_SESSION["role"], $allowed_roles)) {
        header("location: login.php");
        exit();
    }
}

?>