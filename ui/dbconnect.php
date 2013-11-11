<?php
try {
	/*** connect to SQLite database ***/
	$dbh = new PDO("sqlite:../main.db");
	}
catch(PDOException $e) {
	echo $e->getMessage();
}
?>