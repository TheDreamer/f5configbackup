<?php
include("include/session.php");
include("include/dbconnect.php");

// include common content
include("include/header.php");
include("include/menu.php");


// Is URL requesting device info ?
if ( isset($_GET["id"]) ) {
// If yes show details on device
	// Check ID query param, error page if not number
	$ID = $_GET["id"];
	if (is_numeric($ID)) {
		// Get device from DB
		$sth = $dbh->prepare("SELECT NAME,ID,IP,CID_TIME,DATE_ADDED,LAST_DATE FROM DEVICES WHERE ID = ?");
		$sth->bindParam(1,$ID); 
		$sth->execute();
		$row = $sth->fetch();

		$cid = 0;
		$last_date = 0;
		$name = $row['NAME'];
		$ip = $row['IP'];
		if ($row['CID_TIME'] > 0) {$cid = date('Y-m-d H:i:s',$row['CID_TIME']); };
		$date_added = date('Y-m-d H:i:s',$row['DATE_ADDED']);
		if ($row['LAST_DATE'] > 0) {$last_date = date('Y-m-d H:i:s',$row['LAST_DATE']); };
		if ($ip == "NULL") {$ip = "N/A";}; // set IP to N/A for devices w/o IPs
		
		$contents = <<<EOD
	<table class="pagelet_table">
		<tr class="pglt_tb_hdr"><td>IP Address</td><td>CID Time</td>
		<td>Date Added to DB</td><td>Date of Last Backup</td></tr>
		<tr class="odd"><td>$ip</td><td>$cid</td><td>$date_added</td>
		<td>$last_date</td></tr>
	</table>
	<br />\n
EOD;

	// Create UCS listingg
		$contents .= <<<EOD
	<table class="pagelet_table">
		<tr class="pglt_tb_hdr"><td colspan="3">Archive files</td></tr>\n
EOD;
		// Get list of files from DB
		$sth = $dbh->prepare("SELECT ID,DIR,FILE FROM ARCHIVE WHERE DEVICE = ?"
									."ORDER BY FILE");
		$sth->bindParam(1,$ID); 
		$sth->execute();		
		$count = 0;
		$columns = 0;

		foreach ($sth->fetchAll() as $row) {
			$class = "even";
			$ucs_id = $row['ID'] ;
			$file = $row['FILE'] ;
			if ($columns & 1 ) {$class = "odd";};
			if ($count == 0) {$contents .= "\t\t<tr class=\"$class\">";}; //start new row
			$contents .= "<td><a href=\"download.php?id=$ucs_id\">$file</a></td>";
			$count++;
			if ($count == 3) { // on 3rd loop close row and reset counter
				$contents .= "</tr>\n";
				$count = 0;
				$columns++;
			};
		};
		
		// If row did not complete then fill it in
		if ($count > 0) {
			for ($x=$count; $x<3; $x++) {
				$contents .= "<td></td>";
				if ($x == 2) { $contents .= "</tr>\n";};	// add row end
			};
		};
		$contents .= "\t</table>\n";
	} else {
	// Error message if id is not number
		$contents = "<p><strong>Error:</strong> \"$ID\" is not a valid input</p>\n";
	};
} else {
// If not present device list
	$contents = "\t<table class=\"pagelet_table\">\n";
	$contents .= "\t\t<tr class=\"pglt_tb_hdr\"><td>Name</td><td>IP</td></tr>\n";

	// loop through array to make device table
	$count = 1;
	foreach ($dbh->query("SELECT NAME,ID,IP FROM DEVICES") as $row) {
		$name = $row['NAME'];
		$id = $row['ID'];
		$ip = $row['IP'];
		if ($ip == "NULL") {$ip = "N/A";}; // set IP to N/A for devices w/o IPs
		$class = "even";
		if ($count & 1 ) {$class = "odd";};
		$contents .= "\t\t<tr class=\"$class\"><td><a href=\"devices.php?id=$id\">$name</a>";
		$contents .= "</td><td>$ip</td></tr>\n";
		$count++;
	};
	$contents .= "\t</table> \n";

};

// Page HTML
?>
	<div id="pagelet_title">
		<a href="devices.php">F5 Devices</a> <? if ( isset($_GET["id"]) ) {echo "> $name";} ?> 
	</div>
<?
echo $contents;

include("include/footer.php");

/* Close DB  */
$dbh = null;
?>