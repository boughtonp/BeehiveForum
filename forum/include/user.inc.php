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

require_once("./include/db.inc.php");
require_once("./include/forum.inc.php");
require_once("./include/constants.inc.php");

function user_exists($logon)
{
    $db_user_exists = db_connect();

    $sql = "SELECT uid FROM " . forum_table("USER") . " WHERE logon = \"$logon\"";

    $result = db_query($sql, $db_user_exists);

    $exists = (db_num_rows($result)>0);

    return $exists;
}

function user_create($logon,$password,$nickname,$email)
{
    $md5pass = md5($password);

    $sql = "INSERT INTO " . forum_table("USER") . " (logon,passwd,nickname,email) ";
    $sql .= "VALUES (\"$logon\",\"$md5pass\",\"$nickname\",\"$email\")";

    $db_user_create = db_connect();
    $result = db_query($sql, $db_user_create);
    if($result){
        $new_uid = db_insert_id($db_user_create);
    } else {
        $new_uid = -1;
    }
    return $new_uid;
}

function user_update($uid,$password,$nickname,$email)
{
    $bit = ($password) ? "PASSWD = \"" . md5($password) . "\", " : "";

    $sql = "update " . forum_table("USER") . " set " . $bit . "NICKNAME = \"". htmlspecialchars($nickname). "\", EMAIL = \"". htmlspecialchars($email). "\"";
    $sql .= " WHERE UID = $uid";

    //echo $sql;

    $db_user_update = db_connect();
    $result = db_query($sql, $db_user_update);

    return $result;
}

function user_get_status($uid)
{

    $sql = "select STATUS from ". forum_table("USER") . " where UID = $uid";
    $db_user_get_status = db_connect();

    $result = db_query($sql, $db_user_get_status);
    list($status) = db_fetch_array($result);

    return $status;

}

function user_update_status($uid,$status)
{
    $sql = "update " . forum_table("USER") . " set STATUS = $status ";
    $sql .= "WHERE UID = $uid";

    //echo $sql;

    $db_user_update_status = db_connect();
    $result = db_query($sql, $db_user_update_status);

    return $result;
}

function user_update_folders($uid,$folders)
{
    $count = count($folders);
    if($count == 0) return;

    $db = db_connect();

    for($i=0;$i<$count;$i++){
        $fid = $folders[$i]['fid'];
        $allowed = $folders[$i]['allowed'];
        $sql = "select ALLOWED from ".forum_table("USER_FOLDER")." where UID = '$uid' and FID = '$fid'";
        $result = db_query($sql,$db);
        if(db_num_rows($result)){
            $sql = "update ".forum_table("USER_FOLDER")." set ALLOWED = '$allowed' ";
            $sql.= "where UID = '$uid' and FID = '$fid'";
        } else {
            $sql = "insert into ".forum_table("USER_FOLDER")." (UID,FID,ALLOWED) ";
            $sql.= "values ('$uid','$fid','$allowed')";
        }
        db_query($sql,$db);
    }
}


function user_logon($logon, $password)
{

    global $HTTP_SERVER_VARS;

    $md5pass = md5($password);

    $sql = "SELECT uid, status FROM ". forum_table("USER"). " WHERE logon = '$logon' AND passwd = '$md5pass'";

    $db_user_logon = db_connect();
    $result = db_query($sql, $db_user_logon);

    if(!db_num_rows($result)){
        $uid = -1;
    } else {
        $fa = db_fetch_array($result);
        $uid = $fa['uid'];

        if($fa['status'] & USER_PERM_SPLAT){ // User is banned
            $uid = -2;
        }

	if (!empty($HTTP_SERVER_VARS['HTTP_X_FORWARDED_FOR'])) {
	  $ipaddress = $HTTP_SERVER_VARS['HTTP_X_FORWARDED_FOR'];
	}else {
	  $ipaddress = $HTTP_SERVER_VARS['REMOTE_ADDR'];
	}

        db_query("update ".forum_table("USER")." set LAST_LOGON = NOW(), LOGON_FROM = '$ipaddress' where UID = $uid", $db_user_logon);
    }

    return $uid;
}

function user_check_logon($uid, $logon, $md5pass)
{

    if ($uid > 0) {

      $db_user_check_logon = db_connect();

      $sql = "SELECT STATUS FROM ". forum_table("USER"). " WHERE UID = '$uid' AND LOGON = '$logon' AND PASSWD = '$md5pass'";
      $result = db_query($sql, $db_user_check_logon);

      if (!db_num_rows($result)) {
          return false;
      }else {

          list($status) = db_fetch_array($result);

	  if ($status & USER_PERM_SPLAT) {
	    return false;
	  }else {
            return true;
	  }
      }

    }else {

      return true;

    }

}

function user_get($uid)
{
    $db_user_get = db_connect();

    $sql = "select * from " . forum_table("USER") . " where uid = $uid";

    $result = db_query($sql, $db_user_get);

    if(!db_num_rows($result)){
        $fa = array();
    } else {
        $fa = db_fetch_array($result);
    }

    return $fa;
}

function user_get_logon($uid)
{
    $db_user_get_logon = db_connect();

    $sql = "select LOGON from " . forum_table("USER") . " where uid = $uid";

    $result = db_query($sql, $db_user_get_logon);

    if(!db_num_rows($result)){
        $logon = "UNKNOWN";
    } else {
        $fa = db_fetch_array($result);
        $logon = $fa['LOGON'];
    }

    return $logon;
}

function user_get_uid($logon)
{

    $db_user_get_uid = db_connect();

    $sql = "select UID from ". forum_table("USER"). " where LOGON = '$logon'";
    $result = db_query($sql, $db_user_get_uid);

    if (!db_num_rows($result)) {
        return -1;
    }else{
        $fa = db_fetch_array($result);
        return $fa['UID'];
    }

}

function user_get_sig($uid, &$content, &$html)
{
    $db_user_get_sig = db_connect();

    $sql = "select content, html from " . forum_table("USER_SIG") . " where uid = $uid";

    $result = db_query($sql, $db_user_get_sig);

    if(!db_num_rows($result)){
        $ret = false;
    } else {
        $fa = db_fetch_array($result);
        $content = _stripslashes($fa['content']);
        $html = $fa['html'];
        $ret = true;
    }

    return $ret;
}

function user_get_prefs($uid)
{
    $db_user_get_prefs = db_connect();

    $sql = "select * from " . forum_table("USER_PREFS") . " where uid = $uid";

    $result = db_query($sql, $db_user_get_prefs);

    if(!db_num_rows($result)){
        $fa = array('UID' => '', 'FIRSTNAME' => '', 'LASTNAME' => '', 'HOMEPAGE_URL' => '',
                    'PIC_URL' => '', 'EMAIL_NOTIFY' => '', 'TIMEZONE' => '', 'DL_SAVING' => '',
                    'MARK_AS_OF_INT' => '', 'POST_PER_PAGE' => '', 'FONT_SIZE' => '',
					'STYLE' => '', 'VIEW_SIGS' => '');
    } else {
        $fa = db_fetch_array($result);
    }

    return $fa;
}

function user_update_prefs($uid,$firstname,$lastname,$homepage_url,$pic_url,
                           $email_notify,$timezone,$dl_saving,$mark_as_of_int,
                           $posts_per_page, $font_size, $style, $view_sigs)
{

    $db_user_update_prefs = db_connect();

    $sql = "delete from ". forum_table("USER_PREFS"). " where UID = $uid";
    $result = db_query($sql, $db_user_update_prefs);

    if (empty($timezone)) $timezone = 0;
    if (empty($posts_per_page)) $posts_per_page = 0;
    if (empty($font_size)) $font_size = 0;
	if (!ereg("([[:alnum:]]+)", $style)) $style = $default_style;

    $sql = "insert into " . forum_table("USER_PREFS") . " (UID, FIRSTNAME, LASTNAME, HOMEPAGE_URL,";
    $sql .= " PIC_URL, EMAIL_NOTIFY, TIMEZONE, DL_SAVING, MARK_AS_OF_INT, POSTS_PER_PAGE, FONT_SIZE, STYLE, VIEW_SIGS)";
    $sql .= " values ($uid, \"". htmlspecialchars($firstname). "\", \"". htmlspecialchars($lastname). "\",";
    $sql .= " \"". htmlspecialchars($homepage_url). "\", \"". htmlspecialchars($pic_url). "\",";
    $sql .= " \"". htmlspecialchars($email_notify). "\", $timezone, \"$dl_saving\", \"$mark_as_of_int\",";
	$sql .= " $posts_per_page, $font_size, \"$style\", \"$view_sigs\")";

    $result = db_query($sql, $db_user_update_prefs);

    return $result;
}

function user_update_sig($uid,$content,$html){

    $content = mysql_escape_string($content);
    $db_user_update_sig = db_connect();

    $sql = "delete from ". forum_table("USER_SIG"). " where UID = $uid";
    $result = db_query($sql, $db_user_update_sig);

    $sql = "insert into " . forum_table("USER_SIG") . " (UID, CONTENT, HTML)";
    $sql .= " values ($uid, \"$content\", \"$html\")";

    $result = db_query($sql, $db_user_update_sig);

    return $result;
}

function user_update_global_sig($uid,$value){

    $db_user_update_global_sig = db_connect();

	$sql = "update " . forum_table("USER_PREFS") . " set ";
	$sql .= "VIEW_SIGS = '$value' where UID = $uid";

    $result = db_query($sql, $db_user_update_global_sig);

    return $result;
}

function user_get_global_sig($uid){

    $db_user_update_global_sig = db_connect();

	$sql = "select VIEW_SIGS from " . forum_table("USER_PREFS") . " where uid = $uid";

    $result = db_query($sql, $db_user_update_global_sig);

    if (db_num_rows($result)) {
        $fa = db_fetch_array($result);
        return $fa['VIEW_SIGS'];
    }

	return "";
}

?>