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

include_once(BH_INCLUDE_PATH. "constants.inc.php");
include_once(BH_INCLUDE_PATH. "db.inc.php");
include_once(BH_INCLUDE_PATH. "forum.inc.php");
include_once(BH_INCLUDE_PATH. "ip.inc.php");
include_once(BH_INCLUDE_PATH. "lang.inc.php");
include_once(BH_INCLUDE_PATH. "session.inc.php");
include_once(BH_INCLUDE_PATH. "timezone.inc.php");

function user_count()
{
   if (!$db_user_count = db_connect()) return false;

   $sql = "SELECT COUNT(UID) AS COUNT FROM USER";

   if (!$result = db_query($sql, $db_user_count)) return false;

   list($user_count) = db_fetch_array($result, DB_RESULT_NUM);

   return $user_count;
}

function user_exists($logon, $check_uid = false)
{
    if (!$db_user_exists = db_connect()) return false;

    $logon = db_escape_string($logon);

    if (is_numeric($check_uid) && $check_uid !== false) {

        $sql = "SELECT COUNT(UID) AS USER_COUNT FROM USER ";
        $sql.= "WHERE LOGON = '$logon' AND UID <> '$check_uid'";

    }else {

        $sql = "SELECT COUNT(UID) AS USER_COUNT FROM USER ";
        $sql.= "WHERE LOGON = '$logon'";
    }

    if (!$result = db_query($sql, $db_user_exists)) return false;

    list($user_count) = db_fetch_array($result, DB_RESULT_NUM);

    return ($user_count > 0);
}

function user_create($logon, $password, $nickname, $email)
{
    if (!$db_user_create = db_connect()) return false;

    if (!$ipaddress = get_ip_address()) return false;

    $logon = db_escape_string($logon);
    $nickname = db_escape_string($nickname);
    $email = db_escape_string($email);

    if (($http_referer = session_get_value('REFERER'))) {
        $http_referer = db_escape_string($http_referer);
    }else {
        $http_referer = "";
    }

    $salt = user_password_salt();

    $passhash = user_password_encrypt($password, $salt);

    $current_datetime = date(MYSQL_DATETIME, time());

    $sql = "INSERT INTO USER (LOGON, PASSWD, SALT, NICKNAME, EMAIL, REGISTERED, REFERER, IPADDRESS) ";
    $sql.= "VALUES ('$logon', '$passhash', '$salt', '$nickname', '$email', CAST('$current_datetime' AS DATETIME), ";
    $sql.= "'$http_referer', '$ipaddress')";

    if ((db_query($sql, $db_user_create))) {

        $new_uid = db_insert_id($db_user_create);
        return $new_uid;
    }

    return false;
}

function user_update($uid, $logon, $nickname, $email)
{
    if (!$db_user_update = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    // Encode HTML tags and db_escape_string for protection.
    $logon = db_escape_string($logon);
    $nickname = db_escape_string($nickname);
    $email = db_escape_string($email);

    $current_datetime = date(MYSQL_DATETIME, time());

    // Check to see if we need to save the current
    // details to the USER_HISTORY table.
    $sql = "SELECT LOGON, NICKNAME, EMAIL FROM USER_HISTORY ";
    $sql.= "WHERE UID = '$uid' ORDER BY MODIFIED DESC ";
    $sql.= "LIMIT 0, 1";

    if (!$result_check = db_query($sql, $db_user_update)) return false;

    // If there is some existing data we need to retrieve the
    // data and compare it to the new details.
    if (db_num_rows($result_check) > 0) {

        // Get the old data from the database and escape it so the strcmp works.
        $user_history_array = array_map('db_escape_string', db_fetch_array($result_check));

        // Check the data against that passed to the function.
        if ((strcmp($user_history_array['LOGON'], $logon) <> 0) || (strcmp($user_history_array['NICKNAME'], $nickname) <> 0) || (strcmp($user_history_array['EMAIL'], $email) <> 0)) {

            // If there are any differences we need to save the changes.
            // We save everything so that future changes don't cause
            // additional matches (NULL != $logon, etc.)
            $sql = "INSERT INTO USER_HISTORY (UID, LOGON, NICKNAME, EMAIL, MODIFIED) ";
            $sql.= "VALUES ('$uid', '$logon', '$nickname', '$email', CAST('$current_datetime' AS DATETIME))";

            if (!db_query($sql, $db_user_update)) return false;
        }

    }else {

        // No previous data so we just save what we have.
        $sql = "INSERT INTO USER_HISTORY (UID, LOGON, NICKNAME, EMAIL, MODIFIED) ";
        $sql.= "VALUES ('$uid', '$logon', '$nickname', '$email', CAST('$current_datetime' AS DATETIME))";

        if (!db_query($sql, $db_user_update)) return false;
    }

    // Update the user details
    $sql = "UPDATE LOW_PRIORITY USER SET LOGON = '$logon', NICKNAME = '$nickname', ";
    $sql.= "EMAIL = '$email' WHERE UID = '$uid'";

    if (!db_query($sql, $db_user_update)) return false;

    return true;
}

function user_update_nickname($uid, $nickname)
{
    if (!$db_user_update = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    $nickname = db_escape_string($nickname);

    $sql = "UPDATE LOW_PRIORITY USER SET NICKNAME = '$nickname' ";
    $sql.= "WHERE UID = '$uid'";

    if (!db_query($sql, $db_user_update)) return false;

    return true;
}

function user_change_logon($uid, $logon)
{
    if (!$db_user_change_logon = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    $logon = db_escape_string($logon);

    $sql = "UPDATE LOW_PRIORITY USER SET LOGON = '$logon' ";
    $sql.= "WHERE UID = '$uid'";

    if (!db_query($sql, $db_user_change_logon)) return false;

    return true;
}

function user_increment_post_count($uid)
{
    if (!$db_user_increment_post_count = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    if (!$table_data = get_table_prefix()) return false;

    $sql = "UPDATE LOW_PRIORITY `{$table_data['PREFIX']}USER_TRACK` ";
    $sql.= "SET POST_COUNT = POST_COUNT + 1 WHERE UID = '$uid'";

    if (!db_query($sql, $db_user_increment_post_count)) return false;

    return true;
}

function user_update_post_count($uid, $post_count)
{
    if (!$db_user_update_post_count = db_connect()) return false;

    if (!is_numeric($uid)) return false;
    if (!is_numeric($post_count)) return false;

    if (!$table_data = get_table_prefix()) return false;

    $sql = "UPDATE LOW_PRIORITY `{$table_data['PREFIX']}USER_TRACK` ";
    $sql.= "SET POST_COUNT = '$post_count' WHERE UID = '$uid'";

    if (!db_query($sql, $db_user_update_post_count)) return false;

    return true;
}

function user_reset_post_count($uid)
{
    if (!$db_user_reset_post_count = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    if (!$table_data = get_table_prefix()) return false;

    $sql = "UPDATE LOW_PRIORITY `{$table_data['PREFIX']}USER_TRACK` ";
    $sql.= "SET POST_COUNT = NULL WHERE UID = '$uid'";

    if (!db_query($sql, $db_user_reset_post_count)) return false;

    return true;
}

function user_change_password($uid, $new_password, $old_password)
{
    if (!$db_user_change_password = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    $sql = "SELECT PASSWD, SALT FROM USER WHERE UID = '$uid'";

    if (!($result = db_query($sql, $db_user_change_password))) return false;

    list($passhash, $salt) = db_fetch_array($result, DB_RESULT_NUM);

    if ((md5($old_password) == $passhash) && (strlen(trim($salt))) == 0) {
        return user_reset_password($uid, $new_password, $passhash);
    }

    if (user_password_encrypt($old_password, $salt) != $passhash) return false;

    $salt = user_password_salt();

    $passhash = user_password_encrypt($new_password, $salt);

    $salt = db_escape_string($salt);

    $passhash = db_escape_string($passhash);

    $sql = "UPDATE USER SET PASSWD = '$passhash', ";
    $sql.= "SALT = '$salt' WHERE UID = '$uid'";

    if (!db_query($sql, $db_user_change_password)) return false;

    return true;
}

function user_reset_password($uid, $new_password, $old_passhash)
{
    if (!$db_user_reset_password = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    $old_passhash = db_escape_string($old_passhash);

    $salt = user_password_salt();

    $passhash = user_password_encrypt($new_password, $salt);

    $salt = db_escape_string($salt);

    $passhash = db_escape_string($passhash);

    $sql = "UPDATE USER SET PASSWD = '$passhash', SALT = '$salt' ";
    $sql.= "WHERE UID = '$uid' AND PASSWD = '$old_passhash'";

    if (!(db_query($sql, $db_user_reset_password))) return false;

    return true;
}

function user_update_forums($uid, $forum_fid, $allowed)
{
    if (!$db_user_update_forums = db_connect()) return false;

    if (!is_numeric($uid)) return false;
    if (!is_numeric($forum_fid)) return false;
    if (!is_numeric($allowed)) return false;

    $sql = "INSERT INTO USER_FORUM (UID, FID, ALLOWED) ";
    $sql.= "VALUES ('$uid', '$forum_fid', '$allowed') ";
    $sql.= "ON DUPLICATE KEY UPDATE ALLOWED = VALUES(ALLOWED)";

    if (!db_query($sql, $db_user_update_forums)) return false;

    return true;
}

function user_logon($logon, $password)
{
    if (!$db_user_logon = db_connect()) return false;

    $logon = db_escape_string(mb_strtoupper($logon));

    if (!$ipaddress = get_ip_address()) return false;

    $ipaddress = db_escape_string($ipaddress);

    $sql = "SELECT UID, PASSWD, SALT FROM USER WHERE LOGON = '$logon'";

    if (!($result = db_query($sql, $db_user_logon))) return false;

    if (db_num_rows($result) == 0) return false;

    list($uid, $passhash, $salt) = db_fetch_array($result, DB_RESULT_NUM);

    if ((md5($password) == $passhash) && (strlen(trim($salt))) == 0) {

        if (!user_reset_password($uid, $password, $passhash)) return false;

        return $uid;
    }

    if (user_password_encrypt($password, $salt) != $passhash) return false;

    $sql = "UPDATE LOW_PRIORITY USER SET IPADDRESS = '$ipaddress' WHERE UID = '$uid'";

    if (!($result = db_query($sql, $db_user_logon))) return false;

    user_prune_expired_tokens($uid);

    return $uid;
}

function user_logon_token($logon, $token)
{
    if (!$db_user_logon = db_connect()) return false;

    if (!$ipaddress = get_ip_address()) return false;

    if (!is_md5($token)) return false;

    $logon = db_escape_string(mb_strtoupper($logon));

    $token = db_escape_string($token);

    $ipaddress = db_escape_string($ipaddress);

    $current_datetime = date(MYSQL_DATETIME, time());

    $sql = "SELECT USER.UID FROM USER INNER JOIN USER_TOKEN ON (USER_TOKEN.UID = USER.UID) ";
    $sql.= "WHERE USER.LOGON = '$logon' AND USER_TOKEN.TOKEN = '$token' ";
    $sql.= "AND USER_TOKEN.EXPIRES > '$current_datetime'";

    if (!($result = db_query($sql, $db_user_logon))) return false;

    if (db_num_rows($result) == 0) return false;

    list($uid) = db_fetch_array($result, DB_RESULT_NUM);

    $sql = "UPDATE USER SET IPADDRESS = '$ipaddress' WHERE UID = '$uid'";

    if (!($result = db_query($sql, $db_user_logon))) return false;

    if (!user_renew_token($uid, $token)) return false;

    user_prune_expired_tokens($uid);

    return $uid;
}

function user_generate_token($uid)
{
    if (!is_numeric($uid)) return false;

    if (!$db_user_generate_token = db_connect()) return false;

    user_prune_expired_tokens($uid);

    $token = md5(uniqid(mt_rand()));

    $current_datetime = date(MYSQL_DATETIME, time());

    $sql = "INSERT INTO USER_TOKEN (UID, TOKEN, EXPIRES) VALUES ('$uid', ";
    $sql.= "'$token',  DATE_ADD('$current_datetime', INTERVAL 1 MONTH))";

    if (!(db_query($sql, $db_user_generate_token))) return false;

    return $token;
}

function user_prune_expired_tokens($uid)
{
    if (!is_numeric($uid)) return false;

    if (!$db_user_prune_tokens = db_connect()) return false;

    $current_datetime = date(MYSQL_DATETIME, time());

    $sql = "DELETE QUICK FROM USER_TOKEN WHERE UID = '$uid' ";
    $sql.= "AND EXPIRES < '$current_datetime'";

    if (!(db_query($sql, $db_user_prune_tokens))) return false;

    return true;
}

function user_renew_token($uid, $token)
{
    if (!is_numeric($uid)) return false;

    if (!$db_user_renew_token = db_connect()) return false;

    $token = db_escape_string($token);

    $current_datetime = date(MYSQL_DATETIME, time());

    $sql = "UPDATE USER_TOKEN SET EXPIRES = DATE_ADD('$current_datetime', INTERVAL 1 MONTH) ";
    $sql.= "WHERE UID = '$uid' AND TOKEN = '$token'";

    if (!(db_query($sql, $db_user_renew_token))) return false;

    user_prune_expired_tokens($uid);

    return true;
}

function user_password_salt()
{
    return substr(str_replace('+', '.', base64_encode(pack('N4', mt_rand(), mt_rand(), mt_rand(), mt_rand()))), 0, 22);
}

function user_password_encrypt($password, $salt)
{
    return crypt($password, $salt);
}

function user_get($uid)
{
    if (!$db_user_get = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    $sess_uid = session_get_value('UID');

    if ((!$table_data = get_table_prefix()) || ($uid == $sess_uid)) {

        $sql = "SELECT UID, LOGON, PASSWD, NICKNAME, USER.EMAIL, ";
        $sql.= "IPADDRESS, REFERER FROM USER WHERE UID = '$uid'";

    }else {

        $sql = "SELECT USER.UID, USER.LOGON, USER.PASSWD, USER.NICKNAME, ";
        $sql.= "USER.EMAIL, USER.IPADDRESS, USER.REFERER, USER_PEER.PEER_NICKNAME FROM USER ";
        $sql.= "LEFT JOIN `{$table_data['PREFIX']}USER_PEER` USER_PEER ";
        $sql.= "ON (USER_PEER.PEER_UID = USER.UID AND USER_PEER.UID = '$sess_uid') ";
        $sql.= "WHERE USER.UID = '$uid'";
    }

    if (!$result = db_query($sql, $db_user_get)) return false;

    if (db_num_rows($result) > 0) {

        $user_get = db_fetch_array($result);

        if (isset($user_get['PEER_NICKNAME'])) {

            if (!is_null($user_get['PEER_NICKNAME']) && strlen($user_get['PEER_NICKNAME']) > 0) {

                $user_get['NICKNAME'] = $user_get['PEER_NICKNAME'];
            }
        }

        return $user_get;
    }

    return false;
}

function user_get_by_passhash($uid, $passhash)
{
    if (!$db_user_get = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    $passhash = db_escape_string($passhash);

    $sql = "SELECT UID, LOGON, PASSWD, NICKNAME, EMAIL, REGISTERED, ";
    $sql.= "IPADDRESS, REFERER, APPROVED FROM USER WHERE UID = '$uid' ";
    $sql.= "AND PASSWD = '$passhash'";

    if (!$result = db_query($sql, $db_user_get)) return false;

    if (db_num_rows($result) > 0) {

        $user_get = db_fetch_array($result);
        return $user_get;
    }

    return false;
}

function user_get_logon($uid)
{
    if (!$db_user_get_logon = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    $sql = "SELECT LOGON FROM USER WHERE UID = '$uid'";

    if (!$result = db_query($sql, $db_user_get_logon)) return false;

    if (db_num_rows($result) > 0) {

        list($logon) = db_fetch_array($result, DB_RESULT_NUM);
        return $logon;
    }

    return false;
}

function user_get_nickname($uid)
{
    if (!$db_user_get_nickname = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    $sql = "SELECT NICKNAME FROM USER WHERE UID = '$uid'";

    if (!$result = db_query($sql, $db_user_get_nickname)) return false;

    if (db_num_rows($result) > 0) {

        list($nickname) = db_fetch_array($result, DB_RESULT_NUM);
        return $nickname;
    }

    return false;
}

function user_get_email($uid)
{
    if (!$db_user_get_email = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    $sql = "SELECT EMAIL FROM USER WHERE UID = '$uid'";

    if (!$result = db_query($sql, $db_user_get_email)) return false;

    if (db_num_rows($result) > 0) {

        list($email) = db_fetch_array($result, DB_RESULT_NUM);
        return $email;
    }

    return false;
}

function user_get_referer($uid)
{
    if (!$db_user_get_referer = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    $sql = "SELECT REFERER FROM USER WHERE UID = '$uid'";

    if (!$result = db_query($sql, $db_user_get_referer)) return false;

    if (db_num_rows($result) > 0) {

        list($referer) = db_fetch_array($result, DB_RESULT_NUM);
        return $referer;
    }

    return false;
}

function user_get_passwd($uid)
{
    if (!$db_user_get_passwd = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    $sql = "SELECT PASSWD FROM USER WHERE UID = '$uid'";

    if (!$result = db_query($sql, $db_user_get_passwd)) return false;

    if (db_num_rows($result) > 0) {

        list($passwd) = db_fetch_array($result, DB_RESULT_NUM);
        return $passwd;
    }

    return false;
}

function user_get_by_logon($logon)
{
    if (!$db_user_get_by_logon = db_connect()) return false;

    $logon = db_escape_string($logon);

    $sql = "SELECT UID, LOGON, PASSWD, NICKNAME, EMAIL, ";
    $sql.= "REGISTERED, IPADDRESS, REFERER, APPROVED ";
    $sql.= "FROM USER WHERE LOGON LIKE '$logon'";

    if (!$result = db_query($sql, $db_user_get_by_logon)) return false;

    if (db_num_rows($result) > 0) {

        $user_array = db_fetch_array($result);
        return $user_array;
    }

    return false;
}

function user_get_sig($uid, &$content, &$html)
{
    if (!$db_user_get_sig = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    if (!$table_data = get_table_prefix()) return false;

    $sql = "SELECT CONTENT, HTML FROM `{$table_data['PREFIX']}USER_SIG` WHERE UID = '$uid'";

    if (!$result = db_query($sql, $db_user_get_sig)) return false;

    if (db_num_rows($result) > 0) {

        list($content, $html) = db_fetch_array($result, DB_RESULT_NUM);
        return true;
    }

    return false;
}

function user_get_last_ip_address($uid)
{
    if (!$db_user_get_last_ip_address = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    $sql = "SELECT IPADDRESS FROM USER WHERE UID = '$uid'";

    if (!$result = db_query($sql, $db_user_get_last_ip_address)) return false;

    if (db_num_rows($result) > 0) {

        list($ipaddress) = db_fetch_array($result, DB_RESULT_NUM);
        return $ipaddress;
    }

    return false;
}

function user_get_prefs($uid)
{
    // See user_update_prefs() below for an explanation of the prefs system.
    if (!$db_user_get_prefs = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    // Arrays to hold the user preferences.
    $global_prefs_array = array();
    $forum_prefs_array  = array();

    // 2. The user's global prefs, in USER_PREFS:
    $sql = "SELECT USER_PREFS.FIRSTNAME, USER_PREFS.LASTNAME, USER_PREFS.DOB, ";
    $sql.= "USER_PREFS.HOMEPAGE_URL, USER_PREFS.PIC_URL, USER_PREFS.EMAIL_NOTIFY, ";
    $sql.= "USER_PREFS.TIMEZONE, USER_PREFS.DL_SAVING, USER_PREFS.MARK_AS_OF_INT, ";
    $sql.= "USER_PREFS.POSTS_PER_PAGE, USER_PREFS.FONT_SIZE, USER_PREFS.STYLE, ";
    $sql.= "USER_PREFS.EMOTICONS, USER_PREFS.VIEW_SIGS, USER_PREFS.START_PAGE, ";
    $sql.= "USER_PREFS.LANGUAGE, USER_PREFS.PM_NOTIFY, USER_PREFS.PM_NOTIFY_EMAIL, ";
    $sql.= "USER_PREFS.PM_SAVE_SENT_ITEM, USER_PREFS.PM_INCLUDE_REPLY, ";
    $sql.= "USER_PREFS.PM_AUTO_PRUNE, USER_PREFS.PM_EXPORT_TYPE, ";
    $sql.= "USER_PREFS.PM_EXPORT_FILE, USER_PREFS.PM_EXPORT_ATTACHMENTS, USER_PREFS.PM_EXPORT_STYLE, ";
    $sql.= "USER_PREFS.PM_EXPORT_WORDFILTER, USER_PREFS.DOB_DISPLAY, USER_PREFS.ANON_LOGON, ";
    $sql.= "USER_PREFS.SHOW_STATS, USER_PREFS.IMAGES_TO_LINKS, USER_PREFS.USE_WORD_FILTER, ";
    $sql.= "USER_PREFS.USE_ADMIN_FILTER, USER_PREFS.ALLOW_EMAIL, USER_PREFS.USE_EMAIL_ADDR, ";
    $sql.= "USER_PREFS.ALLOW_PM, USER_PREFS.POST_PAGE, USER_PREFS.SHOW_THUMBS, ";
    $sql.= "USER_PREFS.ENABLE_WIKI_WORDS, USER_PREFS.USE_MOVER_SPOILER, ";
    $sql.= "USER_PREFS.USE_LIGHT_MODE_SPOILER, USER_PREFS.USE_OVERFLOW_RESIZE, ";
    $sql.= "USER_PREFS.PIC_AID, USER_PREFS.AVATAR_URL, USER_PREFS.AVATAR_AID, ";
    $sql.= "USER_PREFS.REPLY_QUICK, USER_PREFS.THREADS_BY_FOLDER, USER_PREFS.THREAD_LAST_PAGE, ";
    $sql.= "USER_PREFS.LEFT_FRAME_WIDTH, USER_PREFS.SHOW_AVATARS, USER_PREFS.SHOW_SHARE_LINKS ";
    $sql.= "FROM USER_PREFS LEFT JOIN TIMEZONES ON (TIMEZONES.TZID = USER_PREFS.TIMEZONE) ";
    $sql.= "WHERE USER_PREFS.UID = '$uid'";

    if (!$result = db_query($sql, $db_user_get_prefs)) return false;

    if (db_num_rows($result) > 0) {
        $global_prefs_array = db_fetch_array($result, DB_RESULT_ASSOC);
    }

    // 3. The user's per-forum prefs, in GLOBAL USER_PREFS (not all prefs are set here e.g. name):
    if (($table_data = get_table_prefix())) {

        $sql = "SELECT HOMEPAGE_URL, PIC_URL, EMAIL_NOTIFY, MARK_AS_OF_INT, POSTS_PER_PAGE, ";
        $sql.= "FONT_SIZE, STYLE, EMOTICONS, VIEW_SIGS, START_PAGE, LANGUAGE, SHOW_STATS, ";
        $sql.= "IMAGES_TO_LINKS, USE_WORD_FILTER, USE_ADMIN_FILTER, SHOW_THUMBS, ENABLE_WIKI_WORDS, ";
        $sql.= "USE_MOVER_SPOILER, USE_LIGHT_MODE_SPOILER, USE_OVERFLOW_RESIZE, PIC_AID, AVATAR_URL, ";
        $sql.= "AVATAR_AID, REPLY_QUICK, THREADS_BY_FOLDER, THREAD_LAST_PAGE, LEFT_FRAME_WIDTH, ";
        $sql.= "SHOW_AVATARS, SHOW_SHARE_LINKS FROM `{$table_data['PREFIX']}USER_PREFS` ";
        $sql.= "WHERE UID = '$uid'";

        if (!$result = db_query($sql, $db_user_get_prefs)) return false;

        if (db_num_rows($result) > 0) {
            $forum_prefs_array = db_fetch_array($result, DB_RESULT_ASSOC);
        }
    }

    // Prune empty values from the forum_prefs array to stop them overwriting valid global prefs
    $forum_prefs_array = array_filter($forum_prefs_array, "strlen");

    // Get the array keys.
    $global_prefs_array_keys = array_keys($global_prefs_array);
    $forum_prefs_array_keys = array_keys($forum_prefs_array);

    // Add keys to indicate whether the preference is set globally or not
    foreach ($forum_prefs_array_keys as $key) {
        $forum_prefs_array[$key. '_GLOBAL'] = false;
    }

    foreach ($global_prefs_array_keys as $key) {
        $global_prefs_array[$key. '_GLOBAL'] = true;
    }

    // Merge them all together, with forum prefs overriding global prefs
    return array_merge($global_prefs_array, $forum_prefs_array);
}

function user_update_prefs($uid, $prefs_array, $prefs_global_setting_array = false)
{
    if (!$db_user_update_prefs = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    if (!is_array($prefs_array)) return false;

    // Check that $prefs_global_setting_array is an array
    if (!is_array($prefs_global_setting_array)) {
        $prefs_global_setting_array = array();
    }

    // Arrays to hold preferences
    $global_prefs_array = array();
    $forum_prefs_array  = array();

    // names of preferences that can be set globally
    $global_pref_names = array('FIRSTNAME', 'LASTNAME', 'DOB', 'HOMEPAGE_URL', 
                               'PIC_URL', 'EMAIL_NOTIFY', 'TIMEZONE', 'DL_SAVING', 
                               'MARK_AS_OF_INT', 'POSTS_PER_PAGE', 'FONT_SIZE', 
                               'STYLE', 'EMOTICONS', 'VIEW_SIGS', 'START_PAGE', 
                               'LANGUAGE', 'PM_NOTIFY', 'PM_NOTIFY_EMAIL', 
                               'PM_SAVE_SENT_ITEM', 'PM_INCLUDE_REPLY', 'PM_AUTO_PRUNE', 
                               'PM_EXPORT_TYPE', 'PM_EXPORT_FILE', 'PM_EXPORT_ATTACHMENTS', 
                               'PM_EXPORT_STYLE', 'PM_EXPORT_WORDFILTER', 'DOB_DISPLAY', 
                               'ANON_LOGON', 'SHOW_STATS', 'IMAGES_TO_LINKS', 
                               'USE_WORD_FILTER', 'USE_ADMIN_FILTER', 'ALLOW_EMAIL', 
                               'USE_EMAIL_ADDR', 'ALLOW_PM', 'POST_PAGE', 'SHOW_THUMBS', 
                               'ENABLE_WIKI_WORDS', 'USE_MOVER_SPOILER', 
                               'USE_LIGHT_MODE_SPOILER', 'USE_OVERFLOW_RESIZE', 
                               'PIC_AID', 'AVATAR_URL', 'AVATAR_AID', 'REPLY_QUICK', 
                               'THREADS_BY_FOLDER', 'THREAD_LAST_PAGE', 'LEFT_FRAME_WIDTH', 
                               'SHOW_AVATARS', 'SHOW_SHARE_LINKS');

    // names of preferences that can be set on a per-forum basis
    $forum_pref_names =  array('HOMEPAGE_URL', 'PIC_URL', 'EMAIL_NOTIFY', 
                               'MARK_AS_OF_INT', 'POSTS_PER_PAGE', 'FONT_SIZE', 
                               'STYLE', 'EMOTICONS', 'VIEW_SIGS', 
                               'START_PAGE', 'LANGUAGE', 'SHOW_STATS', 
                               'IMAGES_TO_LINKS', 'USE_WORD_FILTER', 
                               'USE_ADMIN_FILTER', 'SHOW_THUMBS', 
                               'ENABLE_WIKI_WORDS', 'USE_MOVER_SPOILER', 
                               'USE_LIGHT_MODE_SPOILER', 'USE_OVERFLOW_RESIZE', 
                               'PIC_AID', 'AVATAR_URL', 'AVATAR_AID', 
                               'REPLY_QUICK', 'THREADS_BY_FOLDER', 
                               'THREAD_LAST_PAGE', 'LEFT_FRAME_WIDTH', 
                               'SHOW_AVATARS', 'SHOW_SHARE_LINKS');

    // Loop through the passed preference names and check they're valid
    // and whether the value needs to go in the global or forum USER_PREFS table.
    foreach ($prefs_array as $pref_name => $pref_setting) {

        if (user_check_pref($pref_name, $pref_setting)) {

            if (!isset($prefs_global_setting_array[$pref_name]) || $prefs_global_setting_array[$pref_name] == true) {

                if (in_array($pref_name, $global_pref_names)) {

                    $global_prefs_array[$pref_name] = $pref_setting;
                }

            }else {

                if (in_array($pref_name, $forum_pref_names)) {

                    $forum_prefs_array[$pref_name] = $pref_setting;
                }
            }
        }
    }

    // Check to see we have some preferences to set globally.
    if (sizeof($global_prefs_array) > 0) {

        // Concat the column names together, escaping them and enclosing them in backticks.
        $column_names = implode("`, `", array_map('db_escape_string', array_keys($global_prefs_array)));

        // Concat the values together, escaping them and enclosing them in quotes.
        $column_insert_values = implode("', '", array_map('db_escape_string', array_values($global_prefs_array)));

        // Concat the column names together, pass them through user_update_prefs_helper
        // which constructs a valid ON DUPLICATE KEY UPDATE statement for the INSERT.
        $column_update_values = implode(", ", array_map('user_update_prefs_callback', array_keys($global_prefs_array)));

        // Construct the query and run it.
        $sql = "INSERT INTO USER_PREFS (`UID`, `$column_names`) VALUES('$uid', '$column_insert_values') ";
        $sql.= "ON DUPLICATE KEY UPDATE $column_update_values ";

        if (!db_query($sql, $db_user_update_prefs)) return false;

        // If a pref is set globally, we need to remove it from all the
        // per-forum USER_PREFS tables. We use array_intersect to find
        // out which columns we need to update.
        $update_prefs_array = array_intersect($forum_pref_names, array_keys($global_prefs_array));

        // Only proceed if we have something to process.
        if (sizeof($update_prefs_array) > 0) {

            if (!$forum_prefix_array = forum_get_all_prefixes()) return false;

            foreach ($forum_prefix_array as $forum_prefix) {

                $update_prefs_sql = implode(", ", array_map('user_update_prefs_callback2', $update_prefs_array));

                $sql = "UPDATE LOW_PRIORITY `{$forum_prefix}USER_PREFS` SET $update_prefs_sql WHERE UID = '$uid'";

                if (!db_query($sql, $db_user_update_prefs)) return false;
            }
        }
    }

    if ((sizeof($forum_prefs_array) > 0) && ($table_data = get_table_prefix())) {

        // Concat the column names together, escaping them and enclosing them in backticks.
        $column_names = implode("`, `", array_map('db_escape_string', array_keys($forum_prefs_array)));

        // Concat the values together, escaping them and enclosing them in quotes.
        $column_insert_values = implode("', '", array_map('db_escape_string', array_values($forum_prefs_array)));

        // Concat the column names together, pass them through user_update_prefs_helper
        // which constructs a valid ON DUPLICATE KEY UPDATE statement for the INSERT.
        $column_update_values = implode(", ", array_map('user_update_prefs_callback', array_keys($forum_prefs_array)));

        // Construct the query and run it.
        $sql = "INSERT INTO `{$table_data['PREFIX']}USER_PREFS` (`UID`, `$column_names`) ";
        $sql.= "VALUES('$uid', '$column_insert_values') ON DUPLICATE KEY UPDATE $column_update_values ";

        if (!db_query($sql, $db_user_update_prefs)) return false;
    }

    return true;
}

function user_update_prefs_callback($column)
{
    return sprintf('%s = VALUES(%s)', $column, $column);
}

function user_update_prefs_callback2($column)
{
    return sprintf("%s = ''", $column);
}

function user_check_pref($name, $value)
{
    // Checks to ensure that a preference setting contains valid data
    if (strlen(trim($value)) == 0) return true;

    // Different cases for different fields
    switch ($name) {

        case "FIRSTNAME":
        case "LASTNAME":

            return preg_match("/^[a-z0-9 ]*$/Diu", $value);
            break;

        case "STYLE":
        case "EMOTICONS":
        case "LANGUAGE":

            return preg_match("/^[a-z0-9_-]*$/Diu", $value);
            break;

        case "DOB":

            return preg_match("/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/Du", $value);
            break;

        case "HOMEPAGE_URL":
        case "PIC_URL":
        case "AVATAR_URL":

            return (preg_match('/^http:\/\/[_\.0-9a-z\-~]*/iu', $value) || $value == "");
            break;

        case "EMAIL_NOTIFY":
        case "DL_SAVING":
        case "MARK_AS_OF_INT":
        case "VIEW_SIGS":
        case "PM_NOTIFY":
        case "PM_NOTIFY_EMAIL":
        case "PM_INCLUDE_REPLY":
        case "PM_SAVE_SENT_ITEM":
        case "PM_EXPORT_ATTACHMENTS":
        case "PM_EXPORT_STYLE":
        case "PM_EXPORT_WORDFILTER":
        case "IMAGES_TO_LINKS":
        case "SHOW_STATS":
        case "USE_WORD_FILTER":
        case "USE_ADMIN_FILTER":
        case "ALLOW_EMAIL":
        case "USE_EMAIL_ADDR":
        case "ALLOW_PM":
        case "ENABLE_WIKI_WORDS":
        case "USE_MOVER_SPOILER":
        case "USE_LIGHT_MODE_SPOILER":
        case "USE_OVERFLOW_RESIZE":
        case "REPLY_QUICK":
        case "THREADS_BY_FOLDER":
        case "THREAD_LAST_PAGE":
        case "SHOW_AVATARS":
        case "SHOW_SHARE_LINKS":

            return ($value == "Y" || $value == "N") ? true : false;
            break;

        case "PIC_AID":
        case "AVATAR_AID":

            return (is_md5($value) || $value == "");
            break;

        case "ANON_LOGON":
        case "TIMEZONE":
        case "POSTS_PER_PAGE":
        case "FONT_SIZE":
        case "START_PAGE":
        case "DOB_DISPLAY":
        case "POST_PAGE":
        case "SHOW_THUMBS":
        case "PM_AUTO_PRUNE":
        case "PM_EXPORT_FILE":
        case "PM_EXPORT_TYPE":
        case "LEFT_FRAME_WIDTH":

            return is_numeric($value);
            break;
    }

    return false;
}

function user_update_sig($uid, $content, $html, $global_update = false)
{
    if (!$db_user_update_sig = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    $content = db_escape_string($content);

    $html = db_escape_string($html);

    if ($global_update === true) {

        if (!$forum_prefix_array = forum_get_all_prefixes()) return false;

        foreach ($forum_prefix_array as $forum_prefix) {

            $sql = "INSERT INTO `{$forum_prefix}USER_SIG` (UID, CONTENT, HTML) ";
            $sql.= "VALUES ('$uid', '$content', '$html') ON DUPLICATE KEY ";
            $sql.= "UPDATE CONTENT = VALUES(CONTENT), HTML = VALUES(HTML)";

            if (!db_query($sql, $db_user_update_sig)) return false;
        }

    }else {

        if (!$table_data = get_table_prefix()) return false;

        $sql = "INSERT INTO `{$table_data['PREFIX']}USER_SIG` (UID, CONTENT, HTML) ";
        $sql.= "VALUES ('$uid', '$content', '$html') ON DUPLICATE KEY UPDATE ";
        $sql.= "CONTENT = VALUES(CONTENT), HTML = VALUES(HTML)";

        if (!db_query($sql, $db_user_update_sig)) return false;
    }

    return true;
}

function user_update_global_sig($uid, $value, $global = true)
{
    return user_update_prefs($uid, array('VIEW_SIGS' => ($value == 'N') ? 'N' : 'Y'), array('VIEW_SIGS' => $global));
}

function user_is_guest()
{
    return (session_get_value('UID') == 0);
}

function user_guest_enabled()
{
    if (forum_get_setting('guest_account_enabled', 'N')) {
        return false;
    }

    return true;
}

function user_get_forthcoming_birthdays()
{
    if (!$db_user_get_forthcoming_birthdays = db_connect()) return false;

    if (!$table_data = get_table_prefix()) return false;

    $uid = session_get_value('UID');

    // Constants for user relationship
    $user_ignored = USER_IGNORED;
    $user_ignored_completely = USER_IGNORED_COMPLETELY;

    $sql = "SELECT USER.UID, USER.LOGON, USER.NICKNAME, USER_PEER.PEER_NICKNAME, USER_PREFS_GLOBAL.DOB, ";
    $sql.= "IF (DATEDIFF(DATE_FORMAT(USER_PREFS_GLOBAL.DOB, '00-%m-%d'), DATE_FORMAT(CURDATE(), '00-%m-%d')) < 0, ";
    $sql.= "DATEDIFF(DATE_FORMAT(USER_PREFS_GLOBAL.DOB, '01-%m-%d'), DATE_FORMAT(CURDATE(), '00-%m-%d')), ";
    $sql.= "DATEDIFF(DATE_FORMAT(USER_PREFS_GLOBAL.DOB, '00-%m-%d'), DATE_FORMAT(CURDATE(), '00-%m-%d'))) AS DAY_COUNT ";
    $sql.= "FROM USER USER LEFT JOIN USER_PREFS USER_PREFS_GLOBAL ON (USER_PREFS_GLOBAL.UID = USER.UID) ";
    $sql.= "LEFT JOIN `{$table_data['PREFIX']}USER_PEER` USER_PEER ON (USER_PEER.PEER_UID = USER.UID ";
    $sql.= "AND USER_PEER.UID = '$uid') WHERE USER_PREFS_GLOBAL.DOB > 0 AND USER_PREFS_GLOBAL.DOB_DISPLAY > 1 ";
    $sql.= "AND ((USER_PEER.RELATIONSHIP & $user_ignored_completely) = 0 OR USER_PEER.RELATIONSHIP IS NULL) ";
    $sql.= "AND ((USER_PEER.RELATIONSHIP & $user_ignored) = 0 OR USER_PEER.RELATIONSHIP IS NULL) ";
    $sql.= "HAVING DAY_COUNT < DATE_FORMAT(LAST_DAY(CURDATE()), '%d') ";
    $sql.= "ORDER BY DAY_COUNT ";
    $sql.= "LIMIT 0, 5";

    if (!$result = db_query($sql, $db_user_get_forthcoming_birthdays)) return false;

    if (db_num_rows($result) > 0) {

        $user_birthdays_array = array();

        while (($user_birthday_data = db_fetch_array($result))) {

            if (isset($user_birthday_data['PEER_NICKNAME'])) {
                if (!is_null($user_birthday_data['PEER_NICKNAME']) && strlen($user_birthday_data['PEER_NICKNAME']) > 0) {
                    $user_birthday_data['NICKNAME'] = $user_birthday_data['PEER_NICKNAME'];
                }
            }

            $user_birthdays_array[] = $user_birthday_data;
        }

        return $user_birthdays_array;
    }

    return false;
}

function user_search_array_clean($user_search)
{
    return db_escape_string(trim(str_replace("%", "", $user_search)));
}

function user_search($user_search)
{
    if (!$db_user_search = db_connect()) return false;

    $table_data = get_table_prefix();

    $user_array = array();

    $uid = session_get_value('UID');

    $user_search_array = explode(";", $user_search);
    $user_search_array = array_map('user_search_array_clean', $user_search_array);

    $user_search_logon = implode("%' OR LOGON LIKE '", $user_search_array);
    $user_search_nickname = implode("%' OR NICKNAME LIKE '", $user_search_array);

    // Include join to USER_PEER table if we have a valid forum.
    if (isset($table_data['PREFIX'])) {

        // Main query.
        $sql = "SELECT USER.UID, USER.LOGON, USER.NICKNAME, USER_PEER.PEER_NICKNAME, ";
        $sql.= "USER_PEER.RELATIONSHIP FROM USER LEFT JOIN `{$table_data['PREFIX']}USER_PEER` USER_PEER ";
        $sql.= "ON (USER_PEER.PEER_UID = USER.UID AND USER_PEER.UID = '$uid') ";
        $sql.= "WHERE (LOGON LIKE '$user_search_logon%' ";
        $sql.= "OR NICKNAME LIKE '$user_search_nickname%') ";
        $sql.= "LIMIT 10";

    } else {

        // Main query.
        $sql = "SELECT USER.UID, USER.LOGON, USER.NICKNAME FROM USER ";
        $sql.= "WHERE (LOGON LIKE '$user_search_logon%' ";
        $sql.= "OR NICKNAME LIKE '$user_search_nickname%') ";
        $sql.= "LIMIT 10";
    }

    if (!$result = db_query($sql, $db_user_search)) return false;

    // Fetch the number of total results
    $sql = "SELECT FOUND_ROWS() AS ROW_COUNT";

    if (!$result_count = db_query($sql, $db_user_search)) return false;

    list($user_count) = db_fetch_array($result_count, DB_RESULT_NUM);

    // Check if we have any results.
    if (db_num_rows($result) > 0) {

        while (($user_data = db_fetch_array($result))) {

            if (isset($user_data['LOGON']) && isset($user_data['PEER_NICKNAME'])) {
                if (!is_null($user_data['PEER_NICKNAME']) && strlen($user_data['PEER_NICKNAME']) > 0) {
                    $user_data['NICKNAME'] = $user_data['PEER_NICKNAME'];
                }
            }

            if (!isset($user_data['LOGON'])) $user_data['LOGON'] = gettext("Unknown user");
            if (!isset($user_data['NICKNAME'])) $user_data['NICKNAME'] = "";

            $user_array[$user_data['UID']] = $user_data;
        }
    }

    return array('results_count' => $user_count,
                 'results_array' => $user_array);
}

function user_get_ip_addresses($uid)
{
    if (!$db_user_get_ip_addresses = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    if (!$table_data = get_table_prefix()) return false;

    $user_ip_addresses_array = array();

    // Fetch the last 20 IP addresses from the POST table
    $sql = "SELECT DISTINCT IPADDRESS FROM `{$table_data['PREFIX']}POST` ";
    $sql.= "WHERE FROM_UID = '$uid' ORDER BY TID DESC LIMIT 0, 10";

    if (!$result = db_query($sql, $db_user_get_ip_addresses)) return false;

    if (db_num_rows($result) > 0) {

        while (($user_ip_addresses_row = db_fetch_array($result))) {

            if (strlen($user_ip_addresses_row['IPADDRESS']) > 0) {

                $user_ip_addresses_array[] = $user_ip_addresses_row['IPADDRESS'];
            }
        }
    }

    return $user_ip_addresses_array;
}

function user_get_friends($uid)
{
    if (!$db_user_get_peers = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    if (!$table_data = get_table_prefix()) return false;

    $user_rel = USER_FRIEND;

    $sess_uid = session_get_value('UID');

    $sql = "SELECT USER.UID, USER.LOGON, USER.NICKNAME, USER_PEER.PEER_NICKNAME, ";
    $sql.= "USER_PEER.RELATIONSHIP FROM `{$table_data['PREFIX']}USER_PEER` USER_PEER ";
    $sql.= "LEFT JOIN USER USER ON (USER.UID = USER_PEER.PEER_UID) ";
    $sql.= "LEFT JOIN `{$table_data['PREFIX']}USER_PEER` USER_PEER ";
    $sql.= "ON (USER_PEER.PEER_UID = USER.UID AND USER_PEER.UID = '$sess_uid') ";
    $sql.= "WHERE USER.UID IS NOT NULL AND USER_PEER.UID = '$uid' ";
    $sql.= "AND (USER_PEER.RELATIONSHIP & $user_rel > 0) ";
    $sql.= "LIMIT 0, 20";

    if (!$result = db_query($sql, $db_user_get_peers)) return false;

    if (db_num_rows($result) > 0) {

        $user_get_peers_array = array();

        while (($user_data = db_fetch_array($result))) {

            if (isset($user_data['LOGON']) && isset($user_data['PEER_NICKNAME'])) {
                if (!is_null($user_data['PEER_NICKNAME']) && strlen($user_data['PEER_NICKNAME']) > 0) {
                    $user_data['NICKNAME'] = $user_data['PEER_NICKNAME'];
                }
            }

            if (!isset($user_data['LOGON'])) $user_data['LOGON'] = gettext("Unknown user");
            if (!isset($user_data['NICKNAME'])) $user_data['NICKNAME'] = "";

            $user_get_peers_array[] = $user_data;
        }

        return $user_get_peers_array;
    }

    return false;
}

function user_get_relationships($uid, $offset = 0)
{
    if (!$db_user_get_relationships = db_connect()) return false;

    $user_get_peers_array = array();

    if (!is_numeric($uid)) return false;
    if (!is_numeric($offset)) return false;

    $offset = abs($offset);

    if (!$table_data = get_table_prefix()) return false;

    $sql = "SELECT SQL_CALC_FOUND_ROWS USER.UID, USER.LOGON, USER.NICKNAME, ";
    $sql.= "USER_PEER.PEER_NICKNAME, USER_PEER.RELATIONSHIP, USER_PEER.PEER_NICKNAME ";
    $sql.= "FROM `{$table_data['PREFIX']}USER_PEER` USER_PEER ";
    $sql.= "LEFT JOIN USER USER ON (USER.UID = USER_PEER.PEER_UID) ";
    $sql.= "WHERE USER_PEER.UID = '$uid' AND USER.UID IS NOT NULL ";
    $sql.= "LIMIT $offset, 10";

    if (!$result = db_query($sql, $db_user_get_relationships)) return false;

    // Fetch the number of total results
    $sql = "SELECT FOUND_ROWS() AS ROW_COUNT";

    if (!$result_count = db_query($sql, $db_user_get_relationships)) return false;

    list($user_get_peers_count) = db_fetch_array($result_count, DB_RESULT_NUM);

    // Check if we have any results.
    if (db_num_rows($result) > 0) {

        while (($user_data = db_fetch_array($result))) {

            if (isset($user_data['LOGON']) && isset($user_data['PEER_NICKNAME'])) {
                if (!is_null($user_data['PEER_NICKNAME']) && strlen($user_data['PEER_NICKNAME']) > 0) {
                    $user_data['NICKNAME'] = $user_data['PEER_NICKNAME'];
                }
            }

            if (!isset($user_data['LOGON'])) $user_data['LOGON'] = gettext("Unknown user");
            if (!isset($user_data['NICKNAME'])) $user_data['NICKNAME'] = "";

            $user_get_peers_array[$user_data['UID']] = $user_data;
        }

    }else if ($user_get_peers_count > 0) {

        $offset = floor(($user_get_peers_count - 1) / 10) * 10;
        return user_get_relationships($uid, $offset);
    }

    return array('user_count' => $user_get_peers_count,
                 'user_array' => $user_get_peers_array);
}

function user_get_peer_relationship($uid, $peer_uid)
{
    if (!$db_user_get_peer_relationship = db_connect()) return false;

    if (!is_numeric($uid)) return false;
    if (!is_numeric($peer_uid)) return false;

    if (!$table_data = get_table_prefix()) return false;

    $sql = "SELECT RELATIONSHIP FROM `{$table_data['PREFIX']}USER_PEER` ";
    $sql.= "WHERE UID = '$uid' AND PEER_UID = '$peer_uid'";

    if (!$result = db_query($sql, $db_user_get_peer_relationship)) return false;

    if (db_num_rows($result) > 0) {

        list($peer_relationship) = db_fetch_array($result, DB_RESULT_NUM);
        return $peer_relationship;
    }

    return 0;
}

function user_get_peer_nickname($uid, $peer_uid)
{
    if (!$db_user_get_peer_nickname = db_connect()) return false;

    if (!is_numeric($uid)) return false;
    if (!is_numeric($peer_uid)) return false;

    if (!$table_data = get_table_prefix()) return false;

    $sql = "SELECT COALESCE(USER_PEER.PEER_NICKNAME, USER.NICKNAME) AS NICKNAME ";
    $sql.= "FROM USER LEFT JOIN `{$table_data['PREFIX']}USER_PEER` USER_PEER ";
    $sql.= "ON (USER_PEER.PEER_UID = USER.UID AND USER_PEER.UID = '$uid') ";
    $sql.= "WHERE USER_PEER.UID = '$uid' AND USER_PEER.PEER_UID = '$peer_uid'";

    if (!$result = db_query($sql, $db_user_get_peer_nickname)) return false;

    if (db_num_rows($result) > 0) {

        list($peer_nickname) = db_fetch_array($result, DB_RESULT_NUM);
        return $peer_nickname;
    }

    return user_get_nickname($peer_uid);
}

function user_search_relationships($user_search, $offset = 0, $exclude_uid = 0)
{
    if (!$db_user_search = db_connect()) return false;

    if (!is_numeric($offset)) return false;
    if (!is_numeric($exclude_uid)) return false;

    $offset = abs($offset);

    if (!$table_data = get_table_prefix()) return false;

    $user_search_peers_array = array();

    if (($uid = session_get_value('UID')) === false) return false;

    $user_search_array = explode(";", $user_search);
    $user_search_array = array_map('user_search_array_clean', $user_search_array);

    $user_search_logon = implode("%' OR LOGON LIKE '", $user_search_array);
    $user_search_nickname = implode("%' OR NICKNAME LIKE '", $user_search_array);

    $sql = "SELECT SQL_CALC_FOUND_ROWS USER.UID, USER.LOGON, USER.NICKNAME, ";
    $sql.= "USER_PEER.PEER_NICKNAME, USER_PEER.RELATIONSHIP ";
    $sql.= "FROM USER USER LEFT JOIN `{$table_data['PREFIX']}USER_PEER` USER_PEER ";
    $sql.= "ON (USER_PEER.PEER_UID = USER.UID AND USER_PEER.UID = '$uid') ";
    $sql.= "WHERE (LOGON LIKE '$user_search_logon%' ";
    $sql.= "OR NICKNAME LIKE '$user_search_nickname%') ";
    $sql.= "AND USER.UID <> $exclude_uid LIMIT $offset, 10";

    if (!$result = db_query($sql, $db_user_search)) return false;

    // Fetch the number of total results
    $sql = "SELECT FOUND_ROWS() AS ROW_COUNT";

    if (!$result_count = db_query($sql, $db_user_search)) return false;

    list($user_search_peers_count) = db_fetch_array($result_count, DB_RESULT_NUM);

    // Check if we have any results.
    if (db_num_rows($result) > 0) {

        while (($user_data = db_fetch_array($result))) {

            if (isset($user_data['LOGON']) && isset($user_data['PEER_NICKNAME'])) {
                if (!is_null($user_data['PEER_NICKNAME']) && strlen($user_data['PEER_NICKNAME']) > 0) {
                    $user_data['NICKNAME'] = $user_data['PEER_NICKNAME'];
                }
            }

            if (!isset($user_data['LOGON'])) $user_data['LOGON'] = gettext("Unknown user");
            if (!isset($user_data['NICKNAME'])) $user_data['NICKNAME'] = "";

            $user_search_peers_array[$user_data['UID']] = $user_data;
        }

    }else if ($user_search_peers_count > 0) {

        $offset = floor(($user_search_peers_count - 1) / 10) * 10;
        return user_search_relationships($user_search, $offset, $exclude_uid);
    }

    return array('user_count' => $user_search_peers_count,
                 'user_array' => $user_search_peers_array);
}

function user_get_word_filter_list($offset)
{
    if (!$db_user_get_word_filter_list = db_connect()) return false;

    if (!is_numeric($offset)) $offset = 0;

    $offset = abs($offset);

    $word_filter_array = array();

    if (!$table_data = get_table_prefix()) return false;

    if (($uid = session_get_value('UID')) === false) return false;

    $sql = "SELECT SQL_CALC_FOUND_ROWS FID, FILTER_NAME, MATCH_TEXT, ";
    $sql.= "REPLACE_TEXT, FILTER_TYPE, FILTER_ENABLED ";
    $sql.= "FROM `{$table_data['PREFIX']}WORD_FILTER` ";
    $sql.= "WHERE UID = '$uid' ORDER BY FID ";
    $sql.= "LIMIT $offset, 10";

    if (!$result = db_query($sql, $db_user_get_word_filter_list)) return false;

    // Fetch the number of total results
    $sql = "SELECT FOUND_ROWS() AS ROW_COUNT";

    if (!$result_count = db_query($sql, $db_user_get_word_filter_list)) return false;

    list($word_filter_count) = db_fetch_array($result_count, DB_RESULT_NUM);

    // Check if we have any results.
    if (db_num_rows($result) > 0) {

        while (($word_filter_data = db_fetch_array($result))) {

            $word_filter_array[$word_filter_data['FID']] = $word_filter_data;
        }

    }else if ($word_filter_count > 0) {

        $offset = floor(($word_filter_count - 1) / 10) * 10;
        return user_get_word_filter_list($offset);
    }

    return array('word_filter_count' => $word_filter_count,
                 'word_filter_array' => $word_filter_array);
}

function user_get_word_filter($filter_id)
{
    if (!$db_user_get_word_filter = db_connect()) return false;

    if (!is_numeric($filter_id)) return false;

    if (($uid = session_get_value('UID')) === false) return false;

    if (!$table_data = get_table_prefix()) return false;

    $sql = "SELECT FID, FILTER_NAME, MATCH_TEXT, REPLACE_TEXT, FILTER_TYPE, ";
    $sql.= "FILTER_ENABLED FROM `{$table_data['PREFIX']}WORD_FILTER` ";
    $sql.= "WHERE FID = '$filter_id' AND UID = '$uid' ";
    $sql.= "ORDER BY FID";

    if (!$result = db_query($sql, $db_user_get_word_filter)) return false;

    if (db_num_rows($result) > 0) {

        $word_filter_array = db_fetch_array($result);
        return $word_filter_array;
    }

    return false;
}

function user_get_word_filter_count()
{
    if (!$db_user_get_word_filter_count = db_connect()) return false;

    if (($uid = session_get_value('UID')) === false) return false;

    if (!$table_data = get_table_prefix()) return false;

    $sql = "SELECT COUNT(FID) AS FILTER_COUNT ";
    $sql.= "FROM `{$table_data['PREFIX']}WORD_FILTER` ";
    $sql.= "WHERE UID = '$uid'";

    if (!$result = db_query($sql, $db_user_get_word_filter_count)) return false;

    list($word_filter_count) =  db_fetch_array($result, DB_RESULT_NUM);

    return $word_filter_count;
}

function user_clear_word_filter()
{
    if (!$db_user_clear_word_filter = db_connect()) return false;

    if (!$table_data = get_table_prefix()) return false;

    if (($uid = session_get_value('UID')) === false) return false;

    $sql = "DELETE QUICK FROM `{$table_data['PREFIX']}WORD_FILTER` WHERE UID = '$uid'";

    if (!db_query($sql, $db_user_clear_word_filter)) return false;

    return true;
}

function user_add_word_filter($filter_name, $match_text, $replace_text, $filter_option, $filter_enabled)
{
    if (!$db_user_add_word_filter = db_connect()) return false;

    $filter_name  = db_escape_string($filter_name);
    $match_text   = db_escape_string($match_text);
    $replace_text = db_escape_string($replace_text);

    if (!is_numeric($filter_option)) return false;
    if (!is_numeric($filter_enabled)) return false;

    if (!$table_data = get_table_prefix()) return false;

    if (($uid = session_get_value('UID')) === false) return false;

    $sql = "INSERT INTO `{$table_data['PREFIX']}WORD_FILTER` ";
    $sql.= "(UID, FILTER_NAME, MATCH_TEXT, REPLACE_TEXT, FILTER_TYPE, FILTER_ENABLED) ";
    $sql.= "VALUES ('$uid', '$filter_name', '$match_text', '$replace_text', '$filter_option', '$filter_enabled')";

    if (!db_query($sql, $db_user_add_word_filter)) return false;

    return true;
}

function user_update_word_filter($filter_id, $filter_name, $match_text, $replace_text, $filter_option, $filter_enabled)
{
    if (!$db_user_save_word_filter = db_connect()) return false;

    if (!is_numeric($filter_id)) return false;

    if (!is_numeric($filter_option)) return false;
    if (!is_numeric($filter_enabled)) return false;

    $filter_name  = db_escape_string($filter_name);
    $match_text   = db_escape_string($match_text);
    $replace_text = db_escape_string($replace_text);

    if (!$table_data = get_table_prefix()) return false;

    if (($uid = session_get_value('UID')) === false) return false;

    $sql = "UPDATE LOW_PRIORITY `{$table_data['PREFIX']}WORD_FILTER` SET FILTER_NAME = '$filter_name', ";
    $sql.= "MATCH_TEXT = '$match_text', REPLACE_TEXT = '$replace_text', ";
    $sql.= "FILTER_TYPE = '$filter_option', FILTER_ENABLED = '$filter_enabled' ";
    $sql.= "WHERE UID = '$uid' AND FID = '$filter_id'";

    if (!db_query($sql, $db_user_save_word_filter)) return false;

    return true;
}

function user_delete_word_filter($filter_id)
{
    if (!$db_user_delete_word_filter = db_connect()) return false;

    if (!is_numeric($filter_id)) return false;

    if (!$table_data = get_table_prefix()) return false;

    if (($uid = session_get_value('UID')) === false) return false;

    $sql = "DELETE QUICK FROM `{$table_data['PREFIX']}WORD_FILTER` ";
    $sql.= "WHERE UID = '$uid' AND FID = '$filter_id'";

    if (!db_query($sql, $db_user_delete_word_filter)) return false;

    return true;
}

function user_is_active($uid)
{
    if (!$db_user_is_active = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    if (!$table_data = get_table_prefix()) return false;

    $forum_fid = $table_data['FID'];

    $sql = "SELECT COUNT(*) FROM SESSIONS WHERE UID = '$uid' ";
    $sql.= "AND FID = '$forum_fid'";

    if (!$result = db_query($sql, $db_user_is_active)) return false;

    list($user_active_count) = db_fetch_array($result, DB_RESULT_NUM);

    return ($user_active_count > 0);
}

function user_allow_pm($uid)
{
    if (!$db_user_allow_pm = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    $sql = "SELECT COUNT(UID) FROM USER_PREFS ";
    $sql.= "WHERE UID = '$uid' AND ALLOW_PM = 'Y'";

    if (!$result = db_query($sql, $db_user_allow_pm)) return false;

    list($allow_pm_count) = db_fetch_array($result, DB_RESULT_NUM);

    return ($allow_pm_count > 0);
}

function user_allow_email($uid)
{
    if (!$db_user_allow_email = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    $sql = "SELECT COUNT(UID) FROM USER_PREFS ";
    $sql.= "WHERE UID = '$uid' AND ALLOW_EMAIL = 'Y'";

    if (!$result = db_query($sql, $db_user_allow_email)) return false;

    list($allow_email_count) = db_fetch_array($result, DB_RESULT_NUM);

    return ($allow_email_count > 0);
}

function user_prefs_prep_attachments($image_attachments_array)
{
    $attachments_array_prepared = array('' => '&nbsp;');

    if (!$attachment_dir = forum_get_setting('attachment_dir')) return array();

    foreach ($image_attachments_array as $hash => $attachment_details) {

        if (($image_info = getimagesize("$attachment_dir/$hash"))) {

            $dimensions_text = gettext("Dimensions"). ": {$image_info[0]}x{$image_info[1]}px";
            $attachments_array_prepared[$hash] = "{$attachment_details['filename']}, $dimensions_text";
        }
    }

    return $attachments_array_prepared;
}

/**
* Get Local Time.
*
* Get's user's local time as a Unix timestamp by checking their timezone,
* GMT and DST offsets and day light savings settings.
*
* @return integer
* @param void
*/

function user_get_local_time()
{
    if (($timezone_id = session_get_value('TIMEZONE')) === false) {
        $timezone_id = forum_get_setting('forum_timezone', false, 27);
    }

    if (($gmt_offset = session_get_value('GMT_OFFSET')) === false) {
        $gmt_offset = forum_get_setting('forum_gmt_offset', false, 0);
    }

    if (($dst_offset = session_get_value('DST_OFFSET')) === false) {
        $dst_offset = forum_get_setting('forum_dst_offset', false, 0);
    }

    if (($dl_saving = session_get_value('DL_SAVING')) === false) {
        $dl_saving = forum_get_setting('forum_dl_saving', false, 'N');
    }

    if ($dl_saving == "Y" && timestamp_is_dst($timezone_id, $gmt_offset)) {
        $local_time = time() + ($gmt_offset * HOUR_IN_SECONDS) + ($dst_offset * HOUR_IN_SECONDS);
    }else {
        $local_time = time() + ($gmt_offset * HOUR_IN_SECONDS);
    }

    return $local_time;
}

function user_get_posts($uid)
{
    if (!$db_user_get_posts = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    if (!$table_data = get_table_prefix()) return false;

    $sql = "SELECT TID, PID FROM `{$table_data['PREFIX']}POST` WHERE FROM_UID = '$uid'";

    if (!$result = db_query($sql, $db_user_get_posts)) return false;

    if (db_num_rows($result)) {

        $user_post_array = array();

        while (($post_data = db_fetch_array($result))) {

            $user_post_array[] = $post_data;
        }

        return $user_post_array;

    }

    return false;
}

?>