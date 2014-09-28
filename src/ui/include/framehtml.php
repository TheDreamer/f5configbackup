<?
setcookie("LASTFRAME",$_SERVER['REQUEST_URI'],0,Null,Null,1);
?>
<html>
<head>
<meta http-equiv="X-UA-Compatible" content="IE=Edge,chrome=1" />
<link rel="stylesheet" type="text/css" href="css/main.css">
</head>
<body id="framebody">
   <div id="pagelet_title">
      <?=$title;?>
      <? if ( isset($title2) ) {echo " > $title2";} ?> 
      <? if ( isset($title3) ) {echo " > $title3";} ?> 
   </div> 
   <div id="pagelet_body">
<?=$contents;?>
   <br />
   </div>
</body>
<script>
//If page is not loaded in iframe redirect browser 
if (parent.window.location.pathname != '/'){
   parent.window.location = "/";
};

// Whats the combined height of these 2 elements ?
parent.frameContentsHeight = document.getElementById('pagelet_body').scrollHeight + 
document.getElementById('pagelet_title').scrollHeight;
parent.NewFrameHeight();
</script>
</html>