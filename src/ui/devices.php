<?php
include("include/session.php");
include("include/dbconnect.php");
include("include/dbcore.php");

// include common content
include("include/header.php");
include("include/menu.php");

// If id param is present show device details
if ( isset($_GET["id"]) ) {
	include ("include/device_single.php");
} else {
// If prama id not present, the show device list
	$contents = <<<EOD
	<script language='Javascript1.2' src='js/tablesort-min.js'></script>
	<table class="pagelet_table">
		<thead class="pglt_tb_hdr">
			<td>
			<input type="checkbox" name="" value="" checked disabled="disabled">
			</td>
			<td class="nocase">Name</td>
			<td class="nocase">IP</td>
		</thead>
	<form action="devicemod.php" method="get">
EOD;

	// loop through array to make device table
	$count = 1;
	foreach ($dbcore->query("SELECT NAME,ID,IP FROM DEVICES ORDER BY IP") as $row) {
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

// Page HTML
?>
	<div id="pagelet_title">
		<a href="devices.php">F5 Devices</a> <? if ( isset($title) ) {echo "> $title";} ?> 
	</div>
	<div id="pagelet_body">
<?
echo $contents;

echo "</div>";
include("include/footer.php");

/* Close DB  */
$dbh = null;
$dbcore = null;

?>
