<?php
// Check ID query param, error page if not number
$ID = $_GET["id"];
if (is_numeric($ID)) {
	// Get device from DB
	$sth = $dbcore->prepare("SELECT NAME,ID,IP,CID_TIME,DATE_ADDED,LAST_DATE,
								VERSION,BUILD,MODEL,HOSTNAME,DEV_TYPE,SERIAL,ACT_PARTITION
								FROM DEVICES WHERE ID = ?");
	$sth->bindParam(1,$ID); 
	$sth->execute();
	$row = $sth->fetch();

	$cid = 0;
	$last_date = 0;
	$name = $row['NAME'];
	$title = $name;
	$ip = $row['IP'];
	if ($row['CID_TIME'] > 0) {$cid = date('Y-m-d H:i:s',$row['CID_TIME']); };
	$date_added = date('Y-m-d H:i:s',$row['DATE_ADDED']);
	if ($row['LAST_DATE'] > 0) {$last_date = date('Y-m-d H:i:s',$row['LAST_DATE']); };
	if ($ip == "NULL") {$ip = "DNS";}; // set IP to DNS for devices w/o IPs
	$ver = $row['VERSION'];
	$build = $row['BUILD'];
	$model = $row['MODEL'];
	$host = $row['HOSTNAME'];
	$type = $row['DEV_TYPE'];
	$serial = $row['SERIAL'];
	$part = $row['ACT_PARTITION'];
	
	$contents = <<<EOD
<table class="pagelet_table">
	<tr class="pglt_tb_hdr">
		<td>IP Address</td><td>Hostname on Device</td><td>Date Added to DB</td>
	</tr>
	<tr class="odd">
		<td>$ip</td><td>$host</td><td>$date_added</td>
	</tr>
</table>
<br />
<strong>Hardware Details</strong>
<table class="pagelet_table">
	<tr class="pglt_tb_hdr">
		<td>Model</td><td>Device Type</td><td>Serial #</td>
	</tr>
	<tr class="odd">
		<td>$model</td><td>$type</td>
		<td>$serial</td>
	</tr>
</table>
<br />
<strong>Software Details</strong>
<table class="pagelet_table">
	<tr class="pglt_tb_hdr">
		<td>Version</td><td>Build</td><td>Active Partition</td>
	</tr>
	<tr class="odd">
		<td>$ver</td><td>$build</td><td>$part</td>
	</tr>
</table>
<br />
<strong>Backup Info</strong>
<table class="pagelet_table">
	<tr class="pglt_tb_hdr">
		<td>CID Time</td><td>Date of Last Backup</td>
	</tr>
	<tr class="odd">
		<td>$cid</td><td>$last_date</td>
	</tr>
</table>
<br />\n
EOD;
//		<td>Hostname (on box)</td><td>$host</td>
// Create UCS listingg
	$contents .= <<<EOD
<table class="pagelet_table">
	<tr class="pglt_tb_hdr"><td colspan="3">Archive files</td></tr>\n
EOD;
	// Get list of files from DB
	$sth = $dbcore->prepare("SELECT ID,DIR,FILE FROM ARCHIVE WHERE DEVICE = ?"
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
?>