<?php

/*======================================================================
Copyright Project BeehiveForum 2002

This file is part of BeehiveForum.

BeehiveForum is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

BeehiveForum is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Beehive; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  
USA
======================================================================*/

require_once("./include/html.inc.php");
require_once("./include/user.inc.php");
require_once("./include/constants.inc.php");
require_once("./include/session.inc.php");

// Where are we going after we've logged on?
if(isset($HTTP_GET_VARS['final_uri'])){
    $final_uri = urldecode($HTTP_GET_VARS['final_uri']);
} else {
    $final_uri = dirname($HTTP_SERVER_VARS['PHP_SELF']) . "/";
}

// Are we already logged on?
if(bh_session_check()){
    html_draw_top();
    echo "<p>User ID " . $HTTP_COOKIE_VARS['bh_sess_uid'] . " already logged in.</p>";
    echo "<p><a href=\"$final_uri\" target=\"_top\">Continue</a></p>";
    html_draw_bottom();
    exit;
}

$valid = true;

if(isset($HTTP_POST_VARS['logon'])){
    $logon = $HTTP_POST_VARS['logon'];
} else {
    $logon = "";
    $valid = false;
}

if(isset($HTTP_POST_VARS['password'])){
    $password = $HTTP_POST_VARS['password'];
} else {
    $password = "";
    $valid = false;
}

if(isset($HTTP_POST_VARS['submit'])){
    if($logon==""){
        $error_html = "<h2>A logon name is required</h2>";
        $valid = false;
    } else if($password==""){
        $error_html = "<h2>A password is required</h2>";
        $valid = false;
    }
}

if($valid){
    $luid = user_logon($logon,$password);
    if($luid>-1){
        bh_session_init($luid);
//      setcookie("bh_sess_uid",$luid);
        if($HTTP_POST_VARS['remember_user'] == "Y"){
            setcookie("bh_remember_user",$logon,YEAR_IN_SECONDS);
            setcookie("bh_remember_password",$password,YEAR_IN_SECONDS);
        } else {
            setcookie("bh_remember_user","",-3600);
            setcookie("bh_remember_password","",-3600);
        }
    } else {
        $error_html = "<h2>Invalid login</h2>";
        $valid = false;
    }
}

if($valid){
    header("Location: http://".$HTTP_SERVER_VARS['HTTP_HOST'].$final_uri);
    /*echo "<script language=\"Javascript\" type=\"text/javascript\">";
    echo "<!--\n parent.location = \"http://".$HTTP_SERVER_VARS['HTTP_HOST'].$final_uri."\";";
    echo "\n-->\n</script>";*/
    exit;
}

html_draw_top();

if(!$valid){
    if($logon == ""){
        if(isset($HTTP_COOKIE_VARS['bh_remember_user'])){
            $logon = $HTTP_COOKIE_VARS['bh_remember_user'];
            $password = $HTTP_COOKIE_VARS['bh_remember_password'];
        }
    }
    echo "<h1>User Logon</h1>";
    if(isset($error_html)){
        echo $error_html;
    }
    echo "<div align=\"center\">";
    echo "<form name=\"register\" action=\"" . $HTTP_SERVER_VARS['REQUEST_URI'] . "\" method=\"POST\">";
    echo "<table>";
    echo "<tr><td align=\"right\">Login Name</td>";
    echo "<td><input type=\"text\" name=\"logon\" value=\"" . $logon . "\"></td>";
    echo "</tr><tr><td align=\"right\">Password</td>";
    echo "<td><input type=\"password\" name=\"password\" value=\"" . $password . "\"></td></tr>";
    echo "<tr><td>&nbsp;</td><td align=\"right\">";
    echo "<input type=\"checkbox\" name=\"remember_user\" value=\"Y\"";
    if(isset($HTTP_COOKIE_VARS['bh_remember_user']) || $HTTP_POST_VARS['remember_user'] == "Y"){
        echo " checked";
    }
    echo "> Remember me";
    echo "</td></tr></table>";
    echo "<tr><td>&nbsp;</td><td>&nbsp;</td></tr></table>";
    echo "<input name=\"submit\" type=\"submit\" value=\"Submit\">";
    echo "</form></div>";
    echo "<p>&nbsp;</p>";
    echo "<div align=\"center\">";
    echo "<p>Don't have an account?<br />";
    echo "<a href=\"register.php?final_uri=" . urlencode($final_uri);
    echo "\" target=\"_self\">Click here to register...</a></p></div>";
}

html_draw_bottom();
?>
