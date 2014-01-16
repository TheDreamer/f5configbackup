<?php
try {
	/*** connect to SQLite database ***/
	$dbh = new PDO("sqlite:../db/main.db");
	}
catch(PDOException $e) {
	echo $e->getMessage();
}
?>