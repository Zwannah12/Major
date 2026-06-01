<?php
/**
 * Invoice Generation & Management API
 * Generates PDF invoices and manages invoice records
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
 * Generate Invoice for Order
 */
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'generate') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (empty($data['order_id'])) {
        echo json_encode(['success' => false, 'message' => 'Order ID required']);
        exit;
    }
    
    $order_id = (int)$data['order_id'];
    $tax_rate = isset($data['tax_rate']) ? (float)$data['tax_rate'] : 0.18; // 18% default tax
    $discount = isset($data['discount']) ? (float)$data['discount'] : 0;
    
    // Get order details
    $sql = "SELECT o.*, c.crop_name, c.price, f.name as farmer_name, f.email as farmer_email, f.phone as farmer_phone,
                   b.name as buyer_name, b.email as buyer_email, b.phone as buyer_phone
            FROM orders o
            INNER JOIN crops c ON o.crop_id = c.id
            INNER JOIN users f ON o.farmer_id = f.id
            INNER JOIN users b ON o.buyer_id = b.id
            WHERE o.id = ? AND (o.buyer_id = ? OR o.farmer_id = ? OR ? = 'admin')";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iiis", $order_id, $user_id, $user_id, $user_role);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($order = mysqli_fetch_assoc($result)) {
            // Calculate amounts
            $subtotal = $order['quantity'] * $order['price'];
            $tax_amount = $subtotal * $tax_rate;
            $total_amount = $subtotal + $tax_amount - $discount;
            
            // Generate invoice number
            $invoice_number = 'INV-' . date('Y') . '-' . str_pad($order['id'], 5, '0', STR_PAD_LEFT);
            
            // Check if invoice already exists
            $check_sql = "SELECT id FROM invoices WHERE order_id = ?";
            $invoice_exists = false;
            $invoice_id = null;
            
            if ($check_stmt = mysqli_prepare($link, $check_sql)) {
                mysqli_stmt_bind_param($check_stmt, "i", $order_id);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
                
                if ($check_row = mysqli_fetch_assoc($check_result)) {
                    $invoice_exists = true;
                    $invoice_id = $check_row['id'];
                }
                mysqli_stmt_close($check_stmt);
            }
            
            // Create or update invoice record
            if ($invoice_exists) {
                $update_sql = "UPDATE invoices SET total_amount = ?, tax_amount = ?, discount_amount = ? WHERE id = ?";
                if ($update_stmt = mysqli_prepare($link, $update_sql)) {
                    mysqli_stmt_bind_param($update_stmt, "dddi", $total_amount, $tax_amount, $discount, $invoice_id);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);
                }
            } else {
                $insert_sql = "INSERT INTO invoices (order_id, invoice_number, user_id, total_amount, tax_amount, discount_amount, due_at) 
                              VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))";
                
                if ($insert_stmt = mysqli_prepare($link, $insert_sql)) {
                    mysqli_stmt_bind_param($insert_stmt, "issddd", $order_id, $invoice_number, $user_id, $total_amount, $tax_amount, $discount);
                    
                    if (mysqli_stmt_execute($insert_stmt)) {
                        $invoice_id = mysqli_insert_id($link);
                    }
                    mysqli_stmt_close($insert_stmt);
                }
            }
            
            // Generate PDF content
            $pdf_content = generate_invoice_pdf($order, [
                'invoice_number' => $invoice_number,
                'subtotal' => $subtotal,
                'tax_amount' => $tax_amount,
                'discount' => $discount,
                'total_amount' => $total_amount
            ]);
            
            // Save PDF
            $pdf_filename = 'invoice_' . $invoice_number . '.pdf';
            $pdf_path = '../uploads/invoices/' . $pdf_filename;
            
            // Ensure directory exists
            @mkdir(dirname($pdf_path), 0755, true);
            
            file_put_contents($pdf_path, $pdf_content);
            
            // Update invoice record with PDF path
            $update_path_sql = "UPDATE invoices SET pdf_path = ? WHERE id = ?";
            if ($path_stmt = mysqli_prepare($link, $update_path_sql)) {
                mysqli_stmt_bind_param($path_stmt, "si", $pdf_path, $invoice_id);
                mysqli_stmt_execute($path_stmt);
                mysqli_stmt_close($path_stmt);
            }
            
            log_activity($link, $user_id, 'INVOICE_GENERATED', "Invoice generated: {$invoice_number}");
            
            echo json_encode([
                'success' => true,
                'message' => 'Invoice generated successfully',
                'invoice_id' => $invoice_id,
                'invoice_number' => $invoice_number,
                'pdf_path' => $pdf_path,
                'download_link' => '/api/invoice.php?action=download&id=' . $invoice_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Order not found or unauthorized']);
        }
        mysqli_stmt_close($stmt);
    }
}

/**
 * Download Invoice PDF
 */
elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'download') {
    $invoice_id = (int)($_GET['id'] ?? 0);
    
    if (empty($invoice_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invoice ID required']);
        exit;
    }
    
    // Get invoice details
    $sql = "SELECT i.*, o.buyer_id, o.farmer_id FROM invoices i 
            INNER JOIN orders o ON i.order_id = o.id 
            WHERE i.id = ? AND (o.buyer_id = ? OR o.farmer_id = ? OR ? = 'admin')";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iiis", $invoice_id, $user_id, $user_id, $user_role);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($invoice = mysqli_fetch_assoc($result)) {
            if (file_exists($invoice['pdf_path'])) {
                // Update PDF as sent
                $update_sql = "UPDATE invoices SET status = 'sent' WHERE id = ?";
                if ($update_stmt = mysqli_prepare($link, $update_sql)) {
                    mysqli_stmt_bind_param($update_stmt, "i", $invoice_id);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);
                }
                
                // Send file
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . basename($invoice['pdf_path']) . '"');
                readfile($invoice['pdf_path']);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'PDF file not found']);
            }
        } else {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        }
        mysqli_stmt_close($stmt);
    }
}

/**
 * Get Invoice Details
 */
elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'details') {
    $invoice_id = (int)($_GET['id'] ?? 0);
    
    if (empty($invoice_id)) {
        echo json_encode(['success' => false, 'message' => 'Invoice ID required']);
        exit;
    }
    
    $sql = "SELECT i.*, o.*, c.crop_name, f.name as farmer_name, b.name as buyer_name
            FROM invoices i
            INNER JOIN orders o ON i.order_id = o.id
            INNER JOIN crops c ON o.crop_id = c.id
            INNER JOIN users f ON o.farmer_id = f.id
            INNER JOIN users b ON o.buyer_id = b.id
            WHERE i.id = ? AND (b.id = ? OR f.id = ?)";
    
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iii", $invoice_id, $user_id, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($invoice = mysqli_fetch_assoc($result)) {
            echo json_encode(['success' => true, 'invoice' => $invoice]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        }
        mysqli_stmt_close($stmt);
    }
}

else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

/**
 * Generate Invoice PDF
 * Requires TCPDF library or similar
 */
function generate_invoice_pdf($order, $amounts) {
    // Simple HTML to PDF conversion - In production, use TCPDF or similar
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 800px; margin: 0 auto; padding: 20px; }
            .header { display: flex; justify-content: space-between; margin-bottom: 30px; }
            .logo { font-size: 24px; font-weight: bold; color: #28a745; }
            .invoice-title { font-size: 20px; font-weight: bold; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background: #f8f9fa; }
            .total-row { font-weight: bold; }
            .amount-col { text-align: right; }
            .footer { margin-top: 30px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="logo">AgroSphere MarketLink</div>
                <div class="invoice-title">Invoice</div>
            </div>
            
            <div style="margin-bottom: 30px;">
                <strong>' . $amounts['invoice_number'] . '</strong>
            </div>
            
            <table>
                <tr>
                    <th>Bill To</th>
                    <th>Sold By</th>
                </tr>
                <tr>
                    <td>' . htmlspecialchars($order['buyer_name']) . '<br/>' . htmlspecialchars($order['buyer_email']) . '</td>
                    <td>' . htmlspecialchars($order['farmer_name']) . '<br/>' . htmlspecialchars($order['farmer_email']) . '</td>
                </tr>
            </table>
            
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th class="amount-col">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>' . htmlspecialchars($order['crop_name']) . '</td>
                        <td>' . $order['quantity'] . '</td>
                        <td class="amount-col">RWF ' . number_format($order['price']) . '</td>
                        <td class="amount-col">RWF ' . number_format($amounts['subtotal']) . '</td>
                    </tr>
                </tbody>
            </table>
            
            <table style="width: 300px; margin-left: auto;">
                <tr>
                    <td>Subtotal:</td>
                    <td class="amount-col">RWF ' . number_format($amounts['subtotal']) . '</td>
                </tr>
                <tr>
                    <td>Tax (18%):</td>
                    <td class="amount-col">RWF ' . number_format($amounts['tax_amount']) . '</td>
                </tr>
                <tr>
                    <td>Discount:</td>
                    <td class="amount-col">-RWF ' . number_format($amounts['discount']) . '</td>
                </tr>
                <tr class="total-row">
                    <td>Total:</td>
                    <td class="amount-col">RWF ' . number_format($amounts['total_amount']) . '</td>
                </tr>
            </table>
            
            <div class="footer">
                <p>Thank you for your business!</p>
                <p>Payment Terms: Due within 30 days from invoice date</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    // For now, return HTML (in production use TCPDF or wkhtmltopdf)
    return $html;
}

?>
