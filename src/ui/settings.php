<?php
include("include/session.php");
include("include/dbconnect.php");

// include common content
include("include/header.php");
include("include/menu.php");

if ( isset($_GET["page"]) ) {
	// Which settings page is this ?
	switch ( $_GET["page"] ) {
		case "general" :
		// General settings page
			include ("include/general.php");		
			break;
		case "users" :
			if ( isset($_GET["id"]) ) {
			// Specific user page
				include ("include/user_single.php");
			} elseif ( $_GET["change"] == "Add" ) {
			// Add new user page
				include ("include/user_add.php");
			} else {
			// User mgmt page
				include ("include/user_all.php");
			};
			break;
		case "admin" :
			// Admin password page is user is admin
				if ($_SESSION['user'] == "admin" ) {
					include ("include/admin.php");
				} else {
					$contents = "<p class=\"error\">Error: Only admin user can change admin password!</p>";
				};
			break;
	};

} else {
	// Main page
		$admin = '';
		if ($_SESSION['user'] == "admin" ) { $admin = '<tr class="even_ctr"><td><a href="settings.php?page=admin">Admin User</a></td></tr>';};

		$contents = <<<EOD
		<table class="pagelet_table">
		<tr class="pglt_tb_hdr"><td>Settings</td></tr>
		<tr class="odd_ctr"><td><a href="settings.php?page=general">General</a></td></tr>
		<tr class="even_ctr"><td><a href="settings.php?page=users">Users</a></td></tr>
		<tr class="odd_ctr"><td><a href="backupsettings.php">Backups</a></td></tr>
		$admin 
	</table> \n
EOD;
};

// Page HTML
?>
	<div id="pagelet_title">
		<a href="settings.php">Settings</a> <? if ( isset($title) ) {echo "> $title";} ?> 
	</div>
<?
echo $contents;

include("include/footer.php");

// Close DB 
$dbh = null;
?>