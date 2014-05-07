<?
// Time check - verify time entered and convert to minutes
function time_check ($hr,$min) {
	if (! is_numeric($hr) || ! is_numeric($min) ) {
		throw new Exception('Time input must be numeric.');
	};
	if ($hr > 23) {throw new Exception('Hour can\'t exceed 24.'); };
	if ($min > 59) {throw new Exception('Minutes can\'t exceed 60.'); };
	return (intval($hr) * 60) + intval($min);
};

// Internal web service connect
function pass_json ($passwd) {
	$data = json_encode(array('string' => $passwd ));

	//Connect to internal webservice
	$url = 'http://127.0.0.1:5380/api/v1.0/crypto/encrypt/';
	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $data );
	curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); 
	$response = json_decode(curl_exec($curl), true);

	//Did any curl errors occur ?
	if (curl_errno($curl)) {
		$error_msg = curl_error($curl);;
		throw new Exception("\"Internal web service error: $error_msg\"");
	};

	// Did server return an error ?
	$rtn_code = curl_getinfo($curl,CURLINFO_HTTP_CODE);
	if ( $rtn_code != 200 ) {
		$error_msg = '';
		if ( isset($response['error']) ) { $error = $response['error'];};
		throw new Exception("\"$rtn_code - Internal web service error: $error_msg\"");
	};
	return $response['result'];
};

//Start DB writes
$dbh->beginTransaction();
try {
	// UCS archive size
	if ($ucs != $_POST["ucs"]) {
		// Throw exception if not number
		if (! is_numeric($_POST["ucs"])) {throw new Exception('"UCS Archive Size" must be a number!'); };
		//Write to DB
		$sth = $dbh->prepare("UPDATE BACKUP_SETTINGS_INT SET VALUE = ? WHERE NAME = 'UCS_ARCHIVE_SIZE'");
		$sth->bindParam(1,intval($_POST["ucs"])); 
		$sth->execute();
		// Incr post and append updates
		$post++;
		$updates .= '"UCS Archive Size" ';
	};

	if ($log != $_POST["log"]) {
		if (! is_numeric($_POST["log"])) {throw new Exception('"Log Archive Size" must be a number!'); };
		$sth = $dbh->prepare("UPDATE BACKUP_SETTINGS_INT SET VALUE = ? WHERE NAME = 'LOG_ARCHIVE_SIZE'");
		$sth->bindParam(1,intval($_POST["log"])); 
		$sth->execute();
		$post++;
		$updates .= '"Log Archive Size" ';
	};

	// 
	$time_new = time_check($_POST["time_hr"],$_POST["time_min"]);
	if ($time != $time_new){
		$sth = $dbh->prepare("UPDATE BACKUP_SETTINGS_INT SET VALUE = ? WHERE NAME = 'BACKUP_TIME'");
		$sth->bindParam(1,$time_new); 
		$sth->execute();
		$post++;
		$updates .= '"Backup Time" ';
	};


	if ($user != $_POST["user"]) {
		$sth = $dbh->prepare("UPDATE BACKUP_USER SET NAME = ? WHERE ID = '0'");
		$sth->bindParam(1,$_POST["user"]); 
		$sth->execute();
		$post++;
		$updates .= '"Backup User Name" ';
	};

	if ( $_POST["password"] != "nochange" ) {
		// do passwords match ?
		if ($_POST["password"] != $_POST["password2"]) {
			throw new Exception('"Passwords do not match!'); 
		};
		$pass = pass_json($_POST["password"]);

		// insert into DB
		$sth = $dbh->prepare("UPDATE BACKUP_USER SET PASS = ? WHERE ID = '0'");
		$sth->bindParam(1,$pass); 
		$sth->execute();

		$post++;
		$updates .= '"Backup User Password"';
	};

	//Reset vars to new (or keep as old) values
	// If exception happens on any item new values  
	// will get thrown out (never makes it here)
	$ucs = intval($_POST["ucs"]);
	$log = intval($_POST["log"]);
	$time_min = intval($_POST["time_min"]); 
	$time_hr = intval($_POST["time_hr"]);
	$user = $_POST["user" ];

	$dbh->commit();
} catch (Exception $e) {
	$dbh->rollBack();
	$error = $e->getMessage();
};
?>