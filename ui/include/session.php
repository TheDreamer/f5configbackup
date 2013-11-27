<?
session_start();
if(!(isset( $_SESSION['active']))) {
//If user does not have active session then logout user
	$location = "http://".$_SERVER['HTTP_HOST']."/login.php?page=".urlencode($_SERVER['REQUEST_URI']);
	header("Location: $location");
	die();
} elseif ( $_SESSION['clientip'] != $_SERVER['REMOTE_ADDR'] ) {
// If users IP has changed
	header("Location: /logout.php");
} else {
// Check if the user is timed out
	include("include/dbconnect.php");
	
	// Get timeout value from DB
	$sth = $dbh->prepare("SELECT VALUE FROM SETTINGS_INT WHERE NAME = 'timeout'");
	$sth->execute();
	$timeout = $sth->fetchColumn();
	
	if ( (time() - $_SESSION['time']) > $timeout ) { 
	// If current time - session time is > timeout, logout user	
		header("Location: /logout.php"); 
	} else {
	// If not reset session time
		$_SESSION['time'] = time();
	};
	
	// db disconnect
	$dbh = null;
};
?>
