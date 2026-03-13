<?php
// api/db_connect.php

// Database Configuration
$host = 'sql104.ezyro.com'; // Change this if using InfinityFree (e.g., sqlxxx.epizy.com)
$dbname = 'ezyro_41326584_luxe_fashion'; // Your database name
$username = 'ezyro_41326584'; // Database username
$password = 'f01f3e4d8a'; // Database password

try {
    // Create a new PDO instance
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Set PDO error mode to exception for better error handling
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // If connection fails, log it but don't exit to allow fallback code (e.g. JSON files) to run
    $pdo = null;
}
?>
