<?php
include("include/session.php");

// include common content
include("include/header.php");
include("include/menu.php");

// Is page being loaded for an error /
switch ($_SERVER['REDIRECT_STATUS']) {
	case "404" :
		$contents = "<h2>Error 404</h2>\n<p>The page cannot be found.</p>";
		break;
	default :
		// If not then go to home page
		$contents = "<p>Welcome to the F5 Config Backup program.</p>";
		break;
};

echo $contents."\n";
include("include/footer.php");
?>