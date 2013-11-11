<?session_start();
if(!(isset( $_SESSION['active']))) {
        $location = "http://".$_SERVER['HTTP_HOST']."/login.php?page=".urlencode($_SERVER['REQUEST_URI']);
        header("Location: $location");
        die();
}?>
