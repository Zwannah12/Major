<?php
/**
 * Real-Time Delivery Tracking API
 * Handles GPS tracking for deliveries
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
$user_role = $_SESSION["role"];
$method = $_SERVER["REQUEST_METHOD"];

/**
 * Update Delivery Location (GPS)
 */
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'update_location') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (empty($data['delivery_id']) || empty($data['latitude']) || empty($data['longitude'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }
    
    $delivery_id = (int)$data['delivery_id'];
    $latitude = (float)$data['latitude'];
    $longitude = (float)$data['longitude'];
    $notes = sanitize_input($data['notes'] ?? '');
    
    // Validate transporter is assigned to this delivery
    $verify_sql = "SELECT id FROM deliveries WHERE id = ? AND transporter_id = ?";
    if ($stmt = mysqli_prepare($link, $verify_sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $delivery_id, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) == 0 && $user_role !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        mysqli_stmt_close($stmt);
    }
    
    // Update delivery current location
    $update_sql = "UPDATE deliveries SET current_latitude = ?, current_longitude = ? WHERE id = ?";
    if ($update_stmt = mysqli_prepare($link, $update_sql)) {
        mysqli_stmt_bind_param($update_stmt, "ddi", $latitude, $longitude, $delivery_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
    }
    
    // Add tracking record
    $track_sql = "INSERT INTO delivery_tracking (delivery_id, status, latitude, longitude, notes) 
                  VALUES (?, ?, ?, ?, ?)";
    
    if ($track_stmt = mysqli_prepare($link, $track_sql)) {
        $current_status = "in_transit";
        mysqli_stmt_bind_param($track_stmt, "issds", $delivery_id, $current_status, $latitude, $longitude, $notes);
        
        if (mysqli_stmt_execute($track_stmt)) {
            log_activity($link, $user_id, 'DELIVERY_LOCATION_UPDATED', "Updated delivery {$delivery_id} location");
            echo json_encode(['success' => true, 'message' => 'Location updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update location']);
        }
        mysqli_stmt_close($track_stmt);
    }
}

/**
 * Get Delivery Tracking History
 */
elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'history') {
    $delivery_id = (int)($_GET['delivery_id'] ?? 0);
    
    if (empty($delivery_id)) {
        echo json_encode(['success' => false, 'message' => 'Delivery ID required']);
        exit;
    }
    
    // Verify user has access to delivery
    $verify_sql = "SELECT d.id FROM deliveries d 
                   INNER JOIN orders o ON d.order_id = o.id 
                   WHERE d.id = ? AND (d.transporter_id = ? OR o.buyer_id = ? OR o.farmer_id = ?)";
    
    if ($stmt = mysqli_prepare($link, $verify_sql)) {
        mysqli_stmt_bind_param($stmt, "iiii", $delivery_id, $user_id, $user_id, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) == 0 && $user_role !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        mysqli_stmt_close($stmt);
    }
    
    // Get tracking history
    $sql = "SELECT * FROM delivery_tracking WHERE delivery_id = ? ORDER BY timestamp ASC";
    
    if ($track_stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($track_stmt, "i", $delivery_id);
        mysqli_stmt_execute($track_stmt);
        $result = mysqli_stmt_get_result($track_stmt);
        
        $history = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $history[] = $row;
        }
        
        echo json_encode(['success' => true, 'history' => $history]);
        mysqli_stmt_close($track_stmt);
    }
}

/**
 * Get Delivery Status
 */
elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'status') {
    $delivery_id = (int)($_GET['delivery_id'] ?? 0);
    
    if (empty($delivery_id)) {
        echo json_encode(['success' => false, 'message' => 'Delivery ID required']);
        exit;
    }
    
    $sql = "SELECT d.*, u.name as transporter_name, u.phone as transporter_phone, 
                   o.id as order_id, c.crop_name, c.quantity as crop_quantity
            FROM deliveries d
            LEFT JOIN users u ON d.transporter_id = u.id
            LEFT JOIN orders o ON d.order_id = o.id
            LEFT JOIN crops c ON o.crop_id = c.id
            WHERE d.id = ?";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $delivery_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($delivery = mysqli_fetch_assoc($result)) {
            // Calculate distance to destination (simplified - requires actual GPS coordinates)
            echo json_encode(['success' => true, 'delivery' => $delivery]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Delivery not found']);
        }
        mysqli_stmt_close($stmt);
    }
}

/**
 * Update Delivery Status
 */
elseif ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'update_status') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (empty($data['delivery_id']) || empty($data['status'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }
    
    $delivery_id = (int)$data['delivery_id'];
    $new_status = sanitize_input($data['status']);
    $notes = sanitize_input($data['notes'] ?? '');
    
    $valid_statuses = ['pending', 'in_transit', 'arrived', 'completed', 'failed', 'cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    // Verify transporter
    $verify_sql = "SELECT id FROM deliveries WHERE id = ? AND transporter_id = ?";
    if ($stmt = mysqli_prepare($link, $verify_sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $delivery_id, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) == 0 && $user_role !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        mysqli_stmt_close($stmt);
    }
    
    // Update status
    $update_sql = "UPDATE deliveries SET status = ?, updated_at = NOW() WHERE id = ?";
    if ($update_stmt = mysqli_prepare($link, $update_sql)) {
        mysqli_stmt_bind_param($update_stmt, "si", $new_status, $delivery_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
    }
    
    // Add tracking record
    $track_sql = "INSERT INTO delivery_tracking (delivery_id, status, notes) VALUES (?, ?, ?)";
    if ($track_stmt = mysqli_prepare($link, $track_sql)) {
        mysqli_stmt_bind_param($track_stmt, "iss", $delivery_id, $new_status, $notes);
        mysqli_stmt_execute($track_stmt);
        mysqli_stmt_close($track_stmt);
    }
    
    // If completed, update order status
    if ($new_status === 'completed') {
        $order_sql = "UPDATE orders SET status = 'delivered' WHERE id = (SELECT order_id FROM deliveries WHERE id = ?)";
        if ($order_stmt = mysqli_prepare($link, $order_sql)) {
            mysqli_stmt_bind_param($order_stmt, "i", $delivery_id);
            mysqli_stmt_execute($order_stmt);
            mysqli_stmt_close($order_stmt);
        }
    }
    
    log_activity($link, $user_id, 'DELIVERY_STATUS_UPDATED', "Updated delivery {$delivery_id} status to {$new_status}");
    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
}

else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

?>
