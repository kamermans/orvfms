<?php
session_start();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<?php
    if(($_SERVER["REQUEST_METHOD"] != "POST") || (isset($_POST['toMainPage']))){
        echo '<META HTTP-EQUIV="Refresh" CONTENT="60">'."\n";
    }
?>
<title>
S20 remote
</title>

<!-- UPDATE THE PATH OF THE FILE orvfms.css BELOW TO MATCH
     YOUR LOCAL CONFIGURATION.                       -->
<link rel="stylesheet" type="text/css" href="../css/orvfms.css"> 
<script>
      function convToString(time){
            var  h,m,min,s;
            var  hs,ms,ss;
            var t;
            h = Math.floor(time/3600);
            min = time % 3600;
            m = Math.floor(min / 60);
            s = min % 60;
            hs=('0'+h.toString()).slice(-2);
            ms=('0'+m.toString()).slice(-2);
            ss=('0'+s.toString()).slice(-2);
            t = hs+':'+ms+':'+ss;
            return t;
      }
</script>
</head>
<body>
<?php
/*************************************************************************
*  Copyright (C) 2015 by Fernando M. Silva   fcr@netcabo.pt             *
*                                                                       *
*  This program is free software; you can redistribute it and/or modify *
*  it under the terms of the GNU General Public License as published by *
*  the Free Software Foundation; either version 3 of the License, or    *
*  (at your option) any later version.                                  *
*                                                                       *
*  This program is distributed in the hope that it will be useful,      *
*  but WITHOUT ANY WARRANTY; without even the implied warranty of       *
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        *
*  GNU General Public License for more details.                         *
*                                                                       *
*  You should have received a copy of the GNU General Public License    *
*  along with this program.  If not, see <http://www.gnu.org/licenses/>.*
*************************************************************************/
/*
  This program was developed independently and it is not
  supported or endorsed in any way by Orvibo (C).

  This page implements a web interface to control Orvibo S20 sockets 
  attached to the local network. Major functions are implemented in the
  orvfms.php library. 

  The web interface provides status report of all S20 attached to the 
  network and supports ON/OFF actions of the detected devices. It features
  a responsive behavior to changing viewport sizes, including smartphones. It 
  divides the viewport in N horizontal buttons, each one labeled with the 
  name automatically retrieved from the connected S20s. Each button is 
  shown in green or red according to the current S20 state (green = ON).  
  
  Note: adjust the include line orvfms.php below to the correct path, as well
  as the location of the CSS  orvfms.css in the <head> section above.  
*/

/* UPDATE THE PATH to THE  orvfms LIBRARY and img directory 
   BELOW TO MATCH YOUR LOCAL CONFIGURATION.              
*/
define("ORVFMS_PATH","../lib/orvfms/");
define("IMG_PATH","../img/");

require_once(ORVFMS_PATH."orvfms.php"); 

$myUrl = htmlspecialchars($_SERVER["PHP_SELF"]);
if(DEBUG)
    print_r($_SESSION);

$daysOfWeek = array("Monday","Tuesday","Wednesday","Thursday",
                   "Friday","Saturday","Sunday");

$months = array("Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec");

if(isset($_SESSION["s20Table"])) {
    $s20Table = $_SESSION["s20Table"];
}

//
// Get time reference to know when the status was updated by the last time
//
if(isset($_SESSION["time_ref"])) 
    $time_ref = $_SESSION["time_ref"];
else
    $time_ref = 0;

//
// Refresh/update only S20 data if $s20Table was initialized before, data seems consistent and
// time since last refresh was less than 5 minutes. 
//
// Otherwise, reinitialize all $s20Table structure
//
if(isset($_SESSION["s20Table"]) &&
   (count($s20Table)>0) && ((time()-$time_ref <  120))){
    $s20Table = updateAllStatus($s20Table);  
    if(DEBUG)
        error_log("Session restarted; only status update\n");
}
else{
    $time_ref = time(); 
    $s20Table=initS20Data();   
    $_SESSION["s20Table"] = $s20Table;
    $ndev = count($s20Table);
    if($ndev == 0){
        echo "<h2>No sockets found</h2>";
        echo " Please check if all sockets are on-line and assure that they\n";
        echo " they are not locked (check WiWo app -> select socket -> more -> advanced).<p>";
        echo " In this version, locked or password protected devices are not supported.<p>";
        exit(1);
    }
    $_SESSION["devNumber"]=$ndev;
    $_SESSION["time_ref"]=$time_ref;
    if(DEBUG)
        error_log("New session: S20 data initialized\n");
}
//
// Check which page must be displayed
//
if ($_SERVER["REQUEST_METHOD"] != "POST"){
    require_once(ORVFMS_PATH."main_page.php");
    displayMainPage($s20Table,$myUrl);
    require_once(ORVFMS_PATH."main_page_scripts.php");
}
else if(isset($_POST['toMainPage'])){
    $actionValue = $_POST['toMainPage'];
    if(substr($actionValue,0,7)=="switch_"){
        $switchName = substr($actionValue,7);
        $mac = getMacFromName($switchName,$s20Table);
        $st = $s20Table[$mac]['st'];
        $newSt = actionAndCheck($mac,($st==0 ? 1 : 0),$s20Table);
        $s20Table[$mac]['st']=$newSt;
        $swVal = $s20Table[$mac]['switchOffTimer']; 
        if(($st == 0) && ($newSt == 1) && ($swVal > 0)){
            $s20Table[$mac]['timerVal'] = $swVal;
            $s20Table[$mac]['timerAction'] = 0;
        }
    }
    else if(($actionValue == "setCountdown") ||
            ($actionValue == "clearCountdown") ||
            ($actionValue == "clearSwitchOff")){
        require_once(ORVFMS_PATH."timer_settings.php");
        timerSettings($s20Table,$actionValue);
    }
    require_once(ORVFMS_PATH."main_page.php");
    displayMainPage($s20Table,$myUrl);
    require_once(ORVFMS_PATH."main_page_scripts.php");
}
else if(isset($_POST['toCountDownPage'])){
    $actionValue = $_POST['toCountDownPage'];
    if(substr($actionValue,0,6)=="timer_")
        $timerName = substr($actionValue,6);
    require_once(ORVFMS_PATH."timer_page.php");
    displayTimerPage($timerName,$s20Table,$myUrl);
}
else if(isset($_POST['toDetailsPage'])){
    $actionValue = $_POST['toDetailsPage'];
    $timerName = $_POST['name'];
    require_once(ORVFMS_PATH."edit_process.php");
    if($actionValue=="updateOrAdd"){
        editProcess($timerName,$s20Table);
    }
    else if(substr($actionValue,0,4)=="del_"){
        $recCode = substr($actionValue,4);
        delProcess($timerName,$recCode,$s20Table);        
    }
    $timerName = $_POST['name'];
    require_once(ORVFMS_PATH."details_page.php");
    displayDetailsPage($timerName,$s20Table,$myUrl);
}
else if(isset($_POST['toEditPage'])){
    $actionValue = $_POST['toEditPage'];
    if(substr($actionValue,0,4) == "edit"){
        $editIndex = substr($actionValue,4);
    }
    else{
        $editIndex = -1;
    }
    $timerName = $_POST['name'];
    require_once(ORVFMS_PATH."edit_page.php");
    displayEditPage($timerName,$editIndex,$s20Table,$myUrl);
}
else{
    echo "Unexpected error 505<p>\n";
}

?>
</body>
</html>