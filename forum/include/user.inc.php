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

/* $Id: user.inc.php,v 1.216 2004-12-26 12:21:30 decoyduck Exp $ */

include_once("./include/forum.inc.php");
include_once("./include/lang.inc.php");
include_once("./include/session.inc.php");

function user_count()
{
   $db_user_count = db_connect();

   if (!$table_data = get_table_prefix()) return 0;

   $sql = "SELECT COUNT(UID) AS COUNT FROM USER";
   $result = db_query($sql, $db_user_count);

   $user_count = db_fetch_array($result);
   return $user_count['COUNT'];
}

function user_exists($logon)
{
    $db_user_exists = db_connect();

    if (!$table_data = get_table_prefix()) return false;

    $logon = addslashes($logon);

    $sql = "SELECT uid FROM USER WHERE logon = '$logon'";
    $result = db_query($sql, $db_user_exists);

    return (db_num_rows($result) > 0);
}

function user_create($logon, $password, $nickname, $email)
{
    $logon = addslashes($logon);
    $nickname = addslashes($nickname);
    $email = addslashes($email);
    $md5pass = md5($password);

    if (!$ipaddress = get_ip_address()) {
        $ipaddress = "";
    }

    $sql = "INSERT INTO USER (LOGON, PASSWD, NICKNAME, EMAIL) ";
    $sql.= "VALUES ('$logon', '$md5pass', '$nickname', '$email')";

    $db_user_create = db_connect();
    $result = db_query($sql, $db_user_create);

    if ($result) {
        $new_uid = db_insert_id($db_user_create);
    }else {
        $new_uid = -1;
    }

    return $new_uid;
}

function user_update($uid, $nickname, $email)
{
    $db_user_update = db_connect();

    if (!$table_data = get_table_prefix()) return false;

    $nickname = addslashes(_htmlentities($nickname));
    $email = addslashes(_htmlentities($email));

    $sql = "UPDATE USER SET NICKNAME = '$nickname', ";
    $sql.= "EMAIL = '$email' WHERE UID = $uid";

    return db_query($sql, $db_user_update);
}

function user_change_pw($uid, $password, $hash = false)
{
    $db_user_change_pw = db_connect();

    $password = md5($password);

    if (!$table_data = get_table_prefix()) return false;

    if ($hash) {

        $sql = "UPDATE USER SET PASSWD = '$password' ";
        $sql.= "WHERE UID = '$uid' AND PASSWD = '$hash'";

        return db_query($sql, $db_user_change_pw);

    }elseif (perm_is_moderator()) {

        $sql = "UPDATE USER SET PASSWD = '$password' ";
        $sql.= "WHERE UID = '$uid'";

        return db_query($sql, $db_user_change_pw);
    }

    return false;
}

function user_get_status($uid, $fid = 0)
{
    if (!is_numeric($uid)) return 0;

    if (!$table_data = get_table_prefix()) return 0;

    return 0;

    $db_user_get_status = db_connect();

    $result = db_query($sql, $db_user_get_status);
    list($status) = db_fetch_array($result);

    return $status;
}

function user_update_forums($uid, $forums_array)
{
    $db_user_update_forums = db_connect();

    if (!is_numeric($uid)) return false;
    if (!is_array($forums_array)) return false;

    foreach ($forums_array as $forum) {

        if (is_numeric($forum['allowed']) && is_numeric($forum['fid'])) {

            $sql = "SELECT UID FROM USER_FORUM ";
            $sql.= "WHERE UID = '$uid' AND FID = '{$forum['fid']}'";

            $result = db_query($sql, $db_user_update_forums);

            if (db_num_rows($result) > 0) {

                $sql = "UPDATE USER_FORUM SET ALLOWED = '{$forum['allowed']}' ";
                $sql.= "WHERE UID = '$uid' AND FID = '{$forum['fid']}'";

            }else {

                $sql = "INSERT INTO USER_FORUM (UID, FID, ALLOWED) ";
                $sql.= "VALUES ('$uid', '{$forum['fid']}', '{$forum['allowed']}')";
            }

            $result = db_query($sql, $db_user_update_forums);
        }
    }
}

function user_logon($logon, $password, $md5hash = false)
{
    if ($md5hash) {
      $md5pass = addslashes($password);
    }else {
      $md5pass = md5($password);
    }

    $logon = addslashes($logon);

    $db_user_logon = db_connect();

    if ($table_data = get_table_prefix()) {

        $sql = "SELECT USER.UID, BIT_OR(GROUP_PERMS.PERM) AS USER_PERMS, ";
        $sql.= "COUNT(GROUP_PERMS.GID) AS USER_PERM_COUNT FROM USER ";
        $sql.= "LEFT JOIN {$table_data['PREFIX']}GROUP_USERS GROUP_USERS ";
        $sql.= "ON (GROUP_USERS.UID = USER.UID) ";
        $sql.= "LEFT JOIN {$table_data['PREFIX']}GROUP_PERMS GROUP_PERMS ";
        $sql.= "ON (GROUP_PERMS.GID = GROUP_USERS.GID AND GROUP_PERMS.FID = 0) ";
        $sql.= "WHERE USER.LOGON = '$logon' AND USER.PASSWD = '$md5pass' ";
        $sql.= "GROUP BY USER.UID";

    }else {

        $sql = "SELECT USER.UID, 0 AS STATUS FROM USER USER ";
        $sql.= "WHERE USER.LOGON = '$logon' AND USER.PASSWD = '$md5pass'";
    }

    $result = db_query($sql, $db_user_logon);

    if (db_num_rows($result) > 0) {

        $row = db_fetch_array($result);

        // Check to see if the user is banned.

        if (isset($row['USER_PERM_COUNT']) && $row['USER_PERM_COUNT'] > 0) {

            if (isset($row['USER_PERMS']) && $row['USER_PERMS'] & USER_PERM_BANNED) {

                return -2;
            }
        }

        return $row['UID'];
    }

    return -1;
}

function user_get($uid, $hash = false)
{
    $db_user_get = db_connect();

    if (!is_numeric($uid)) return false;

    $table_data = get_table_prefix();

    $sql = "SELECT * FROM USER USER WHERE USER.UID = $uid ";

    if ($hash) {
        $hash = addslashes($hash);
        $sql.= "AND PASSWD = '$hash'";
    }

    $result = db_query($sql, $db_user_get);

    if (db_num_rows($result) > 0) {

        $user_get = db_fetch_array($result);
        return $user_get;
    }

    return false;
}

function user_get_logon($uid)
{
    $db_user_get_logon = db_connect();

    if (!is_numeric($uid)) return false;

    $table_data = get_table_prefix();

    $sql = "SELECT LOGON FROM USER WHERE UID = $uid";
    $result = db_query($sql, $db_user_get_logon);

    if (db_num_rows($result) > 0) {

        list($logon) = db_fetch_array($result, DB_RESULT_NUM);
        return $logon;
    }

    return "Unknown";
}

function user_get_uid($logon)
{
    $db_user_get_uid = db_connect();

    $logon = addslashes($logon);

    if (!$table_data = get_table_prefix()) return false;

    $sql = "SELECT * FROM USER WHERE LOGON = '$logon'";
    $result = db_query($sql, $db_user_get_uid);

    if (db_num_rows($result) > 0) {

        return db_fetch_array($result);
    }

    return false;
}

function user_get_sig($uid, &$content, &$html)
{
    $db_user_get_sig = db_connect();

    if (!is_numeric($uid)) return false;

    if (!$table_data = get_table_prefix()) return false;

    $sql = "SELECT CONTENT, HTML FROM {$table_data['PREFIX']}USER_SIG WHERE UID = $uid";
    $result = db_query($sql, $db_user_get_sig);

    if (db_num_rows($result) > 0) {

        list($content, $html) = db_fetch_array($result, DB_RESULT_NUM);
        return true;
    }

    return false;
}

function user_get_prefs($uid)
{
    // See user_update_prefs() below for an explanation of the prefs system.

    $db_user_get_prefs = db_connect();

    if (!is_numeric($uid)) return false;

    // There are three sources of preferences:
    // 1. The defaults, set here:

    $default_prefs = array('FIRSTNAME'            => '',
                           'LASTNAME'             => '',
                           'DOB'                  => '',
                           'HOMEPAGE_URL'         => '',
                           'PIC_URL'              => '',
                           'EMAIL_NOTIFY'         => 'Y',
                           'TIMEZONE'             => '0',
                           'DL_SAVING'            => 'N',
                           'MARK_AS_OF_INT'       => 'Y',
                           'POSTS_PER_PAGE'       => '20',
                           'FONT_SIZE'            => '10',
                           'STYLE'                => '',
                           'VIEW_SIGS'            => 'Y',
                           'START_PAGE'           => '0',
                           'LANGUAGE'             => '',
                           'PM_NOTIFY'            => 'Y',
                           'PM_NOTIFY_EMAIL'      => 'Y',
                           'PM_SAVE_SENT_ITEM'    => 'Y',
                           'PM_INCLUDE_REPLY'     => 'Y',
                           'PM_AUTO_PRUNE'        => 'N',
                           'PM_AUTO_PRUNE_LENGTH' => '60',
                           'DOB_DISPLAY'          => 'Y',
                           'ANON_LOGON'           => 'N',
                           'SHOW_STATS'           => 'Y',
                           'IMAGES_TO_LINKS'      => 'N',
                           'USE_WORD_FILTER'      => 'N',
                           'USE_ADMIN_FILTER'     => 'N',
                           'EMOTICONS'            => '',
                           'ALLOW_EMAIL'          => 'Y',
                           'ALLOW_PM'             => 'Y',
                           'POST_PAGE'            => '0');

    $forum_prefs = array();

    // 2. The user's global prefs, in USER_PREFS:

    $sql  = "SELECT FIRSTNAME, LASTNAME, DOB, HOMEPAGE_URL, PIC_URL, EMAIL_NOTIFY, TIMEZONE, DL_SAVING, ";
    $sql .= "MARK_AS_OF_INT, POSTS_PER_PAGE, FONT_SIZE, STYLE, VIEW_SIGS, START_PAGE, LANGUAGE, PM_NOTIFY, ";
    $sql .= "PM_NOTIFY_EMAIL, PM_SAVE_SENT_ITEM, PM_INCLUDE_REPLY, PM_AUTO_PRUNE, PM_AUTO_PRUNE_LENGTH, ";
    $sql .= "DOB_DISPLAY, ANON_LOGON, SHOW_STATS, IMAGES_TO_LINKS, USE_WORD_FILTER, USE_ADMIN_FILTER, ";
    $sql .= "EMOTICONS, ALLOW_EMAIL, ALLOW_PM, POST_PAGE FROM USER_PREFS WHERE UID = $uid";

    $result = db_query($sql, $db_user_get_prefs);

    $global_prefs = (db_num_rows($result) > 0) ? db_fetch_array($result, DB_RESULT_ASSOC) : array();

    // 3. The user's per-forum prefs, in {webtag}_USER_PREFS (not all prefs are set here e.g. name):

    if ($table_data = get_table_prefix()) {

        $sql  = "SELECT HOMEPAGE_URL, PIC_URL, EMAIL_NOTIFY, ";
        $sql .= "MARK_AS_OF_INT, POSTS_PER_PAGE, FONT_SIZE, STYLE, VIEW_SIGS, START_PAGE, LANGUAGE, ";
        $sql .= "DOB_DISPLAY, ANON_LOGON, SHOW_STATS, IMAGES_TO_LINKS, USE_WORD_FILTER, USE_ADMIN_FILTER, ";
        $sql .= "EMOTICONS, ALLOW_EMAIL, ALLOW_PM FROM {$table_data['PREFIX']}USER_PREFS WHERE UID = $uid";

        $result = db_query($sql, $db_user_get_prefs);
        $forum_prefs = (db_num_rows($result) > 0) ? db_fetch_array($result, DB_RESULT_ASSOC) : array();
    }

    // Prune empty values from the arrays (to stop them overwriting valid values)
    // using strlen() as a callback function.

    $global_prefs = array_filter($global_prefs, "strlen");
    $forum_prefs = array_filter($forum_prefs, "strlen");

    // Add keys to indicate whether the preference is set globally or not

    foreach ($default_prefs as $key => $value) {
        $default_prefs[$key.'_GLOBAL'] = true;
    }

    foreach ($forum_prefs as $key => $value) {
        $forum_prefs[$key.'_GLOBAL'] = false;
    }

    foreach ($global_prefs as $key => $value) {
        $global_prefs[$key.'_GLOBAL'] = true;
    }

    // Merge them all together, with forum prefs overriding
    // global prefs overriding default prefs

    $prefs_array = array_merge($default_prefs, $global_prefs, $forum_prefs);

    return $prefs_array;
}

function user_update_prefs($uid, $prefs_array, $prefs_global_setting_array = false)
{
    /* Attempt at explaining the new prefs system:

    $prefs_array contains the preference settings to be altered. Its keys are the names of the preference
    settings (same as the names of the corresponding database fields). $prefs_global_setting_array
    also has keys which are the names of the preference settings to be changed but contain Boolean values
    that when true set the appropriate preference globally and when false only set it for the current forum.
    The default behaviour is to set a preference globally if it is not specified otherwise.

    e.g.    $prefs_array                  $prefs_global_setting_array      Result
            'STYLE' => 'default'          'STYLE' => true                  Sets STYLE to 'default' globally
            'VIEW_SIGS' => 'N'            'VIEW_SIGS' => false             Sets VIEW_SIGS to 'N' for current forum only
            'FONT_SIZE' => 11             'FONT_SIZE' not set              Sets FONT_SIZE to 11 globally

    FIRSTNAME, LASTNAME, DOB, TIMEZONE, DL_SAVING and POST_PAGE can only be set globally - there's no sense
    in changing them on a per-forum basis.

    */

    if (!is_numeric($uid)) return false;
    if (!is_array($prefs_array)) return false;
    if (!$prefs_global_setting_array) $prefs_global_setting_array = array();
    if (!is_array($prefs_global_setting_array)) return false;

    // names of preferences that can be set globally

    $global_pref_names = array('FIRSTNAME', 'LASTNAME', 'DOB', 'HOMEPAGE_URL',
                               'PIC_URL', 'EMAIL_NOTIFY', 'TIMEZONE', 'DL_SAVING',
                               'MARK_AS_OF_INT', 'POSTS_PER_PAGE', 'FONT_SIZE',
                               'STYLE', 'VIEW_SIGS', 'START_PAGE', 'LANGUAGE',
                               'PM_NOTIFY', 'PM_NOTIFY_EMAIL', 'PM_SAVE_SENT_ITEM',
                               'PM_INCLUDE_REPLY', 'PM_AUTO_PRUNE', 'PM_AUTO_PRUNE_LENGTH',
                               'DOB_DISPLAY', 'ANON_LOGON', 'SHOW_STATS',
                               'IMAGES_TO_LINKS', 'USE_WORD_FILTER', 'USE_ADMIN_FILTER',
                               'EMOTICONS', 'ALLOW_EMAIL', 'ALLOW_PM', 'POST_PAGE');

    // names of preferences that can be set on a per-forum basis

    $forum_pref_names =  array('HOMEPAGE_URL', 'PIC_URL', 'EMAIL_NOTIFY',
                               'MARK_AS_OF_INT', 'POSTS_PER_PAGE', 'FONT_SIZE',
                               'STYLE', 'VIEW_SIGS', 'START_PAGE', 'LANGUAGE',
                               'DOB_DISPLAY', 'ANON_LOGON', 'SHOW_STATS',
                               'IMAGES_TO_LINKS', 'USE_WORD_FILTER', 'USE_ADMIN_FILTER',
                               'EMOTICONS', 'ALLOW_EMAIL', 'ALLOW_PM');

    foreach ($prefs_array as $pref_name => $pref_setting) {

        if (user_check_pref($pref_name, $pref_setting)) {

            if (!isset($prefs_global_setting_array[$pref_name]) || $prefs_global_setting_array[$pref_name] == true) {

                // preference is to be set globally.
                // check this pref name is allowed to be set globally

                if (in_array($pref_name, $global_pref_names)) $global_prefs[$pref_name] = $pref_setting;

            }else {

                // preference is to be set for current forum only
                // check this pref name is allowed to be set on a per-forum basis

                if (in_array($pref_name, $forum_pref_names)) $forum_prefs[$pref_name] = $pref_setting;
            }
        }
    }

    $db_user_update_prefs = db_connect();

    if (isset($global_prefs)) {

        // Is there an entry in USER_PREFS already for this user?

        $sql = "SELECT * FROM USER_PREFS WHERE UID = $uid";
        $result = db_query($sql, $db_user_update_prefs);

        if (db_num_rows($result) > 0) {

            // previous entry which we will UPDATE

            $values = array();

            foreach($global_prefs as $pref_name => $pref_setting) {
                 if (user_check_pref($pref_name, $pref_setting)) {
                     $values[] = "$pref_name = '$pref_setting'";
                 }
            }

            $sql = "UPDATE USER_PREFS SET ";
            $sql.= implode(",", $values);
            $sql.= " WHERE UID = $uid";

        }else {

            // no previous entry, construct an INSERT query

            $sql  = "INSERT INTO USER_PREFS (UID,";
            $sql .= implode(",",$global_pref_names);
            $sql .= ") VALUES ('$uid'";

            foreach ($global_pref_names as $pref_name) {

                if (isset($global_prefs[$pref_name])) {

                     $sql .= ", '{$global_prefs[$pref_name]}'";

                }else {

                     $sql .= ", ''";
                }
            }

            $sql .= ")";
        }

        $result_global = db_query($sql, $db_user_update_prefs);

        // If a pref is set globally, we need to remove it from all the [webtag]_USER_PREFS tables too.
        // MySQL doesn't mind if a record for this user doesn't exist in a particular table.

        $values = array();

        foreach($global_prefs as $pref_name => $pref_setting) {

            if (in_array($pref_name, $forum_pref_names)) {

                $values[] = "$pref_name = ''";
            }
        }

        if (count($values) > 0) {

            $values = implode(",",$values);
            $webtags = forum_get_all_webtags();

            foreach($webtags as $webtag) {

                $sql = "UPDATE {$webtag}_USER_PREFS SET $values WHERE UID = $uid";
                $result = db_query($sql, $db_user_update_prefs);
            }
        }

    }else {

        $result_global = true;
    }

    if (isset($forum_prefs) && $table_data = get_table_prefix()) {

        $sql = "SELECT * FROM {$table_data['PREFIX']}USER_PREFS WHERE UID = $uid";
        $result = db_query($sql, $db_user_update_prefs);

        if (db_num_rows($result) > 0) {

            // previous entry which we will UPDATE

            $values = array();

            foreach($forum_prefs as $pref_name => $pref_setting) {

                $values[] = "$pref_name = '$pref_setting'";
            }

            $values = implode(",", $values);
            $sql = "UPDATE {$table_data['PREFIX']}USER_PREFS SET $values WHERE UID = $uid";

        }else {

            // no previous entry, construct an INSERT query

            $sql  = "INSERT INTO {$table_data['PREFIX']}USER_PREFS (UID,";
            $sql .= implode(",", $forum_pref_names);
            $sql .= ") VALUES ('$uid'";

            foreach ($forum_pref_names as $pref_name) {

                if (isset($forum_prefs[$pref_name])) {

                    $sql .= ", '{$forum_prefs[$pref_name]}'";

                }else {

                    $sql .= ", ''";
                }
            }

            $sql .= ")";
        }

        $result_forum = db_query($sql, $db_user_update_prefs);

    }else {

        $result_forum = true;
    }

    return ($result_global && $result_forum);
}

function user_check_pref($name, $value)
{
        // Checks to ensure that a preference setting contains valid data
        if ($name == "FIRSTNAME" || $name == "LASTNAME") {
            return preg_match("/^[a-z0-9 ]*$/i", $value);
        } elseif ($name == "STYLE" || $name == "EMOTICONS" || $name == "LANGUAGE") {
            // NB: this does not check that the files/folders for STYLE, EMOTICONS, and LANGUAGE actually exist
            return preg_match("/^[a-z0-9_-]*$/i", $value);
        } elseif ($name ==  "DOB") {
            return preg_match("/^[0-9]{4}-[0-9]{1,2}-[0-9]{1,2}$/", $value);
        } elseif ($name == "HOMEPAGE_URL" || $name == "PIC_URL") {
            return preg_match("/^(http:\/\/[a-z0-9\/.-_]+|)$/i", $value);
        } elseif ($name == "EMAIL_NOTIFY" || $name == "DL_SAVING" || $name == "MARK_AS_OF_INT" || $name == "VIEW_SIGS" || $name == "PM_NOTIFY" || $name == "PM_NOTIFY_EMAIL" || $name == "PM_INCLUDE_REPLY" || $name == "PM_SAVE_SENT_ITEM" || $name == "PM_AUTO_PRUNE" || $name == "IMAGES_TO_LINKS" || $name == "USE_WORD_FILTER" || $name == "USE_ADMIN_FILTER" || $name == "ALLOW_EMAIL" || $name == "ALLOW_PM") {
            return ($value == "Y" || $value == "N") ? true : false;
        } elseif ($name == "TIMEZONE" || $name == "POSTS_PER_PAGE" || $name == "FONT_SIZE" || $name == "START_PAGE" || $name == "DOB_DISPLAY" || $name == "ANON_LOGON" || $name == "SHOW_STATS" || $name == "POST_PAGE" || $name == "PM_AUTO_PRUNE_LENGTH") {
            return is_numeric($value);
        } else {
            return false;
        }
}

function user_update_sig($uid, $content, $html)
{
    if (!is_numeric($uid)) return false;

    $content = addslashes($content);
    $db_user_update_sig = db_connect();

    if (!$table_data = get_table_prefix()) return false;

    $sql = "SELECT UID FROM {$table_data['PREFIX']}USER_SIG ";
    $sql.= "WHERE UID = '$uid'";

    $result = db_query($sql, $db_user_update_sig);

    if (db_num_rows($result) > 0) {

        $sql = "UPDATE {$table_data['PREFIX']}USER_SIG SET CONTENT = '$content', ";
        $sql.= "HTML = '$html' WHERE UID = '$uid'";

    }else {

        $sql = "INSERT INTO {$table_data['PREFIX']}USER_SIG (UID, CONTENT, HTML) ";
        $sql.= "VALUES ('$uid', '$content', '$html')";
    }

    return db_query($sql, $db_user_update_sig);
}

function user_update_global_sig($uid, $value, $global = true)
{
    return user_update_prefs($uid, array('VIEW_SIGS' => ($value == 'N') ? 'N' : 'Y'), array('VIEW_SIGS' => $global));
}

function user_get_global_sig($uid)
{
    $prefs = user_get_prefs($uid);
    return $prefs['VIEW_SIGS'];
}

function user_guest_enabled()
{
    if (forum_get_setting('guest_account_enabled', 'N', false)) {
        return false;
    }

    return true;
}

function user_get_dob($uid)
{
    if (!is_numeric($uid)) return false;

    $prefs = user_get_prefs($uid);

    if (isset($prefs['DOB_DISPLAY']) && $prefs['DOB_DISPLAY'] == 2 && !empty($prefs['DOB']) && $prefs['DOB'] != "0000-00-00") {
        return format_birthday($prefs['DOB']);
    } else {
        return false;
    }
}

function user_get_age($uid)
{
    if (!is_numeric($uid)) return false;

    $prefs = user_get_prefs($uid);

    if (isset($prefs['DOB_DISPLAY']) && $prefs['DOB_DISPLAY'] > 0 && !empty($prefs['DOB']) && $prefs['DOB'] != "0000-00-00") {
        return format_age($prefs['DOB']);
    }else {
        return false;
    }
}

function user_get_forthcoming_birthdays()
{
    $db_user_get_forthcoming_birthdays = db_connect();

    if (!$table_data = get_table_prefix()) return false;

    $sql  = "SELECT U.UID, U.LOGON, U.NICKNAME, UP.DOB, DAYOFMONTH(UP.DOB) AS BDAY, MONTH(UP.DOB) AS BMONTH ";
    $sql .= "FROM USER U, USER_PREFS UP ";
    $sql .= "WHERE U.UID = UP.UID AND UP.DOB > 0 AND UP.DOB_DISPLAY = 2 ";
    $sql .= "AND ((MONTH(UP.DOB) = MONTH(NOW()) AND DAYOFMONTH(UP.DOB) >= DAYOFMONTH(NOW())) ";
    $sql .= "OR MONTH(UP.DOB) > MONTH(NOW())) ";
    $sql .= "ORDER BY BMONTH ASC, BDAY ASC ";
    $sql .= "LIMIT 0, 5";

    $result = db_query($sql, $db_user_get_forthcoming_birthdays);

    if (db_num_rows($result) > 0) {

        $birthdays = array();

        while ($row = db_fetch_array($result)) {

            $birthdays[] = $row;
        }

        return $birthdays;

    }else {

        return false;
    }
}

function user_search($usersearch, $offset = 0)
{
    $db_user_search = db_connect();

    $user_search_array = array();
    $user_search_count = 0;

    if (!$table_data = get_table_prefix()) return array('user_count' => 0,
                                                        'user_array' => array());

    if (!is_numeric($offset)) $offset = 0;

    $usersearch = addslashes($usersearch);

    $sql = "SELECT COUNT(USER.UID) AS USER_COUNT FROM USER ";
    $sql.= "WHERE (USER.LOGON LIKE '$usersearch%' OR USER.NICKNAME LIKE '$usersearch%') ";

    $result = db_query($sql, $db_user_search);
    list($user_search_count) = db_fetch_array($result, DB_RESULT_NUM);

    $sql = "SELECT USER.UID, USER.LOGON, USER.NICKNAME FROM USER USER ";
    $sql.= "WHERE (USER.LOGON LIKE '$usersearch%' OR USER.NICKNAME LIKE '$usersearch%') ";
    $sql.= "LIMIT $offset, 20";

    $result = db_query($sql, $db_user_search);

    if (db_num_rows($result) > 0) {

        while ($row = db_fetch_array($result)) {

            if (!isset($user_search_array[$row['UID']])) {

                $user_search_array[$row['UID']] = $row;
            }
        }
    }

    return array('user_count' => $user_search_count,
                 'user_array' => $user_search_array);
}

function user_get_aliases($uid)
{
    $db_user_get_aliases = db_connect();

    if (!$table_data = get_table_prefix()) return array();

    if (!is_numeric($uid)) return false;

    // Initialise arrays

    $user_ip_address_array = array();
    $user_get_aliases_array = array();

    // Fetch the last 20 IP addresses from the POST table

    $sql = "SELECT IPADDRESS FROM {$table_data['PREFIX']}POST ";
    $sql.= "WHERE FROM_UID = '$uid' ORDER BY TID DESC LIMIT 0, 20";

    $result = db_query($sql, $db_user_get_aliases);

    if (db_num_rows($result) > 0) {

        while($user_get_aliases_row = db_fetch_array($result)) {

            if (!in_array($user_get_aliases_row['IPADDRESS'], $user_ip_address_array) && strlen($user_get_aliases_row['IPADDRESS']) > 0) {

                $user_ip_address_array[] = $user_get_aliases_row['IPADDRESS'];
            }
        }
    }

    // Search the POST table for any matches - limit 10 matches

    $user_ip_address_list = implode("' OR IPADDRESS = '", $user_ip_address_array);

    $sql = "SELECT USER.UID, USER.LOGON, POST.IPADDRESS FROM {$table_data['PREFIX']}POST POST ";
    $sql.= "LEFT JOIN USER USER ON (POST.FROM_UID = USER.UID) ";
    $sql.= "WHERE (POST.IPADDRESS = '$user_ip_address_list') AND POST.FROM_UID <> '$uid' ";
    $sql.= "GROUP BY USER.UID ORDER BY POST.TID DESC LIMIT 0, 10";

    $result = db_query($sql, $db_user_get_aliases);

    if (db_num_rows($result) > 0) {

        while($user_get_aliases_row = db_fetch_array($result)) {

            $user_get_aliases_array[$user_get_aliases_row['UID']] = $user_get_aliases_row;
        }
    }

    return $user_get_aliases_array;
}

function users_get_recent($offset, $limit)
{
    if (!is_numeric($offset)) $offset = 0;
    if (!is_numeric($limit)) $limit = 20;

    $users_get_recent_array = array();
    $users_get_recent_count = 0;

    $db_users_get_recent = db_connect();

    if (!$table_data = get_table_prefix()) return array('user_count' => 0,
                                                        'user_array' => array());

    $sql = "SELECT COUNT(USER.UID) AS USER_COUNT FROM USER ";
    $sql.= "LEFT JOIN {$table_data['PREFIX']}VISITOR_LOG VISITOR_LOG ON (USER.UID = VISITOR_LOG.UID) ";
    $sql.= "WHERE VISITOR_LOG.LAST_LOGON IS NOT NULL AND VISITOR_LOG.LAST_LOGON > 0";

    $result = db_query($sql, $db_users_get_recent);
    list($users_get_recent_count) = db_fetch_array($result, DB_RESULT_NUM);

    $sql = "SELECT USER.UID, USER.LOGON, USER.NICKNAME, ";
    $sql.= "UNIX_TIMESTAMP(VISITOR_LOG.LAST_LOGON) AS LAST_LOGON FROM USER USER ";
    $sql.= "LEFT JOIN {$table_data['PREFIX']}VISITOR_LOG VISITOR_LOG ON (USER.UID = VISITOR_LOG.UID) ";
    $sql.= "WHERE VISITOR_LOG.LAST_LOGON IS NOT NULL AND VISITOR_LOG.LAST_LOGON > 0 ";
    $sql.= "ORDER BY VISITOR_LOG.LAST_LOGON DESC ";
    $sql.= "LIMIT $offset, $limit";

    $result = db_query($sql, $db_users_get_recent);

    if (db_num_rows($result) > 0) {

        while ($row = db_fetch_array($result)) {

            if (!isset($users_get_recent_array[$row['UID']])) {

                $users_get_recent_array[$row['UID']] = $row;
            }
        }
    }

    return array('user_count' => $users_get_recent_count,
                 'user_array' => $users_get_recent_array);
}

function users_search_recent($usersearch, $offset)
{
    if (!is_numeric($offset)) $offset = 0;
    $usersearch = addslashes($usersearch);

    $user_search_array = array();
    $user_search_count = 0;

    $db_users_search_recent = db_connect();

    if (!$table_data = get_table_prefix()) return array('user_count' => 0,
                                                        'user_array' => array());

    $sql = "SELECT COUNT(USER.UID) AS USER_COUNT FROM USER ";
    $sql.= "LEFT JOIN {$table_data['PREFIX']}VISITOR_LOG VISITOR_LOG ON (USER.UID = VISITOR_LOG.UID) ";
    $sql.= "WHERE VISITOR_LOG.LAST_LOGON IS NOT NULL AND VISITOR_LOG.LAST_LOGON > 0";

    $result = db_query($sql, $db_users_search_recent);
    list($user_search_count) = db_fetch_array($result, DB_RESULT_NUM);

    $sql = "SELECT USER.UID, USER.LOGON, USER.NICKNAME, ";
    $sql.= "UNIX_TIMESTAMP(VISITOR_LOG.LAST_LOGON) AS LAST_LOGON FROM USER USER ";
    $sql.= "LEFT JOIN {$table_data['PREFIX']}VISITOR_LOG VISITOR_LOG ON (USER.UID = VISITOR_LOG.UID) ";
    $sql.= "WHERE (USER.LOGON LIKE '$usersearch%' OR USER.NICKNAME LIKE '$usersearch%') ";
    $sql.= "AND VISITOR_LOG.LAST_LOGON IS NOT NULL AND VISITOR_LOG.LAST_LOGON > 0 ";
    $sql.= "ORDER BY VISITOR_LOG.LAST_LOGON DESC ";
    $sql.= "LIMIT $offset, 20";

    $result = db_query($sql, $db_users_search_recent);

    if (db_num_rows($result) > 0) {

        while ($row = db_fetch_array($result)) {

            if (!isset($user_search_array[$row['UID']])) {

                $user_search_array[$row['UID']] = $row;
            }
        }
    }

    return array('user_count' => $user_search_count,
                 'user_array' => $user_search_array);
}

function user_get_friends($uid)
{
    $db_user_get_peers = db_connect();

    if (!is_numeric($uid)) return false;

    if (!$table_data = get_table_prefix()) return false;

    $sql = "SELECT USER.UID, USER.LOGON, USER.NICKNAME, USER_PEER.RELATIONSHIP ";
    $sql.= "FROM {$table_data['PREFIX']}USER_PEER USER_PEER ";
    $sql.= "LEFT JOIN USER USER ON (USER.UID = USER_PEER.PEER_UID) ";
    $sql.= "WHERE USER_PEER.UID = $uid AND USER_PEER.RELATIONSHIP & 1 = 1 ";
    $sql.= "LIMIT 0, 20";

    $result = db_query($sql, $db_user_get_peers);

    if (db_num_rows($result) > 0) {

        $user_get_peers_array = array();

        while ($row = db_fetch_array($result)) {

            $user_get_peers_array[] = $row;
        }

        return $user_get_peers_array;
    }

    return false;
}

function user_get_ignored($uid)
{
    $db_user_get_peers = db_connect();

    if (!is_numeric($uid)) return false;

    if (!$table_data = get_table_prefix()) return false;

    $sql = "SELECT USER.UID, USER.LOGON, USER.NICKNAME, USER_PEER.RELATIONSHIP ";
    $sql.= "FROM {$table_data['PREFIX']}USER_PEER USER_PEER ";
    $sql.= "LEFT JOIN USER USER ON (USER.UID = USER_PEER.PEER_UID) ";
    $sql.= "WHERE USER_PEER.UID = $uid AND USER_PEER.RELATIONSHIP & 2 = 2 ";
    $sql.= "LIMIT 0, 20";

    $result = db_query($sql, $db_user_get_peers);

    if (db_num_rows($result) > 0) {

        $user_get_peers_array = array();

        while ($row = db_fetch_array($result)) {

            $user_get_peers_array[] = $row;
        }

        return $user_get_peers_array;
    }

    return false;
}

function user_get_ignored_signatures($uid)
{
    $db_user_get_peers = db_connect();

    if (!is_numeric($uid)) return false;

    if (!$table_data = get_table_prefix()) return false;

    $sql = "SELECT USER.UID, USER.LOGON, USER.NICKNAME, USER_PEER.RELATIONSHIP ";
    $sql.= "FROM {$table_data['PREFIX']}USER_PEER USER_PEER ";
    $sql.= "LEFT JOIN USER USER ON (USER.UID = USER_PEER.PEER_UID) ";
    $sql.= "WHERE USER_PEER.UID = $uid AND USER_PEER.RELATIONSHIP & 4 = 4 ";
    $sql.= "LIMIT 0, 20";

    $result = db_query($sql, $db_user_get_peers);

    if (db_num_rows($result) > 0) {

        $user_get_peers_array = array();

        while ($row = db_fetch_array($result)) {
            $user_get_peers_array[] = $row;
        }

        return $user_get_peers_array;
    }

    return false;
}

function user_get_relationships($uid, $offset = 0)
{
    $db_user_get_relationships = db_connect();

    $user_get_peers_array = array();
    $user_get_peers_count = 0;

    if (!$table_data = get_table_prefix()) return array('user_count' => 0,
                                                        'user_array' => array());

    if (!is_numeric($offset)) $offset = 0;

    $sql = "SELECT COUNT(USER.UID) AS USER_COUNT FROM USER USER ";
    $sql.= "LEFT JOIN {$table_data['PREFIX']}USER_PEER USER_PEER ON (USER_PEER.PEER_UID = USER.UID) ";
    $sql.= "WHERE USER_PEER.UID = '$uid' AND USER_PEER.RELATIONSHIP <> 0";

    $result = db_query($sql, $db_user_get_relationships);
    list($user_get_peers_count) = db_fetch_array($result, DB_RESULT_NUM);

    $sql = "SELECT USER.UID, USER.LOGON, USER.NICKNAME, USER_PEER.RELATIONSHIP ";
    $sql.= "FROM {$table_data['PREFIX']}USER_PEER USER_PEER ";
    $sql.= "LEFT JOIN USER USER ON (USER.UID = USER_PEER.PEER_UID) ";
    $sql.= "WHERE USER_PEER.UID = $uid AND USER_PEER.RELATIONSHIP <> 0 ";
    $sql.= "LIMIT $offset, 20";

    $result = db_query($sql, $db_user_get_relationships);

    if (db_num_rows($result) > 0) {

        while ($row = db_fetch_array($result)) {

            if (!isset($user_search_array[$row['UID']])) {

                $user_get_peers_array[$row['UID']] = $row;
            }
        }
    }

    return array('user_count' => $user_get_peers_count,
                 'user_array' => $user_get_peers_array);
}

function user_get_word_filter()
{
    $db_user_get_word_filter = db_connect();

    if (!$table_data = get_table_prefix()) return array();

    $uid = bh_session_get_value('UID');

    if (bh_session_get_value('USE_ADMIN_FILTER') == 'Y') {

        $sql = "SELECT * FROM {$table_data['PREFIX']}FILTER_LIST ";
        $sql.= "WHERE UID = '$uid' OR UID = 0 ORDER BY ID LIMIT 0, 20";

    }else {

        $sql = "SELECT * FROM {$table_data['PREFIX']}FILTER_LIST ";
        $sql.= "WHERE UID = '$uid' ORDER BY ID LIMIT 0, 20";
    }

    $result = db_query($sql, $db_user_get_word_filter);

    $filter_array = array();

    while($row = db_fetch_array($result)) {
        $filter_array[$row['ID']] = $row;
    }

    return $filter_array;
}

function user_clear_word_filter()
{
    $db_user_clear_word_filter = db_connect();

    if (!$table_data = get_table_prefix()) return false;

    $uid = bh_session_get_value('UID');

    $sql = "DELETE FROM {$table_data['PREFIX']}FILTER_LIST WHERE UID = '$uid'";
    return db_query($sql, $db_user_clear_word_filter);
}

function user_add_word_filter($match, $replace, $filter_option)
{
    $match = addslashes($match);
    $replace = addslashes($replace);

    $db_user_save_word_filter = db_connect();

    if (!$table_data = get_table_prefix()) return false;

    $uid = bh_session_get_value('UID');

    $sql = "INSERT INTO {$table_data['PREFIX']}FILTER_LIST (UID, MATCH_TEXT, REPLACE_TEXT, FILTER_OPTION) ";
    $sql.= "VALUES ('$uid', '$match', '$replace', '$filter_option')";

    $result = db_query($sql, $db_user_save_word_filter);
}

function user_delete_word_filter($id)
{
    if (!is_numeric($id)) return false;

    $db_user_delete_word_filter = db_connect();

    if (!$table_data = get_table_prefix()) return false;

    $uid = bh_session_get_value('UID');

    $sql = "DELETE FROM {$table_data['PREFIX']}FILTER_LIST ";
    $sql.= "WHERE ID = '$id' AND UID = '$uid'";

    $result = db_query($sql, $db_user_delete_word_filter);
}

function user_is_active($uid)
{
    if (!is_numeric($uid)) return false;

    $db_user_is_active = db_connect();

    if (!$table_data = get_table_prefix()) return false;

    $sql = "SELECT * FROM SESSIONS WHERE UID = '$uid' ";
    $sql.= "AND FID = '{$table_data['FID']}'";

    $result = db_query($sql, $db_user_is_active);

    return (db_num_rows($result) > 0);
}

function user_allow_pm($uid)
{
    $prefs = user_get_prefs($uid);
    return $prefs['ALLOW_PM'] == "Y" ? true : false;
}

function user_allow_email($uid)
{
    $prefs = user_get_prefs($uid);
    return $prefs['ALLOW_EMAIL'] == "Y" ? true : false;
}

function user_is_guest()
{
    $uid = bh_session_get_value('UID');
    return ($uid == 0);
}

?>