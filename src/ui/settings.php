<?php
include("include/session.php");
include("include/dbconnect.php");

// include common content
include("include/header.php");
include("include/menu.php");

// Page HTML
?>
	<div id="pagelet_title">
		<a href="settings.php">Settings</a> 
	</div>
	<table class="pagelet_table">
		<tr class="pglt_tb_hdr">
			<td>Settings</td>
		</tr>
		<tr class="odd_ctr">
			<td><a href="generalsettings.php">General</a></td>
		</tr>
		<tr class="even_ctr">
			<td><a href="users.php">Users</a></td>
		</tr>
		<tr class="odd_ctr">
			<td><a href="backupsettings.php">Backups</a></td>
		</tr>
		<tr class="even_ctr">
			<td><a href="admin.php">Admin Password</a></td>
		</tr>
	</table>
<?

include("include/footer.php");

// Close DB 
$dbh = null;
?>