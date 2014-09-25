<?
include("include/session.php");
require_once '/opt/f5backup/ui/include/PestJSON.php';
// Internal web service connect
function webcheck () {
   try {
      //Connect to internal webservice
      $pest = new PestJSON('http://127.0.0.1:5380');
      $result = $pest->get('/api/v1.0/status');  
   } catch (Exception $e) {
      return '<img style="vertical-align: middle;" src="/images/red_button.png"> Status: OFFLINE';
   };

   if ($result['status'] == 'ERROR' ) {
      return '<img style="vertical-align: middle;" src="/images/yellow_button.png"> Status: ERROR';
   } elseif ($result['status'] == 'GOOD' ) {
      return '<img style="vertical-align: middle;" src="/images/green_button.png"> Status: ONLINE';
   };
};

// Make array of role names from DB
include("include/dbconnect.php");
$sth = $dbh->query("SELECT ID,NAME FROM ROLES ORDER BY ID");
$sth->execute();
foreach ($sth->fetchAll() as $role) {
   $rolearray[$role['ID']] = $role['NAME'];
};
$dbh = null;

//Get last frame cookie and set iframe URL
if ( isset($_COOKIE['LASTFRAME']) ) {
   $URL = $_COOKIE['LASTFRAME'];
} else {
   $URL = "/main.php";
};

?>
<!DOCTYPE html> 
<html>
<head>
<meta http-equiv="X-UA-Compatible" content="IE=Edge,chrome=1" />
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="stylesheet" type="text/css" href="css/menu.css">
</head>
<body onresize="NewFrameHeight()">
<table class="main">
<tr> <!-- Page Header ------------>
   <td id="header" colspan="2">
      <div id="hostname">Hostname: &nbsp; <?= gethostname() ?></div>
      <div id="ip">User IP: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 
         <?= $_SERVER['REMOTE_ADDR'] ?>
      </div>
      <div id="date">Date: <?= date('Y-m-d',time()) ?></div>
      <div id="time">Time: <?= date('H:i',time()) ?></div>  
      <div id="user">Username: <?= $_SESSION['user'] ?></div>
      <div id="role">User Role:&nbsp; <?= $rolearray[$_SESSION['role']] ?></div>
      <div id="logout"><a href="/logout.php"><div>Log out</div></a></div>
   </td>
</tr>
<tr>
   <td id="banner" colspan="2">
      <div id="title"><a href="/">Config Backup for F5</a></div>
      <div id="status">
         <a href="/status.php" target="bodyframe">
         <span id="statusspan"><?= webcheck()?></span>
         </a>
      </div>
</td>
</tr>
<tr id="mainbodytd"> 
   <td class="menu"> <!-- Page Menu ------------->
   <div id="menu">
   <div class="panel">
      <a onclick="menuSelect('devices')">
      <div>
      <img src="/images/devices.png" class="panel" />
       Devices
      </div>
      </a>
   </div>
   
   <div class="cssmenu" id="devices">
      <ul>
         <li><a href="/devices.php" target="bodyframe">Devices</a></li>
      </ul>
   </div>
   
   <div class="panel">
      <a href="/jobs.php" target="bodyframe" onclick="menuSelect('jobs')">
      <div>
      <img src="/images/jobs.png" class="panel" />
       Backup Jobs
      </div>
      </a>
   </div>
   
   <div class="panel">
      <a href="/certs.php" target="bodyframe" onclick="menuSelect('certs')">
      <div>
      <img src="/images/cert.png" class="panel" />
       Certificates
      </div>
      </a>
   </div>
   
   <div class="panel">
      <a onclick="menuSelect('system')">
      <div>
      <img src="/images/settings.png" class="panel" />
       System
      </div>
      </a>
   </div>
   
   <div class="cssmenu" id="system">
      <ul>
         <li><a href="/generalsettings.php" target="bodyframe">General</a></li>
         <li class="has-sub"><a>Authentication</a>
            <ul>
               <li><a href="/auth.php" target="bodyframe">Auth Method</a></li>
               <li><a href="/authgrp.php" target="bodyframe">Auth Groups</a></li>
               <li><a href="/users.php" target="bodyframe">Users</a></li>
               <li><a href="/admin.php" target="bodyframe">Admin User</a></li>
            </ul>
         </li>
         <li><a href="/backupsettings.php" target="bodyframe">Backups</a></li>
         <li><a href="/certreport.php" target="bodyframe">Certificate Report</a></li>
      </ul>
   </div>
   
   </div>
   </td>
   
   <td id="bodytd">
   <iframe name="bodyframe" id="bodyframe" src="<?=$URL?>" scrolling="no" marginwidth="0" 
   marginheight="0" frameborder="0" vspace="0" hspace="0">
   </iframe>
   </td>

</tr>
</table>
<div id="timeout">
   <div id="timeoutbox">
   <p>Your session has timed out <br />or is no longer valid!</p>
   <input type="submit" onclick="logout()" value="Login">
   </div>
</div>
</body>
<script src="/scripts/main.js"></script>
</html> 