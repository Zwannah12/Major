<?php
require_once "includes/db.php";

$sql = "ALTER TABLE users ADD COLUMN is_verified BOOLEAN DEFAULT FALSE";

if (mysqli_query($link, $sql)) {
    // Also mark the existing sample admin and some users as verified for convenience
    mysqli_query($link, "UPDATE users SET is_verified = TRUE WHERE role = 'admin'");
    echo "Database updated successfully: Added 'is_verified' column to 'users' table.";
} else {
    // Check if it already exists to avoid error on re-run
    if (mysqli_errno($link) == 1060) {
        echo "Column 'is_verified' already exists.";
    } else {
        echo "Error updating database: " . mysqli_error($link);
    }
}

mysqli_close($link);
?>