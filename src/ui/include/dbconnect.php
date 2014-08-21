<?php
try {
	/*** connect to SQLite database ***/
	$dbh = new PDO("sqlite:../db/ui.db");
	}
catch(PDOException $e) {
	echo $e->getMessage();
}
?>