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

// Get page that send to login and add as param of form action url
$URL = "";
if (isset($_GET["page"])) {
  $URL="?page=".urlencode($_GET["page"]);
};

header("Location: /login.php$URL");
?>
