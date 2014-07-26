<?
// Internal web service connect
function webcheck () {
  //Connect to internal webservice
  $url = 'http://127.0.0.1:5380/api/v1.0/status';
  $curl = curl_init($url);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); 
  json_decode(curl_exec($curl), true);

  //Did any curl errors occur ?
  if (curl_errno($curl)) {
    return '<img style="vertical-align: middle;" src="/images/red_button.png"> Status: OFFLINE';
  };

  // Did server return an error ?
  $rtn_code = curl_getinfo($curl,CURLINFO_HTTP_CODE);
  if ( $rtn_code != 200 ) {
    return '<img style="vertical-align: middle;" src="/images/yellow_button.png"> Status: ERROR';
  };
  
return '<img style="vertical-align: middle;" src="/images/green_button.png"> Status: ONLINE';
  
};

// Make array of role names from DB
$sth = $dbh->query("SELECT ID,NAME FROM ROLES ORDER BY ID");
$sth->execute();
foreach ($sth->fetchAll() as $role) {
	$rolearray[$role['ID']] = $role['NAME'];
};
?>

<html>
<head>
<link rel="stylesheet" type="text/css" href="css/style.css">
<link rel="stylesheet" type="text/css" href="css/main.css">
</head>
<body>
<table class="main">
<tr> <!-- Page Header ------------>
	<td class="header" colspan="2">
		<div id="title"><a href="/">Config Backup for F5</a></div>
		<div id="logout"><a href="/logout.php">Log out</a></div>
		<div id="status"><a href="/status.php"><?= webcheck()?></a></div>
		<div id="user">Username: <?= $_SESSION['user'] ?></div>
		<div id="role">User Role: <?= $rolearray[$_SESSION['role']] ?></div>
		<div id="ip">User IP: <?= $_SERVER['REMOTE_ADDR'] ?></div>
		<div id="date">Date: <?= date('Y-m-d',time()) ?></div>
		<div id="time">Time: <?= date('H:i',time()) ?></div>
	</td>
</tr>


