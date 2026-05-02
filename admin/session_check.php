<?php
session_start();
echo "<h1>Session Check</h1>";
echo "Session ID: " . session_id() . "<br>";
if (isset($_SESSION['test'])) {
    echo "✅ Session persisted! Value: " . $_SESSION['test'];
} else {
    echo "❌ Session did NOT persist!";
}
?>
