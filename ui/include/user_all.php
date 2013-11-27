<?php
$title = "Users";  //Page title
// Build user table
$contents = "\t<table class=\"pagelet_table\">\n";
$contents .= "\t\t<tr class=\"pglt_tb_hdr\"><td>Name</td></tr>\n";

// Get list of users from DB
$sql = "SELECT NAME,ID FROM USERS";

// loop through array to make user table
$count = 1;
foreach ($dbh->query($sql) as $row) {
	$name = $row['NAME'];
	$id = $row['ID'];
	$class = "even";
	if ($count & 1 ) {$class = "odd";};
	$contents .= "\t\t<tr class=\"$class\"><td><a href=\"settings.php?page=users&id=$id\">";
	$contents .= "$name</a></td></tr>\n";
	$count++;
};
$contents .= <<<EOD
	</table> 
	<form action="settings.php?" method="get">
	<input type="hidden" name="page" value="users">
	<input type="submit" name="change" value="Add">
	</form> 
EOD;
?>	