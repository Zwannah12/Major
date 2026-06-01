<?php
// Database connection using environment variables (Back4App compatible)

define('DB_SERVER', getenv('DB_HOST'));
define('DB_USERNAME', getenv('DB_USER'));
define('DB_PASSWORD', getenv('DB_PASS'));
define('DB_NAME', getenv('DB_NAME'));

// Attempt to connect to MySQL database
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($link === false) {
    die("ERROR: Could not connect. " . mysqli_connect_error());
}
?>