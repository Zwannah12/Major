<?php
session_start();
require_once "includes/db.php";
require_once "includes/functions.php";
require_once "includes/security.php";

if (!is_logged_in()) {
    header("location: login.php");
    exit();
}

$user_id = $_SESSION['id'];
$user_role = $_SESSION['role'];
$dashboard_path = "dashboard/{$user_role}.php";
$csrf_token = get_csrf_token();

$message_sent = false;
$error = "";

// Handle sending a new message
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'send_message') {
    // Validate CSRF token
    if (empty($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error = "Security token validation failed. Please try again.";
    } else {
        $subject = sanitize_input($_POST['subject']);
        $message = sanitize_input($_POST['message']);

        if (!empty($subject) && !empty($message)) {
            $sql = "INSERT INTO messages (user_id, subject, message) VALUES (?, ?, ?)";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "iss", $user_id, $subject, $message);
                if (mysqli_stmt_execute($stmt)) {
                    $message_sent = true;
                    
                    // Notify Admins
                    $sql_admins = "SELECT id FROM users WHERE role = 'admin'";
                    if ($res_admins = mysqli_query($link, $sql_admins)) {
                        while ($admin = mysqli_fetch_assoc($res_admins)) {
                            create_notification($link, $admin['id'], "New support message: $subject", "admin.php#userMessages");
                        }
                    }
                } else {
                    $error = "Something went wrong. Please try again later.";
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            $error = "Please fill in all fields.";
        }
    }
}

// Fetch user's message history
$my_messages = [];
$sql_history = "SELECT * FROM messages WHERE user_id = ? ORDER BY created_at DESC";
if ($stmt_history = mysqli_prepare($link, $sql_history)) {
    mysqli_stmt_bind_param($stmt_history, "i", $user_id);
    mysqli_stmt_execute($stmt_history);
    $result = mysqli_stmt_get_result($stmt_history);
    while ($row = mysqli_fetch_assoc($result)) {
        $my_messages[] = $row;
    }
    mysqli_stmt_close($stmt_history);
}

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Support - AgroSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=modern-ui-2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div id="wrapper">
        <!-- Sidebar -->
        <div id="sidebar-wrapper">
            <div class="sidebar-heading"><i class="fas fa-leaf me-2"></i>AgroSphere</div>
            <div class="list-group list-group-flush mt-3">
                <a href="<?php echo $dashboard_path; ?>" class="list-group-item list-group-item-action">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="contact_admin.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-envelope"></i> Contact Support
                </a>
                <a href="profile.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-user-cog"></i> Profile Settings
                </a>
                <a href="logout.php" class="list-group-item list-group-item-action text-danger mt-5">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Page Content -->
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom sticky-top">
                <div class="container-fluid">
                    <button class="btn btn-outline-success" id="menu-toggle"><i class="fas fa-bars"></i></button>
                    <div class="ms-3 fw-bold text-success text-uppercase"><?php echo $user_role; ?> PANEL</div>
                </div>
            </nav>

            <div class="container-fluid p-4 animated-fade-in">
                <h1 class="h3 mb-4">Contact Admin Support</h1>

                <?php if ($message_sent): ?>
                    <div class="alert alert-success shadow-sm">Your message has been sent to the admin. We will reply soon!</div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger shadow-sm"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="row">
                    <!-- Send Message Form -->
                    <div class="col-lg-5 mb-4">
                        <div class="card shadow border-0">
                            <div class="card-header bg-success text-white py-3">
                                <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i>New Message</h5>
                            </div>
                            <div class="card-body p-4">
                                <form action="contact_admin.php" method="post">
                                    <input type="hidden" name="action" value="send_message">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Subject</label>
                                        <input type="text" name="subject" class="form-control" placeholder="What do you need help with?" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Message</label>
                                        <textarea name="message" class="form-control" rows="5" placeholder="Explain your issue in detail..." required></textarea>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-success fw-bold">Send Message</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Message History -->
                    <div class="col-lg-7">
                        <div class="card shadow border-0">
                            <div class="card-header bg-dark text-white py-3">
                                <h5 class="mb-0"><i class="fas fa-history me-2"></i>My Conversation History</h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($my_messages)): ?>
                                    <div class="text-center py-5 text-muted">You haven't sent any messages yet.</div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($my_messages as $msg): ?>
                                            <div class="list-group-item p-4 message-item" id="msg-<?php echo $msg['id']; ?>">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <h6 class="fw-bold mb-0 text-success msg-subject-text"><?php echo htmlspecialchars($msg['subject']); ?></h6>
                                                    <div class="d-flex align-items-center">
                                                        <span class="badge <?php echo ($msg['status'] == 'replied') ? 'bg-primary' : 'bg-warning text-dark'; ?> me-2">
                                                            <?php echo ucfirst($msg['status']); ?>
                                                        </span>
                                                        <?php if($msg['status'] != 'replied'): ?>
                                                            <button class="btn btn-sm btn-outline-info me-1 edit-support" data-id="<?php echo $msg['id']; ?>" data-subject="<?php echo htmlspecialchars($msg['subject']); ?>" data-message="<?php echo htmlspecialchars($msg['message']); ?>"><i class="fas fa-edit"></i></button>
                                                        <?php endif; ?>
                                                        <button class="btn btn-sm btn-outline-danger delete-support" data-id="<?php echo $msg['id']; ?>"><i class="fas fa-trash"></i></button>
                                                    </div>
                                                </div>
                                                <p class="mb-2 text-dark msg-message-text"><?php echo htmlspecialchars($msg['message']); ?></p>
                                                <small class="text-muted d-block mb-3"><?php echo date('M d, Y H:i', strtotime($msg['created_at'])); ?></small>

                                                <?php if (!empty($msg['admin_reply'])): ?>
                                                    <div class="bg-light p-3 rounded-3 border-start border-4 border-primary">
                                                        <div class="fw-bold text-primary small mb-1"><i class="fas fa-user-shield me-1"></i>Admin Reply:</div>
                                                        <p class="mb-1 small text-dark"><?php echo htmlspecialchars($msg['admin_reply']); ?></p>
                                                        <small class="text-muted smaller"><?php echo date('M d, Y H:i', strtotime($msg['replied_at'])); ?></small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Support Modal -->
    <div class="modal fade" id="editSupportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Edit Support Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="edit_support_id">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Subject</label>
                        <input type="text" id="edit_support_subject" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Message</label>
                        <textarea id="edit_support_message" class="form-control" rows="5"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="saveSupportEdit" class="btn btn-success">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script>
        $("#menu-toggle").click(function(e) { e.preventDefault(); $("#wrapper").toggleClass("toggled"); });

        // Delete Support Message
        $('.delete-support').click(function() {
            const msgId = $(this).data('id');
            if (confirm('Are you sure you want to delete this support request?')) {
                $.ajax({
                    url: 'includes/message_actions.php',
                    method: 'POST',
                    data: { action: 'delete_support', msg_id: msgId },
                    success: function(res) {
                        if (res === 'success') {
                            $('#msg-' + msgId).fadeOut();
                        }
                    }
                });
            }
        });

        // Edit Support Message
        $('.edit-support').click(function() {
            const msgId = $(this).data('id');
            const subject = $(this).data('subject');
            const message = $(this).data('message');
            $('#edit_support_id').val(msgId);
            $('#edit_support_subject').val(subject);
            $('#edit_support_message').val(message);
            $('#editSupportModal').modal('show');
        });

        $('#saveSupportEdit').click(function() {
            const msgId = $('#edit_support_id').val();
            const subject = $('#edit_support_subject').val();
            const message = $('#edit_support_message').val();
            
            $.ajax({
                url: 'includes/message_actions.php',
                method: 'POST',
                data: { 
                    action: 'edit_support', 
                    msg_id: msgId, 
                    subject: subject,
                    message: message 
                },
                success: function(res) {
                    if (res === 'success') {
                        $('#editSupportModal').modal('hide');
                        location.reload(); // Reload to see changes
                    }
                }
            });
        });
    </script>
    <script src="assets/js/main.js?v=modern-ui-2"></script>
</body>
</html>
