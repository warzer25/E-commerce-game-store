<?php
session_start();

// Destroy all session data
session_unset();     // removes all session variables
session_destroy();   // destroys the session

// Redirect to homepage
header("Location: index.php");
exit;
?>