<?php
try {
	// connect to SQLite DB for backup core program
	$dbcore = new PDO("sqlite:../db/main.db");
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
	}
catch(PDOException $e) {
	echo $e->getMessage();
}
?>