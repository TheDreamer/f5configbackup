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
	// If input contains bad chars then give errors
	if ( bad_chars($_POST["name"]) || bad_chars($_POST["ip"]) ) {
		$contents .= "Device name or IP address can't contain spaces or the following special characters: ' \" = % ; < > --";
	} else {
		$name = $_POST["name"];
		$ip = $_POST["ip"];
		if (isset($_POST["dns"])) { $ip = "NULL"; };
			
		// Are there any blank fields ?
		if ($name != "" && $ip != "") {			
			// If all checks pass then insert into DB
			//if ($error == 0) { 
			$time = time();
			$sth = $dbh->prepare("INSERT INTO DEVICES ('NAME','IP','DATE_ADDED','CID_TIME','LAST_DATE') 
										VALUES (:name,:ip,$time,0,0)");
			$sth->bindValue(':name',$name); 
			$sth->bindValue(':ip',$ip); 
			$sth->execute();
			//};				
			$contents .= "\t<p>Device has been added: $name</p>\n";
		} else {
			// Else display error message
			$contents .= "<p>One or more fields where blank.</p>";
		};
	};
} else {
	$contents = <<<EOD
		<form action="devicemod.php?page=Add" method="post">
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
		</table>
		<input type="submit" name="change" value="Add">
		</form>\n
EOD;
};
?>