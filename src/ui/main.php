<?php
include("include/session.php");
include("include/dbconnect.php");

// Title
$title = "<a href=\"/main.php\">Welcome</a>";

// Is page being loaded for an error /
if ( isset($_SERVER['REDIRECT_STATUS']) ) {
	switch ($_SERVER['REDIRECT_STATUS']) {
		case "404" :
			$contents = "<h2>Error 404</h2>\n<p>The page cannot be found.</p>";
         $title = "<a href=\"/main.php\">Error</a>";
         break;
	};
} elseif ( isset($_GET["error"]) ) { // is there an error ?
	switch ($_GET["error"]) {
		case "403"; // Access denied to page
			$page = $_GET["page"];
         $title = "<a href=\"/main.php\">Error</a>";
			$contents = <<<EOD
		<h2>Error 403: Access Denied</h2>
		<p>You do not have access to the page "$page"</p>
EOD;
			break;
	};
} else {
	// If not then go to home page
	$contents = "<p>Welcome to the Config Backup for F5 program.</p>";
};

// Close DB  
$dbh = null;

// HTML template
include("include/framehtml.php");
?>