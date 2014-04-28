<?php
include("include/session.php");
include("include/dbconnect.php");

# include common content
include("include/header.php");
include("include/menu.php");


# Make device array
$sth = $dbh->query("SELECT NAME,ID FROM DEVICES ORDER BY NAME");
$sth->execute();		
foreach ($sth->fetchAll() as $dev) {
	$devarray[$dev['ID']] = $dev['NAME'];
};

// Are we requesting one cert detail ?
if (isset($_GET["id"]) && is_numeric($_GET["id"])) {
	// Get cert details
	$time = time();
	$sth = $dbh->prepare("SELECT NAME,DEVICE,ISSUER,SN,
									KEY,SUB_C,SUB_S,SUB_L,SUB_O,
									SUB_OU,SUB_CN,EXPIRE 
									FROM CERTS WHERE ID = ?");
	$sth->bindParam(1,$_GET["id"]); 
	$sth->execute();
	
	$row = $sth->fetch();
	$name = $row['NAME'];
	$device = $row['DEVICE'];
	$devname = $devarray[$device];
	$issuer = $row['ISSUER'];
	$sn = $row['SN'];
	$key = $row['KEY'];
	$sub_c = $row['SUB_C'];
	$sub_s = $row['SUB_S'];
	$sub_l = $row['SUB_L'];
	$sub_o = $row['SUB_O'];
	$sub_ou = $row['SUB_OU'];
	$sub_cn = $row['SUB_CN'];
	$title = $sub_cn;

	// Cert expiration
	$expire = date('Y-m-d H:i',$row['EXPIRE']);
	if ($row['EXPIRE'] <= $time) {
	// If cert is expired then make bold red
		$expire = "<strong class=\"error\">$expire (Expired)<strong>";
	} elseif ( ($row['EXPIRE'] - $time) <= 2592000) {
	//Or if cert is expiring within 30 days
		$days = intval(($row['EXPIRE'] - $time) / 86400);
		$expire = "<strong class=\"warning\">$expire (Expires in $days days)<strong>";
	};
	

	$contents = <<<EOD
	<table class="pagelet_table">
		<tr class="pglt_tb_hdr"><td>Info</td><td>Value</td></tr>
		<tr class="odd"><td>Name</td><td>$name</td></tr>
		<tr class="even"><td>Device</td><td><a href="devices.php?page=device&id=$device">$devname</a></td></tr>
		<tr class="odd"><td>Issuer</td><td>$issuer</td></tr>
		<tr class="even"><td>Expiration Date</td><td>$expire</td></tr>		
		<tr class="odd"><td>Serial #</td><td>$sn</td></tr>
		<tr class="even"><td>Key Length</td><td>$key</td></tr>
		<tr class="odd"><td>Subject</td><td><pre>
C=$sub_c
S=$sub_s
L=$sub_l
O=$sub_o
OU=$sub_ou
CN=$sub_cn
</pre>
		</td></tr>
	</table>\n
EOD;
} else {
// Else get all certs
	// If not present device list
	$contents = "\t\t<table class=\"pagelet_table\">\n";
	$contents .= "\t\t<tr class=\"pglt_tb_hdr\"><td>Cert Name</td><td>Device</td>";
	$contents .= "<td>CN</td><td>Expiration</td></tr>\n";

	// Get list of certs from DB
	$sql = "SELECT ID,NAME,DEVICE,SUB_CN,EXPIRE FROM CERTS ORDER BY EXPIRE";
	 

	// loop through array to make cert table
	$count = 1;
	$time = time();
	foreach ($dbh->query($sql) as $row) {
		$id = $row['ID'];
		$name = $row['NAME'];
		$device = $row['DEVICE'];
		$devname = $devarray[$device];
		$cn = $row['SUB_CN'];
		$class = "even";
		if ($count & 1 ) {$class = "odd";};

		$expire = date('Y-m-d H:i',$row['EXPIRE']);
		if ($row['EXPIRE'] <= $time) {
		// If cert is expired then make bold red
			$expire = "<strong class=\"error\">$expire<strong>";
		} elseif ( ($row['EXPIRE'] - $time) <= 2592000) {
		//Or if cert is expiring within 30 days
			$expire = "<strong class=\"warning\">$expire<strong>";
		};
		
		$contents .= "\t\t<tr class=\"$class\"><td><a href=\"certs.php?id=$id\">$name</a>";
		$contents .= "<td><a href=\"devices.php?page=device&id=$device\">$devname</a></td><td>$cn</td><td>$expire</td></tr>\n";
		$count++;
	};
	$contents .= "\t\t</table>\n";
};
# Page HTML
?>
	<div id="pagelet_title">
		<a href="certs.php">Certificates </a><? if ( isset($title) ) {echo "> $title";} ?> 
	</div> 
<?
echo $contents;

include("include/footer.php");

/* Close DB  */
$dbh = null;
?>