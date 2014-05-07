<?php
include("include/session.php");
include("include/dbconnect.php");

// include common content
include("include/header.php");
include("include/menu.php");

if ( isset($_GET["page"]) ) {
	switch ( $_GET["page"] ) {
		case "device" :
		// General settings page
			include ("include/device_single.php");		
			break;
		case "Delete" :
			include ("include/device_delete.php");
			break;
		case "Add" :
			include ("include/device_add.php");
			break;
	};
} else {
// If prama id not present, the show device list
	$contents = "\t<table class=\"pagelet_table\">\n\t\t<tr class=\"pglt_tb_hdr\"><td>";
	$contents .= "<input type=\"checkbox\" name=\"\" value=\"\" checked disabled=\"disabled\">";
	$contents .= "</td><td>Name</td><td>IP</td></tr>\n";
	$contents .= "\t\t<form action=\"devices.php\" method=\"get\">\n";

	// loop through array to make device table
	$count = 1;
	foreach ($dbh->query("SELECT NAME,ID,IP FROM DEVICES ORDER BY NAME") as $row) {
		$name = $row['NAME'];
		$id = $row['ID'];
		$ip = $row['IP'];
		if ($ip == "NULL") {$ip = "N/A";}; // set IP to N/A for devices w/o IPs
		$class = "even";
		if ($count & 1 ) {$class = "odd";};
		$contents .= "\t\t<tr class=\"$class\">\n\t\t\t<td><input type=\"checkbox\" name=\"id[]\" value=\"$id\"></td><td>";
		$contents .= "<a href=\"devices.php?page=device&id=$id\">$name</a></td>\n\t\t\t<td>$ip</td>\n\t\t</tr>\n";
		$count++;
	};
	$contents .= "\t</table>\n\t<input type=\"submit\" name=\"page\" value=\"Add\">\n";
	$contents .= "\t<input type=\"submit\" name=\"page\" value=\"Delete\">\n";
	$contents .= "\t</form> \n";
};

// Page HTML
?>
	<div id="pagelet_title">
		<a href="devices.php">F5 Devices</a> <? if ( isset($title) ) {echo "> $title";} ?> 
	</div>
<?
echo $contents;

include("include/footer.php");

/* Close DB  */
$dbh = null;
?>