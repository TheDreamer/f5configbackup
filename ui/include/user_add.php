<?php

include("include/functions.php");

// Update post processing
$post = 0;
if ($_SERVER['REQUEST_METHOD'] == "POST" && $_POST["change"] == "Create") {
	
	$error = 0;
	$username = strtolower($_POST["username"]); //lower username
	
	// Cannot create admin user
	if ($username == 'admin') {
		$contents = "<p class=\"error\">Error: Cannot create a user named \"admin\".</p>";
		$error++;			
	};
	
	// Username cant have special characters or spaces
	if ($error == 0 && preg_match('/([[:punct:]]|\s)/', $username) ) { 
		$contents = "<p class=\"error\">Error: Username cannot contain special characters or spaces.</p>";
		$error++;		
	};
	
	if ($error == 0) { 
		// Check if user name exists
		$sth = $dbh->prepare("SELECT count(NAME) FROM USERS WHERE NAME= ?");
		$sth->bindParam(1,$username); 
		$sth->execute();
		$user_exist = $sth->fetchColumn();
//		$sth = null;
		if ($user_exist) {
			$contents = "<p class=\"error\">Error: Username already exists.</p>";
			$error++;	
		};
	};
	
	// Password validation and hash creation
	if ($error == 0) { 
		$hash = password($_POST["password"],$_POST["password2"]);
		if ( isset($PASS_ERR) ) {
			$contents = "<p class=\"error\">Error: $PASS_ERR</p>";
			$error++;
		};
	};

	// If all checks pass then insert into DB
	if ($error == 0) { 
		$contents = "<p>Created user: $username</p>";
		$time = time();
		$added_by = $_SESSION['user'];
		$sth = $dbh->prepare("INSERT INTO USERS ('NAME','HASH','ADDED_BY','DATE_ADDED') VALUES (:username,:hash,'$added_by',$time)");
		$sth->bindValue(':username',$username); 
		$sth->bindValue('hash',$hash); 
		$sth->execute();
	};

} else {
	// Page body - Create
	$contents = <<<EOD
		<form action="settings.php?page=users&change=Add" method="post">	<table class="pagelet_table">
			<tr class="pglt_tb_hdr"><td colspan="2">Create New User</td></tr>
			<tr class="odd"><td>User Name</td><td><input type="text" name="username" class="input" maxlength="30"></td></tr>
			<tr class="even"><td>Password</td><td><input type="password" name="password" class="input" maxlength="30"></td></tr>
			<tr class="odd"><td>Confirm Password</td><td><input type="password" name="password2" class="input" maxlength="30"></td></tr>
		</table>
		<input type="submit" name="change" value="Create">
		</form>	
EOD;

};

$title = "<a href=\"settings.php?page=users\">Users</a> > Create New User";
?>	