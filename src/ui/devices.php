<?php
include("include/session.php");
include("include/dbconnect.php");
include("include/dbcore.php");

// If id param is present show device details
if ( isset($_GET["id"]) ) {
	include ("include/device_single.php");
} else {
// If prama id not present, the show device list
	$contents = <<<EOD
	<table class="pagelet_table">
		<tr class="pglt_tb_hdr">
			<td>
			<input type="checkbox" checked disabled="disabled">
			</td>
			<td>Name</td>
			<td>IP</td>
		</tr>
	<form action="devicemod.php" method="get">
EOD;

	// loop through array to make device table
	$count = 1;
	foreach ($dbcore->query("SELECT NAME,ID,IP FROM DEVICES ORDER BY NAME") as $row) {
		$name = $row['NAME'];
		$id = $row['ID'];
		$ip = $row['IP'];
		if ($ip == "NULL") {$ip = "DNS";}; // set IP to DNS for devices w/o IPs
		$class = "even";
		if ($count & 1 ) {$class = "odd";};
		$contents .= <<<EOD
		<tr class="$class">
			<td><input type="checkbox" name="id[]" value="$id">
			</td>
			<td>
				<a href="devices.php?page=device&id=$id">$name</a>
			</td>
			<td>$ip</td>
		</tr> 
EOD;
		$count++;
	};
	$contents .= <<<EOD
	</table>
	<input type="submit" name="page" value="Add">
	<input type="submit" name="page" value="Delete">
	</form> 
EOD;
};

/* Close DB  */
$dbh = null;
$dbcore = null;

$title = "<a href=\"devices.php\">F5 Devices</a>";

// Page HTML
include("include/framehtml.php");
?>