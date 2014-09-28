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
$permissions = array(1);

include("include/session.php");
include("include/dbconnect.php");

// Build role select options
function roleselect ($rarray,$selected) {
   $output = '';
   foreach ($rarray as $key=>$value) {
      $select = '';
      // Make the users current role selected
      if ( $key == $selected ){$select = 'selected';} ;
      $output .= "<option value=\"$key\" $select>$value</option>";
   };
   return $output;
};

// Build Role array
$sth = $dbh->query("SELECT ID,NAME FROM ROLES ORDER BY ID");
$sth->execute();
foreach ($sth->fetchAll() as $role) {
   $rolearray[$role['ID']] = $role['NAME'];
};

// What auth mode is set ?
$sth = $dbh->prepare("SELECT MODE FROM AUTH");
$sth->execute();
$mode = $sth->fetchColumn();

// Is this the default page ?
if ( isset($_GET["page"]) ) {
   // Which 
   switch ( $_GET["page"] ) {
      case "user" :
      // Specific user page
         include ("include/user_single.php");
         break;
      case "Delete" :
         include ("include/user_delete.php");
         $title3 = "Delete User";
         break;
      case "Add" :
         include ("include/user_add.php");
         $title3 = "Add User";
         break;
   };

} else {
   // Default page
   $title = "Users" ;
   // Get list of users from DB
   $sql = "SELECT NAME,ID,ROLE FROM USERS";

   // Build user table
   $contents = <<<EOD
   <form action="users.php" method="get">
   <table class="pagelet_table">
      <tr class="pglt_tb_hdr">
         <td>
            <input type="checkbox" name="" value="" checked disabled="disabled">
         </td>
         <td>Name</td>
         <td>Role</td>
      </tr> 
EOD;

   // loop through array to make user table
   $count = 1;
   foreach ($dbh->query($sql) as $row) {
      $name = $row['NAME'];
      $id = $row['ID'];
      $role = $rolearray[$row['ROLE']]; // Get name of role from array
      $class = "even_ctr";
      if ($count & 1 ) {$class = "odd_ctr";};
      $contents .= <<<EOD
      <tr class="$class">
         <td><input type="checkbox" name="id[]" value="$id"></td>
         <td><a href="users.php?page=user&id=$id">$name</a></td>
         <td >$role</td>
      </tr> 
EOD;
      $count++;
   };
   $contents .= <<<EOD
   </table> 
   <input type="submit" name="page" value="Add">
   <input type="submit" name="page" value="Delete">
   </form> 
EOD;
};

// Close DB 
$dbh = null;

$title = "System";
$title2 = "<a href=\"users.php\">Users </a>";

// Page HTML
include("include/framehtml.php");
?>