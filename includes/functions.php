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
    $target = '';
    
    switch ($role) {
        case 'admin':
            $target = "{$prefix}admin.php";
            break;
        case 'farmer':
            $target = "{$prefix}farmer.php";
            break;
        case 'buyer':
            $target = "{$prefix}buyer.php";
            break;
        case 'transporter':
            $target = "{$prefix}transporter.php";
            break;
        default:
            $login_path = ($current_dir == 'dashboard') ? '../login.php' : 'login.php';
            $target = $login_path;
            break;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    header("Location: {$target}");
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

// Function to update login metadata when the database has those optional columns
function update_last_login($link, $user_id) {
    $has_last_login = mysqli_query($link, "SHOW COLUMNS FROM users LIKE 'last_login'");
    $has_last_login_ip = mysqli_query($link, "SHOW COLUMNS FROM users LIKE 'last_login_ip'");

    if (!$has_last_login || !$has_last_login_ip || mysqli_num_rows($has_last_login) === 0 || mysqli_num_rows($has_last_login_ip) === 0) {
        return false;
    }

    $last_login = date('Y-m-d H:i:s');
    $ip = function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '');
    $login_sql = "UPDATE users SET last_login = ?, last_login_ip = ? WHERE id = ?";

    if ($login_stmt = mysqli_prepare($link, $login_sql)) {
        mysqli_stmt_bind_param($login_stmt, "ssi", $last_login, $ip, $user_id);
        $updated = mysqli_stmt_execute($login_stmt);
        mysqli_stmt_close($login_stmt);
        return $updated;
    }

    return false;
}

// Function to ensure public contact messages can be stored without a logged-in user
function ensure_public_messages_table($link) {
    $sql = "CREATE TABLE IF NOT EXISTS public_messages (
        id INT(11) NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        admin_reply TEXT DEFAULT NULL,
        status ENUM('unread','read','replied') DEFAULT 'unread',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        replied_at TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    return mysqli_query($link, $sql);
}

// Function to create a notification
function create_notification($link, $user_id, $message, $link_url = '#') {
    $sql = "INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iss", $user_id, $message, $link_url);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return true;
    }
    return false;
}

?>
