<?php
/**
 * MTN Payment Integration API
 * Handles payment processing with MTN Mobile Money
 */

require_once "../includes/db.php";
require_once "../includes/security.php";

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION["id"];
$method = $_SERVER["REQUEST_METHOD"];

// MTN API Configuration
define('MTN_API_KEY', 'your_mtn_api_key_here');
define('MTN_API_SECRET', 'your_mtn_api_secret_here');
define('MTN_API_URL', 'https://mtn-api-production.azure-api.net');
define('MTN_SUBSCRIPTION_KEY', 'your_subscription_key_here');

/**
 * Initiate Payment
 */
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'initiate') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (empty($data['order_id']) || empty($data['amount']) || empty($data['phone'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }
    
    $order_id = (int)$data['order_id'];
    $amount = (float)$data['amount'];
    $phone = preg_replace('/\D/', '', $data['phone']);
    
    // Normalize phone number (remove +250 and leading 0)
    if (substr($phone, 0, 3) === '250') {
        $phone = '250' . substr($phone, -9);
    } elseif ($phone[0] === '0') {
        $phone = '250' . substr($phone, 1);
    } else {
        $phone = '250' . $phone;
    }
    
    // Verify order belongs to user
    $verify_sql = "SELECT id FROM orders WHERE id = ? AND buyer_id = ?";
    if ($stmt = mysqli_prepare($link, $verify_sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) == 0) {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit;
        }
        mysqli_stmt_close($stmt);
    }
    
    // Create payment record
    $transaction_id = "TXN-" . time() . "-" . rand(1000, 9999);
    $sql = "INSERT INTO payments (order_id, user_id, amount, currency, payment_method, status, transaction_id) 
            VALUES (?, ?, ?, 'RWF', 'mtn_money', 'pending', ?)";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iids", $order_id, $user_id, $amount, $transaction_id);
        mysqli_stmt_execute($stmt);
        $payment_id = mysqli_insert_id($link);
        mysqli_stmt_close($stmt);
    }
    
    // Call MTN API
    $mtn_response = initiate_mtn_payment($phone, $amount, $transaction_id);
    
    if ($mtn_response['success']) {
        log_activity($link, $user_id, 'PAYMENT_INITIATED', 'Payment initiated: ' . $transaction_id);
        echo json_encode([
            'success' => true,
            'message' => 'Payment initiated',
            'payment_id' => $payment_id,
            'transaction_id' => $transaction_id,
            'mtn_reference' => $mtn_response['reference'] ?? null
        ]);
    } else {
        // Update payment status to failed
        $update_sql = "UPDATE payments SET status = 'failed' WHERE id = ?";
        if ($update_stmt = mysqli_prepare($link, $update_sql)) {
            mysqli_stmt_bind_param($update_stmt, "i", $payment_id);
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);
        }
        
        log_security_event($link, 'PAYMENT_FAILED', 'MTN payment initiation failed: ' . $mtn_response['error'], 'warning', $user_id);
        echo json_encode(['success' => false, 'message' => $mtn_response['error']]);
    }
}

/**
 * Check Payment Status
 */
elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check_status') {
    $transaction_id = sanitize_input($_GET['transaction_id'] ?? '');
    
    if (empty($transaction_id)) {
        echo json_encode(['success' => false, 'message' => 'Missing transaction ID']);
        exit;
    }
    
    $sql = "SELECT p.id, p.status, p.amount, o.id as order_id FROM payments p 
            LEFT JOIN orders o ON p.order_id = o.id 
            WHERE p.transaction_id = ? AND p.user_id = ?";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "si", $transaction_id, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $payment_id, $status, $amount, $order_id);
        
        if (mysqli_stmt_fetch($stmt)) {
            // Check with MTN API
            $mtn_status = check_mtn_payment_status($transaction_id);
            
            if ($mtn_status['success']) {
                // Update payment status
                $new_status = $mtn_status['status'] === 'completed' ? 'completed' : $status;
                $update_sql = "UPDATE payments SET status = ? WHERE id = ?";
                if ($update_stmt = mysqli_prepare($link, $update_sql)) {
                    mysqli_stmt_bind_param($update_stmt, "si", $new_status, $payment_id);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);
                    
                    // If payment completed, update order status
                    if ($new_status === 'completed') {
                        $order_update = "UPDATE orders SET status = 'accepted' WHERE id = ?";
                        if ($order_stmt = mysqli_prepare($link, $order_update)) {
                            mysqli_stmt_bind_param($order_stmt, "i", $order_id);
                            mysqli_stmt_execute($order_stmt);
                            mysqli_stmt_close($order_stmt);
                        }
                        log_activity($link, $user_id, 'PAYMENT_COMPLETED', 'Payment completed: ' . $transaction_id);
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'status' => $new_status ?? $status,
                'amount' => $amount,
                'transaction_id' => $transaction_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Payment not found']);
        }
        mysqli_stmt_close($stmt);
    }
}

/**
 * Get Payment History
 */
elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'history') {
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = (int)($_GET['offset'] ?? 0);
    
    $sql = "SELECT p.*, o.quantity, c.crop_name FROM payments p 
            LEFT JOIN orders o ON p.order_id = o.id 
            LEFT JOIN crops c ON o.crop_id = c.id 
            WHERE p.user_id = ? 
            ORDER BY p.created_at DESC 
            LIMIT ? OFFSET ?";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iii", $user_id, $limit, $offset);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $payments = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $payments[] = $row;
        }
        
        echo json_encode(['success' => true, 'payments' => $payments]);
        mysqli_stmt_close($stmt);
    }
}

else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

/**
 * Initiate MTN Payment
 */
function initiate_mtn_payment($phone, $amount, $transaction_id) {
    $payload = [
        'amount' => $amount,
        'currency' => 'RWF',
        'externalId' => $transaction_id,
        'payer' => [
            'partyIdType' => 'MSISDN',
            'partyId' => $phone
        ],
        'payerMessage' => 'AgroSphere Payment',
        'payeeNote' => 'Payment for agricultural products'
    ];
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => MTN_API_URL . '/collection/v1_0/requesttopay',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => [
            'X-Reference-Id: ' . $transaction_id,
            'X-Callback-Url: https://' . $_SERVER['HTTP_HOST'] . '/api/payment_callback.php',
            'Content-Type: application/json',
            'Ocp-Apim-Subscription-Key: ' . MTN_SUBSCRIPTION_KEY,
            'Authorization: Bearer ' . get_mtn_bearer_token()
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($http_code === 202 || $http_code === 200) {
        return ['success' => true, 'reference' => $transaction_id];
    } else {
        return ['success' => false, 'error' => 'Failed to initiate payment. Please try again later.'];
    }
}

/**
 * Check MTN Payment Status
 */
function check_mtn_payment_status($transaction_id) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => MTN_API_URL . '/collection/v1_0/requesttopay/' . $transaction_id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => [
            'Ocp-Apim-Subscription-Key: ' . MTN_SUBSCRIPTION_KEY,
            'Authorization: Bearer ' . get_mtn_bearer_token()
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        $status = $data['status'] === 'SUCCESSFUL' ? 'completed' : ($data['status'] === 'FAILED' ? 'failed' : 'pending');
        return ['success' => true, 'status' => $status];
    } else {
        return ['success' => false, 'error' => 'Could not verify payment status'];
    }
}

/**
 * Get MTN Bearer Token
 */
function get_mtn_bearer_token() {
    // In production, implement OAuth2 flow to get bearer token
    // For now, return a placeholder
    return 'your_bearer_token_here';
}

?>
