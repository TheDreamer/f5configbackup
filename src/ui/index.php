<?php
include("include/session.php");

// include common content
include("include/header.php");
include("include/menu.php");

// Is page being loaded for an error /
if ( isset($_SERVER['REDIRECT_STATUS']) ) {
	switch ($_SERVER['REDIRECT_STATUS']) {
		case "404" :
			$contents = "<h2>Error 404</h2>\n<p>The page cannot be found.</p>";
			break;
	};
} else {
	// If not then go to home page
	$contents = "<p>Welcome to the Config Backup for F5 program.</p>";
};

echo $contents."\n";
include("include/footer.php");
?>