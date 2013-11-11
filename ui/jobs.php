<?php
include("session.php");
include("dbconnect.php");

# include common content
include("include/header.php");
include("include/menu.php");

# Is URL requesting job info ?
if ( ! isset($_GET["id"])) {
# If not present device list
?>
<table class="table">
	<tr><th colspan="2" class="table">Backup Jobs</th></tr>
<?
	# Get list of jobs from DB
	$sql = "SELECT ID,DATE,TIME FROM JOBS";

	# loop through array to make device table
	foreach ($dbh->query($sql) as $row) {
		$id = $row['ID'];
		$date = $row['DATE'];
		$time = date('Y-m-d H:i:s',$row['TIME']);

		echo "	<tr><td><a href=\"jobs.php?id=$id\">$date</a></td><td>$time</td></tr>\n";
	};
	echo "</table> \n";

} else {
# If yes show details on device
	# Check ID query param, error page if not number
	$ID = $_GET["id"];
	if (is_numeric($ID)) {
		# Get device from DB
		$sth = $dbh->prepare("SELECT DATE,TIME,ERRORS,COMPLETE,DEVICE_TOTAL,DEVICE_COMPLETE"
									.",DEVICE_W_ERRORS FROM JOBS WHERE ID = ?");
		$sth->bindParam(1,$ID); 
		$sth->execute();
		$row = $sth->fetch();

		$date = $row['DATE'];
		$time = date('Y-m-d H:i:s',$row['TIME']);
		$errors = $row['ERRORS'];
		$complete = "No";
		if ($row['COMPLETE'] = 1) {$complete = "Yes";};

		$device_w_errors = $row['DEVICE_W_ERRORS'];

		$contents .= "\t<tr><td>$time</td><td>$errors</td><td>$complete</td>";
		$contents .= "<td>$device_w_errors</td></tr>\n";

?>

<table class="table">
	<tr><th colspan="6" class="table">Backup Job <?=$date?> Details</th></tr>
	<tr><th>Time</th><th># of Errors</th><th>Job Complete</th><th>Devices /w Errors</th></tr>
<?=$contents?>
</table>
<h3>Backup Job Log -</h3>
<pre class="log">
<?=file_get_contents("../log/$date-backup.log")?>
</pre>
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