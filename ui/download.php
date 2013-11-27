<?php

include("include/session.php");
include("include/dbconnect.php");

// Does request have file id param and is numeric ?
if ( isset($_GET["id"]) && is_numeric($_GET["id"]) ) {
		// Get file name from DB
		$sth = $dbh->prepare("SELECT DIR,FILE FROM ARCHIVE WHERE ID = ?");
		$sth->bindParam(1,$_GET["id"]); 
		$sth->execute();
		$row = $sth->fetch();
		
		$file = $row['DIR'].'/'.$row['FILE'];
		if (file_exists($file)) {
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename='.basename($row['FILE']));
			header('Cache-Control: no-cache');
			header('Content-Length:'.filesize($file));
			ob_clean();
			flush();
			readfile($file);
			exit;
		} else {
			// 404 error if file does not exist
			header("HTTP/1.0 404 Not Found");
			echo <<<EOD
	<h1>File not found.</h1>
	<p>Can't find file $file.</p>
				
EOD;
		};
} else {
	header("HTTP/1.0 404 Not Found");
};

/* Close DB  */
$dbh = null;
?>
