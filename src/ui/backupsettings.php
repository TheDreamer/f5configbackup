<?php
include("include/session.php");
include("include/dbconnect.php");

// include common content
include("include/header.php");
include("include/menu.php");

// Get backup settings
$sth = $dbh->query("SELECT NAME,VALUE FROM BACKUP_SETTINGS_INT");
$sth->execute();
foreach ($sth->fetchAll() as $setting) {
	$setarray[$setting['NAME']] = $setting['VALUE'];
};

// Set vars
$ucs = $setarray['UCS_ARCHIVE_SIZE'];
$log = $setarray['LOG_ARCHIVE_SIZE'];
$time = $setarray['BACKUP_TIME']; 
// Get minutes remainder minutes
$time_min = $setarray['BACKUP_TIME'] % 60; 
// Get hours - remainder minutes
$time_hr =  ($setarray['BACKUP_TIME'] - $time_min)/60;

// Get username 
$sth = $dbh->prepare("SELECT NAME FROM BACKUP_USER WHERE ID = 0");
$sth->execute();
$user = $sth->fetchColumn();
	$message = '';


// Is this a POST ?
if ($_SERVER['REQUEST_METHOD'] == "POST") {
	$updates = '';
	$post = 0;

	include("include/backupset_post.php");

	// Was anything updated -- Do something about errors
	if ( $post > 0 ) { 
		$message .= "<p>The following items have been updated: $updates</p>"; 
	} else {
		$message .= "<p>No settings where updated.</p>";
	};

	//Was there any errors /
	if (isset($error) ) {$message = "<p class=\"error\">Error: $error</p>";};
};



$contents = <<<EOD
	<div id="pagelet_title">
		<a href="settings.php">Settings</a> > Backup Settings 
	</div>
	$message
	<form action="backupsettings.php"method="post">
	<table class="pagelet_table">
	<tr class="pglt_tb_hdr"><td>Backup Setting</td><td>Value</td></tr>
	<tr class="odd">
		<td>UCS Archive Size</td>
		<td><input type="text" name="ucs" size="5" maxlength="5" value="$ucs"></td>
	</tr>
	<tr class="even">
		<td>Log Archive Size</td>
		<td><input type="text" name="log" size="5" maxlength="5"  value="$log"></td>
	</tr>
	<tr class="odd">
		<td>Backup Time (Hr:Min)</td>
		<td>
			<input type="text"name="time_hr" size="2" maxlength="2" style="width: 20px;" value="$time_hr">
			<strong>:</strong>
			<input type="text"name="time_min" size="2" maxlength="2" style="width: 20px;" value="$time_min">
			&nbsp 24Hr Format
		</td>
	</tr>
	<tr class="even">
		<td>Backup User Name</td>
		<td><input type="text" name="user" size="15" maxlength="50" value="$user"></td>
	</tr>
	<tr class="odd"><td>Backup User Password</td>
		<td><input type="password" name="password" class="input" maxlength="50" value="nochange"></td>
	</tr>
	<tr class="even"><td>Confirm Password</td>
		<td><input type="password" name="password2" class="input" maxlength="50" value="nochange"></td>
	</tr>
	</table>
	<input type="submit"name="submit"value="Update">
	</form>\n
EOD;

echo $contents;

include("include/footer.php");

// Close DB 
$dbh = null;
?>