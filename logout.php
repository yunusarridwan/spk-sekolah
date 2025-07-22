<?php
session_start();
session_destroy();
header("Location: index.php"); // Redirect ke index.php yang baru
exit();
?>
