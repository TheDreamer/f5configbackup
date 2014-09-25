<?
session_start();
$idle_time = time() - $_SESSION['time'];
$timeout = intval($_SESSION['timeout']);
if( ! isset( $_SESSION['active']) ) {
//If user does not have active session then logout user
   header("HTTP/1.0 403 Forbidden");
   die();
} elseif ( $_SESSION['clientip'] != $_SERVER['REMOTE_ADDR'] ) {
// If users IP has changed
   header("HTTP/1.0 403 Forbidden");
   die();
} elseif ( $idle_time > $timeout ) { 
// Check if the user is timed out
   header("HTTP/1.0 403 Forbidden");
   die();
};

//Connect to internal webservice
require_once '/opt/f5backup/ui/include/PestJSON.php';
try {
   $pest = new PestJSON('http://127.0.0.1:5380');
   $result = $pest->get('/api/v1.0/status');  
   if ($result['status'] == 'ERROR' ) {
      $services = "ERROR";
   } elseif ($result['status'] == 'GOOD' ) {
      $services = "ONLINE";
   };
} catch (Exception $e) {
   $services = "OFFLINE";
};

// Build return array
$status = array(
   'date' => date('Y-m-d',time()),
   'time' => date('H:i',time()),
   'services' => $services,
   'idletime' => $idle_time,
   'timeout' => $timeout
);

header("Content-Type: application/json");
echo json_encode($status);
?>