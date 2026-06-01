<?php
/**
 * Advanced Search & Filtering API
 * Provides powerful search capabilities for crops and users
 */

require_once "../includes/db.php";
require_once "../includes/security.php";

header('Content-Type: application/json');

$method = $_SERVER["REQUEST_METHOD"];

/**
 * Search Crops with Advanced Filters
 */
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'crops') {
    $query = sanitize_input($_GET['q'] ?? '');
    $category = sanitize_input($_GET['category'] ?? '');
    $min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
    $max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : PHP_FLOAT_MAX;
    $min_rating = isset($_GET['min_rating']) ? (float)$_GET['min_rating'] : 0;
    $location = sanitize_input($_GET['location'] ?? '');
    $sort = sanitize_input($_GET['sort'] ?? 'newest');
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = (int)($_GET['offset'] ?? 0);
    
    // Build SQL query
    $sql = "SELECT c.*, u.name as farmer_name, u.location, COUNT(r.id) as review_count, AVG(r.rating) as avg_rating
            FROM crops c
            LEFT JOIN users u ON c.farmer_id = u.id
            LEFT JOIN reviews r ON c.id = r.crop_id AND r.review_type = 'product'
            WHERE c.status = 'approved' ";
    
    $types = [];
    $params = [];
    
    // Search query
    if (!empty($query)) {
        $sql .= " AND (c.crop_name LIKE ? OR c.search_vector LIKE ?)";
        $search_term = "%{$query}%";
        $types[] = "s";
        $types[] = "s";
        $params[] = $search_term;
        $params[] = $search_term;
        
        // Log search
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION["id"])) {
            log_search($link, $_SESSION["id"], $query, null, count($params));
        }
    }
    
    // Price range
    $sql .= " AND c.price BETWEEN ? AND ?";
    $types[] = "d";
    $types[] = "d";
    $params[] = $min_price;
    $params[] = $max_price;
    
    // Location
    if (!empty($location)) {
        $sql .= " AND u.location = ?";
        $types[] = "s";
        $params[] = $location;
    }
    
    $sql .= " GROUP BY c.id ";
    
    // Rating filter
    if ($min_rating > 0) {
        $sql .= " HAVING avg_rating >= ?";
        $types[] = "d";
        $params[] = $min_rating;
    }
    
    // Sorting
    switch ($sort) {
        case 'price_low':
            $sql .= " ORDER BY c.price ASC";
            break;
        case 'price_high':
            $sql .= " ORDER BY c.price DESC";
            break;
        case 'highest_rated':
            $sql .= " ORDER BY avg_rating DESC";
            break;
        case 'most_reviewed':
            $sql .= " ORDER BY review_count DESC";
            break;
        case 'newest':
        default:
            $sql .= " ORDER BY c.created_at DESC";
    }
    
    $sql .= " LIMIT ? OFFSET ?";
    $types[] = "i";
    $types[] = "i";
    $params[] = $limit;
    $params[] = $offset;
    
    // Execute query
    if ($stmt = mysqli_prepare($link, $sql)) {
        if (!empty($types)) {
            mysqli_stmt_bind_param($stmt, implode('', $types), ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $crops = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $crops[] = $row;
        }
        
        echo json_encode(['success' => true, 'crops' => $crops, 'count' => count($crops)]);
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'message' => mysqli_error($link)]);
    }
}

/**
 * Get Search Suggestions
 */
elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'suggestions') {
    $query = sanitize_input($_GET['q'] ?? '');
    
    if (strlen($query) < 2) {
        echo json_encode(['success' => true, 'suggestions' => []]);
        exit;
    }
    
    $search_term = "{$query}%";
    $sql = "SELECT DISTINCT crop_name FROM crops 
            WHERE crop_name LIKE ? AND status = 'approved'
            LIMIT 10";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $search_term);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $suggestions = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $suggestions[] = $row['crop_name'];
        }
        
        echo json_encode(['success' => true, 'suggestions' => $suggestions]);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Get Available Filters
 */
elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'filters') {
    $filters = [];
    
    // Price range
    $price_sql = "SELECT MIN(price) as min_price, MAX(price) as max_price FROM crops WHERE status = 'approved'";
    if ($price_stmt = mysqli_prepare($link, $price_sql)) {
        mysqli_stmt_execute($price_stmt);
        $price_result = mysqli_stmt_get_result($price_stmt);
        $prices = mysqli_fetch_assoc($price_result);
        $filters['price_range'] = $prices;
        mysqli_stmt_close($price_stmt);
    }
    
    // Locations
    $location_sql = "SELECT DISTINCT u.location FROM users u 
                     INNER JOIN crops c ON u.id = c.farmer_id 
                     WHERE c.status = 'approved'
                     ORDER BY u.location";
    if ($location_stmt = mysqli_prepare($link, $location_sql)) {
        mysqli_stmt_execute($location_stmt);
        $location_result = mysqli_stmt_get_result($location_stmt);
        $locations = [];
        while ($row = mysqli_fetch_assoc($location_result)) {
            if ($row['location']) $locations[] = $row['location'];
        }
        $filters['locations'] = $locations;
        mysqli_stmt_close($location_stmt);
    }
    
    // Rating ranges
    $filters['ratings'] = [
        ['min' => 4, 'max' => 5, 'label' => '4+ Stars'],
        ['min' => 3, 'max' => 5, 'label' => '3+ Stars'],
        ['min' => 2, 'max' => 5, 'label' => '2+ Stars'],
        ['min' => 0, 'max' => 5, 'label' => 'All Ratings']
    ];
    
    // Sort options
    $filters['sort_options'] = [
        ['value' => 'newest', 'label' => 'Newest'],
        ['value' => 'price_low', 'label' => 'Price: Low to High'],
        ['value' => 'price_high', 'label' => 'Price: High to Low'],
        ['value' => 'highest_rated', 'label' => 'Highest Rated'],
        ['value' => 'most_reviewed', 'label' => 'Most Reviewed']
    ];
    
    echo json_encode(['success' => true, 'filters' => $filters]);
}

else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

/**
 * Log Search Query
 */
function log_search($link, $user_id, $query, $filters = null, $results_count = 0) {
    $filters_json = $filters ? json_encode($filters) : null;
    $sql = "INSERT INTO search_history (user_id, search_query, filters, results_count) 
            VALUES (?, ?, ?, ?)";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "issi", $user_id, $query, $filters_json, $results_count);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

?>
