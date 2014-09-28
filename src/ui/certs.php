<?php
include("include/session.php");
include("include/dbconnect.php");
include("include/dbcore.php");


# Make device array
$sth = $dbcore->query("SELECT NAME,ID FROM DEVICES ORDER BY NAME");
$sth->execute();      
foreach ($sth->fetchAll() as $dev) {
   $devarray[$dev['ID']] = $dev['NAME'];
};

// Are we requesting one cert detail ?
if (isset($_GET["id"]) && is_numeric($_GET["id"])) {
   $id = $_GET["id"];
   // Get cert details
   $time = time();
   $sth = $dbcore->prepare("SELECT NAME,DEVICE,ISSUER,SN,
                           KEY,SUB_C,SUB_S,SUB_L,SUB_O,
                           SUB_OU,SUB_CN,EXPIRE,ACK
                           FROM CERTS WHERE ID = ?");
   $sth->bindParam(1,$_GET["id"]); 
   $sth->execute();
   
   $row = $sth->fetch();
   $name = $row['NAME'];
   $device = $row['DEVICE'];
   $devname = $devarray[$device];
   $issuer = $row['ISSUER'];
   $sn = $row['SN'];
   $key = $row['KEY'];
   $sub_c = $row['SUB_C'];
   $sub_s = $row['SUB_S'];
   $sub_l = $row['SUB_L'];
   $sub_o = $row['SUB_O'];
   $sub_ou = $row['SUB_OU'];
   $sub_cn = $row['SUB_CN'];
   $ack =  $row['ACK'];
   $title2 = $sub_cn;

   // Cert expiration
   $expire = gmdate('Y-m-d H:i',$row['EXPIRE']);
   if ($row['EXPIRE'] <= $time) {
   // If cert is expired then make bold red
      $expire = "<strong class=\"error\">$expire (Expired)<strong>";
   } elseif ( ($row['EXPIRE'] - $time) <= 2592000) {
   //Or if cert is expiring within 30 days
      $days = intval(($row['EXPIRE'] - $time) / 86400);
      $expire = "<strong class=\"warning\">$expire (Expires in $days days)<strong>";
   };

   // Is this a POST ?
   $message = '' ;
   if ($_SERVER['REQUEST_METHOD'] == "POST") {
      try {
         // Update ack
         if ( $ack != $_POST['ack'] ) {
            // RBAC check
            if ( $_SESSION['role'] != 1 ) {
               throw new Exception("Only administrators can acknowledge certs!"); 
            };
         
            // Is this input valid
            if (! in_array( $_POST['ack'],array(0,1)) ) {
               throw new Exception("Ack input not valid"); 
            };
            
            // Write mode to DB
            $sth = $dbcore->prepare("UPDATE CERTS SET ACK = ? WHERE ID = ?");
            $sth->bindParam(1,$_POST['ack']); 
            $sth->bindParam(2,$id); 
            $sth->execute();
         };
         $ack = $_POST['ack'];
         $message = "<p>Cert acknowledgement updated.</p>";
      } catch (Exception $e) {
         if ( $dbcore->inTransaction ) { $dbcore->rollBack(); };
         $message = '<p class="error">Error: '.$e->getMessage().'</p>';
      };
   };

   // Which radio buttons are checked, build after post
   $ackY = '';
   $ackN = '';
   if ( $ack ) {
      $ackY = 'checked';
   } else {
      $ackN = 'checked';
   };
   
   $contents = <<<EOD
   <form action="certs.php?id=$id" method="post">
   $message
   <table class="pagelet_table">
      <tr class="pglt_tb_hdr"><td>Info</td><td>Value</td></tr>
      <tr class="odd"><td>Name</td><td>$name</td></tr>
      <tr class="even">
         <td>Device</td>
         <td><a href="devices.php?page=device&id=$device">$devname</a></td>
      </tr>
      <tr class="odd"><td>Issuer</td><td>$issuer</td></tr>
      <tr class="even"><td>Expiration Date (GMT)</td><td>$expire</td></tr>      
      <tr class="odd"><td>Serial #</td><td>$sn</td></tr>
      <tr class="even"><td>Key Length</td><td>$key</td></tr>
      <tr class="odd"><td>Subject</td><td><pre>
C=$sub_c
S=$sub_s
L=$sub_l
O=$sub_o
OU=$sub_ou
CN=$sub_cn
</pre></td>
      </tr>
      <tr class="even">
         <td>Acknowledge Cert</td>
         <td>
         <input type="radio" name="ack" value="1" $ackY>Yes
         <input type="radio" name="ack" value="0" $ackN>No
         </td>
      </tr>
   </table>
   <input type="submit"name="submit"value="Update">
   </form>\n
EOD;
} else {

   // Are we doing a search ?
   $search = '';
   if (isset($_GET["searchaction"]) && $_GET["searchaction"] == 'Search') {
      $search = strtolower( strip_tags($_GET["search"]) );
   };

// Else get all certs
   // If not present device list
   $contents = <<<EOD
      <form action="certs.php" method="get">
      <input type="text" name="search" size="20" maxlength="40" value="$search">
      <input type="submit" name="searchaction" value="Search">
      <input type="submit" name="searchaction" value="Reset Search">
      </form>
      <table class="pagelet_table">
      <tr class="pglt_tb_hdr">
         <td>Cert Name</td>
         <td>Device</td>
         <td>CN</td>
         <td>Expiration (GMT)</td>
      </tr>\n
EOD;

   // Get list of certs from DB, are we searching ?
   if ( $search != '' ) {
      $param = "%$search%";
      $sth = $dbcore->prepare("SELECT ID,NAME,DEVICE,SUB_CN,EXPIRE FROM CERTS 
                              WHERE NAME LIKE ? OR 
                              SUB_CN LIKE ?
                              ORDER BY EXPIRE;");
    	$sth->bindParam(1,$param); 
    	$sth->bindParam(2,$param); 
   } else {
      // If not get list
      $sth = $dbcore->query("SELECT ID,NAME,DEVICE,SUB_CN,EXPIRE 
                          FROM CERTS ORDER BY EXPIRE");
   };
   
   $sth->execute();
   $certs = $sth->fetchAll();
   
   // loop through array to make cert table
   $count = 1;
   $time = time();
   foreach ( $certs as $row) {
      $id = $row['ID'];
      $name = $row['NAME'];
      $device = $row['DEVICE'];
      $devname = $devarray[$device];
      $cn = $row['SUB_CN'];
      $class = "even";
      if ($count & 1 ) {$class = "odd";};

      $expire = gmdate('Y-m-d H:i',$row['EXPIRE']);
      if ($row['EXPIRE'] <= $time) {
      // If cert is expired then make bold red
         $expire = "<strong class=\"error\">$expire<strong>";
      } elseif ( ($row['EXPIRE'] - $time) <= 2592000) {
      //Or if cert is expiring within 30 days
         $expire = "<strong class=\"warning\">$expire<strong>";
      };
      
      $contents .= "\t\t<tr class=\"$class\"><td><a href=\"certs.php?id=$id\">$name</a></td>";
      $contents .= "<td><a href=\"devices.php?page=device&id=$device\">$devname</a></td><td>$cn</td><td>$expire</td></tr>\n";
      $count++;
   };
   $contents .= "\t\t</table>\n";
};

// Close DB  
$dbcore = null;
$dbh = null;

$title = "<a href=\"certs.php\">Certificates </a>";

// Page HTML
include("include/framehtml.php");
?>