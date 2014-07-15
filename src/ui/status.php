<?php
include("include/session.php");

// include common content
include("include/header.php");
include("include/menu.php");

// Get status details from internal web service
function webstatus () {
  //Connect to internal webservice
  $url = 'http://127.0.0.1:5380/api/v1.0/status';
  $curl = curl_init($url);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); 
  $result = json_decode(curl_exec($curl), true);

  //Did any curl errors occur ?
  if (curl_errno($curl)) {
    $error_msg = curl_error($curl);;
    return "<p><font color=\"red\"><strong>Internal web service error: $error_msg</strong></font></p>";
  };

  // Did server return an error ?
  $rtn_code = curl_getinfo($curl,CURLINFO_HTTP_CODE);
  if ( $rtn_code != 200 ) {
    $error_msg = $result['error'];
    return "<p><font color=\"red\"><strong>ERROR: $error_msg</strong></font></p>";
  };

$status = $result['status'];
return "<p><strong>System status: $status</strong></p>";
  
};
?>
	<div id="pagelet_title">
		Status
	</div>
<?
echo webstatus();
include("include/footer.php");
?>