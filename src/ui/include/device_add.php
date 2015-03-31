<?php
$title = "Add Device";

// Check for certain SQL chars
function bad_chars ($input) {
	if ( preg_match('/([\'\"%=;<>\s]|--)/', $input) ) {
		return 1;
	} else {
		return 0;
	};
};

// If post then update DB
if ($_SERVER['REQUEST_METHOD'] == "POST" && $_POST["change"] == "Add") {
	$dsa_set = isset($_POST["DevSpecAcct"]);
	if ($dsa_set) {
		$user = $_POST["user"];
		$password = $_POST["password"];
		$password2 = $_POST["password2"];
	}
	// if all the fields are blank, ignore checkbox
	if ($user == "" && $password == "" && $password2 == "") { $dsa_set = false; }

	// If input contains bad chars then give errors
	if ( bad_chars($_POST["name"]) || bad_chars($_POST["ip"]) ||
	     ($dsa_set && bad_chars($user))) {
	     	if ($dsa_set) {
		    $contents .= "Device name or IP address or Backup user can't contain spaces or the following special characters: ' \" = % ; < > --";
		} else {
		    $contents .= "Device name or IP address can't contain spaces or the following special characters: ' \" = % ; < > --";
		}
	} elseif ($dsa_set && ($password != $password2)) {
		$contents .= "Backup User passwords do not match";
	} elseif ($dsa_set && ($user == "" || $password == "")) {
		$contents .+ "Both the Backup user's name and the Backup user's password need to be provided";
	} else {
		$name = $_POST["name"];
		$ip = $_POST["ip"];
		if (isset($_POST["dns"])) { $ip = "NULL"; };
		if ($dsa_set) {
			// Get encrypted password string
			$pest = new PestJSON('http://127.0.0.1:5380');
			$data = array('string' => $password );
	                $pass = $pest->post('/api/v1.0/crypto/encrypt/',$data);
			$pass = $pass['result'];
		}
			
		// Are there any blank fields ?
		if ($name != "" && $ip != "") {
			// If all checks pass then insert into DB
			//if ($error == 0) { 
			$time = time();

			$dbcore->beginTransaction();

			$sth = $dbcore->prepare("INSERT INTO DEVICES ('NAME','IP','DATE_ADDED','CID_TIME','LAST_DATE') 
										VALUES (:name,:ip,$time,0,0)");
			$sth->bindValue(':name',$name); 
			$sth->bindValue(':ip',$ip); 
			$sth->execute();

			if ($dsa_set) {
				$devid = $dbcore->lastInsertId();

				$sth = $dbcore->prepare("INSERT INTO BACKUP_USER ('ID','NAME','PASS')
									VALUES ($devid,:name,:pass)");
				$sth->bindValue(':name',$user);
				$sth->bindValue(':pass',$pass);
				$sth->execute();
			}

			$dbcore->commit();
			//};				
			$contents .= "\t<p>Device has been added: $name</p>\n";
		} else {
			// Else display error message
			$contents .= "<p>Name or IP fields were blank.</p>";
		};
	};
} else {
	$contents = <<<EOD
		<form action="devicemod.php?page=Add" method="post">
		<script type="text/javascript">
		function myShowHide() {
			var target = document.getElementById("DevSpecAcct");
			var row1 = document.getElementById("DevSpecOne");
			var row2 = document.getElementById("DevSpecTwo");
			var row3 = document.getElementById("DevSpecThree");
			if (target.checked) {
				row1.style.display = "table-row";
				row2.style.display = "table-row";
				row3.style.display = "table-row";
			} else {
				row1.style.display = "none";
				row2.style.display = "none";
				row3.style.display = "none";
			}
		}
		</script>
		<table class="pagelet_table">
			<tr class="pglt_tb_hdr"><td colspan="2">Add New Device</td></tr>
			<tr class="odd">
				<td>Device Name</td>
				<td><input type="text" name="name" class="input" maxlength="50"></td>
			</tr>
			<tr class="even">
				<td>Use DNS name ?</td>
				<td><input type="checkbox" name="dns" value="NULL"></td>
			</tr>
			<tr class="odd">
				<td>IP Address</td>
				<td><input type="text" name="ip" id="ip" class="input" maxlength="20"></td>
			</tr>
			<tr class="even">
				<td>Have Specific Backup Acct ?</td>
				<td><input type="checkbox" name="DevSpecAcct" id="DevSpecAcct" class="input" onclick="myShowHide()"></td>
			</tr>
			<tr class="odd" id="DevSpecOne" style="display:none;">
				<td>Specific Backup User</td>
				<td><input type="text" name="user" id="user" class="input" maxlength="50"></td>
			</tr>
			<tr class="even" id="DevSpecTwo" style="display:none;">
				<td>Specific Backup Password</td>
				<td><input type="password" name="password" id="password" class="input" maxlength="50"></td>
			</tr>
			<tr class="odd" id="DevSpecThree" style="display:none;">
				<td>Confirm Backup Password</td>
				<td><input type="password" name="password2" id="password2" class="input" maxlength="50"></td>
			</tr>
		</table>
		<input type="submit" name="change" value="Add">
		</form>\n
EOD;
};
?>
