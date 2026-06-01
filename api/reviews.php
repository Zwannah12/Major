<?php
/**
 * Reviews & Ratings API
 * Handles product and seller reviews with rating system
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

/**
 * Create Review
 */
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'create') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $required = ['reviewed_user_id', 'rating', 'review_type'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            echo json_encode(['success' => false, 'message' => "Missing {$field}"]);
            exit;
        }
    }
    
    $reviewed_user_id = (int)$data['reviewed_user_id'];
    $rating = (int)$data['rating'];
    $title = sanitize_input($data['title'] ?? '');
    $comment = sanitize_input($data['comment'] ?? '');
    $review_type = sanitize_input($data['review_type']);
    $crop_id = isset($data['crop_id']) ? (int)$data['crop_id'] : null;
    $order_id = isset($data['order_id']) ? (int)$data['order_id'] : null;
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
        exit;
    }
    
    // Validate review type
    if (!in_array($review_type, ['product', 'seller', 'transporter'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid review type']);
        exit;
    }
    
    // Don't allow self-reviews
    if ($user_id === $reviewed_user_id) {
        echo json_encode(['success' => false, 'message' => 'You cannot review yourself']);
        exit;
    }
    
    // Check if review already exists
    $check_sql = "SELECT id FROM reviews WHERE reviewer_id = ? AND reviewed_user_id = ? 
                  AND crop_id <=> ? AND review_type = ?";
    if ($stmt = mysqli_prepare($link, $check_sql)) {
        mysqli_stmt_bind_param($stmt, "iiis", $user_id, $reviewed_user_id, $crop_id, $review_type);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            echo json_encode(['success' => false, 'message' => 'You have already reviewed this']);
            exit;
        }
        mysqli_stmt_close($stmt);
    }
+++++++ REPLACE------- SEARCH
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iiiisss", $user_id, $reviewed_user_id, $crop_id, $order_id, $rating, $title, $comment, $review_type);
        
        if (mysqli_stmt_execute($stmt)) {
            $review_id = mysqli_insert_id($link);
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iiiissss", $user_id, $reviewed_user_id, $crop_id, $order_id, $rating, $title, $comment, $review_type);
        
        if (mysqli_stmt_execute($stmt)) {
            $review_id = mysqli_insert_id($link);
    
    // Insert review
    $sql = "INSERT INTO reviews (reviewer_id, reviewed_user_id, crop_id, order_id, rating, title, comment, review_type) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iiiisss", $user_id, $reviewed_user_id, $crop_id, $order_id, $rating, $title, $comment, $review_type);
        
        if (mysqli_stmt_execute($stmt)) {
            $review_id = mysqli_insert_id($link);
            
            // Update crop average rating if product review
            if ($review_type === 'product' && $crop_id) {
                update_crop_rating($link, $crop_id);
            }
            
            log_activity($link, $user_id, 'REVIEW_CREATED', "Created {$review_type} review for user {$reviewed_user_id}");
            
            echo json_encode([
                'success' => true,
                'message' => 'Review submitted successfully',
                'review_id' => $review_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to submit review']);
        }
        mysqli_stmt_close($stmt);
    }
}

/**
 * Get Reviews for User/Product
 */
elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'list') {
    $reviewed_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $crop_id = isset($_GET['crop_id']) ? (int)$_GET['crop_id'] : null;
    $review_type = sanitize_input($_GET['type'] ?? '');
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = (int)($_GET['offset'] ?? 0);
    
    $sql = "SELECT r.*, u.name as reviewer_name, u.email FROM reviews r 
            LEFT JOIN users u ON r.reviewer_id = u.id 
            WHERE 1=1 ";
    $types = [];
    $params = [];
    
    if ($reviewed_user_id) {
        $sql .= " AND r.reviewed_user_id = ?";
        $types[] = "i";
        $params[] = $reviewed_user_id;
    }
    
    if ($crop_id) {
        $sql .= " AND r.crop_id = ?";
        $types[] = "i";
        $params[] = $crop_id;
    }
    
    if (!empty($review_type)) {
        $sql .= " AND r.review_type = ?";
        $types[] = "s";
        $params[] = $review_type;
    }
    
    $sql .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
    $types[] = "i";
    $types[] = "i";
    $params[] = $limit;
    $params[] = $offset;
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, implode('', $types), ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $reviews = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $reviews[] = $row;
        }
        
        echo json_encode(['success' => true, 'reviews' => $reviews]);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Get Review Stats
 */
elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'stats') {
    $reviewed_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $crop_id = isset($_GET['crop_id']) ? (int)$_GET['crop_id'] : null;
    $review_type = sanitize_input($_GET['type'] ?? '');
    
    $sql = "SELECT 
                AVG(rating) as avg_rating,
                COUNT(*) as total_reviews,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
            FROM reviews 
            WHERE 1=1 ";
    
    $types = [];
    $params = [];
    
    if ($reviewed_user_id) {
        $sql .= " AND reviewed_user_id = ?";
        $types[] = "i";
        $params[] = $reviewed_user_id;
    }
    
    if ($crop_id) {
        $sql .= " AND crop_id = ?";
        $types[] = "i";
        $params[] = $crop_id;
    }
    
    if (!empty($review_type)) {
        $sql .= " AND review_type = ?";
        $types[] = "s";
        $params[] = $review_type;
    }
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        if (!empty($types)) {
            mysqli_stmt_bind_param($stmt, implode('', $types), ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $stats = mysqli_fetch_assoc($result);
        
        echo json_encode(['success' => true, 'stats' => $stats]);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Mark Review as Helpful
 */
elseif ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'helpful') {
    $review_id = (int)($_POST['review_id'] ?? 0);
    
    if (empty($review_id)) {
        echo json_encode(['success' => false, 'message' => 'Review ID required']);
        exit;
    }
    
    $sql = "UPDATE reviews SET helpful_count = helpful_count + 1 WHERE id = ?";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $review_id);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Helpful count updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update']);
        }
        mysqli_stmt_close($stmt);
    }
}

else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

/**
 * Update Crop Average Rating
 */
function update_crop_rating($link, $crop_id) {
    $sql = "UPDATE crops SET 
            avg_rating = (SELECT AVG(rating) FROM reviews WHERE crop_id = ? AND review_type = 'product'),
            review_count = (SELECT COUNT(*) FROM reviews WHERE crop_id = ? AND review_type = 'product')
            WHERE id = ?";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iii", $crop_id, $crop_id, $crop_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

?>
