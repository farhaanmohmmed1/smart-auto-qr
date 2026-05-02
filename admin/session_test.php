<?php
echo "<h1>Session Test</h1>";
session_start();
$_SESSION['test'] = 'Hello World';
echo "Session ID: " . session_id() . "<br>";
echo "Session started. <a href='session_check.php'>Click here to verify</a>";
?>
