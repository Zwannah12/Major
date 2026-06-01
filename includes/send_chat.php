<?php
session_start();
require_once "db.php";
require_once "functions.php";

if (!is_logged_in() || $_SERVER["REQUEST_METHOD"] != "POST") {
    exit();
}

$sender_id = $_SESSION['id'];
$receiver_id = (int)$_POST['receiver_id'];
$message = sanitize_input($_POST['message']);

if (!empty($message) && $receiver_id > 0) {
    $sql = "INSERT INTO direct_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "iis", $sender_id, $receiver_id, $message);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

mysqli_close($link);
?>
