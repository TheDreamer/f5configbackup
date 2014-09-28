<?php
include("include/session.php");
include("include/dbconnect.php");
include("include/dbcore.php");

// Ensure we are asking for a specific log file
if ( ! isset($_GET["log"]) ) {
   header("Location: /main.php"); 
   die;
};

// Which log file is this ?
switch ($_GET["log"]) {
   case "backupd":
      $logf = "backupd.log";
      $title2 = "Backup Service";
      $log = "backupd";
      break;
   case "auth":
      $logf = "auth.log";
      $title2 = "Authentication";
      $log = "auth";
      break;
   case "api":
      $logf = "api.log";
      $title2 = "Internal API";
      $log = "api";
      break;   
};

// Are we doing a reverse sort of the log table?
$sort = "&sort=change";
$sortchar = '&#8681;';
if ( isset($_GET["sort"]) ) { 
   $sort = ''; 
   $sortchar = '&#8679;';
};

$contents = <<<EOD
      <table class="pagelet_table">
      <tr class="pglt_tb_hdr">
         <td><a href="/logs.php?log=$log&id=$ID$sort">Date/Time  $sortchar </a></td>
         <td>Level</td>
         <td>Message</td>
      </tr>\n   
EOD;
// Build log file view
$log_file = explode("\n",htmlspecialchars( file_get_contents("../log/$logf") ) );
$pages = intval( count($log_file)/50 );
if ( isset($_GET["sort"]) ) { $log_file = array_reverse($log_file); }; // Reverse sort ?

$start = 0;
$end = 49;

if ( isset($_GET["page"]) ) {
   // Validate page number  is in range
   if ( in_array($_GET["page"],range(1,$pages)) ) {
      // use page number to to get index of 50 records
      $start = ($_GET["page"] - 1 ) * 50;
      $end = $start + 49;
   };
};

$log_file = array_slice($log_file,$start,$end);

// Loop through log lines to build table
$count = 1;
foreach ($log_file as $i) {
   if ($i == '') { continue; }; // Dont display if empty
   // TD color
   $class = "even";
   if ($count & 1 ) {$class = "odd";};
   
   $row = explode(": ",$i,2); // Split message from time stamp
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

  
//Build page dropdown
$pageselect = '';
foreach (range(1,$pages) as $i) {
   // Are we revere sorting ?
   $sortpage = ''; 
   if ( isset($_GET["sort"]) ) { 
      $sortpage = '&sort=change'; 
   };
   $pageurl = "/logs.php?log=$log&page=$i$sortpage";

   // If this is our page then make option selected
   $selected = '';
   if ( isset($_GET["page"]) && $_GET["page"] == $i) { 
      $selected = 'selected';
   };
   
   $pageselect .= "\t\t\t<option value=\"$pageurl\" $selected>Page $i of $pages</option>\n";
};

$contents .= <<<EOD
      </table>
      <select name="page" onchange="gotoPage(this)">
$pageselect         
      </select>
<script>
function gotoPage(url){
   window.location = url.value;
};
</script>
EOD;

/* Close DB  */
$dbcore = null;

$title = "<a href=\"logs.php\">Logs</a>";

// Page HTML
include("include/framehtml.php");
?>