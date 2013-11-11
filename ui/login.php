<?php
$USER = "user";
$PASSWORD = "password";
session_start();
if ($_SERVER['REQUEST_METHOD'] == "POST" && $_POST["username"] == "$USER" && $_POST["password"] == "$PASSWORD") {
	$_SESSION['clientip'] = $_SERVER['REMOTE_ADDR'];
	$_SESSION['active'] = 1;
	$location = "http://".$_SERVER['HTTP_HOST'].urldecode($_GET["page"]);
	header("Location: $location");
} else {
	if (isset($_GET["page"])) {
	  $URL="?page=".urlencode($_GET["page"]);
	} else {
		$URL = "";
	}
?>
<html>
<head>
<link rel="stylesheet" type="text/css" href="include/style.css">
</head>
<body>
<h1>F5 Config Backup</h1>
<form action="login.php<?=$URL?>" method="post">
<div id="login"><table>
	<tr>
		<td>Username - user,</td>
		<td>Password - password</td>
	</tr>
	<tr>
		<td>Username</td>
		<td><input type="text" name="username" size="16" maxlength="20"></td>
	</tr>
	<tr>
		<td>Password</td>
		<td><input type="password" name="password" size="16" maxlength="20"></td>
	</tr>
	<tr>
		<td align="center" colspan="2">
		<input type="submit" name="submit" value="Log In">
		</td>
	</tr>

	<? if ($_SERVER['REQUEST_METHOD'] == "POST" && ($_POST["username"] != "$USER" || $_POST["password"] != "$PASSWORD")) { ?>
	<tr><td align="center" colspan="2">Bad username or password</td></tr>
	<?}?>

</table>
</div>
</form>
</body>
</html>
<?}?>
