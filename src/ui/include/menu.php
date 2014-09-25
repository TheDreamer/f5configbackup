<tr> 
   <td class="menu"> <!-- Page Menu ------------->
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
         <li><a href="/devices.php">Devices</a></li>
         <li><a href="#">Test</a></li>
      </ul>
   </div>
   
   <div class="panel">
      <a href="/jobs.php" >
<!--  <a onclick="menuSelect('jobs')"> -->
      <div>
      <img src="/images/jobs.png" class="panel" />
       Backup Jobs
      </div>
      </a>
   </div>
   
   <div class="panel">
      <a href="/certs.php">
<!--  <a onclick="menuSelect('certs')"> -->
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
         <li><a href="/generalsettings.php">General</a></li>
         <li class="has-sub"><a>Authentication</a>
            <ul>
               <li><a href="/auth.php">Auth Method</a></li>
               <li><a href="/authgrp.php">Auth Groups</a></li>
               <li><a href="/users.php">Users</a></li>
               <li><a href="/admin.php">Admin User</a></li>
            </ul>
         </li>
         <li><a href="/backupsettings.php">Backups</a></li>
         <li><a href="/certreport.php">Certificate Report</a></li>
      </ul>
   </div>
   
   </td>
   <td class="body"> <!-- Page body -------------->