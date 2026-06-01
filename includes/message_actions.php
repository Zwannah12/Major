<?php
session_start();
require_once "db.php";
require_once "functions.php";

if (!is_logged_in() || $_SERVER["REQUEST_METHOD"] != "POST") {
    exit();
}

$my_id = $_SESSION['id'];
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action == 'delete_dm') {
    $msg_id = (int)$_POST['msg_id'];
    $sql = "DELETE FROM direct_messages WHERE id = ? AND sender_id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $msg_id, $my_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo "success";
    }
} elseif ($action == 'edit_dm') {
    $msg_id = (int)$_POST['msg_id'];
    $new_msg = sanitize_input($_POST['message']);
    $sql = "UPDATE direct_messages SET message = ? WHERE id = ? AND sender_id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "sii", $new_msg, $msg_id, $my_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo "success";
    }
} elseif ($action == 'delete_support') {
    $msg_id = (int)$_POST['msg_id'];
    $sql = "DELETE FROM messages WHERE id = ? AND user_id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $msg_id, $my_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo "success";
    }
} elseif ($action == 'edit_support') {
    $msg_id = (int)$_POST['msg_id'];
    $new_subject = sanitize_input($_POST['subject']);
    $new_msg = sanitize_input($_POST['message']);
    $sql = "UPDATE messages SET subject = ?, message = ? WHERE id = ? AND user_id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "ssii", $new_subject, $new_msg, $msg_id, $my_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo "success";
    }
} elseif ($action == 'delete_admin_view') {
    // Only admins can use this
    if ($_SESSION['role'] == 'admin') {
        $msg_id = (int)$_POST['msg_id'];
        $sql = "DELETE FROM messages WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $msg_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            echo "success";
        }
    }
}

mysqli_close($link);
?>
