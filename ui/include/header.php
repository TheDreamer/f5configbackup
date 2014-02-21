<html>
<head>
<link rel="stylesheet" type="text/css" href="css/style.css">
</head>
<body>
<table class="main">
<tr> <!-- Page Header ------------>
	<td class="header" colspan="2">
		<div id="title"><a href="/">Config Backup for F5</a></div>
		<div id="logout"><a href="logout.php">Log out</a></div>
		<div id="user">Username: <?= $_SESSION['user'] ?></div>
		<div id="ip">User IP: <?= $_SERVER['REMOTE_ADDR'] ?></div>
	</td>
</tr>