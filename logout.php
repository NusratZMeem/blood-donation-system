<?php
session_start();
unset($_SESSION['bb_user_id']);
unset($_SESSION['bb_role']);
header("Location: index.php");
exit;
?>
