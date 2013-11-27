<?php
session_start();
include("include/dbconnect.php");
include("include/functions.php");

// login post
if ($_SERVER['REQUEST_METHOD'] == "POST") {
	$login = 1;
	// If any bad chars in post contents dont allow DB lookup
	if ( bad_chars($_POST["username"]) || bad_chars($_POST["password"]) ) {
		$injection = 1;
		$error = "Input will not accept those special characters!";
	} else {
		// admin login
		if ($_POST["username"] == "admin") {
			$sth = $dbh->prepare("SELECT HASH FROM ADMIN WHERE ID = 1");
			$sth->execute();
			$db_hash = $sth->fetchColumn();
		} else {
		// other user login
			// is user valid ??
			$sth = $dbh->prepare("SELECT count(HASH) FROM USERS WHERE NAME = ?");
			$sth->bindParam(1,$_POST["username"]); 
			$sth->execute();
			$user_valid = $sth->fetchColumn();
			$sth = null;
			
			if ( $user_valid ) {
			// if user is valid get hash
				$sth = $dbh->prepare("SELECT HASH FROM USERS WHERE NAME = ?");
				$sth->bindParam(1,$_POST["username"]); 
				$sth->execute();
				$db_hash = $sth->fetchColumn();
			} else {
			// otherwise null hash
				$db_hash = "null";
			};
		};
	// Hashed input password
	$post_hash = crypt($_POST["password"], $db_hash);
	};
};

// If user POST and not injecting and new hash eq hash from db	 
if (isset($login) && ! isset($injection) && $db_hash == $post_hash ) {
	$_SESSION['clientip'] = $_SERVER['REMOTE_ADDR'];
	$_SESSION['active'] = 1;
	$_SESSION['user'] = $_POST["username"];
	$_SESSION['time'] = time();
	$location = "http://".$_SERVER['HTTP_HOST'].urldecode($_GET["page"]);
	header("Location: $location");
} else {
// Otherwise give them the login page
	// Get page that send to login and add as param of form action url
	$URL = "";
	if (isset($_GET["page"])) {
	  $URL="?page=".urlencode($_GET["page"]);
	};

	// Get MOTD from DB
	$sth = $dbh->prepare("SELECT MOTD FROM MOTD WHERE ID = 1");
	$sth->execute();
	$motd = $sth->fetchColumn();
	
	// Display error if POST and not injecting 
	if ( isset($login) && ! isset($injection) ) {
		$error = "Bad username or password.";
	};

?>
<html>
<head>
<link rel="stylesheet" type="text/css" href="css/login.css">
</head>
<body>
<div id="login">
	<div id="header">
		<div id="title">F5 Config Backup</div>
		<div id="title2">F5 Backups. Your way. <sup>( ! TM)</sup></div>
	</div>
	<div id="body">
		<div id="form">
			<form action="login.php<?=$URL?>" method="post">
			<p>
			Username<br />
			<input type="text" name="username" class="input" maxlength="20">
			</p><p>
			Password<br />
			<input type="password" name="password" class="input" maxlength="30">
			</p>
			<input type="submit" name="submit" value="Log In">
			</form>
	<? // Error messages
	if (isset($error)) { 
	?>
			<p id="error"><?= $error ?></p>
	<?}?>
		</div>
		<div id="message">
			<div style="padding-left:10px"><pre><?= $motd ?></pre></div>
		</div>
	</div>
</div>
</body>
</html>
<?
};

// Close DB
$dbh = null;
?>