<?php
    $dbname = 'game_website_project';
    $username = 'root';
    $password = '';
    $host = 'localhost';
    $conn = mysqli_connect($host, $username, $password, $dbname);
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
?>