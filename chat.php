<?php
session_start();
require_once "includes/db.php";
require_once "includes/functions.php";

if (!is_logged_in()) {
    header("location: login.php");
    exit();
}

$my_id = $_SESSION['id'];
$other_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

// Fetch other user details if ID is provided
$other_user = null;
if ($other_user_id) {
    $sql_ou = "SELECT id, name, role, profile_image FROM users WHERE id = ?";
    if ($stmt_ou = mysqli_prepare($link, $sql_ou)) {
        mysqli_stmt_bind_param($stmt_ou, "i", $other_user_id);
        mysqli_stmt_execute($stmt_ou);
        $res_ou = mysqli_stmt_get_result($stmt_ou);
        $other_user = mysqli_fetch_assoc($res_ou);
        mysqli_stmt_close($stmt_ou);
    }
}

// Fetch list of users I have conversations with
$conversations = [];
$sql_conv = "SELECT u.id as contact_id, u.name, u.role, u.profile_image, m.message as last_msg, m.created_at as last_time
             FROM users u
             JOIN (
                SELECT 
                    CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as contact_id,
                    MAX(id) as max_id
                FROM direct_messages
                WHERE sender_id = ? OR receiver_id = ?
                GROUP BY contact_id
             ) as last_msgs ON u.id = last_msgs.contact_id
             JOIN direct_messages m ON m.id = last_msgs.max_id
             ORDER BY m.created_at DESC";

if ($stmt_conv = mysqli_prepare($link, $sql_conv)) {
    mysqli_stmt_bind_param($stmt_conv, "iii", $my_id, $my_id, $my_id);
    mysqli_stmt_execute($stmt_conv);
    $res_conv = mysqli_stmt_get_result($stmt_conv);
    while ($row = mysqli_fetch_assoc($res_conv)) {
        $conversations[] = $row;
    }
    mysqli_stmt_close($stmt_conv);
}

mysqli_close($link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - AgroSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=modern-ui-2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .chat-container { height: calc(100vh - 100px); background: white; border-radius: 15px; overflow: hidden; display: flex; }
        .chat-sidebar { width: 350px; border-right: 1px solid #eee; display: flex; flex-direction: column; }
        .chat-main { flex: 1; display: flex; flex-direction: column; background: #f9f9f9; }
        .conv-list { overflow-y: auto; flex: 1; }
        .conv-item { padding: 15px; border-bottom: 1px solid #f5f5f5; cursor: pointer; transition: 0.2s; }
        .conv-item:hover { background: #f0fdf4; }
        .conv-item.active { background: #e8f5e9; border-left: 4px solid #28a745; }
        .chat-header { padding: 15px 25px; background: white; border-bottom: 1px solid #eee; display: flex; align-items: center; }
        .chat-messages { flex: 1; overflow-y: auto; padding: 25px; display: flex; flex-direction: column; gap: 15px; }
        .msg-bubble { max-width: 70%; padding: 10px 15px; border-radius: 15px; font-size: 0.95rem; position: relative; }
        .msg-sent { align-self: flex-end; background: #28a745; color: white; border-bottom-right-radius: 2px; }
        .msg-received { align-self: flex-start; background: white; color: #333; border-bottom-left-radius: 2px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .chat-input-area { padding: 20px; background: white; border-top: 1px solid #eee; }
        .avatar-sm { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; cursor: pointer; border: 2px solid transparent; transition: 0.2s; }
        .avatar-sm:hover { border-color: #28a745; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold text-success" href="index.php"><i class="fas fa-leaf"></i> AGROSPHERE</a>
            <div class="ms-auto">
                <a href="dashboard/<?php echo $_SESSION['role']; ?>.php" class="btn btn-outline-success btn-sm">Back to Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="chat-container shadow-sm">
            <!-- Sidebar -->
            <div class="chat-sidebar">
                <div class="p-3 bg-success text-white">
                    <h5 class="mb-0 fw-bold">Chats</h5>
                </div>
                <div class="conv-list">
                    <?php if (empty($conversations) && !$other_user_id): ?>
                        <div class="text-center p-5 text-muted small">No conversations yet. Start a chat from the marketplace!</div>
                    <?php endif; ?>

                    <?php foreach ($conversations as $conv): ?>
                        <a href="chat.php?user_id=<?php echo $conv['contact_id']; ?>" class="text-decoration-none text-dark">
                            <div class="conv-item <?php echo ($other_user_id == $conv['contact_id']) ? 'active' : ''; ?>">
                                <div class="d-flex align-items-center">
                                    <img src="assets/images/avatars/<?php echo $conv['profile_image']; ?>" class="avatar-sm me-3" title="View Profile" onclick="window.location.href='view_profile.php?user_id=<?php echo $conv['contact_id']; ?>'; event.preventDefault();">
                                    <div class="flex-grow-1 overflow-hidden">
                                        <div class="d-flex justify-content-between">
                                            <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($conv['name']); ?></h6>
                                            <small class="text-muted" style="font-size: 0.7rem;"><?php echo date('H:i', strtotime($conv['last_time'])); ?></small>
                                        </div>
                                        <p class="mb-0 small text-muted text-truncate"><?php echo htmlspecialchars($conv['last_msg']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Main Chat -->
            <div class="chat-main">
                <?php if ($other_user): ?>
                    <div class="chat-header">
                        <a href="view_profile.php?user_id=<?php echo $other_user_id; ?>">
                            <img src="assets/images/avatars/<?php echo $other_user['profile_image']; ?>" class="avatar-sm me-3" title="View Profile">
                        </a>
                        <div>
                            <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($other_user['name']); ?></h6>
                            <small class="text-success text-uppercase" style="font-size: 0.7rem;"><?php echo $other_user['role']; ?></small>
                        </div>
                    </div>

                    <div class="chat-messages" id="chatBox">
                        <!-- Messages loaded via AJAX -->
                        <div class="text-center text-muted small py-5">Loading conversation...</div>
                    </div>

                    <div class="chat-input-area">
                        <form id="chatForm" class="input-group">
                            <input type="hidden" id="receiver_id" value="<?php echo $other_user_id; ?>">
                            <input type="text" id="messageText" class="form-control border-0 bg-light p-3" placeholder="Type your message here..." autocomplete="off">
                            <button class="btn btn-success px-4" type="submit" aria-label="Send message" title="Send message"><i class="fas fa-paper-plane"></i></button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="h-100 d-flex flex-column align-items-center justify-content-center text-muted">
                        <i class="fas fa-comments fa-4x mb-3 opacity-25"></i>
                        <h5>Select a conversation to start chatting</h5>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="edit_msg_id">
                    <textarea id="edit_msg_text" class="form-control" rows="4"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="saveEdit" class="btn btn-success">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js?v=modern-ui-2"></script>
    <script>
        $(document).ready(function() {
            const receiverId = $('#receiver_id').val();
            
            if (receiverId) {
                fetchMessages();
                setInterval(fetchMessages, 3000); // Poll every 3 seconds
            }

            function fetchMessages() {
                $.ajax({
                    url: 'includes/fetch_chat.php',
                    method: 'GET',
                    data: { user_id: receiverId },
                    success: function(data) {
                        $('#chatBox').html(data);
                    }
                });
            }

            function scrollToBottom() {
                const cb = document.getElementById('chatBox');
                cb.scrollTop = cb.scrollHeight;
            }

            $('#chatForm').on('submit', function(e) {
                e.preventDefault();
                const msg = $('#messageText').val();
                if (msg.trim() === '') return;

                $.ajax({
                    url: 'includes/send_chat.php',
                    method: 'POST',
                    data: { receiver_id: receiverId, message: msg },
                    success: function() {
                        $('#messageText').val('');
                        fetchMessages();
                    }
                });
            });

            // Delete Message
            $(document).on('click', '.delete-msg', function(e) {
                e.preventDefault();
                const msgId = $(this).data('id');
                if (confirm('Delete this message?')) {
                    $.ajax({
                        url: 'includes/message_actions.php',
                        method: 'POST',
                        data: { action: 'delete_dm', msg_id: msgId },
                        success: function() {
                            fetchMessages();
                        }
                    });
                }
            });

            // Edit Message
            $(document).on('click', '.edit-msg', function(e) {
                e.preventDefault();
                const msgId = $(this).data('id');
                const msgText = $(this).data('msg');
                $('#edit_msg_id').val(msgId);
                $('#edit_msg_text').val(msgText);
                $('#editModal').modal('show');
            });

            $('#saveEdit').click(function() {
                const msgId = $('#edit_msg_id').val();
                const newText = $('#edit_msg_text').val();
                $.ajax({
                    url: 'includes/message_actions.php',
                    method: 'POST',
                    data: { action: 'edit_dm', msg_id: msgId, message: newText },
                    success: function() {
                        $('#editModal').modal('hide');
                        fetchMessages();
                    }
                });
            });
        });
    </script>
</body>
</html>
