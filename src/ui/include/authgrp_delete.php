<?php
# Make group array
foreach ($groups as $i) {
	$grparray[$i['ID']] = $i['NAME'];
};

// Is method POST ?
if ($_SERVER['REQUEST_METHOD'] == "POST") {
// Delete from DB
	// If cancel button is clicked
	if ($_POST["confirm"] == "Cancel") {
		$location = "https://".$_SERVER['HTTP_HOST']."/authgrp.php";
		header("Location: $location");
		die();
	} else {
		// If not then loop though IDs
		$contents .= "\t<p>Auth groups have been removed. </p>\n\t<ul>\n";
		foreach ($_POST["id"] as $i) {
			// Is input numeric ?
			if (is_numeric($i)) {
				//Delete from DB
				$sth = $dbh->prepare("DELETE FROM AUTHGROUPS WHERE ID = ?");
				$sth->bindValue(1,$i); 
				$sth->execute();
			
				// Add to list on page
				$grp = $grparray[$i];
				$contents .= "\t\t<li>$grp\n";
			};
		};
	$contents .= "\t</ul>";
	// Reorder groups after delete
	grp_reorder($dbh);
	};
} else {
//If not post then display confirmation page
	// Check if any groups are selected
	if (isset($_GET["id"])) {
		$inputs = '';
		$contents .= <<<EOD
		<p>Are you sure you want to delete the following auth groups ?</p>
		<ul>
EOD;
		// Loop though array of params
		foreach ($_GET["id"] as $i) {
			if (is_numeric($i)) {
				$grp = $grparray[$i];
				$contents .= "\t\t<li>$grp\n";
				//Create hidden input html
				$inputs .= "\t\t<input type=\"hidden\" name=\"id[]\" value=\"$i\">\n";
			};
		};
		$contents .= <<<EOD
		</ul>
		<form action="authgrpmod.php?page=Delete" method="post">
$inputs
		<input type="submit" name="confirm" value="Yes">
		<input type="submit" name="confirm" value="Cancel">
		</form>
EOD;
	} else {
	// If not then display error message
		$contents .= "<p>No auth groups selected.</p>";
	};
};

?>