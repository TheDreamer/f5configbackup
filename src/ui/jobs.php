<?php
include("include/session.php");
include("include/dbconnect.php");
include("include/dbcore.php");

// Is this request for the main page?
$main_page = 1;
if (isset($_GET["id"])) { $main_page = 0 ;};

// Is URL requesting job info ?
if ( $main_page ) {
// If not present device list
   $contents = "<table class=\"pagelet_table\">\n";
   $contents .= "\t<tr class=\"pglt_tb_hdr\"><td>Date</td></tr>\n";

   // Get list of jobs from DB
   $sql = "SELECT ID,DATE,TIME FROM JOBS ORDER BY ID DESC";

   // loop through array to make device table
   $count = 1;
   foreach ($dbcore->query($sql) as $row) {
      $id = $row['ID'];
      $date = $row['DATE'];
      $time = date('Y-m-d H:i:s',$row['TIME']);
      $class = "even_ctr";
      if ($count & 1 ) {$class = "odd_ctr";};
      
      $contents .= "\t<tr class=\"$class\"><td><a href=\"jobs.php?id=$id\">$date</a></tr>\n";
      $count++;
   };
   $contents .= "</table> \n";

} else {
// If yes show details on job
   // Check ID query param, error page if not number
   $ID = $_GET["id"];
   if (is_numeric($ID)) {
      
      // Are we doing a reverse sort of the log table?
      $sort = "&sort=change";
      $sortchar = '&#8681;';
      if ( isset($_GET["sort"]) ) { 
         $sort = ''; 
         $sortchar = '&#8679;';
      };
      
      // Get jobs from DB
      $sth = $dbcore->prepare("SELECT DATE,TIME,ERRORS,COMPLETE,DEVICE_TOTAL,DEVICE_COMPLETE"
                           .",DEVICE_W_ERRORS FROM JOBS WHERE ID = ?");
      $sth->bindParam(1,$ID); 
      $sth->execute();
      $row = $sth->fetch();

      $date = $row['DATE'];
      $time = date('Y-m-d H:i:s',$row['TIME']);
      $errors = $row['ERRORS'];
      $complete = "No";
      if ($row['COMPLETE'] == 1) {$complete = "Yes";};
      $device_w_errors = explode(' ', $row['DEVICE_W_ERRORS']);

      // Get device ID for hyperlink to device w/ errors
      $error_list = '';
      foreach ($device_w_errors as $i) {
         $sth = $dbcore->prepare("SELECT NAME FROM DEVICES WHERE ID = '$i'");
         $sth->execute();
         $devname = $sth->fetchColumn();
         $error_list .= "<a href=\"/devices.php?page=device&id=$i\">$devname</a> &nbsp";
      };

      $contents = <<<EOD
   <table class="pagelet_table">
      <tr class="pglt_tb_hdr">
         <td>Time</td><td># of Errors</td><td>Job Complete</td><td>Devices /w Errors</td>
      </tr>
      <tr class="odd_ctr">
         <td>$time</td><td>$errors</td><td>$complete</td><td>$error_list</td>
      </tr>
   </table>
   <h3>Backup Job Log</h3>
      <table class="pagelet_table">
      <tr class="pglt_tb_hdr">
         <td><a href="/jobs.php?id=$ID$sort">Date/Time  $sortchar </a></td>
         <td>Level</td>
         <td>Message</td>
      </tr>\n   
EOD;
      // Build Log file view
      $log_file = htmlspecialchars(file_get_contents("../log/$date-backup.log"));
      $log_file = explode("\n",$log_file);
      
      // Reverse sort ?
      if ( isset($_GET["sort"]) ) { $log_file = array_reverse($log_file); };

      $count = 1;
      foreach ($log_file as $i) {
         if ($i == '') { continue; };
         $class = "even";
         if ($count & 1 ) {$class = "odd";};
         $row = explode(': ',$i,2); // Split message from time stamp
         $logmsg = $row[1];
         list($logdate,$logtime,$loglvl) = explode(' ',$row[0]); 
         
         $contents .= <<<EOD
      <tr class="$class">
         <td> $logdate &nbsp; $logtime </td>
         <td> $loglvl </td>
         <td> $logmsg </td>
      </tr>\n
EOD;
         $count++;
      };
      $contents .= "\t\t</table>\n";      
      
   } else {
   // Error message if id is not number
      $contents = "<p><strong>Error:</strong> \"$ID\" is not a valid input</p>\n";
   };
};

/* Close DB  */
$dbcore = null;

$title = "<a href=\"jobs.php\">Backup Jobs</a>";
if ( ! $main_page ) { $title2 = $date; };


// Page HTML
include("include/framehtml.php");
?>