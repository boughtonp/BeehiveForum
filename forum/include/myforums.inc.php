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

/* $Id: myforums.inc.php,v 1.82 2008-07-28 21:05:55 decoyduck Exp $ */

// We shouldn't be accessing this file directly.

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header("Request-URI: ../index.php");
    header("Content-Location: ../index.php");
    header("Location: ../index.php");
    exit;
}

include_once(BH_INCLUDE_PATH. "constants.inc.php");
include_once(BH_INCLUDE_PATH. "folder.inc.php");
include_once(BH_INCLUDE_PATH. "forum.inc.php");
include_once(BH_INCLUDE_PATH. "html.inc.php");
include_once(BH_INCLUDE_PATH. "lang.inc.php");
include_once(BH_INCLUDE_PATH. "session.inc.php");
include_once(BH_INCLUDE_PATH. "threads.inc.php");

function get_forum_list($offset)
{
    if (!$db_get_forum_list = db_connect()) return false;

    if (!is_numeric($offset)) return false;

    $lang = load_language_file();

    if (($uid = bh_session_get_value('UID')) === false) return false;

    // Array to hold our forums in.

    $forums_array = array();

    $sql = "SELECT SQL_CALC_FOUND_ROWS FORUMS.FID, FORUMS.ACCESS_LEVEL, USER_FORUM.INTEREST, ";
    $sql.= "CONCAT(FORUMS.DATABASE_NAME, '.', FORUMS.WEBTAG, '_') AS PREFIX FROM FORUMS ";
    $sql.= "LEFT JOIN USER_FORUM USER_FORUM ON (USER_FORUM.FID = FORUMS.FID ";
    $sql.= "AND USER_FORUM.UID = '$uid') WHERE FORUMS.ACCESS_LEVEL > -1 ";
    $sql.= "ORDER BY FORUMS.FID LIMIT $offset, 10";

    if (!$result_forums = db_query($sql, $db_get_forum_list)) return false;

    // Fetch the number of total results

    $sql = "SELECT FOUND_ROWS() AS ROW_COUNT";

    if (!$result_count = db_query($sql, $db_get_forum_list)) return false;

    list($forums_count) = db_fetch_array($result_count, DB_RESULT_NUM);

    if (db_num_rows($result_forums) > 0) {

        while (($forum_data = db_fetch_array($result_forums))) {

            $forum_fid = $forum_data['FID'];

            $forum_settings = forum_get_settings_by_fid($forum_fid);

            foreach ($forum_settings as $key => $value) {

                if (!isset($forum_data[strtoupper($key)])) {

                    $forum_data[strtoupper($key)] = $value;
                }
            }

            // Check the forum name is set. If it isn't set it to 'A Beehive Forum'

            if (!isset($forum_data['FORUM_NAME']) || strlen(trim($forum_data['FORUM_NAME'])) < 1) {
                $forum_data['FORUM_NAME'] = "A Beehive Forum";
            }

            // Check the forum description variable is set.

            if (!isset($forum_data['FORUM_DESC'])) {
                $forum_data['FORUM_DESC'] = "";
            }

            // Get number of messages on forum

            $sql = "SELECT COUNT(PID) AS POST_COUNT FROM {$forum_data['PREFIX']}POST POST ";

            if (!$result_post_count = db_query($sql, $db_get_forum_list)) return false;

            $forum_post_data = db_fetch_array($result_post_count);

            if (!isset($forum_post_data['POST_COUNT']) || is_null($forum_post_data['POST_COUNT'])) {
                $forum_data['MESSAGES'] = 0;
            }else {
                $forum_data['MESSAGES'] = $forum_post_data['POST_COUNT'];
            }

            $forums_array[] = $forum_data;
        }

    }else if ($forums_count > 0) {

        $offset = floor(($forums_count - 1) / 10) * 10;
        return get_forum_list($offset);
    }

    return array('forums_array' => $forums_array,
                 'forums_count' => $forums_count);
}

function get_my_forums($view_type, $offset)
{
    if (!$db_get_my_forums = db_connect()) return false;

    if (!is_numeric($view_type)) return false;
    if (!is_numeric($offset)) return false;

    $lang = load_language_file();

    if (($uid = bh_session_get_value('UID')) === false) return false;

    // Array to hold our forums in.

    $forums_array = array();

    // Fetch the forums

    if ($view_type == FORUMS_SHOW_ALL) {

        $sql = "SELECT SQL_CALC_FOUND_ROWS FORUMS.FID, FORUMS.ACCESS_LEVEL, USER_FORUM.INTEREST, ";
        $sql.= "CONCAT(FORUMS.DATABASE_NAME, '.', FORUMS.WEBTAG, '_') AS PREFIX FROM FORUMS ";
        $sql.= "LEFT JOIN USER_FORUM USER_FORUM ON (USER_FORUM.FID = FORUMS.FID ";
        $sql.= "AND USER_FORUM.UID = '$uid') WHERE FORUMS.ACCESS_LEVEL > -1 ";
        $sql.= "AND (USER_FORUM.INTEREST > -1 OR USER_FORUM.INTEREST IS NULL) ";
        $sql.= "ORDER BY FORUMS.FID LIMIT $offset, 10";

    }elseif ($view_type == FORUMS_SHOW_FAVS) {

        $sql = "SELECT SQL_CALC_FOUND_ROWS FORUMS.FID, FORUMS.ACCESS_LEVEL, USER_FORUM.INTEREST, ";
        $sql.= "CONCAT(FORUMS.DATABASE_NAME, '.', FORUMS.WEBTAG, '_') AS PREFIX FROM FORUMS ";
        $sql.= "LEFT JOIN USER_FORUM USER_FORUM ON (USER_FORUM.FID = FORUMS.FID ";
        $sql.= "AND USER_FORUM.UID = '$uid') WHERE FORUMS.ACCESS_LEVEL > -1 ";
        $sql.= "AND USER_FORUM.INTEREST = 1 ";
        $sql.= "ORDER BY FORUMS.FID LIMIT $offset, 10";

    }elseif ($view_type == FORUMS_SHOW_IGNORED) {

        $sql = "SELECT SQL_CALC_FOUND_ROWS FORUMS.FID, FORUMS.ACCESS_LEVEL, USER_FORUM.INTEREST, ";
        $sql.= "CONCAT(FORUMS.DATABASE_NAME, '.', FORUMS.WEBTAG, '_') AS PREFIX FROM FORUMS ";
        $sql.= "LEFT JOIN USER_FORUM USER_FORUM ON (USER_FORUM.FID = FORUMS.FID ";
        $sql.= "AND USER_FORUM.UID = '$uid') WHERE FORUMS.ACCESS_LEVEL > -1 ";
        $sql.= "AND USER_FORUM.INTEREST = -1 ";
        $sql.= "ORDER BY FORUMS.FID LIMIT $offset, 10";
    }

    if (!$result_forums = db_query($sql, $db_get_my_forums)) return false;

    // Fetch the number of total results

    $sql = "SELECT FOUND_ROWS() AS ROW_COUNT";

    if (!$result_count = db_query($sql, $db_get_my_forums)) return false;

    list($forums_count) = db_fetch_array($result_count, DB_RESULT_NUM);

    if (db_num_rows($result_forums) > 0) {

        while (($forum_data = db_fetch_array($result_forums, DB_RESULT_ASSOC))) {

            $forum_fid = $forum_data['FID'];

            $forum_settings = forum_get_settings_by_fid($forum_fid);

            foreach ($forum_settings as $key => $value) {

                if (!isset($forum_data[strtoupper($key)])) {

                    $forum_data[strtoupper($key)] = $value;
                }
            }

            // Check the forum name is set. If it isn't set it to 'A Beehive Forum'

            if (!isset($forum_data['FORUM_NAME']) || strlen(trim($forum_data['FORUM_NAME'])) < 1) {
                $forum_data['FORUM_NAME'] = "A Beehive Forum";
            }

            // Check the forum description variable is set.

            if (!isset($forum_data['FORUM_DESC'])) {
                $forum_data['FORUM_DESC'] = "";
            }

            // Unread cut-off stamp.

            $unread_cutoff_stamp = forum_process_unread_cutoff($forum_settings);

            // Get available folders for queries below

            $folders = folder_get_available_by_forum($forum_fid);

            // User relationship constants

            $user_ignored = USER_IGNORED;
            $user_ignored_completely = USER_IGNORED_COMPLETELY;

            // Get any unread messages

            if (is_numeric($unread_cutoff_stamp) && $unread_cutoff_stamp !== false) {

                $sql = "SELECT SUM(THREAD.LENGTH) - SUM(COALESCE(USER_THREAD.LAST_READ, 0)) ";
                $sql.= "AS UNREAD_MESSAGES FROM {$forum_data['PREFIX']}THREAD THREAD ";
                $sql.= "LEFT JOIN {$forum_data['PREFIX']}USER_THREAD USER_THREAD ";
                $sql.= "ON (USER_THREAD.TID = THREAD.TID AND USER_THREAD.UID = '$uid') ";
                $sql.= "WHERE THREAD.FID IN ($folders) ";
                $sql.= "AND (THREAD.MODIFIED > FROM_UNIXTIME(UNIX_TIMESTAMP(NOW()) - ";
                $sql.= "$unread_cutoff_stamp) OR $unread_cutoff_stamp = 0) ";

                if (!$result_unread_count = db_query($sql, $db_get_my_forums)) return false;

                list($unread_messages) = db_fetch_array($result_unread_count, DB_RESULT_NUM);

                $forum_data['UNREAD_MESSAGES'] = $unread_messages;

            }else {

                $forum_data['UNREAD_MESSAGES'] = 0;
            }

            // Total number of messages

            $sql = "SELECT SUM(THREAD.LENGTH) AS NUM_MESSAGES ";
            $sql.= "FROM {$forum_data['PREFIX']}THREAD THREAD ";
            $sql.= "WHERE THREAD.FID IN ($folders) ";

            if (!$result_messages_count = db_query($sql, $db_get_my_forums)) return false;

            $num_messages_data = db_fetch_array($result_messages_count);

            if (!isset($num_messages_data['NUM_MESSAGES']) || is_null($num_messages_data['NUM_MESSAGES'])) {
                $forum_data['NUM_MESSAGES'] = 0;
            }else {
                $forum_data['NUM_MESSAGES'] = $num_messages_data['NUM_MESSAGES'];
            }

            // Get unread to me message count

            $sql = "SELECT COUNT(POST.PID) AS UNREAD_TO_ME ";
            $sql.= "FROM {$forum_data['PREFIX']}THREAD THREAD ";
            $sql.= "LEFT JOIN {$forum_data['PREFIX']}POST POST ";
            $sql.= "ON (POST.TID = THREAD.TID) WHERE THREAD.FID IN ($folders) ";
            $sql.= "AND POST.TO_UID = '$uid' AND POST.VIEWED IS NULL ";

            if (!$result_unread_to_me = db_query($sql, $db_get_my_forums)) return false;

            $forum_unread_post_data = db_fetch_array($result_unread_to_me);

            if (!isset($forum_unread_post_data['UNREAD_TO_ME']) || is_null($forum_unread_post_data['UNREAD_TO_ME'])) {
                $forum_data['UNREAD_TO_ME'] = 0;
            }else {
                $forum_data['UNREAD_TO_ME'] = $forum_unread_post_data['UNREAD_TO_ME'];
            }

            // Sometimes the USER_THREAD table might have a higher count that the thread
            // length due to table corruption. I've only seen this on the SF provided
            // webspace but none the less we do this check here anyway.

            if ($forum_data['NUM_MESSAGES'] < 0) $forum_data['NUM_MESSAGES'] = 0;
            if ($forum_data['UNREAD_MESSAGES'] < 0) $forum_data['UNREAD_MESSAGES'] = 0;
            if ($forum_data['UNREAD_TO_ME'] < 0) $forum_data['UNREAD_TO_ME'] = 0;

            // Get Last Visited

            $sql = "SELECT UNIX_TIMESTAMP(LAST_VISIT) AS LAST_VISIT FROM USER_FORUM ";
            $sql.= "WHERE UID = '$uid' AND FID = '$forum_fid' ";
            $sql.= "AND LAST_VISIT IS NOT NULL AND LAST_VISIT > 0";

            if (!$result_last_visit = db_query($sql, $db_get_my_forums)) return false;

            $user_visit_data = db_fetch_array($result_last_visit);

            if (!isset($user_visit_data['LAST_VISIT']) || is_null($user_visit_data['LAST_VISIT'])) {
                $forum_data['LAST_VISIT'] = 0;
            }else {
                $forum_data['LAST_VISIT'] = $user_visit_data['LAST_VISIT'];
            }

            $forums_array[] = $forum_data;
        }

    }else if ($forums_count > 0) {

        $offset = floor(($forums_count - 1) / 10) * 10;
        return get_my_forums($view_type, $offset);
    }

    return array('forums_array' => $forums_array,
                 'forums_count' => $forums_count);
}

function user_set_forum_interest($fid, $interest)
{
    if (!$db_user_set_forum_interest = db_connect()) return false;

    if (($uid = bh_session_get_value('UID')) === false) return false;

    if (!is_numeric($fid)) return false;
    if (!is_numeric($interest)) return false;

    if ($uid > 0) {

        $sql = "SELECT UID FROM USER_FORUM WHERE UID = '$uid' AND FID = '$fid'";

        if (!$result = db_query($sql, $db_user_set_forum_interest)) return false;

        if (db_num_rows($result) > 0) {

            $sql = "UPDATE LOW_PRIORITY USER_FORUM SET INTEREST = '$interest' ";
            $sql.= "WHERE UID = '$uid' AND FID = '$fid'";

        }else {

            $sql = "INSERT INTO USER_FORUM (UID, FID, INTEREST) ";
            $sql.= "VALUES ('$uid', '$fid', 1)";
        }

        if (!$result = db_query($sql, $db_user_set_forum_interest)) return false;
    }

    return true;
}

function forums_any_favourites()
{
    if (!$db_forums_any_favourites = db_connect()) return false;

    if (($uid = bh_session_get_value('UID')) === false) return false;

    $sql = "SELECT COUNT(FID) AS FAV_COUNT FROM USER_FORUM ";
    $sql.= "WHERE INTEREST = 1 AND UID = '$uid'";

    if (!$result = db_query($sql, $db_forums_any_favourites)) return false;

    list($fav_count) = db_fetch_array($result, DB_RESULT_NUM);

    return $fav_count > 0;
}

?>