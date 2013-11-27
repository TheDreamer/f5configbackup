<?php
include("include/session.php");
include("include/dbconnect.php");

# include common content
include("include/header.php");
include("include/menu.php");

# Is this request for the main page?
$main_page = 1;
if (isset($_GET["id"])) { $main_page = 0 ;};

# Is URL requesting job info ?
if ( $main_page ) {
# If not present device list
	$contents = "<table class=\"pagelet_table\">\n";
	$contents .= "\t<tr class=\"pglt_tb_hdr\"><td>Date</td></tr>\n";

	# Get list of jobs from DB
	$sql = "SELECT ID,DATE,TIME FROM JOBS";

	# loop through array to make device table
	$count = 1;
	foreach ($dbh->query($sql) as $row) {
		$id = $row['ID'];
		$date = $row['DATE'];
		$time = date('Y-m-d H:i:s',$row['TIME']);
		$class = "even";
		if ($count & 1 ) {$class = "odd";};
		
		$contents .= "\t<tr class=\"$class\"><td><a href=\"jobs.php?id=$id\">$date</a></tr>\n";
		$count++;
	};
	$contents .= "</table> \n";

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

		$contents = "<table class=\"pagelet_table\">\n";
		$contents .= "\t<tr class=\"pglt_tb_hdr\"><td>Time</td><td># of Errors</td><td>Job Complete</td>";
		$contents .= "<td>Devices /w Errors</td></tr>";
		$contents .= "\t<tr class=\"odd\"><td>$time</td><td>$errors</td><td>$complete</td>";
		$contents .= "<td>$device_w_errors</td></tr>\n";
		$contents .= "</table>\n";
		$contents .= "<h3>Backup Job Log -</h3>\n";
		$contents .= "<pre class=\"log\">\n";
		$contents .= file_get_contents("../log/$date-backup.log");
		$contents .= "</pre>";
	} else {
	# Error message if id is not number
		$contents = "<p><strong>Error:</strong> \"$ID\" is not a valid input</p>\n";
	};
};

# Page HTML
?>
<div id="pagelet_title">
	<a href="jobs.php">Backup Jobs</a>
	<? if ( ! $main_page ) {echo "> $date";} ?>
</div>
<?
echo $contents;

include("include/footer.php");

/* Close DB  */
$dbh = null;
?>