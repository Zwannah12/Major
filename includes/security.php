<?php
/**
 * Security Module - CSRF, Rate Limiting, Email Verification, Logging
 */

// Initialize session if needed
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate CSRF Token
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF Token from Session
 */
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF Token
 */
function validate_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Rate Limiting - Check if user/IP has exceeded action limit
 * @param string $action Action identifier (e.g., 'login', 'registration')
 * @param int $limit Maximum attempts allowed
 * @param int $window Time window in seconds
 * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => int]
 */
function check_rate_limit($action, $limit = 5, $window = 300) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    $ip = get_client_ip();
    $rate_limit_key = "rate_limit_{$action}_{$ip}";
    
    if (!isset($_SESSION[$rate_limit_key])) {
        $_SESSION[$rate_limit_key] = [
            'attempts' => 0,
            'first_attempt' => time(),
            'reset_time' => time() + $window
        ];
    }

    $rate_data = &$_SESSION[$rate_limit_key];
    $current_time = time();

    // Check if window has expired
    if ($current_time > $rate_data['reset_time']) {
        $rate_data['attempts'] = 0;
        $rate_data['first_attempt'] = $current_time;
        $rate_data['reset_time'] = $current_time + $window;
    }

    $rate_data['attempts']++;
    $allowed = $rate_data['attempts'] <= $limit;
    $remaining = max(0, $limit - $rate_data['attempts']);
    $reset_time = $rate_data['reset_time'];

    return [
        'allowed' => $allowed,
        'attempts' => $rate_data['attempts'],
        'limit' => $limit,
        'remaining' => $remaining,
        'reset_time' => $reset_time,
        'window' => $window
    ];
}

/**
 * Reset Rate Limit for Action
 */
function reset_rate_limit($action) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    $ip = get_client_ip();
    $rate_limit_key = "rate_limit_{$action}_{$ip}";
    unset($_SESSION[$rate_limit_key]);
}

/**
 * Get Client IP Address
 */
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED'];
    } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
        $ip = $_SERVER['HTTP_FORWARDED'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    // In case of multiple IPs, take the first one
    if (strpos($ip, ',') !== false) {
        $ips = explode(',', $ip);
        $ip = trim($ips[0]);
    }
    
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Generate Email Verification Token
 */
function generate_verification_token($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Log User Activity
 */
function log_activity($link, $user_id, $action, $description = '', $ip_address = '') {
    $ip_address = $ip_address ?: get_client_ip();
    $timestamp = date('Y-m-d H:i:s');
    
    $sql = "INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?)";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        $null_user = $user_id ?? NULL;
        mysqli_stmt_bind_param($stmt, "issss", $null_user, $action, $description, $ip_address, $timestamp);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    return false;
}

/**
 * Log Security Event
 */
function log_security_event($link, $event_type, $description = '', $severity = 'info', $user_id = null, $ip_address = '') {
    $ip_address = $ip_address ?: get_client_ip();
    $timestamp = date('Y-m-d H:i:s');
    
    $sql = "INSERT INTO security_logs (event_type, description, severity, user_id, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        $null_user = $user_id ?? NULL;
        mysqli_stmt_bind_param($stmt, "sssiss", $event_type, $description, $severity, $null_user, $ip_address, $timestamp);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    return false;
}

/**
 * Validate Email Format
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate Password Strength
 * Returns array with strength level and requirements met
 */
function validate_password_strength($password) {
    $requirements = [
        'length' => strlen($password) >= 8,
        'uppercase' => preg_match('/[A-Z]/', $password),
        'lowercase' => preg_match('/[a-z]/', $password),
        'numbers' => preg_match('/[0-9]/', $password),
        'special' => preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\\/]/', $password)
    ];
    
    $met = array_sum($requirements);
    $strength = match ($met) {
        0, 1 => 'weak',
        2, 3 => 'medium',
        4, 5 => 'strong',
        default => 'weak'
    };
    
    return [
        'strength' => $strength,
        'score' => $met,
        'requirements' => $requirements,
        'is_valid' => $strength !== 'weak'
    ];
}

/**
 * Hash Password with Bcrypt
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify Password
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate Secure Random String
 */
function generate_random_string($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Sanitize Email
 */
function sanitize_email($email) {
    return filter_var($email, FILTER_SANITIZE_EMAIL);
}

/**
 * Check if email exists in database
 */
function email_exists($link, $email) {
    $email = sanitize_email($email);
    $sql = "SELECT id FROM users WHERE email = ?";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        $exists = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);
        return $exists;
    }
    return false;
}

/**
 * Get user by email
 */
function get_user_by_email($link, $email) {
    $email = sanitize_email($email);
    $sql = "SELECT id, name, email, password, role, verified FROM users WHERE email = ?";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) == 1) {
            mysqli_stmt_bind_result($stmt, $id, $name, $email, $password, $role, $verified);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            return [
                'id' => $id,
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => $role,
                'verified' => $verified
            ];
        }
        mysqli_stmt_close($stmt);
    }
    return null;
}

/**
 * Two-Factor Authentication - Generate 2FA Token
 */
function generate_2fa_token() {
    return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Check if 2FA is enabled for user
 */
function is_2fa_enabled($link, $user_id) {
    $sql = "SELECT two_factor_enabled FROM users WHERE id = ?";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $two_factor_enabled);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        return $two_factor_enabled === 1;
    }
    return false;
}

/**
 * Verify 2FA Token
 */
function verify_2fa_token($link, $user_id, $token) {
    $sql = "SELECT two_factor_token, two_factor_token_expires FROM users WHERE id = ?";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $stored_token, $expires);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        
        if ($stored_token && time() < strtotime($expires)) {
            return hash_equals($stored_token, $token);
        }
    }
    return false;
}

?>
