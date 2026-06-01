<?php
/**
 * Email Notification System
 * Handles sending email notifications for various events
 */

class EmailNotificationSystem {
    private $db_link;
    private $smtp_host = 'localhost';
    private $smtp_port = 587;
    private $from_email = 'noreply@agrosphere.rw';
    private $from_name = 'AgroSphere MarketLink';
    
    public function __construct($db_link) {
        $this->db_link = $db_link;
    }
    
    /**
     * Send Email Verification
     */
    public function send_verification_email($email, $name, $verification_link) {
        $subject = 'Verify Your Email - AgroSphere MarketLink';
        
        $html_body = $this->render_template('verification_email', [
            'name' => $name,
            'verification_link' => $verification_link,
            'expiry_hours' => 24
        ]);
        
        return $this->send_email($email, $subject, $html_body);
    }
    
    /**
     * Send Order Confirmation
     */
    public function send_order_confirmation($user_id, $order_id) {
        $sql = "SELECT u.email, u.name, o.*, c.crop_name, c.quantity as crop_quantity, f.name as farmer_name
                FROM orders o
                INNER JOIN users u ON o.buyer_id = u.id
                INNER JOIN crops c ON o.crop_id = c.id
                INNER JOIN users f ON o.farmer_id = f.id
                WHERE o.id = ? AND u.id = ?";
        
        if ($stmt = mysqli_prepare($this->db_link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($order = mysqli_fetch_assoc($result)) {
                $subject = "Order Confirmation #{$order['id']} - AgroSphere";
                
                $html_body = $this->render_template('order_confirmation', [
                    'name' => $order['name'],
                    'order_id' => $order['id'],
                    'crop_name' => $order['crop_name'],
                    'quantity' => $order['quantity'],
                    'farmer_name' => $order['farmer_name'],
                    'created_at' => $order['created_at']
                ]);
                
                $sent = $this->send_email($order['email'], $subject, $html_body);
                
                // Log notification
                $this->log_notification($user_id, 'order_confirmation', 'Order confirmation email sent', $order_id, $sent);
                
                return $sent;
            }
            mysqli_stmt_close($stmt);
        }
        return false;
    }
    
    /**
     * Send Delivery Status Update
     */
    public function send_delivery_update($user_id, $delivery_id, $status) {
        $sql = "SELECT u.email, u.name, d.*, o.id as order_id, c.crop_name
                FROM deliveries d
                INNER JOIN orders o ON d.order_id = o.id
                INNER JOIN crops c ON o.crop_id = c.id
                INNER JOIN users u ON o.buyer_id = u.id
                WHERE d.id = ?";
        
        if ($stmt = mysqli_prepare($this->db_link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $delivery_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($delivery = mysqli_fetch_assoc($result)) {
                $subject = "Delivery Status Update - {$status}";
                
                $status_messages = [
                    'pending' => 'Your delivery is being prepared',
                    'in_transit' => 'Your delivery is on the way',
                    'arrived' => 'Your delivery has arrived',
                    'completed' => 'Your delivery has been completed',
                    'failed' => 'Your delivery encountered an issue'
                ];
                
                $html_body = $this->render_template('delivery_update', [
                    'name' => $delivery['name'],
                    'status' => $status,
                    'message' => $status_messages[$status] ?? 'Status updated',
                    'crop_name' => $delivery['crop_name'],
                    'delivery_id' => $delivery['id']
                ]);
                
                $sent = $this->send_email($delivery['email'], $subject, $html_body);
                
                // Log notification
                $this->log_notification($user_id, 'delivery_update', "Delivery status updated to {$status}", $delivery_id, $sent);
                
                return $sent;
            }
            mysqli_stmt_close($stmt);
        }
        return false;
    }
    
    /**
     * Send Payment Confirmation
     */
    public function send_payment_confirmation($user_id, $payment_id) {
        $sql = "SELECT u.email, u.name, p.amount, p.transaction_id, o.id as order_id, p.created_at
                FROM payments p
                INNER JOIN users u ON p.user_id = u.id
                INNER JOIN orders o ON p.order_id = o.id
                WHERE p.id = ? AND p.user_id = ?";
        
        if ($stmt = mysqli_prepare($this->db_link, $sql)) {
            mysqli_stmt_bind_param($stmt, "ii", $payment_id, $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($payment = mysqli_fetch_assoc($result)) {
                $subject = "Payment Confirmation - RWF {$payment['amount']}";
                
                $html_body = $this->render_template('payment_confirmation', [
                    'name' => $payment['name'],
                    'amount' => $payment['amount'],
                    'transaction_id' => $payment['transaction_id'],
                    'order_id' => $payment['order_id'],
                    'date' => $payment['created_at']
                ]);
                
                $sent = $this->send_email($payment['email'], $subject, $html_body);
                
                // Log notification
                $this->log_notification($user_id, 'payment_confirmation', 'Payment confirmation email sent', $payment_id, $sent);
                
                return $sent;
            }
            mysqli_stmt_close($stmt);
        }
        return false;
    }
    
    /**
     * Send Reset Password Email
     */
    public function send_password_reset($email, $name, $reset_link) {
        $subject = 'Reset Your Password - AgroSphere MarketLink';
        
        $html_body = $this->render_template('password_reset', [
            'name' => $name,
            'reset_link' => $reset_link,
            'expiry_hours' => 1
        ]);
        
        return $this->send_email($email, $subject, $html_body);
    }
    
    /**
     * Send Email
     */
    private function send_email($to, $subject, $html_body) {
        $text_body = strip_tags($html_body);
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$this->from_name} <{$this->from_email}>\r\n";
        $headers .= "Reply-To: {$this->from_email}\r\n";
        $headers .= "X-Mailer: AgroSphere\r\n";
        
        return mail($to, $subject, $html_body, $headers);
    }
    
    /**
     * Render Email Template
     */
    private function render_template($template, $data = []) {
        ob_start();
        
        switch ($template) {
            case 'verification_email':
                ?>
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                    <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); padding: 20px; text-align: center; color: white;">
                        <h1 style="margin: 0;">Verify Your Email</h1>
                    </div>
                    <div style="padding: 30px; background: #f8f9fa;">
                        <p>Hi <?php echo htmlspecialchars($data['name']); ?>,</p>
                        <p>Thank you for registering with AgroSphere MarketLink! To complete your registration, please verify your email address.</p>
                        <div style="text-align: center; margin: 30px 0;">
                            <a href="<?php echo htmlspecialchars($data['verification_link']); ?>" style="background: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Verify Email</a>
                        </div>
                        <p style="color: #666; font-size: 12px;">This link will expire in <?php echo $data['expiry_hours']; ?> hours.</p>
                    </div>
                </div>
                <?php
                break;
                
            case 'order_confirmation':
                ?>
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                    <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); padding: 20px; text-align: center; color: white;">
                        <h1 style="margin: 0;">Order Confirmed!</h1>
                    </div>
                    <div style="padding: 30px; background: #f8f9fa;">
                        <p>Hi <?php echo htmlspecialchars($data['name']); ?>,</p>
                        <p>Your order has been confirmed. Here are the details:</p>
                        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                            <tr style="background: #fff;">
                                <td style="padding: 10px; border: 1px solid #ddd;"><strong>Order ID</strong></td>
                                <td style="padding: 10px; border: 1px solid #ddd;">#<?php echo $data['order_id']; ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid #ddd;"><strong>Product</strong></td>
                                <td style="padding: 10px; border: 1px solid #ddd;"><?php echo htmlspecialchars($data['crop_name']); ?></td>
                            </tr>
                            <tr style="background: #fff;">
                                <td style="padding: 10px; border: 1px solid #ddd;"><strong>Quantity</strong></td>
                                <td style="padding: 10px; border: 1px solid #ddd;"><?php echo $data['quantity']; ?> units</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid #ddd;"><strong>Farmer</strong></td>
                                <td style="padding: 10px; border: 1px solid #ddd;"><?php echo htmlspecialchars($data['farmer_name']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php
                break;
                
            case 'delivery_update':
                ?>
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                    <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); padding: 20px; text-align: center; color: white;">
                        <h1 style="margin: 0;">Delivery Update</h1>
                    </div>
                    <div style="padding: 30px; background: #f8f9fa;">
                        <p>Hi <?php echo htmlspecialchars($data['name']); ?>,</p>
                        <p><strong><?php echo htmlspecialchars($data['message']); ?></strong></p>
                        <p>Status: <span style="color: #28a745; font-weight: bold;"><?php echo ucfirst($data['status']); ?></span></p>
                        <p>Product: <?php echo htmlspecialchars($data['crop_name']); ?></p>
                    </div>
                </div>
                <?php
                break;
                
            case 'payment_confirmation':
                ?>
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                    <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); padding: 20px; text-align: center; color: white;">
                        <h1 style="margin: 0;">Payment Received</h1>
                    </div>
                    <div style="padding: 30px; background: #f8f9fa;">
                        <p>Hi <?php echo htmlspecialchars($data['name']); ?>,</p>
                        <p>Your payment has been received successfully!</p>
                        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                            <tr style="background: #fff;">
                                <td style="padding: 10px; border: 1px solid #ddd;"><strong>Amount</strong></td>
                                <td style="padding: 10px; border: 1px solid #ddd;">RWF <?php echo number_format($data['amount']); ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid #ddd;"><strong>Transaction ID</strong></td>
                                <td style="padding: 10px; border: 1px solid #ddd;"><?php echo htmlspecialchars($data['transaction_id']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php
                break;
                
            case 'password_reset':
                ?>
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                    <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); padding: 20px; text-align: center; color: white;">
                        <h1 style="margin: 0;">Reset Your Password</h1>
                    </div>
                    <div style="padding: 30px; background: #f8f9fa;">
                        <p>Hi <?php echo htmlspecialchars($data['name']); ?>,</p>
                        <p>Click the link below to reset your password:</p>
                        <div style="text-align: center; margin: 30px 0;">
                            <a href="<?php echo htmlspecialchars($data['reset_link']); ?>" style="background: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Reset Password</a>
                        </div>
                        <p style="color: #666; font-size: 12px;">This link will expire in <?php echo $data['expiry_hours']; ?> hour(s).</p>
                    </div>
                </div>
                <?php
                break;
        }
        
        return ob_get_clean();
    }
    
    /**
     * Log Notification
     */
    private function log_notification($user_id, $type, $message, $related_id, $email_sent) {
        $sql = "INSERT INTO notification_history (user_id, type, message, related_id, email_sent) 
                VALUES (?, ?, ?, ?, ?)";
        
        if ($stmt = mysqli_prepare($this->db_link, $sql)) {
            $email_sent_int = $email_sent ? 1 : 0;
            mysqli_stmt_bind_param($stmt, "issii", $user_id, $type, $message, $related_id, $email_sent_int);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

?>
