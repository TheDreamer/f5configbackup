<?php
include("include/functions.php");
$contents = '';

	// Update post processing
	if ($_SERVER['REQUEST_METHOD'] == "POST") {

		// Is this an update and has password been updated ?
		if ( $_POST["change"] == "Update" && $_POST["password"] != "nochange" ) {
			// Password validation and hash creation
			$hash = password($_POST["password"],$_POST["password2"]);
			if ( isset($PASS_ERR) ) {
				$error = "<p class=\"error\">Error: $PASS_ERR</p>";
			} else {
				// Write hash to DB
				$sth = $dbh->prepare("UPDATE ADMIN SET HASH = ? WHERE ID = 1");
				$sth->bindParam(1,$hash); 
				$sth->execute();
				$post = "<p>Admin password has been updated.</p>";
			};
		} elseif ( $_POST["change"] == "Update" ) {
			$error = "<p>Admin password was not updated.</p>";
		};
	};
	
	// Page body
	$title = "Admin";
	if ( isset($post) ) { 
		$contents .= $post;
	} else {
		if ( isset($error) ) {$contents .= $error;}; // did an error occur ?
		
		// User info
		$contents .= <<<EOD
	<form action="settings.php?page=admin" method="post">
	<table class="pagelet_table">
		<tr class="pglt_tb_hdr"><td colspan="2">Admin User</td></tr>
		<tr class="even"><td>Change Password</td>
			<td><input type="password" name="password" class="input" maxlength="30" value="nochange"></td>
		</tr>
		<tr class="odd"><td>Confirm Password</td>
			<td><input type="password" name="password2" class="input" maxlength="30" value="nochange"></td>
		</tr>
	</table>
	<input type="submit" name="change" value="Update">
	</form>
EOD;
	};
	
?>	