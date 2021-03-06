<?php

/*======================================================================
Copyright Project Beehive Forum 2002

This file is part of Beehive Forum.

Beehive Forum is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

Beehive Forum is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Beehive; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307
USA
======================================================================*/

// We shouldn't be accessing this file directly.
if (basename($_SERVER['SCRIPT_NAME']) == basename(__FILE__)) {
    header("Request-URI: ../index.php");
    header("Content-Location: ../index.php");
    header("Location: ../index.php");
    exit;
}

include_once(BH_INCLUDE_PATH. "admin.inc.php");
include_once(BH_INCLUDE_PATH. "constants.inc.php");
include_once(BH_INCLUDE_PATH. "db.inc.php");
include_once(BH_INCLUDE_PATH. "format.inc.php");
include_once(BH_INCLUDE_PATH. "forum.inc.php");
include_once(BH_INCLUDE_PATH. "fixhtml.inc.php");
include_once(BH_INCLUDE_PATH. "html.inc.php");
include_once(BH_INCLUDE_PATH. "ip.inc.php");
include_once(BH_INCLUDE_PATH. "lang.inc.php");
include_once(BH_INCLUDE_PATH. "profile.inc.php");
include_once(BH_INCLUDE_PATH. "search.inc.php");
include_once(BH_INCLUDE_PATH. "session.inc.php");
include_once(BH_INCLUDE_PATH. "sphinx.inc.php");
include_once(BH_INCLUDE_PATH. "thread.inc.php");
include_once(BH_INCLUDE_PATH. "user.inc.php");
include_once(BH_INCLUDE_PATH. "user_profile.inc.php");
include_once(BH_INCLUDE_PATH. "word_filter.inc.php");

function post_create($fid, $tid, $reply_pid, $fuid, $tuid, $content, $hide_ipaddress = false)
{
    if (!$db_post_create = db_connect()) return -1;

    $post_content = db_escape_string($content);

    $ipaddress = ($hide_ipaddress == false) ? get_ip_address() : '';

    if (!is_numeric($tid)) return -1;
    if (!is_numeric($reply_pid)) return -1;
    if (!is_numeric($fuid)) return -1;
    if (!is_numeric($tuid)) return -1;

    $current_datetime = date(MYSQL_DATETIME, time());

    if (!$table_data = get_table_prefix()) return -1;

    // Check that the post needs approval. If the user is a moderator their posts are self-approved.
    if (perm_check_folder_permissions($fid, USER_PERM_POST_APPROVAL, $fuid) && !perm_is_moderator($fuid, $fid)) {

        $sql = "INSERT INTO `{$table_data['PREFIX']}POST` (TID, REPLY_TO_PID, FROM_UID, ";
        $sql.= "TO_UID, CREATED, APPROVED, IPADDRESS) VALUES ($tid, $reply_pid, $fuid, ";
        $sql.= "$tuid, CAST('$current_datetime' AS DATETIME), NULL, '$ipaddress')";

    }else {

        $sql = "INSERT INTO `{$table_data['PREFIX']}POST` (TID, REPLY_TO_PID, FROM_UID, ";
        $sql.= "TO_UID, CREATED, APPROVED, APPROVED_BY, IPADDRESS) VALUES ($tid, $reply_pid, ";
        $sql.= "$fuid, $tuid, CAST('$current_datetime' AS DATETIME), ";
        $sql.= "CAST('$current_datetime' AS DATETIME), $fuid, '$ipaddress')";
    }

    if (!db_query($sql, $db_post_create)) return -1;

    $new_pid = db_insert_id($db_post_create);

    $sql = "INSERT INTO `{$table_data['PREFIX']}POST_CONTENT` (TID, PID, CONTENT) ";
    $sql.= "VALUES ('$tid', '$new_pid', '$post_content')";

    if (!db_query($sql, $db_post_create)) return -1;
    
    $sql = "INSERT INTO `{$table_data['PREFIX']}POST_SEARCH_ID` (TID, PID) ";
    $sql.= "VALUES('$tid', '$new_pid')";
    
    if (!db_query($sql, $db_post_create)) return -1;

    post_update_thread_length($tid, $new_pid);

    user_increment_post_count($fuid);

    if (perm_check_folder_permissions($fid, USER_PERM_POST_APPROVAL, $fuid) && !perm_is_moderator($fuid, $fid)) {
        admin_send_post_approval_notification($fid);
    }

    return $new_pid;
}

function post_approve($tid, $pid)
{
    if (!is_numeric($tid)) return false;
    if (!is_numeric($pid)) return false;

    if (!$db_post_approve = db_connect()) return false;

    $approve_uid = session_get_value('UID');

    if (!$table_data = get_table_prefix()) return false;

    $current_datetime = date(MYSQL_DATETIME, time());

    $sql = "UPDATE LOW_PRIORITY `{$table_data['PREFIX']}POST` ";
    $sql.= "SET APPROVED = CAST('$current_datetime' AS DATETIME), ";
    $sql.= "APPROVED_BY = '$approve_uid' WHERE TID = '$tid' ";
    $sql.= "AND PID = '$pid'";

    if (!db_query($sql, $db_post_approve)) return false;

    return true;
}

function post_save_attachment_id($tid, $pid, $aid)
{
    if (!is_numeric($tid)) return false;
    if (!is_numeric($pid)) return false;
    if (!is_md5($aid)) return false;

    if (!$db_post_save_attachment_id = db_connect()) return false;

    if (!$table_data = get_table_prefix()) return false;

    $forum_fid = $table_data['FID'];

    $sql = "INSERT INTO POST_ATTACHMENT_IDS (FID, TID, PID, AID) ";
    $sql.= "VALUES ($forum_fid, $tid, $pid, '$aid') ON DUPLICATE KEY ";
    $sql.= "UPDATE AID = VALUES(AID)";

    if (!db_query($sql, $db_post_save_attachment_id)) return false;

    return true;
}

function post_create_thread($fid, $uid, $title, $poll = 'N', $sticky = 'N', $closed = false, $deleted = false)
{
    if (!is_numeric($fid)) return false;

    if (!is_numeric($uid)) return false;

    $title = db_escape_string($title);

    $poll = ($poll == 'Y') ? 'Y' : 'N';

    $sticky = ($sticky == 'Y') ? 'Y' : 'N';

    $closed = ($closed === true) ? sprintf("'%s'", date(MYSQL_DATETIME, time())) : 'NULL';

    $deleted = ($deleted === true) ? 'Y' : 'N';

    if (!$db_post_create_thread = db_connect()) return false;

    if (!$table_data = get_table_prefix()) return false;

    // Current datetime
    $current_datetime = date(MYSQL_DATETIME, time());

    $sql = "INSERT INTO `{$table_data['PREFIX']}THREAD` (FID, BY_UID, TITLE, LENGTH, POLL_FLAG, ";
    $sql.= "STICKY, CREATED, MODIFIED, CLOSED, DELETED) VALUES ('$fid', '$uid', '$title', 0, '$poll', ";
    $sql.= "'$sticky', CAST('$current_datetime' AS DATETIME), CAST('$current_datetime' AS DATETIME), ";
    $sql.= "$closed, '$deleted')";

    if (!db_query($sql, $db_post_create_thread)) return false;

    return db_insert_id($db_post_create_thread);
}

function post_update_thread_length($tid, $length)
{
    if (!$db_post_update_thread_length = db_connect()) return false;

    if (!$table_data = get_table_prefix()) return false;

    if (!is_numeric($tid)) return false;
    if (!is_numeric($length)) return false;

    $current_datetime = date(MYSQL_DATETIME, time());

    $sql = "UPDATE LOW_PRIORITY `{$table_data['PREFIX']}THREAD` SET LENGTH = '$length', ";
    $sql.= "MODIFIED = CAST('$current_datetime' AS DATETIME) WHERE TID = '$tid'";

    if (!db_query($sql, $db_post_update_thread_length)) return false;

    if (($unread_cutoff_datetime = forum_get_unread_cutoff_datetime()) !== false) {

        $sql = "INSERT INTO `{$table_data['PREFIX']}THREAD` (TID, UNREAD_PID) ";
        $sql.= "SELECT THREAD.TID, MAX(POST.PID) AS UNREAD_PID FROM `{$table_data['PREFIX']}THREAD` THREAD ";
        $sql.= "LEFT JOIN `{$table_data['PREFIX']}POST` POST ON (POST.TID = THREAD.TID) ";
        $sql.= "WHERE POST.CREATED < CAST('$unread_cutoff_datetime' AS DATETIME) ";
        $sql.= "AND THREAD.TID = '$tid' GROUP BY THREAD.TID ";
        $sql.= "ON DUPLICATE KEY UPDATE UNREAD_PID = VALUES(UNREAD_PID)";

        if (!db_query($sql, $db_post_update_thread_length)) return false;
    }

    return true;
}

function post_draw_to_dropdown($default_uid, $show_all = true)
{
    $class = defined('BEEHIVEMODE_LIGHT') ? 'select' : 'bhselect';

    $html = "<select name=\"t_to_uid\" class=\"$class\">";

    if (!$db_post_draw_to_dropdown = db_connect()) return false;

    if (!is_numeric($default_uid)) $default_uid = 0;

    if (!$table_data = get_table_prefix()) return false;

    $forum_fid = $table_data['FID'];

    $uid = session_get_value('UID');

    if (isset($default_uid) && $default_uid != 0) {

        $sql = "SELECT USER.LOGON, USER.NICKNAME, USER_PEER.PEER_NICKNAME ";
        $sql.= "FROM USER LEFT JOIN `{$table_data['PREFIX']}USER_PEER` USER_PEER ";
        $sql.= "ON (USER_PEER.PEER_UID = USER.UID AND USER_PEER.UID = '$uid') ";
        $sql.= "WHERE USER.UID = '$default_uid' ";

        if (!$result = db_query($sql, $db_post_draw_to_dropdown)) return false;

        if (db_num_rows($result) > 0) {

            if (($top_user = db_fetch_array($result))) {

                if (isset($top_user['PEER_NICKNAME'])) {
                    if (!is_null($top_user['PEER_NICKNAME']) && strlen($top_user['PEER_NICKNAME']) > 0) {
                        $top_user['NICKNAME'] = $top_user['PEER_NICKNAME'];
                    }
                }

                $fmt_username = word_filter_add_ob_tags(format_user_name($top_user['LOGON'], $top_user['NICKNAME']), true);
                $html.= "<option value=\"$default_uid\" selected=\"selected\">$fmt_username</option>";
            }
        }
    }

    if ($show_all) {
        $html .= "<option value=\"0\">". gettext("ALL"). "</option>";
    }

    $sql = "SELECT VISITOR_LOG.UID, USER.LOGON, USER.NICKNAME, USER_PEER.PEER_NICKNAME, ";
    $sql.= "UNIX_TIMESTAMP(VISITOR_LOG.LAST_LOGON) AS LAST_LOGON FROM VISITOR_LOG VISITOR_LOG ";
    $sql.= "LEFT JOIN USER USER ON (USER.UID = VISITOR_LOG.UID) ";
    $sql.= "LEFT JOIN `{$table_data['PREFIX']}USER_PEER` USER_PEER ";
    $sql.= "ON (USER_PEER.PEER_UID = USER.UID AND USER_PEER.UID = '$uid') ";
    $sql.= "WHERE VISITOR_LOG.FORUM = '$forum_fid' AND VISITOR_LOG.UID <> '$default_uid' ";
    $sql.= "AND VISITOR_LOG.UID > 0 ORDER BY VISITOR_LOG.LAST_LOGON DESC ";
    $sql.= "LIMIT 0, 20";

    if (!$result = db_query($sql, $db_post_draw_to_dropdown)) return false;

    while (($user_data = db_fetch_array($result))) {

        if (isset($user_data['LOGON'])) {

            if (isset($user_data['LOGON']) && isset($user_data['PEER_NICKNAME'])) {
                if (!is_null($user_data['PEER_NICKNAME']) && strlen($user_data['PEER_NICKNAME']) > 0) {
                    $user_data['NICKNAME'] = $user_data['PEER_NICKNAME'];
                }
            }

            $fmt_username = word_filter_add_ob_tags(format_user_name($user_data['LOGON'], $user_data['NICKNAME']), true);
            $html .= "<option value=\"{$user_data['UID']}\">$fmt_username</option>";
        }
    }

    $html.= "</select>";
    return $html;
}

function post_draw_to_dropdown_recent($default_uid)
{
    $class = defined('BEEHIVEMODE_LIGHT') ? 'select' : 'recent_user_dropdown';

    $html = "<select name=\"t_to_uid_recent\" class=\"$class\">";

    if (!$db_post_draw_to_dropdown = db_connect()) return false;

    if (!$table_data = get_table_prefix()) return false;
    if (!is_numeric($default_uid)) $default_uid = 0;

    $forum_fid = $table_data['FID'];

    $uid = session_get_value('UID');

    if (isset($default_uid) && $default_uid != 0) {

        $sql = "SELECT USER.LOGON, USER.NICKNAME, USER_PEER.PEER_NICKNAME ";
        $sql.= "FROM USER LEFT JOIN `{$table_data['PREFIX']}USER_PEER` USER_PEER ";
        $sql.= "ON (USER_PEER.PEER_UID = USER.UID AND USER_PEER.UID = '$uid') ";
        $sql.= "WHERE USER.UID = '$default_uid' ";

        if (!$result = db_query($sql, $db_post_draw_to_dropdown)) return false;

        if (db_num_rows($result) > 0) {

            if (($top_user = db_fetch_array($result))) {

                if (isset($top_user['PEER_NICKNAME'])) {
                    if (!is_null($top_user['PEER_NICKNAME']) && strlen($top_user['PEER_NICKNAME']) > 0) {
                        $top_user['NICKNAME'] = $top_user['PEER_NICKNAME'];
                    }
                }

                $fmt_username = word_filter_add_ob_tags(format_user_name($top_user['LOGON'], $top_user['NICKNAME']), true);
                $html.= "<option value=\"$default_uid\" selected=\"selected\">$fmt_username</option>";
            }
        }
    }

    $html .= "<option value=\"0\">". gettext("ALL"). "</option>";

    $sql = "SELECT VISITOR_LOG.UID, USER.LOGON, USER.NICKNAME, USER_PEER.PEER_NICKNAME, ";
    $sql.= "UNIX_TIMESTAMP(VISITOR_LOG.LAST_LOGON) AS LAST_LOGON FROM VISITOR_LOG VISITOR_LOG ";
    $sql.= "LEFT JOIN USER USER ON (USER.UID = VISITOR_LOG.UID) ";
    $sql.= "LEFT JOIN `{$table_data['PREFIX']}USER_PEER` USER_PEER ";
    $sql.= "ON (USER_PEER.PEER_UID = USER.UID AND USER_PEER.UID = '$uid') ";
    $sql.= "WHERE VISITOR_LOG.FORUM = '$forum_fid' AND VISITOR_LOG.UID <> '$default_uid' ";
    $sql.= "AND VISITOR_LOG.UID > 0 ORDER BY VISITOR_LOG.LAST_LOGON DESC ";
    $sql.= "LIMIT 0, 20";

    if (!$result = db_query($sql, $db_post_draw_to_dropdown)) return false;

    while (($user_data = db_fetch_array($result))) {

        if (isset($user_data['LOGON'])) {

            if (isset($user_data['LOGON']) && isset($user_data['PEER_NICKNAME'])) {
                if (!is_null($user_data['PEER_NICKNAME']) && strlen($user_data['PEER_NICKNAME']) > 0) {
                    $user_data['NICKNAME'] = $user_data['PEER_NICKNAME'];
                }
            }

            $fmt_username = word_filter_add_ob_tags(format_user_name($user_data['LOGON'], $user_data['NICKNAME']), true);
            $html .= "<option value=\"{$user_data['UID']}\">$fmt_username</option>";
        }
    }

    $html.= "</select>";
    return $html;
}

function post_draw_to_dropdown_in_thread($tid, $default_uid, $show_all = true, $inc_blank = false, $class = 'user_in_thread_dropdown', $custom_html = "")
{
    $html = "<select name=\"t_to_uid_in_thread\" class=\"$class\" $custom_html>";

    if (!$db_post_draw_to_dropdown = db_connect()) return false;

    if (!is_numeric($tid)) return false;
    if (!is_numeric($default_uid)) $default_uid = 0;

    if (!$table_data = get_table_prefix()) return "";

    $uid = session_get_value('UID');

    if (isset($default_uid) && $default_uid != 0) {

        $sql = "SELECT USER.LOGON, USER.NICKNAME, USER_PEER.PEER_NICKNAME ";
        $sql.= "FROM USER LEFT JOIN `{$table_data['PREFIX']}USER_PEER` USER_PEER ";
        $sql.= "ON (USER_PEER.PEER_UID = USER.UID AND USER_PEER.UID = '$uid') ";
        $sql.= "WHERE USER.UID = '$default_uid' ";

        if (!$result = db_query($sql, $db_post_draw_to_dropdown)) return false;

        if (db_num_rows($result) > 0) {

            if (($top_user = db_fetch_array($result))) {

                if (isset($top_user['PEER_NICKNAME'])) {
                    if (!is_null($top_user['PEER_NICKNAME']) && strlen($top_user['PEER_NICKNAME']) > 0) {
                        $top_user['NICKNAME'] = $top_user['PEER_NICKNAME'];
                    }
                }

                $fmt_username = word_filter_add_ob_tags(format_user_name($top_user['LOGON'], $top_user['NICKNAME']), true);
                $html.= "<option value=\"$default_uid\" selected=\"selected\">$fmt_username</option>";
            }
        }
    }

    if ($show_all) {

        $html.= "<option value=\"0\">". gettext("ALL"). "</option>";

    }else if ($inc_blank) {

        if (isset($default_uid) && $default_uid != 0) {
            $html.= "<option value=\"0\">&nbsp;</option>";
        }else {
            $html.= "<option value=\"0\" selected=\"selected\">&nbsp;</option>";
        }
    }

    $sql = "SELECT POST.FROM_UID AS UID, USER.LOGON, USER.NICKNAME, ";
    $sql.= "USER_PEER.PEER_NICKNAME FROM `{$table_data['PREFIX']}POST` POST ";
    $sql.= "LEFT JOIN USER USER ON (USER.UID = POST.FROM_UID) ";
    $sql.= "LEFT JOIN `{$table_data['PREFIX']}USER_PEER` USER_PEER ";
    $sql.= "ON (USER_PEER.PEER_UID = POST.FROM_UID AND USER_PEER.UID = '$uid') ";
    $sql.= "WHERE POST.TID = '$tid' AND POST.FROM_UID <> '$default_uid' ";
    $sql.= "GROUP BY POST.FROM_UID LIMIT 0, 20";

    if (!$result = db_query($sql, $db_post_draw_to_dropdown)) return false;

    while (($user_data = db_fetch_array($result))) {

        if (isset($user_data['LOGON'])) {

            if (isset($user_data['LOGON']) && isset($user_data['PEER_NICKNAME'])) {
                if (!is_null($user_data['PEER_NICKNAME']) && strlen($user_data['PEER_NICKNAME']) > 0) {
                    $user_data['NICKNAME'] = $user_data['PEER_NICKNAME'];
                }
            }

            $fmt_username = word_filter_add_ob_tags(format_user_name($user_data['LOGON'], $user_data['NICKNAME']), true);
            $html .= "<option value=\"{$user_data['UID']}\">$fmt_username</option>";
        }
    }

    $html .= "</select>";
    return $html;
}

function post_check_ddkey($ddkey)
{
    if (!$db_post_check_ddkey = db_connect()) return false;

    if (!is_numeric($ddkey)) return false;

    $ddkey_datetime = date(MYSQL_DATETIME, $ddkey);

    if (($uid = session_get_value('UID')) === false) return false;

    if (!$table_data = get_table_prefix()) return false;

    $sql = "SELECT DDKEY FROM `{$table_data['PREFIX']}USER_TRACK` ";
    $sql.= "WHERE UID = '$uid'";

    if (!$result = db_query($sql, $db_post_check_ddkey)) return false;

    if (db_num_rows($result)) {

        list($ddkey_datetime_check) = db_fetch_array($result);

        $sql = "UPDATE LOW_PRIORITY `{$table_data['PREFIX']}USER_TRACK` ";
        $sql.= "SET DDKEY = CAST('$ddkey_datetime' AS DATETIME) WHERE UID = '$uid'";

        if (!$result = db_query($sql, $db_post_check_ddkey)) return false;

    }else{

        $ddkey_datetime_check = '';

        $sql = "INSERT INTO `{$table_data['PREFIX']}USER_TRACK` (UID, DDKEY) ";
        $sql.= "VALUES ('$uid', CAST('$ddkey_datetime' AS DATETIME))";

        if (!$result = db_query($sql, $db_post_check_ddkey)) return false;
    }

    return !($ddkey_datetime == $ddkey_datetime_check);
}

function post_check_frequency()
{
    if (!$db_post_check_frequency = db_connect()) return false;

    if (($uid = session_get_value('UID')) === false) return false;

    if (!$table_data = get_table_prefix()) return false;

    $minimum_post_frequency = intval(forum_get_setting('minimum_post_frequency', false, 0));

    if ($minimum_post_frequency == 0) return true;

    $current_datetime = date(MYSQL_DATE_HOUR_MIN, time());

    $sql = "SELECT UNIX_TIMESTAMP(LAST_POST) + $minimum_post_frequency, ";
    $sql.= "UNIX_TIMESTAMP('$current_datetime') FROM `{$table_data['PREFIX']}USER_TRACK` ";
    $sql.= "WHERE UID = '$uid'";

    if (!$result = db_query($sql, $db_post_check_frequency)) return false;

    if (db_num_rows($result) > 0) {

        list($last_post_stamp, $current_timestamp) = db_fetch_array($result);

        if (!is_numeric($last_post_stamp) || $last_post_stamp < $current_timestamp) {

            $sql = "UPDATE LOW_PRIORITY `{$table_data['PREFIX']}USER_TRACK` ";
            $sql.= "SET LAST_POST = CAST('$current_datetime' AS DATETIME) ";
            $sql.= "WHERE UID = '$uid'";

            if (!$result = db_query($sql, $db_post_check_frequency)) return false;

            return true;
        }

    }else{

        $sql = "INSERT INTO `{$table_data['PREFIX']}USER_TRACK` (UID, LAST_POST) ";
        $sql.= "VALUES ('$uid', CAST('$current_datetime' AS DATETIME))";

        if (!$result = db_query($sql, $db_post_check_frequency)) return false;

        return true;
    }

    return false;
}

function post_update($fid, $tid, $pid, $content)
{
    if (!is_numeric($tid)) return false;
    if (!is_numeric($pid)) return false;

    if (!$db_post_update = db_connect()) return false;

    $content = db_escape_string($content);

    if (!$table_data = get_table_prefix()) return false;

    $sql = "UPDATE LOW_PRIORITY `{$table_data['PREFIX']}POST_CONTENT` SET CONTENT = '$content' ";
    $sql.= "WHERE TID = '$tid' AND PID = '$pid' LIMIT 1";

    if (!db_query($sql, $db_post_update)) return false;

    if (session_check_perm(USER_PERM_POST_APPROVAL, $fid) && !session_check_perm(USER_PERM_FOLDER_MODERATE, $fid)) {

        $sql = "UPDATE LOW_PRIORITY `{$table_data['PREFIX']}POST` SET APPROVED = 0, APPROVED_BY = 0 ";
        $sql.= "WHERE TID = '$tid' AND PID = '$pid' LIMIT 1";

        if (!db_query($sql, $db_post_update)) return false;
    }

    return true;
}

function post_add_edit_text($tid, $pid)
{
    if (!is_numeric($tid)) return false;
    if (!is_numeric($pid)) return false;

    if (!$db_post_add_edit_text = db_connect()) return false;

    if (($edit_uid = session_get_value('UID')) === false) return false;

    if (!$table_data = get_table_prefix()) return false;

    $current_datetime = date(MYSQL_DATETIME, time());

    $sql = "UPDATE LOW_PRIORITY `{$table_data['PREFIX']}POST` ";
    $sql.= "SET EDITED = CAST('$current_datetime' AS DATETIME), ";
    $sql.= "EDITED_BY = '$edit_uid' WHERE TID = '$tid' AND PID = '$pid'";

    if (!db_query($sql, $db_post_add_edit_text)) return false;

    return true;
}

function post_delete($tid, $pid)
{
    if (!is_numeric($tid)) return false;
    if (!is_numeric($pid)) return false;

    if (!$table_data = get_table_prefix()) return false;

    if (!$db_post_delete = db_connect()) return false;

    if (($approve_uid = session_get_value('UID')) === false) return false;

    $current_datetime = date(MYSQL_DATETIME, time());

    if (thread_is_poll($tid) && $pid == 1) {

        $sql = "UPDATE LOW_PRIORITY `{$table_data['PREFIX']}THREAD` SET POLL_FLAG = 'N', ";
        $sql.= "MODIFIED = CAST('$current_datetime' AS DATETIME) WHERE TID = '$tid'";

        if (!db_query($sql, $db_post_delete)) return false;
    }

    $sql = "UPDATE LOW_PRIORITY `{$table_data['PREFIX']}THREAD` SET DELETED = 'Y', ";
    $sql.= "MODIFIED = CAST('$current_datetime' AS DATETIME) WHERE TID = '$tid' AND LENGTH = 1";

    if (!db_query($sql, $db_post_delete)) return false;

    $sql = "UPDATE LOW_PRIORITY `{$table_data['PREFIX']}POST_CONTENT` SET CONTENT = NULL ";
    $sql.= "WHERE TID = '$tid' AND PID = '$pid'";

    if (!db_query($sql, $db_post_delete)) return false;

    $sql = "UPDATE LOW_PRIORITY `{$table_data['PREFIX']}POST` ";
    $sql.= "SET APPROVED = CAST('$current_datetime' AS DATETIME), ";
    $sql.= "APPROVED_BY = '$approve_uid' WHERE TID = '$tid' ";
    $sql.= "AND PID = '$pid'";

    if (!db_query($sql, $db_post_delete)) return false;

    return true;
}

function post_edit_refuse($tid, $pid)
{
    html_error_msg(gettext("You are not permitted to edit this message."), 'discussion.php', 'get', array('back' => gettext("Back")), array('msg' => "$tid.$pid"));
}

class MessageText {

    private $html = "";
    private $text = "";
    private $original_text = "";
    private $emoticons = true;
    private $links = true;
    private $tiny_mce;

    public $diff = false;

    function MessageText ($html = 0, $content = "", $emoticons = true, $links = true, $tiny_mce = null)
    {
        $post_prefs = session_get_post_page_prefs();
        
        if (is_bool($tiny_mce)) {
            $this->tiny_mce = $tiny_mce;
        } else {
            $this->tiny_mce = ($post_prefs & POST_TINYMCE_DISPLAY) && !defined('BEEHIVEMODE_LIGHT');
        }

        $this->diff = false;
        $this->original_text = "";
        $this->links = $links;
        $this->setEmoticons($emoticons);
        $this->setHTML($html);
        $this->setContent($content);
    }

    function setHTML ($html, $strip_tags = false)
    {
        if ($html == false || $html == "N") {
            $this->html = POST_HTML_DISABLED;
        } else if ($html == POST_HTML_AUTO || $html == "A") {
            $this->html = POST_HTML_AUTO;
        } else {
            $this->html = POST_HTML_ENABLED;
        }

        if ($this->html == POST_HTML_DISABLED && $strip_tags === true) {
            $this->setContent(strip_tags($this->getOriginalContent()));
        }else {
            $this->setContent($this->getOriginalContent());
        }
    }

    function getHTML ()
    {
        return $this->html;
    }

    function setEmoticons ($bool)
    {
        $this->emoticons = ($bool == true) ? true : false;
        $this->setContent($this->getOriginalContent());
    }

    function getEmoticons ()
    {
        return $this->emoticons;
    }

    function getLinks ()
    {
        return $this->links;
    }

    function setLinks($bool)
    {
        $this->links = ($bool == true) ? true : false;
        $this->setContent($this->getOriginalContent());
    }

    function setContent($text)
    {
        $this->original_text = $text;

        if ($this->html == POST_HTML_DISABLED) {

            $text = make_html($text, false, $this->emoticons, $this->links);

        }else if ($this->html > POST_HTML_DISABLED) {

            $text = fix_html($text, $this->emoticons, $this->links);
            
            if ($this->tiny_mce) {
                
                $text = fix_tiny_mce_html($text);

            }else {

                $tidy_text = tidy_html($text, ($this->html == POST_HTML_AUTO) ? true : false);

                if (trim(preg_replace('/<code[^>]*>.*<\/code>/su', '', $this->original_text)) != trim(preg_replace('/<code[^>]*>.*<\/code>/su', '', $tidy_text))) {
                    $this->diff = true;
                }
            }

            if ($this->html == POST_HTML_AUTO) {
                $text = add_paragraphs($text);
            }
        }

        $this->text = $text;
    }

    function getContent()
    {
        return $this->text;
    }

    function getTidyContent()
    {
        if ($this->tiny_mce) {

            return htmlentities_array(tidy_tiny_mce($this->text));        
            
        } else if ($this->html > POST_HTML_DISABLED) {

            return htmlentities_array(tidy_html($this->text, ($this->html == POST_HTML_AUTO) ? true : false, $this->links));
        }

        return strip_tags($this->text);
    }

    function getOriginalContent()
    {
        return $this->original_text;
    }

    function isDiff()
    {
        return $this->diff;
    }
}

class MessageTextParse {

    private $message;
    private $message_html;
    private $sig;
    private $sig_html;
    private $original;
    private $tiny_mce;
    private $emoticons;
    private $links;

    function MessageTextParse($message, $emots_default = true, $links_enabled = true, $tiny_mce = null)
    {
        $this->original = $message;

        $post_prefs = session_get_post_page_prefs();

        if (is_bool($tiny_mce)) {
            $this->tiny_mce = $tiny_mce;
        } else {
            $this->tiny_mce = ($post_prefs & POST_TINYMCE_DISPLAY) && !defined('BEEHIVEMODE_LIGHT');
        }
        
        $message_parts = preg_split('/(<[^<>]+>)/u', $message, -1, PREG_SPLIT_DELIM_CAPTURE);

        $signature_parts = array();

        if (($signature_offset = array_search("<div class=\"sig\">", $message_parts)) !== false) {

            while (sizeof($message_parts) > 0) {

                $signature_parts = array_merge($signature_parts, array_splice($message_parts, $signature_offset, 1));
                if (count(explode('<div', implode('', $signature_parts))) == count(explode('</div>', implode('', $signature_parts)))) break;
            }
        }

        $signature = preg_replace('/^<div class="sig">(.*)<\/div>$/Dsu', '$1', implode('', $signature_parts));

        $message = implode('', $message_parts);

        $emoticons = $emots_default;

        if (preg_match('/^<noemots>.*<\/noemots>$/Dsu', $message) > 0) {
            $emoticons = false;
        }

        $message_html = POST_HTML_DISABLED;

        $links_replace_count = 0;

        $message = preg_replace('/<a href="(http:\/\/)?([^"]*)">((http:\/\/)?\\2)<\/a>/u', '\3', $message, -1, $links_replace_count);

        if ($links_replace_count > 0) {
            $links = true;
        }else {
            $links = $links_enabled;
        }
        
        if ($this->tiny_mce) {
            
            $message = tidy_tiny_mce($message);

            $message_html = POST_HTML_ENABLED;
        
        } else {
            
            $message = tidy_html($message, false, $links);
            
            $message_check_html = strip_tags($message, '<p><br>');

            if (strcmp($message_check_html, $message) <> 0) {

                $message_html = POST_HTML_ENABLED;

                if (add_paragraphs($message) == $message) {
                    $message_html = POST_HTML_AUTO;
                }

            }else {

                $message = htmlentities_decode_array(tidy_html_linebreaks($message));
            }
        }
        
        $signature_check_html = strip_tags($signature);
        
        if (strcmp($signature_check_html, $signature) <> 0) {
            
            $sig_html = POST_HTML_ENABLED;
            $signature = tidy_html($signature, false, false);
        
        } else {
            
            $sig_html = POST_HTML_DISABLED;        
            $signature = htmlentities_decode_array($signature);
        }

        $this->message = $message;
        $this->message_html = $message_html;
        
        $this->sig = $signature;
        $this->sig_html = $sig_html;
        
        $this->emoticons = $emoticons;
        $this->links = $links;
    }

    function getMessage ()
    {
        return $this->message;
    }

    function getSig ()
    {
        return $this->sig;
    }

    function getMessageHTML ()
    {
        return $this->message_html;
    }
    
    function getSigHTML ()
    {
        return $this->sig_html;
    }

    function getEmoticons ()
    {
        return $this->emoticons;
    }

    function getLinks ()
    {
        return $this->links;
    }

    function getOriginal ()
    {
        return $this->original;
    }
}

?>
