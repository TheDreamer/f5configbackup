<?php
// Get settings from DB
$dbh->beginTransaction();

// Get timeout from DB
$sth = $dbh->prepare("SELECT VALUE FROM SETTINGS_INT WHERE NAME = 'timeout'");
$sth->execute();
$timeout = $sth->fetchColumn();

// Get MOTD from DB
$sth = $dbh->prepare("SELECT MOTD FROM MOTD WHERE ID = 1");
$sth->execute();
$motd = $sth->fetchColumn();
$sth = null;
// Commit lookups
$dbh->commit();

// Update values for post
$post = 0;
if ($_SERVER['REQUEST_METHOD'] == "POST") {

	$dbh->beginTransaction();
	
	// Timeout update
	if ( $_POST["timeout"] != $timeout ) {
	// Update timeout if value is new
		$post++;
		$updates .= "Timeout ";
		$timeout = $_POST["timeout"];
		
		// Write new timeout to DB
		$sth = $dbh->prepare("UPDATE SETTINGS_INT SET VALUE = ? WHERE NAME = 'timeout'");
		$sth->bindParam(1,$_POST["timeout"]); 
		$sth->execute();
	};
	
	// MOTD update
	$post_motd = str_replace("\r", '',$_POST["motd"]); // Remove CR
	if ( $post_motd != $motd ) {
	// Update motd if value is new
		$post++;
		$updates .= "Login Banner ";
		$motd = $post_motd;
		
		$sth = $dbh->prepare("UPDATE MOTD SET MOTD = ? WHERE ID = 1");
		$sth->bindParam(1,$post_motd); 
		$sth->execute();
	};	
	
	$dbh->commit();
};

// Page Body
$contents = '';
if ( $post > 0 ) { $contents .= "<p>The following items have been updated: $updates</p>"; };
if ($_SERVER['REQUEST_METHOD'] == "POST" && $post == 0) {
	$contents .= "<p>No settings where updated</p>";
};

$contents .= "\t<form action=\"settings.php?page=general\" method=\"post\">";
$contents .= "\t<table class=\"pagelet_table\">\n";
$contents .= "\t\t<tr class=\"pglt_tb_hdr\"><td>Setting</td><td>Value</td></tr>\n";

// Timeout
$contents .= "\t\t<tr class=\"odd\"><td>Timeout</td><td>";
$contents .= "<input type=\"text\" name=\"timeout\" size=\"10\" maxlength=\"5\" value=\"$timeout\">";
$contents .= "</td></tr>\n";

//Motd
$contents .= "\t\t<tr class=\"even\"><td>Login Banner</td><td>";
$contents .= "<textarea cols=\"35\" rows=\"10\" name=\"motd\">$motd</textarea>";
$contents .= "</td></tr>\n";	


$contents .= "\t</table>\n";
$contents .= "\t<input type=\"submit\" name=\"submit\" value=\"Update\">\n";
$contents .= "\t</form>\n";

$title = "General Settings";
?>	