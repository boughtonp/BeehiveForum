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

/* $Id: post.inc.php,v 1.77 2004-04-24 18:42:46 decoyduck Exp $ */

include_once("./include/forum.inc.php");
include_once("./include/fixhtml.inc.php");
include_once("./include/html.inc.php");

function post_create($tid, $reply_pid, $fuid, $tuid, $content)
{
    $db_post_create = db_connect();
    $content = addslashes($content);

    if (!$ipaddress = get_ip_address()) {
        $ipaddress = "";
    }

    if (!is_numeric($tid)) return -1;
    if (!is_numeric($reply_pid)) return -1;
    if (!is_numeric($fuid)) return -1;
    if (!is_numeric($tuid)) return -1;

    if (!$table_data = get_table_prefix()) return -1;

    $sql = "INSERT INTO {$table_data['PREFIX']}POST ";
    $sql.= "(TID, REPLY_TO_PID, FROM_UID, TO_UID, CREATED, IPADDRESS) ";
    $sql.= "VALUES ($tid, $reply_pid, $fuid, $tuid, NOW(), '$ipaddress')";

    $result = db_query($sql,$db_post_create);

    if ($result) {

        $new_pid = db_insert_id($db_post_create);

        $sql = "insert into  {$table_data['PREFIX']}POST_CONTENT ";
        $sql.= "(TID,PID,CONTENT) ";
        $sql.= "values ($tid, $new_pid, '$content')";

        $result = db_query($sql, $db_post_create);

        if ($result) {

            $sql = "update {$table_data['PREFIX']}THREAD set length = $new_pid, modified = NOW() ";
            $sql.= "where tid = $tid";
            $result = db_query($sql, $db_post_create);

        }else {

            $new_pid = -1;

        }

    }else {
        $new_pid = -1;
    }

    return $new_pid;
}

function post_save_attachment_id($tid, $pid, $aid)
{
    if (!is_numeric($tid)) return false;
    if (!is_numeric($pid)) return false;
    if (!is_md5($aid)) return false;

    $db_post_save_attachment_id = db_connect();

    if (!$table_data = get_table_prefix()) return false;

    $sql = "SELECT TID FROM {$table_data['PREFIX']}POST_ATTACHMENT_IDS ";
    $sql.= "WHERE TID = '$tid' AND PID = '$pid'";

    $result = db_query($sql, $db_post_save_attachment_id);

    if (db_num_rows($result) > 0) {

        $sql = "UPDATE {$table_data['PREFIX']}POST_ATTACHMENT_IDS SET AID = '$aid' ";
        $sql.= "WHERE TID = '$tid' AND PID = '$pid'";

    }else {

        $sql = "INSERT INTO {$table_data['PREFIX']}POST_ATTACHMENT_IDS ";
        $sql.= "(TID, PID, AID) VALUES ('$tid', '$pid', '$aid')";
    }

    return db_query($sql, $db_post_save_attachment_id);
}

function post_create_thread($fid, $title, $poll = 'N', $sticky = 'N', $closed = false)
{
    if (!is_numeric($fid)) return -1;

    $title  = addslashes(_htmlentities($title));

    $poll = ($poll == 'Y') ? 'Y' : 'N';
    $sticky = ($sticky == 'Y') ? 'Y' : 'N';
    $closed = $closed ? "NOW()" : "NULL";

    $db_post_create_thread = db_connect();

    if (!$table_data = get_table_prefix()) return -1;

    $sql = "insert into {$table_data['PREFIX']}THREAD" ;
    $sql.= "(FID,TITLE,LENGTH,POLL_FLAG,STICKY,MODIFIED,CLOSED) ";
    $sql.= "values ($fid, '$title', 0, '$poll', '$sticky', NOW(), $closed)";

    $result = db_query($sql, $db_post_create_thread);

    if($result){
        $new_tid = db_insert_id($db_post_create_thread);
    } else {
        $new_tid = -1;
    }

    return $new_tid;
}

function post_draw_to_dropdown($default_uid, $show_all = true)
{
    $html = "<select name=\"t_to_uid\">\n";
    $db_post_draw_to_dropdown = db_connect();

    if (!is_numeric($default_uid)) $default_uid = 0;

    if (isset($default_uid) && $default_uid != 0){

        $top_sql = "SELECT LOGON, NICKNAME FROM USER where UID = '$default_uid'";
        $result = db_query($top_sql,$db_post_draw_to_dropdown);

        if (db_num_rows($result) > 0) {

            $top_user = db_fetch_array($result);
            $fmt_username = format_user_name($top_user['LOGON'],$top_user['NICKNAME']);
            $html .= "<option value=\"$default_uid\" selected=\"selected\">$fmt_username</option>\n";
        }
    }

    if ($show_all) {
        $html .= "<option value=\"0\">ALL</option>\n";
    }

    $sql = "SELECT USER.UID, USER.LOGON, USER.NICKNAME, ";
    $sql.= "UNIX_TIMESTAMP(VISITOR_LOG.LAST_LOGON) AS LAST_LOGON FROM USER USER ";
    $sql.= "LEFT JOIN VISITOR_LOG VISITOR_LOG ON (USER.UID = VISITOR_LOG.UID) ";
    $sql.= "WHERE (USER.LOGON <> 'GUEST' AND USER.PASSWD <> MD5('GUEST')) ";
    $sql.= "AND USER.UID <> '$default_uid' ORDER BY VISITOR_LOG.LAST_LOGON DESC ";
    $sql.= "LIMIT 0, 20";

    $result = db_query($sql, $db_post_draw_to_dropdown);

    while ($row = db_fetch_array($result)) {

        if (isset($row['LOGON'])) {
           $logon = $row['LOGON'];
        } else {
           $logon = "";
        }

        if(isset($row['NICKNAME'])){
            $nickname = $row['NICKNAME'];
        } else {
            $nickname = "";
        }

        $fmt_uid = $row['UID'];
        $fmt_username = format_user_name($logon,$nickname);

        if($fmt_uid != $default_uid && $fmt_uid != 0){
            $html .= "<option value=\"$fmt_uid\">$fmt_username</option>\n";
        }
    }

    $html .= "</select>";
    return $html;
}

function post_draw_to_dropdown_recent($default_uid, $show_all = true)
{
    $html = "<select name=\"t_to_uid_recent\" style=\"width: 190px\" onClick=\"checkToRadio(". ($default_uid == 0 ? 1 : 0).")\">\n";
    $db_post_draw_to_dropdown = db_connect();

    if (!$table_data = get_table_prefix()) return "";

    if (!is_numeric($default_uid)) $default_uid = 0;

    if (isset($default_uid) && $default_uid != 0) {

        $top_sql = "SELECT LOGON, NICKNAME FROM USER WHERE UID = '$default_uid'";
        $result = db_query($top_sql,$db_post_draw_to_dropdown);

        if (db_num_rows($result) > 0) {

            $top_user = db_fetch_array($result);
            $fmt_username = format_user_name($top_user['LOGON'],$top_user['NICKNAME']);
            $html .= "<option value=\"$default_uid\" selected=\"selected\">$fmt_username</option>\n";
        }
    }

    if ($show_all) {
        $html .= "<option value=\"0\">ALL</option>\n";
    }

    $sql = "SELECT USER.UID, USER.LOGON, USER.NICKNAME, ";
    $sql.= "UNIX_TIMESTAMP(VISITOR_LOG.LAST_LOGON) AS LAST_LOGON FROM USER USER ";
    $sql.= "LEFT JOIN VISITOR_LOG VISITOR_LOG ON (USER.UID = VISITOR_LOG.UID) ";
    $sql.= "WHERE (USER.LOGON <> 'GUEST' AND USER.PASSWD <> MD5('GUEST')) ";
    $sql.= "AND USER.UID <> '$default_uid' ORDER BY VISITOR_LOG.LAST_LOGON DESC ";
    $sql.= "LIMIT 0, 20";

    $result = db_query($sql, $db_post_draw_to_dropdown);

    while ($row = db_fetch_array($result)) {

        if (isset($row['LOGON'])) {
           $logon = $row['LOGON'];
        } else {
           $logon = "";
        }

        if(isset($row['NICKNAME'])){
            $nickname = $row['NICKNAME'];
        } else {
            $nickname = "";
        }

        $fmt_uid = $row['UID'];
        $fmt_username = format_user_name($logon,$nickname);

        if($fmt_uid != $default_uid && $fmt_uid != 0){
            $html .= "<option value=\"$fmt_uid\">$fmt_username</option>\n";
        }
    }

    $html .= "</select>";
    return $html;
}

function post_draw_to_dropdown_in_thread($tid, $default_uid, $show_all = true, $inc_blank = false, $custom_html = "")
{
    $html = "<select name=\"t_to_uid_in_thread\" style=\"width: 190px\" ".$custom_html.">\n";
    $db_post_draw_to_dropdown = db_connect();

    if (!is_numeric($tid)) return false;
    if (!is_numeric($default_uid)) $default_uid = 0;

    if (!$table_data = get_table_prefix()) return "";

    if (isset($default_uid) && $default_uid != 0) {

        $top_sql = "SELECT LOGON, NICKNAME FROM USER WHERE UID = '$default_uid'";
        $result = db_query($top_sql,$db_post_draw_to_dropdown);

        if (db_num_rows($result) > 0) {

            $top_user = db_fetch_array($result);
            $fmt_username = format_user_name($top_user['LOGON'],$top_user['NICKNAME']);
            $html.= "<option value=\"$default_uid\" selected=\"selected\">$fmt_username</option>\n";
        }
    }

    if ($show_all) {

        $html.= "<option value=\"0\">ALL</option>\n";

    } else if ($inc_blank) {

        if (isset($default_uid) && $default_uid != 0) {
            $html.= "<option value=\"0\"></option>\n";
		}else {
            $html.= "<option value=\"0\" selected=\"selected\"></option>\n";
		}
    }

    $sql = "SELECT DISTINCT P.FROM_UID AS UID, U.LOGON, U.NICKNAME ";
    $sql.= "FROM {$table_data['PREFIX']}POST P ";
    $sql.= "LEFT JOIN USER U ON (P.FROM_UID = U.UID) ";
    $sql.= "WHERE P.TID = '$tid' ";
    $sql.= "LIMIT 0, 20";

    $result = db_query($sql, $db_post_draw_to_dropdown);

    while ($row = db_fetch_array($result)) {

        if (isset($row['LOGON'])) {
           $logon = $row['LOGON'];
        } else {
           $logon = "";
        }

        if(isset($row['NICKNAME'])){
            $nickname = $row['NICKNAME'];
        } else {
            $nickname = "";
        }

        $fmt_uid = $row['UID'];
        $fmt_username = format_user_name($logon,$nickname);

        if ($fmt_uid != $default_uid && $fmt_uid != 0) {
            $html .= "<option value=\"$fmt_uid\">$fmt_username</option>\n";
        }
    }

    $html .= "</select>";
    return $html;
}

function get_user_posts($uid)
{
    $db_get_user_posts = db_connect();

    if (!is_numeric($uid)) return false;

    if (!$table_data = get_table_prefix()) return false;

    $sql = "SELECT TID, PID FROM {$table_data['PREFIX']}POST WHERE FROM_UID = '$uid'";
    $result = db_query($sql, $db_get_user_posts);

    if (db_num_rows($result)) {
        $user_post_array = array();
	while ($row = db_fetch_array($result)) {
	    $user_post_array[] = $row;
	}
	return $user_post_array;
    }else {
        return false;
    }
}

function check_ddkey($ddkey)
{
    $db_check_ddkey = db_connect();
    $uid = bh_session_get_value('UID');

    if (!$table_data = get_table_prefix()) return false;

    $sql = "SELECT DDKEY FROM {$table_data['PREFIX']}DEDUPE WHERE UID = '$uid'";
    $result = db_query($sql, $db_check_ddkey);

    if (db_num_rows($result)) {

        list($ddkey_check) = db_fetch_array($result);
        $sql = "UPDATE {$table_data['PREFIX']}DEDUPE SET DDKEY = '$ddkey' WHERE UID = '$uid'";
        $result = db_query($sql, $db_check_ddkey);

    }else{

        $ddkey_check = "";

        $sql = "INSERT INTO {$table_data['PREFIX']}DEDUPE (UID, DDKEY) ";
        $sql.= "VALUES ('$uid', '$ddkey')";
        $result = db_query($sql, $db_check_ddkey);
    }

    return !($ddkey == $ddkey_check);
}


class MessageText {

	var $html;
	var $text;
	var $original_text;
	var $diff;
	var $emoticons;

	function MessageText ($html = 0, $content = "", $emoticons = true) {
		$this->diff = false;
		$this->emoticons = $emoticons;
		$this->setHTML($html);
		$this->setContent($content);
	}

	function setHTML ($html) {
		if ($html == false || $html == "N") {
			$this->html = 0;
		} else if ($html == 1 || $html == "A") {
			$this->html = 1;
		} else {
			$this->html = 2;
		}
	}

	function getHTML () {
		return $this->html;
	}

	function setContent ($text) {

		$text = _stripslashes($text);

		$this->original_text = $text;

		if ($this->html == 0) {
			$text = make_html($text);
		} else if ($this->html > 0) {
			$text = fix_html($text, $this->emoticons);

			if ($this->original_text != tidy_html($text, ($this->html == 1) ? true : false)) {
				$this->diff = true;
			}

			if ($this->html == 1) {
				$text = add_paragraphs($text);
			}
		}

		$this->text = $text;
	}

	function getContent () {
		return $this->text;
	}

	function getTidyContent () {
		if ($this->html == 0) {
			return strip_tags($this->text);
		} else if ($this->html > 0) {
			return _htmlentities(tidy_html($this->text, ($this->html == 1) ? true : false));
		}
	}

	function getOriginalContent () {
		return $this->original_text;
	}

	function isDiff () {
		return $this->diff;
	}
}

?>