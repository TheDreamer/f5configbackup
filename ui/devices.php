<?php
include("session.php");
include("dbconnect.php");

# include common content
include("include/header.php");
include("include/menu.php");

# Is URL requesting device info ?
if ( ! isset($_GET["id"])) {
# If not present device list
?>
<br />
<table class="table">
	<tr><th colspan="2" class="table">F5 Devices</th></tr>
<?
	# Get list of devices from DB
	$sql = "SELECT NAME,ID,IP FROM DEVICES";

	# loop through array to make device table
	foreach ($dbh->query($sql) as $row) {
		$name = $row['NAME'];
		$id = $row['ID'];
		$ip = $row['IP'];
		if ($ip == "NULL") {$ip = "N/A";}; # set IP to N/A for devices w/o IPs

		echo "	<tr><td><a href=\"devices.php?id=$id\">$name</a></td><td>$ip</td></tr>\n";
	};
	echo "</table> \n";

} else {
# If yes show details on device
	# Check ID query param, error page if not number
	$ID = $_GET["id"];
	if (is_numeric($ID)) {
		# Get device from DB
		$sth = $dbh->prepare("SELECT NAME,ID,IP,CID_TIME,DATE_ADDED,LAST_DATE FROM DEVICES WHERE ID = ?");
		$sth->bindParam(1,$ID); 
		$sth->execute();
		$row = $sth->fetch();

		$cid = 0;
		$last_date = 0;
		$ip = $row['IP'];
		if ($row['CID_TIME'] > 0) {$cid = date('Y-m-d H:i:s',$row['CID_TIME']); };
		$date_added = date('Y-m-d H:i:s',$row['DATE_ADDED']);
		if ($row['LAST_DATE'] > 0) {$last_date = date('Y-m-d H:i:s',$row['LAST_DATE']); };
		if ($ip == "NULL") {$ip = "N/A";}; # set IP to N/A for devices w/o IPs

		$contents .= "\t<tr><td>$ip</td><td>$cid</td>";
		$contents .= "<td>$date_added</td><td>$last_date</td></tr>\n";

?>

<table class="table">
	<tr><th colspan="4" class="table"><?=$row['NAME']?> Properties</th></tr>
	<tr><td>IP Address</td><td>CID Time</td><td>Date Added to DB</td><td>Date of Last Backup</td></tr>
<?=$contents?>
</table>

<?
	} else {
	# Error message if id is not number
		echo "<p><strong>Error:</strong> \"$ID\" is not a valid input</p>\n";
	};
};

include("include/footer.php");

/* Close DB  */
$dbh = null;
?>