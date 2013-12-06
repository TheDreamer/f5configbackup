<?php
session_start();
session_regenerate_id(true);
if(isset( $_SESSION['active'])) {
	unset($_SESSION['clientip']);
	unset($_SESSION['active']);
	unset($_SESSION['user']);
	unset($_SESSION['time']);
	session_destroy();
}
header("Location: /login.php");
?>
