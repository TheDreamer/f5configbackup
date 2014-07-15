<?php
include("include/functions.php");
$contents = '';
if (is_numeric($_GET["id"])) {

	// Get user from DB
	$sth = $dbh->prepare("SELECT ID,NAME,ADDED_BY,DATE_ADDED FROM USERS WHERE ID = ?");
	$sth->bindParam(1,$_GET["id"]); 
	$sth->execute();
	$row = $sth->fetch();

	$id = $row['ID'];
	$name = $row['NAME'];
	$created_by = $row['ADDED_BY'];
	$date_added = date('Y-m-d H:i:s',$row['DATE_ADDED']);

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
				$sth = $dbh->prepare("UPDATE USERS SET HASH = ? WHERE ID = $id");
				$sth->bindParam(1,$hash); 
				$sth->execute();
				$post = "<p>User profile $name has been updated.</p>";
			};
		};
		
		// Are we deleting the user
		if ( $_POST["change"] == "Delete" ) {

			switch ( $_POST["confirm"] ) {
				case "Yes" :
					// Yes, delete the user from DB
					$sth = $dbh->prepare("DELETE FROM USERS WHERE ID = $id");
					$sth->execute();
					$post = "<p>User $name has been deleted.</p>";
					break;
				case "No" :
					$contents = "<p>User $name was not deleted.</p>";
					break;
				default:
					$post = <<<EOD
	<p>Are you sure you want to delete user $name ?</p>
	<form action="settings.php?page=users&id=$id" method="post">
	<input type="hidden" name="change" value="Delete">
	<input type="submit" name="confirm" value="Yes">
	<input type="submit" name="confirm" value="No">
	</form>
EOD;
			};
		};		
	};
	
	
	// Page body
	$title = "<a href=\"settings.php?page=users\">Users</a> > $name";
	if ( isset($post) ) { 
		$contents .= $post;
	} else {
		if ( isset($error) ) {$contents .= $error;}; // did an error occur ?
		
		// User info
		$contents .= <<<EOD
	<form action="settings.php?page=users&id=$id" method="post">
	<table class="pagelet_table">
		<tr class="pglt_tb_hdr"><td>Created By</td><td>Date Created</td></tr>
		<tr class="odd"><td>$created_by</td><td>$date_added</td></tr>
		<tr class="even"><td>Change Password</td>
			<td><input type="password" name="password" class="input" maxlength="30" value="nochange"></td>
		</tr>
		<tr class="odd"><td>Confirm Password</td>
			<td><input type="password" name="password2" class="input" maxlength="30" value="nochange"></td>
		</tr>
	</table>
	<input type="submit" name="change" value="Update">
	<input type="submit" name="change" value="Delete">
	</form>
EOD;
	};
} else {
// Error message if id is not number
	$contents .= "<p><strong>Error:</strong> \"$ID\" is not a valid input</p>\n";
};
?>	