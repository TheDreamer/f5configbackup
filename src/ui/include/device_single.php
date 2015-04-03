<?php

// RBAC permissions
$modperms = array(1,2);

include ("include/functions.php");

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
	
	$dsba = 0;
	$dsba_name = "admin";
	$sth = $dbcore->prepare("SELECT COUNT(*) FROM BACKUP_USER WHERE ID = ?");
	$sth->bindParam(1,$ID);
	$sth->execute();

	if ($sth->fetchColumn() > 0) {
		$dsba = 1;

		$sth = $dbcore->prepare("SELECT NAME FROM BACKUP_USER WHERE ID = ?");
		$sth->bindParam(1,$ID);
		$sth->execute();

		$row = $sth->fetch();

		$dsb_name = $row['NAME'];
	}

	// Update post processing
	if ($_SERVER['REQUEST_METHOD'] == "POST" && $_POST["change"] == "Update" && in_array($_SESSION['role'],$modperms)) {
		$dbcore->beginTransaction();
		$post = 0;
		$post_message = '';

		$new_ip = $_POST["ip"];
		if (isset($_POST["dns"])) { $new_ip = ($ip == "DNS") ? "DNS":"NULL"; };

		$new_dsba = isset($_POST["dsba_check"]);
		if ($new_dsba) {
			$new_user = $_POST["user"];
			$new_passwd = $_POST["password"];
			$new_passwd2 = $_POST["password2"];
		}
		// if all the fields are blank, ignore checkbox
		if ($new_user == "" && $new_passwd == "" && $new_passwd2 == "") { $new_dsba = false; }

		try{
			// Has device IP changed?
			if ($new_ip != $ip) {
				if (bad_chars($new_ip)) {
					throw new Exception('Device IP can\'t contain spaces or the following special characters \' " = % ; < > --');
				}
				$sth = $dbcore->prepare("UPDATE DEVICES SET IP = ? WHERE ID = ?");
				$sth->bindParam(1,$new_ip);
				$sth->bindParam(2,$ID);
				$sth->execute();
				$post_message .= ($new_ip == "NULL") ? '"Use DNS Name" ' : '"Device IP" ';
				$post ++;
				$ip = ($new_ip == "NULL") ? "DNS" : $new_ip;
			}

			// dsba ?
			if ($new_dsba && $dsba) {
				// update
				// validate $new_user
				if (($new_user != $dsb_name) && bad_chars($new_user)) {
					throw new Exception('Device Backup User can\'t contain spaces or the following special characters \' " = % ; < > --');
				}
				if ($new_user == "") { $new_user = $dsb_name; };
				if ($new_passwd != "nochange" && $new_passwd != "") {
					if ($new_passwd != $new_passwd2) {
						throw new Exception('Device Backup Passwords don\'t match');
					}
					$pest = new PestJSON('http://127.0.0.1:5380');
					$data = array('string' => $new_passwd);
					$pass = $pest->post('/api/v1.0/crypto/encrypt/',$data);
					$pass = $pass['result'];

					$sth = $dbcore->prepare("UPDATE OR IGNORE BACKUP_USER SET NAME = ?, PASS = ? WHERE ID = ?");
					$sth->bindParam(1,$new_user);
					$sth->bindParam(2,$pass);
					$sth->bindParam(3,$ID);
					$sth->execute();
					if ($new_user != $dsb_name) {
						$post_message .= '"Device Backup User Name" ';
						$post ++;
						$dsb_name = $new_user;
					}
					$post_message .= '"Device Backup User Password" ';
					$post ++;
				} else if ($new_user != $dsb_name) {
					$sth = $dbcore->prepare("UPDATE OR IGNORE BACKUP_USER SET NAME = ? WHERE ID = ?");
					$sth->bindParam(1,$new_user);
					$sth->bindParam(2,$ID);
					$sth->execute();
					$post_message .= '"Device Backup User Name" ';
					$post ++;
					$dsb_name = $new_user;
				}
			} else if ($new_dsba) {
				// insert
				// validate $new_user
				if (bad_chars($new_user)) {
					throw new Exception('Device Backup User can\'t contain spaces or the following special characters \' " = % ; < > --');
				}
				if ($new_user == "" || $new_passwd == "" || $new_passwd == "nochange") {
					throw new Exception('Device Backup User or Passwords are missing or invalid');
				}
				if ($new_passwd != $new_passwd2) {
					throw new Exception('Device Backup Passwords don\'t match');
				}
				$pest = new PestJSON('http://127.0.0.1:5380');
				$data = array('string' => $new_passwd);
				$pass = $pest->post('/api/v1.0/crypto/encrypt/',$data);
				$pass = $pass['result'];

				$sth = $dbcore->prepare("INSERT OR REPLACE INTO BACKUP_USER ('ID','NAME','PASS') VALUES ($ID,:name,:pass)");
				$sth->bindValues(':name',$new_user);
				$sth->bindValues(':pass',$pass);
				$sth->execute();
				$post_message .= '"Device Specific Backup Account" ';
				$post ++;
			} else if ($dsba) {
				// delete
				$sth = $dbcore->prepare("DELETE FROM BACKUP_USER WHERE ID = ?");
				$sth->bindParam(1,$ID);
				$sth->execute();
				$post_message .= '"Removed Device Specific Backup Account" ';
				$post ++;
			}

			// Was anything updated -- Do something about errors
			if ( $post > 0 ) {
				$post_message = "<p>The folloing items have been updated: $post_message</p>";
			} else {
				$post_message = "<p>Nothing here was updated.</p>";
			}

			$dbcore->commit();
		} catch (Exception $e) {
			$dbcore->rollBack();
			$post_message = $e->getMessage();
			$post_message = "<p class=\"error\"><strong>Error:</strong> $post_message </p>\n";
			$post = 1;
		};
	};

	$contents = '';
	if ( isset($post) && $post > 0) {
		$contents .= $post_message;
	};

	$contents .= <<<EOD
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

	if (in_array($_SESSION['role'],$modperms)
	{
		$dnschecked = ($ip == "DNS") ? "checked" : "";
		$showip = ($ip == "DNS") ? "" : $ip;
		$dsbachecked = ($dsba) ? "checked" : "";
		$dsb_ipass = ($dsba) ? "nochange" : "";

	$contents .= <<<EOD
<br />
<form action "devices.php?page=device&id=$ID" method="post">
<script type="text/javascript">
function dShowHideDSBAf() {
	var target = document.getElementById("dsba_check");
	var trs = document.getElementsByTagName("tr");
	
	for (i=0;i<trs.length;i++) {
		if (trs[i].getAttribute("name") == "dsba") {
			trs[i].style.display = ((target.checked) ? '' : 'none');
		}
	}
}
function dShowHideDMform() {
	var target = document.getElementById("dmodify");
	var target2 = document.getElementById("dsba_check");
	var trs = document.getElementsByTagName("tr");
	var submit = document.getElementById("dmsubmit");
	var gAttr;

	for (i=0;i<trs.length;i++) {
		if ((gAttr = trs[i].getAttribute("name")) == "dmod") {
			trs[i].style.display = ((target.checked) ? '' : 'none');
		} else if (gAttr == "dsba") {
			trs[i].style.display = ((target2.checked) ? '' : 'none');
		}
	}
	dmsubmit.style.display = ((target.checked) ? '' : 'none');
	dmsubmit.disabled = ((target.checked) ? false:true);
}
function dCheckPass() {
	// Store the password field objects into variables
	var pass1 = document.getElementById('password');
	var pass2 = document.getElementById('password2');
	// Store the Confirmation Message Object
	var message = document.getElementById('dsb_pass_ok');
	// Set the colors we will be using ...
	var goodColor = "#859900";
	var badColor = "#dc322f";
	// Compare the values in the password fields and the confirmation field
	if(pass1.value == pass2.value) {
		pass2.style.backgroundColor = goodColor;
		message.style.color = goodColor;
		message.innerHTML = "Match!";
	} else {
		pass2.style.backgroundColor = badColor;
		message.style.color = badColor;
		message.innerHTML = "No Match!";
	}
}
</script>
<table class="pagelet_table">
	<tr class="pglt_tb_hdr">
		<td>Modify Device Configuration</td>
		<td><input type="checkbox" name="dmodify" id="dmodify" value="NULL" onclick="dShowHideDMform()"></td>
	</tr>
	<tr name="dmod" class="odd" style="display:none;">
		<td>Device Name</td>
		<td><input type="text" name="name" class="input" maxlength="50" value="$name" readonly="readonly"></td>
	</tr>
	<tr name="dmod" class="even" style="display:none;">
		<td>Use DNS name ?</td>
		<td><input type="checkbox" name="dns" value="NULL" $dnschecked></td>
	</tr>
	<tr name="dmod" class="odd" style="display:none;">
		<td>IP Address</td>
		<td><input type="text" name="ip" id="ip" class="input" maxlength="20" value="$showip"></td>
	</tr>
	<tr name="dmod" class="even" style="display:none;">
		<td>Device Specific Backup Account ?</td>
		<td><input type="checkbox" name="dsba_check" id="dsba_check" value="NULL" $dsbachecked onclick="dShowHideDSBAf()"></td>
	</tr>
	<tr name="dsba" class="odd" style="display:none;">
		<td>Specific Backup User</td>
		<td><input type="text" name="user" id="user" class="input" maxlength="50" value="$dsb_name"></td>
	</tr>
	<tr name="dsba" class="even" style="display:none;">
		<td>Specific Backup Password</td>
		<td><input type="password" name="password" id="password" class="input" maxlength="50" value="$dsb_ipass"></td>
	</tr>
	<tr name="dsba" class="odd" style="display:none;">
		<td>Confirm Backup Password</td>
		<td><input type="password" name="password2" id="password2" class="input" maxlength="50" value="$dsb_ipass" onkeyup="dCheckPass()"><span id="dsb_pass_ok" class="dsb_pass_ok"></span></td>
	</tr>
</table>
<input type="submit" name="change" id="dmsubmit" value="Update" style="display:none;" disabled>
</form>
EOD;
	}
} else {
// Error message if id is not number
	$contents = "<p><strong>Error:</strong> \"$ID\" is not a valid input</p>\n";
};
?>
