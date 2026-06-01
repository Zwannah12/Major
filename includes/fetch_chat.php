<?php
session_start();
require_once "db.php";
require_once "functions.php";

if (!is_logged_in() || !isset($_GET['user_id'])) {
    exit();
}

$my_id = $_SESSION['id'];
$other_id = (int)$_GET['user_id'];

// Mark messages as read
$sql_read = "UPDATE direct_messages SET is_read = TRUE WHERE sender_id = ? AND receiver_id = ?";
if ($stmt_read = mysqli_prepare($link, $sql_read)) {
    mysqli_stmt_bind_param($stmt_read, "ii", $other_id, $my_id);
    mysqli_stmt_execute($stmt_read);
    mysqli_stmt_close($stmt_read);
}

// Fetch messages
$sql_msgs = "SELECT * FROM direct_messages 
             WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
             ORDER BY created_at ASC";

if ($stmt_msgs = mysqli_prepare($link, $sql_msgs)) {
    mysqli_stmt_bind_param($stmt_msgs, "iiii", $my_id, $other_id, $other_id, $my_id);
    mysqli_stmt_execute($stmt_msgs);
    $result = mysqli_stmt_get_result($stmt_msgs);
    
    if (mysqli_num_rows($result) == 0) {
        echo '<div class="text-center text-muted small py-5">No messages yet. Send a message to start the conversation!</div>';
    } else {
        while ($row = mysqli_fetch_assoc($result)) {
            $is_mine = ($row['sender_id'] == $my_id);
            $class = $is_mine ? 'msg-sent' : 'msg-received';
            
            echo '<div class="msg-group d-flex ' . ($is_mine ? 'justify-content-end' : 'justify-content-start') . ' mb-3">';
            echo '  <div class="msg-bubble ' . $class . ' shadow-sm position-relative group">';
            echo '      <div class="msg-text">' . htmlspecialchars($row['message']) . '</div>';
            echo '      <div class="d-flex justify-content-between align-items-center mt-1" style="font-size: 0.65rem; opacity: 0.7;">';
            echo '          <span>' . date('H:i', strtotime($row['created_at'])) . '</span>';
            if ($is_mine) {
                echo '      <div class="msg-actions ms-2">';
                echo '          <a href="#" class="text-white me-1 edit-msg" data-id="'.$row['id'].'" data-msg="'.htmlspecialchars($row['message']).'"><i class="fas fa-edit"></i></a>';
                echo '          <a href="#" class="text-white delete-msg" data-id="'.$row['id'].'"><i class="fas fa-trash"></i></a>';
                echo '      </div>';
            }
            echo '      </div>';
            echo '  </div>';
            echo '</div>';
        }
    }
    mysqli_stmt_close($stmt_msgs);
}

mysqli_close($link);
?>
<style>
    .msg-group .msg-actions { opacity: 0; transition: 0.2s; }
    .msg-group:hover .msg-actions { opacity: 1; }
    .msg-sent .msg-actions a:hover { color: #eee !important; }
    .msg-bubble { min-width: 80px; }
</style>
