<html>
<body>
<?php
session_start();
if(isset( $_SESSION['active'])) {
        unset($_SESSION['clientip']);
        unset($_SESSION['active']);
?>
<h1>You are now logged out</h1>
<a href="/">Click here to login</a>
<?} else { ?>
<h1>You are already logged out</h1>
<a href="/">Click here to login</a>
<?}?>
</body>
</html>
