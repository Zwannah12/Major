<?php
require_once "includes/db.php";

$email = 'admin@agrosphere.com';
$password = 'password123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$sql = "UPDATE users SET password = ? WHERE email = ?";

if ($stmt = mysqli_prepare($link, $sql)) {
    mysqli_stmt_bind_param($stmt, "ss", $hashed_password, $email);
    if (mysqli_stmt_execute($stmt)) {
        echo "Admin password updated successfully. You can now login with:<br>";
        echo "Email: " . $email . "<br>";
        echo "Password: " . $password . "<br>";
        echo "<br><a href='login.php'>Go to Login</a>";
    } else {
        echo "Error updating password: " . mysqli_error($link);
    }
    mysqli_stmt_close($stmt);
} else {
    echo "Error preparing statement: " . mysqli_error($link);
}

mysqli_close($link);
?>