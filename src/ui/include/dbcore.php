<?php
try {
   // connect to SQLite DB for backup core program
   $dbcore = new PDO("sqlite:../db/main.db");
   }
catch(PDOException $e) {
   echo $e->getMessage();
}
?>