// Expanding top level menu functions
var cssmenu = document.getElementsByClassName('cssmenu');
var menuHeight = document.getElementById('menu').scrollHeight;
function menuSelect(id) {
   var i;
   // loop through all cssmenu divs
   for (i = 0; i < cssmenu.length; i++) {
      if (cssmenu[i].id == id && cssmenu[i].style.display != 'table') {
         // Unhide the menu who's ID matches input and 
         // if it is not already visible 
         cssmenu[i].style.display = 'table';
      } else {
         // Hide all else
         cssmenu[i].style.display = 'none';
      };
   };
   // Reset window height
   menuHeight = document.getElementById('menu').scrollHeight;
   NewFrameHeight();
};

// iFrame sizing functions
var header = document.getElementById('header').scrollHeight;
var banner = document.getElementById('banner').scrollHeight;
var frameContentsHeight = 0;
function NewFrameHeight() { 
   var frameHeightTemp = frameContentsHeight;
   var bodyHeight = window.innerHeight - (header + banner);

   // Is frame contents smaller than menu ?
   if (frameContentsHeight < menuHeight){
      frameHeightTemp = menuHeight;
   };
   
   // is client window larger than the iframe size ?
   if ( bodyHeight > frameHeightTemp) {
      frameHeightTemp = bodyHeight - 4;
   };
   document.getElementById('bodyframe').style.height = frameHeightTemp + 'px'; 
};


// Header status indicator and time
var date = '';
var time = '';
var services = '';
var timeout = 0;
var timeDiv = document.getElementById('time');
var dateDiv = document.getElementById('date');
var statusSpan = document.getElementById('statusspan');
var timeoutDiv = document.getElementById('timeout');

function statusUpdate() {
   // Call status REST API
   var jsonstatus = new XMLHttpRequest();
   jsonstatus.open('GET', 'https://10.45.196.150/updatestatus.php', false);
   jsonstatus.setRequestHeader("Content-type","application/json");
   jsonstatus.send();
   
   // If 403 error from no session
   if (jsonstatus.status  == 403 ){
      timeoutDiv.style.display = 'block';
      window.clearInterval(statusTimer);
   };
   
   // If not 200 display unavailable, stop timer and exit function
   if (jsonstatus.status  != 200){
      statusSpan.innerHTML = '<img style="vertical-align: middle;" src="/images/blue_button.png"> Status: UNAVAILABLE';
      services = 'NULL';
      return;
   };
   
   var update = JSON.parse(jsonstatus.responseText);
   
   //Update elements only if contents have changed
   if ( update['time'] != time ) {
      time = update['time'];
      timeDiv.innerHTML = "Time: " + update['time'];
   };

   if ( update['date'] != date ) {
      date = update['date'];
      dateDiv.innerHTML = "Date: " + update['date'];
   };
   
   if ( update['services'] != services ) {
      services = update['services'];
      var statusHTML;
      switch(update['services']) {
         case "ONLINE":
            statusHTML = '<img style="vertical-align: middle;" src="/images/green_button.png"> Status: ONLINE';
            break;
         case "ERROR":
            statusHTML = '<img style="vertical-align: middle;" src="/images/yellow_button.png"> Status: ERROR';
            break;
         case "OFFLINE":
            statusHTML = '<img style="vertical-align: middle;" src="/images/red_button.png"> Status: OFFLINE';
            break;
      };
      statusSpan.innerHTML = statusHTML;
   };
};
var statusTimer = window.setInterval(statusUpdate, 5000);

// Timeout actions

function logout() {
   parent.window.location = "/logout.php";
};