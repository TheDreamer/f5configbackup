<?php
/* RBAC permissions
Add the role ID to the permissions array for the required
level to restrict access. Remove the permissions array to 
allow all. 

$permissions = array(1,2,3);

1 - Administrator
2 - Device Admin
3 - Operator
4 - Guest
*/
$permissions = array(1,2);

include("include/session.php");
include("include/dbconnect.php");
include("include/dbcore.php");

//Which device mod page is this ?
$contents = '';
switch ( $_GET["page"] ) {
   case "Delete" :
      include ("include/device_delete.php");
      $title2 = "Delete Devices";
      break;
   case "Add" :
      include ("include/device_add.php");
      $title2 = "Add Devices";
      break;
   default:
      // if none, redirect to devices page
      header("Location: /devices.php"); 
      die;
};

/* Close DB  */
$dbh = null;
$dbcore = null;

$title = "<a href=\"devices.php\">F5 Devices</a>";

// Page HTML
include("include/framehtml.php");
?>