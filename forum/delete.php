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

//Check logged in status
require_once("./include/session.inc.php");
if(!bh_session_check()){
    $go = "Location: http://".$HTTP_SERVER_VARS['HTTP_HOST'];
    $go .= "/".dirname($HTTP_SERVER_VARS['PHP_SELF']);
    $go .= "/logon.php?final_uri=";
    $go .= urlencode($HTTP_SERVER_VARS['REQUEST_URI']);
    header($go);
}

if(isset($HTTP_POST_VARS['cancel'])){
    $go = "Location: http://".$HTTP_SERVER_VARS['HTTP_HOST'];
    $go .= dirname($HTTP_SERVER_VARS['PHP_SELF']);
    $go .= "/discussion.php?msg=" . $HTTP_POST_VARS['t_back'];
    //echo $go;
    header($go);
}

require_once("./include/html.inc.php");
require_once("./include/user.inc.php");
require_once("./include/post.inc.php");
require_once("./include/format.inc.php");
require_once("./include/folder.inc.php");
require_once("./include/threads.inc.php");
require_once("./include/messages.inc.php");
require_once("./include/fixhtml.inc.php");
require_once("./include/edit.inc.php");

$valid = true;

if(isset($HTTP_POST_VARS['submit'])){
    $delete_msg = $HTTP_POST_VARS['t_msg'];
    $msg_bits = explode(".",$delete_msg);
} else {
    if(isset($HTTP_GET_VARS['msg'])){
        $delete_msg = $HTTP_GET_VARS['msg'];
        $msg_bits = explode(".",$delete_msg);
        $back = $HTTP_GET_VARS['back'];
    } else {
        $valid = false;
        $error_html = "<h2>No message specified for deleting</h2>";
    }
    if($msg_bits){
        $ema = messages_get($msg_bits[0],$msg_bits[1],1);
        if(count($ema) > 0){
            $preview_message = $ema[0];
            $to_uid = $preview_message['TO_UID'];
            $from_uid = $preview_message['FROM_UID'];
        } else {
            $valid = false;
            $error_html = "<h2>Message " . $HTTP_GET_VARS['msg'] . " was not found</h2>";
        }
        unset($ema);
    }
}

html_draw_top();

/* echo "<table border=\"1\">";
foreach ($HTTP_POST_VARS as $var => $value) {
    echo "<tr><td>$var</td><td>$value</td></tr>";
}
echo "</table>"; */

if($valid){
    if(isset($HTTP_POST_VARS['submit'])){
        echo $msg_bits[0].".".$msg_bits[1]."<br>";
        $deleted = post_delete($msg_bits[0],$msg_bits[1]);
        if($deleted){
            echo "<p>&nbsp;</p>";
            echo "<p>&nbsp;</p>";
            echo "<div align=\"center\">";
            echo "<p>Post deleted successfully</p>";
            echo "<p><a href=\"discussion.php?msg=" . $HTTP_POST_VARS['t_back'];
            echo "\">Return to messages</a></p>";
            echo "</div>";
            html_draw_bottom();
            exit;
        } else {
            $error_html = "<h2>Error deleting post</h2>";
        }
    }

    echo "<h2>Delete this message:</h2>";
    echo "<h2>" . thread_get_title($msg_bits[0]) . "</h2>";
    if($to_uid == 0){
        $preview_message['TLOGON'] = "ALL";
        $preview_message['TNICK'] = "ALL";
    } else {
        $preview_tuser = user_get($to_uid);
        $preview_message['TLOGON'] = $preview_tuser['LOGON'];
        $preview_message['TNICK'] = $preview_tuser['NICKNAME'];
    }
    $preview_tuser = user_get($from_uid);
    $preview_message['FLOGON'] = $preview_tuser['LOGON'];
    $preview_message['FNICK'] = $preview_tuser['NICKNAME'];
    /*if($t_post_html != "Y"){
        $preview_message['CONTENT'] = make_html($t_content);
    } else {
        $preview_message['CONTENT'] = $t_content;
    }*/
    message_display(0,$preview_message,0,0,false);
}

if(isset($error_html)){
    echo $error_html;
}
echo "<p><form name=\"f_delete\" action=\"" . $HTTP_SERVER_VARS['PHP_SELF'] . "\" method=\"POST\">";
echo "<input type=\"hidden\" name=\"t_msg\" value=\"$delete_msg\">";
echo "<input type=\"hidden\" name=\"t_back\" value=\"$back\">";
echo "<input name=\"submit\" type=\"submit\" value=\"Delete\">";
echo "&nbsp;&nbsp;<input name=\"cancel\" type=\"submit\" value=\"Cancel\"></form>";
echo "<p>&nbsp;&nbsp;</p>";
html_draw_bottom();
?>