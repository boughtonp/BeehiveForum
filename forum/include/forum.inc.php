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

include_once(BH_INCLUDE_PATH. "attachments.inc.php");
include_once(BH_INCLUDE_PATH. "constants.inc.php");
include_once(BH_INCLUDE_PATH. "db.inc.php");
include_once(BH_INCLUDE_PATH. "fixhtml.inc.php");
include_once(BH_INCLUDE_PATH. "folder.inc.php");
include_once(BH_INCLUDE_PATH. "form.inc.php");
include_once(BH_INCLUDE_PATH. "format.inc.php");
include_once(BH_INCLUDE_PATH. "header.inc.php");
include_once(BH_INCLUDE_PATH. "html.inc.php");
include_once(BH_INCLUDE_PATH. "install.inc.php");
include_once(BH_INCLUDE_PATH. "lang.inc.php");
include_once(BH_INCLUDE_PATH. "perm.inc.php");
include_once(BH_INCLUDE_PATH. "pm.inc.php");
include_once(BH_INCLUDE_PATH. "post.inc.php");
include_once(BH_INCLUDE_PATH. "server.inc.php");
include_once(BH_INCLUDE_PATH. "session.inc.php");
include_once(BH_INCLUDE_PATH. "sitemap.inc.php");
include_once(BH_INCLUDE_PATH. "stats.inc.php");
include_once(BH_INCLUDE_PATH. "text_captcha.inc.php");
include_once(BH_INCLUDE_PATH. "threads.inc.php");
include_once(BH_INCLUDE_PATH. "user.inc.php");
include_once(BH_INCLUDE_PATH. "word_filter.inc.php");

function get_forum_data()
{
    static $forum_data = false;

    if (!$db_get_forum_data = db_connect()) return false;

    if (!is_array($forum_data) || !isset($forum_data['WEBTAG']) || !isset($forum_data['PREFIX'])) {

        if (($webtag = get_webtag())) {

            // Check #1: See if the webtag specified in GET/POST
            // actually exists.
            $webtag = db_escape_string($webtag);

            $sql = "SELECT FID, WEBTAG, ACCESS_LEVEL, DEFAULT_FORUM, DATABASE_NAME, ";
            $sql.= "CONCAT(DATABASE_NAME, '`.`', WEBTAG, '_') AS PREFIX ";
            $sql.= "FROM FORUMS WHERE WEBTAG = '$webtag'";

            if (($result = db_query($sql, $db_get_forum_data))) {

                if (db_num_rows($result) > 0) {

                    $forum_data = db_fetch_array($result);

                    if (!isset($forum_data['ACCESS_LEVEL'])) $forum_data['ACCESS_LEVEL'] = 0;
                }
            }

        }else {

            // Check #2: Try and select a default webtag from
            // the databse
            $sql = "SELECT FID, WEBTAG, ACCESS_LEVEL, DEFAULT_FORUM, DATABASE_NAME, ";
            $sql.= "CONCAT(DATABASE_NAME, '`.`', WEBTAG, '_') AS PREFIX ";
            $sql.= "FROM FORUMS WHERE DEFAULT_FORUM = 1";

            if (($result = db_query($sql, $db_get_forum_data))) {

                if (db_num_rows($result) > 0) {

                    $forum_data = db_fetch_array($result);

                    if (!isset($forum_data['ACCESS_LEVEL'])) $forum_data['ACCESS_LEVEL'] = 0;
                }
            }
        }
    }

    return $forum_data;
}

function get_webtag()
{
    if (isset($_GET['webtag']) && preg_match("/^[A-Z]{1}[A-Z0-9_]+$/D", trim(stripslashes_array($_GET['webtag'])))) {
        return trim(stripslashes_array($_GET['webtag']));
    } else if (isset($_POST['webtag']) && preg_match("/^[A-Z]{1}[A-Z0-9_]+$/D", trim(stripslashes_array($_POST['webtag'])))) {
        return trim(stripslashes_array($_POST['webtag']));
    }

    return false;
}

function get_table_prefix()
{
    $forum_data = get_forum_data();

    if (is_array($forum_data) && isset($forum_data['PREFIX'])) {
        return $forum_data;
    }

    return false;
}

function get_all_table_prefixes()
{
    $db_get_all_table_prefixes = db_connect();

    $sql = "SELECT FID, WEBTAG, ACCESS_LEVEL, DEFAULT_FORUM, DATABASE_NAME, ";
    $sql.= "CONCAT(DATABASE_NAME, '`.`', WEBTAG, '_') AS PREFIX FROM FORUMS";

    if (!($result = db_query($sql, $db_get_all_table_prefixes))) return false;

    if (db_num_rows($result) == 0) return false;

    $forum_data_array = array();

    while (($forum_data = db_fetch_array($result))) {

        if (!isset($forum_data['ACCESS_LEVEL'])) $forum_data['ACCESS_LEVEL'] = 0;

        $forum_data_array[] = $forum_data;
    }

    return $forum_data_array;
}

function forum_check_webtag_available(&$webtag = false)
{
    $forum_data = get_forum_data();

    if (is_array($forum_data) && isset($forum_data['WEBTAG'])) {

        if (isset($forum_data['DEFAULT_FORUM']) && $webtag === false) {
            $webtag = ($forum_data['DEFAULT_FORUM'] == FORUM_DEFAULT) ? $forum_data['WEBTAG'] : $webtag;
        }

        return ($forum_data['ACCESS_LEVEL'] != FORUM_DISABLED);
    }

    return false;
}

function forum_check_access_level()
{
    static $forum_data = false;

    if (!($db_forum_check_access_level = db_connect())) return false;

    if (($uid = session_get_value('UID')) === false) return false;

    if (!($table_data = get_table_prefix())) return true;

    $forum_fid = $table_data['FID'];

    if (!is_array($forum_data) || !isset($forum_data['ACCESS_LEVEL']) || !isset($forum_data['ALLOWED'])) {

        $sql = "SELECT FORUMS.FID, FORUMS.ACCESS_LEVEL, USER_FORUM.ALLOWED FROM FORUMS ";
        $sql.= "LEFT JOIN USER_FORUM ON (USER_FORUM.FID = FORUMS.FID ";
        $sql.= "AND USER_FORUM.UID = '$uid') WHERE FORUMS.FID = '$forum_fid' ";
        $sql.= "AND FORUMS.ACCESS_LEVEL < 3";

        if (!$result = db_query($sql, $db_forum_check_access_level)) return false;

        if (db_num_rows($result) > 0) {

            $forum_data = db_fetch_array($result);
        }
    }

    if (isset($forum_data['ACCESS_LEVEL'])) {

        if ($forum_data['ACCESS_LEVEL'] < FORUM_UNRESTRICTED) {

            return forum_closed_message();

        }elseif ($forum_data['ACCESS_LEVEL'] == FORUM_RESTRICTED && $forum_data['ALLOWED'] != FORUM_USER_ALLOWED) {

            return forum_restricted_message();

        }elseif ($forum_data['ACCESS_LEVEL'] == FORUM_PASSWD_PROTECTED) {

            return forum_check_password($forum_data['FID']);
        }
    }

    return true;
}

function forum_closed_message()
{
    html_draw_top(sprintf("title=%s", gettext("Closed")));

    $forum_name = forum_get_setting('forum_name', false, 'A Beehive Forum');

    echo "<h1>", gettext("Closed"), "</h1>\n";

    if (($closed_message = forum_get_setting('closed_message', false))) {

        html_display_error_msg(fix_html($closed_message), '600', 'center');

    }else {

        html_display_error_msg(sprintf(gettext("%s is currently closed"), htmlentities_array($forum_name)), '600', 'center');
    }

    if (session_check_perm(USER_PERM_ADMIN_TOOLS, 0) || session_check_perm(USER_PERM_FORUM_TOOLS, 0)) {

        html_display_warning_msg(gettext("If you want to change some settings on your forum click the Admin link in the navigation bar above."), '600', 'center');
    }

    html_draw_bottom();
    exit;
}

function forum_restricted_message()
{
    $webtag = get_webtag();

    $final_uri = basename(get_request_uri());

    $forum_name = forum_get_setting('forum_name', false, 'A Beehive Forum');

    $forum_owner_link = "<a href=\"index.php?webtag=$webtag&amp;final_uri=%s\" target=\"%s\">%s</a>";

    $popup_files_preg = get_available_js_popup_files_preg();

    if (preg_match("/^$popup_files_preg/", $final_uri) > 0) {
        $forum_owner_link_target = "_blank";
    }else {
        $forum_owner_link_target = html_get_top_frame_name();
    }

    if (($restricted_message = forum_get_setting('restricted_message', false))) {

        html_draw_top(sprintf("title=%s", gettext("Restricted")));
        html_display_error_msg(fix_html($restricted_message), '600', 'center');
        html_draw_bottom();

    }else {

        if (($forum_owner_uid = forum_get_setting('owner_uid'))) {

            $pm_write_link = "pm_write.php?webtag=$webtag&amp;uid=$forum_owner_uid";

            $forum_owner_pm_link = sprintf($forum_owner_link, rawurlencode($pm_write_link), $forum_owner_link_target, gettext("forum owner"));

            $apply_for_access_text = sprintf(gettext("To apply for access please contact the %s."), $forum_owner_pm_link);

            html_draw_top("title=", gettext("Restricted"), "", 'pm_popup_disabled', 'robots=noindex,nofollow');
            html_error_msg(sprintf(gettext("You do not have access to %s. %s"), htmlentities_array($forum_name), $apply_for_access_text));

            if (session_check_perm(USER_PERM_ADMIN_TOOLS, 0) || session_check_perm(USER_PERM_FORUM_TOOLS, 0)) {
                html_display_warning_msg(gettext("If you want to change some settings on your forum click the Admin link in the navigation bar above."), '600', 'left');
            }

            html_draw_bottom();
            exit;

        }else {

            html_draw_top("title=", gettext("Restricted"), "", 'pm_popup_disabled', 'robots=noindex,nofollow');
            html_error_msg(sprintf(gettext("You do not have access to %s. %s"), htmlentities_array($forum_name), ''));
            html_draw_bottom();
        }
    }

    exit;
}

function forum_get_password($forum_fid)
{
    if (!$db_forum_get_password = db_connect()) return false;

    if (!is_numeric($forum_fid)) return false;

    $sql = "SELECT FORUM_PASSWD FROM FORUMS WHERE FID = '$forum_fid'";

    if (!$result = db_query($sql, $db_forum_get_password)) return false;

    if (db_num_rows($result) > 0) {

        list($forum_passwd) = db_fetch_array($result, DB_RESULT_NUM);
        return is_md5($forum_passwd) ? $forum_passwd : false;
    }

    return false;
}

function forum_check_password($forum_fid)
{
    $webtag = get_webtag();

    if (!forum_check_webtag_available($webtag)) return false;

    if (!is_numeric($forum_fid)) return false;

    // Get the forum's password hash.
    if (!($forum_passhash = forum_get_password($forum_fid))) return true;

    // Check the stored cookie against the known hash of the forum password.
    if (html_get_cookie("sess_hash_{$webtag}", 'is_md5') == $forum_passhash) return true;

    html_draw_top(sprintf("title=%s", gettext("Password Protected Forum")));

    echo "<h1>", gettext("Password Protected Forum"), "</h1>\n";

    if (html_get_cookie("sess_hash_{$webtag}", 'strlen')) {

        html_set_cookie("sess_hash_{$webtag}", "", time() - YEAR_IN_SECONDS);
        html_display_error_msg(gettext("The username or password you supplied is not valid."), '550', 'center');
    }

    if (($password_protected_message = forum_get_setting('password_protected_message', false))) {

        echo fix_html($password_protected_message);

    }else {

        html_display_warning_msg(gettext("This forum is password protected. To gain access enter the password below."), '400', 'center');
    }

    echo "<br />\n";
    echo "<div align=\"center\">\n";
    echo "  <form accept-charset=\"utf-8\" method=\"post\" action=\"forum_password.php\" target=\"", html_get_top_frame_name(), "\">\n";
    echo "    ", form_input_hidden('webtag', htmlentities_array($webtag)), "\n";
    echo "    ", form_input_hidden('final_uri', htmlentities_array(get_request_uri())), "\n";
    echo "    <table cellpadding=\"0\" cellspacing=\"0\" width=\"400\">\n";
    echo "      <tr>\n";
    echo "        <td align=\"left\">\n";
    echo "          <table class=\"box\" width=\"400\">\n";
    echo "            <tr>\n";
    echo "              <td class=\"posthead\" align=\"center\">\n";
    echo "                <table class=\"posthead\" width=\"100%\">\n";
    echo "                  <tr>\n";
    echo "                    <td align=\"left\" class=\"subhead\" colspan=\"2\">", gettext("Enter Password"), "</td>\n";
    echo "                  </tr>\n";
    echo "                </table>\n";
    echo "                <table class=\"posthead\" width=\"90%\">\n";
    echo "                  <tr>\n";
    echo "                    <td align=\"left\">", gettext("Password"), ":</td>\n";
    echo "                    <td align=\"left\">", form_input_password('forum_password', '', 40, false, ''), "</td>\n";
    echo "                  </tr>\n";
    echo "                  <tr>\n";
    echo "                    <td align=\"left\" colspan=\"2\">&nbsp;</td>\n";
    echo "                  </tr>\n";
    echo "                </table>\n";
    echo "              </td>\n";
    echo "            </tr>\n";
    echo "          </table>\n";
    echo "        </td>\n";
    echo "      </tr>\n";
    echo "      <tr>\n";
    echo "        <td align=\"left\">&nbsp;</td>\n";
    echo "      </tr>\n";
    echo "      <tr>\n";
    echo "        <td align=\"center\">", form_submit("logon", gettext("Logon")), "&nbsp;", form_submit("cancel", gettext("Cancel")), "</td>\n";
    echo "      </tr>\n";
    echo "    </table>\n";

    if (session_check_perm(USER_PERM_ADMIN_TOOLS, 0) || session_check_perm(USER_PERM_FORUM_TOOLS, 0)) {
        html_display_warning_msg(gettext("If you want to change some settings on your forum click the Admin link in the navigation bar above."), '400', 'center');
    }

    echo "  </form>\n";
    echo "</div>\n";

    html_draw_bottom();

    exit;
}

function forum_get_settings()
{
    if (!$db_forum_get_settings = db_connect()) return false;

    static $forum_settings_array = false;

    if (!$table_data = get_table_prefix()) return false;

    $forum_fid = $table_data['FID'];

    if (!is_array($forum_settings_array)) {

        // Get the named settings from FORUM_SETTINGS table.
        $sql = "SELECT SNAME, SVALUE FROM FORUM_SETTINGS WHERE FID = '$forum_fid'";

        if (!$result = db_query($sql, $db_forum_get_settings)) return false;

        if (db_num_rows($result) > 0) {

            // Preset some of the settings, including GMT offset and DST offset.
            $forum_settings_array = array('fid' => $forum_fid, 'forum_gmt_offset' => 0, 'forum_dst_offset' => 1);

            while (($forum_data = db_fetch_array($result))) {

                if (forum_check_setting_name($forum_data['SNAME'])) {

                    $forum_settings_array[$forum_data['SNAME']] = $forum_data['SVALUE'];
                }
            }
        }

        // Get the forum timezone, GMT offset and DST offset.
        if (isset($forum_settings_array['forum_timezone'])) {

            $sql = "SELECT GMT_OFFSET, DST_OFFSET FROM TIMEZONES ";
            $sql.= "WHERE TZID = '{$forum_settings_array['forum_timezone']}'";

            if (!$result = db_query($sql, $db_forum_get_settings)) return false;

            if (db_num_rows($result) > 0) {

                list($gmt_offset, $dst_offset) = db_fetch_array($result, DB_RESULT_NUM);

                $forum_settings_array['forum_gmt_offset'] = $gmt_offset;
                $forum_settings_array['forum_dst_offset'] = $dst_offset;
            }
        }

        // Get the WEBTAG, ACCESS_LEVEL and OWNER_UID
        $sql = "SELECT WEBTAG, ACCESS_LEVEL, OWNER_UID FROM FORUMS WHERE FID = '$forum_fid'";

        if (!$result = db_query($sql, $db_forum_get_settings)) return false;

        list($webtag, $access_level, $owner_uid) = db_fetch_array($result, DB_RESULT_NUM);

        $forum_settings_array['webtag'] = $webtag;
        $forum_settings_array['access_level'] = $access_level;
        $forum_settings_array['owner_uid'] = $owner_uid;
    }

    return $forum_settings_array;
}

function forum_get_global_settings()
{
    if (!$db_forum_get_global_settings = db_connect()) return false;

    static $forum_global_settings_array = false;

    if (!is_array($forum_global_settings_array)) {

        // Get the named settings from FORUM_SETTINGS table.
        $sql = "SELECT SNAME, SVALUE FROM FORUM_SETTINGS WHERE FID = '0'";

        if (!$result = db_query($sql, $db_forum_get_global_settings)) return false;

        if (db_num_rows($result) > 0) {

            // Preset some of the settings, including GMT offset and DST offset.
            $forum_global_settings_array = array('forum_gmt_offset' => 0, 'forum_dst_offset' => 1);

            while (($forum_data = db_fetch_array($result))) {

                if (forum_check_global_setting_name($forum_data['SNAME'])) {

                    $forum_global_settings_array[$forum_data['SNAME']] = $forum_data['SVALUE'];
                }
            }
        }

        // Get the forum timezone, GMT offset and DST offset.
        if (isset($forum_global_settings_array['forum_timezone'])) {

            $sql = "SELECT GMT_OFFSET, DST_OFFSET FROM TIMEZONES ";
            $sql.= "WHERE TZID = '{$forum_global_settings_array['forum_timezone']}'";

            if (!$result = db_query($sql, $db_forum_get_global_settings)) return false;

            if (db_num_rows($result) > 0) {

                list($gmt_offset, $dst_offset) = db_fetch_array($result, DB_RESULT_NUM);

                $forum_global_settings_array['forum_gmt_offset'] = $gmt_offset;
                $forum_global_settings_array['forum_dst_offset'] = $dst_offset;
            }
        }
    }

    return $forum_global_settings_array;
}

function forum_get_settings_by_fid($forum_fid)
{
    if (!$db_forum_get_settings = db_connect()) return false;

    static $forum_settings_array = false;

    if (!is_numeric($forum_fid)) return false;

    if (!is_array($forum_settings_array)) {

        // Get the named settings from FORUM_SETTINGS table.
        $sql = "SELECT SNAME, SVALUE FROM FORUM_SETTINGS WHERE FID = '$forum_fid'";

        if (!$result = db_query($sql, $db_forum_get_settings)) return false;

        if (db_num_rows($result) > 0) {

            // Preset some of the settings, including GMT offset and DST offset.
            $forum_settings_array = array('fid' => $forum_fid, 'forum_gmt_offset' => 0, 'forum_dst_offset' => 1);

            while (($forum_data = db_fetch_array($result))) {

                if (forum_check_setting_name($forum_data['SNAME'])) {

                    $forum_settings_array[$forum_data['SNAME']] = $forum_data['SVALUE'];
                }
            }
        }

        // Get the forum timezone, GMT offset and DST offset.
        if (isset($forum_settings_array['forum_timezone'])) {

            $sql = "SELECT GMT_OFFSET, DST_OFFSET FROM TIMEZONES ";
            $sql.= "WHERE TZID = '{$forum_settings_array['forum_timezone']}'";

            if (!$result = db_query($sql, $db_forum_get_settings)) return false;

            if (db_num_rows($result) > 0) {

                list($gmt_offset, $dst_offset) = db_fetch_array($result, DB_RESULT_NUM);

                $forum_settings_array['forum_gmt_offset'] = $gmt_offset;
                $forum_settings_array['forum_dst_offset'] = $dst_offset;
            }
        }
    }

    return $forum_settings_array;
}

function forum_save_settings($forum_settings_array)
{
    if (!is_array($forum_settings_array)) return false;

    if (!$db_forum_save_settings = db_connect()) return false;

    if (!$table_data = get_table_prefix()) return false;

    $forum_fid = $table_data['FID'];

    foreach ($forum_settings_array as $setting_name => $setting_value) {

        if (forum_check_setting_name($setting_name)) {

            $setting_name  = db_escape_string($setting_name);
            $setting_value = db_escape_string($setting_value);

            $sql = "INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) ";
            $sql.= "VALUES ($forum_fid, '$setting_name', '$setting_value')";
            $sql.= "ON DUPLICATE KEY UPDATE SVALUE = VALUES(SVALUE)";

            if (!db_query($sql, $db_forum_save_settings)) return false;
        }
    }

    return true;
}

function forum_save_default_settings($forum_settings_array)
{
    if (!is_array($forum_settings_array)) return false;

    if (!$db_forum_save_default_settings = db_connect()) return false;

    foreach ($forum_settings_array as $setting_name => $setting_value) {

        if (forum_check_global_setting_name($setting_name)) {

            $setting_name = db_escape_string($setting_name);
            $setting_value = db_escape_string($setting_value);

            $sql = "INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) ";
            $sql.= "VALUES ('0', '$setting_name', '$setting_value') ";
            $sql.= "ON DUPLICATE KEY UPDATE SVALUE = VALUES(SVALUE)";

            if (!db_query($sql, $db_forum_save_default_settings)) return false;
        }
    }

    return true;
}

function forum_check_setting_name($setting_name)
{
    $valid_forum_settings = array('adsense_display_users', 'adsense_display_pages', 'allow_polls',
                                  'allow_post_editing', 'allow_search_spidering', 'closed_message',
                                  'default_emoticons', 'default_language', 'default_style',
                                  'enable_wiki_integration', 'enable_wiki_quick_links', 'enable_google_analytics',
                                  'force_word_filter', 'forum_desc', 'forum_content_rating',
                                  'forum_dl_saving', 'forum_email', 'forum_keywords', 'forum_name',
                                  'forum_links_top_link', 'forum_timezone', 'google_analytics_code',
                                  'guest_account_enabled', 'guest_show_recent', 'maximum_post_length',
                                  'minimum_post_frequency', 'password_protected_message', 'poll_allow_guests',
                                  'post_edit_grace_period', 'post_edit_time', 'require_post_approval',
                                  'restricted_message', 'searchbots_show_active', 'searchbots_show_recent',
                                  'send_new_user_email', 'session_cutoff', 'show_links', 'require_link_approval',
                                  'show_share_links', 'show_stats', 'start_page', 'start_page_css', 
                                  'wiki_integration_uri');

    return in_array($setting_name, $valid_forum_settings);
}

function forum_check_global_setting_name($setting_name)
{
    $valid_global_forum_settings = array('adsense_publisher_id', 'adsense_medium_ad_id', 'adsense_small_ad_id',
                                         'adsense_display_users', 'adsense_display_pages', 'adsense_message_number',
                                         'active_sess_cutoff', 'allow_new_registrations', 'allow_search_spidering', 
                                         'allow_username_changes', 'attachments_allow_embed', 'attachments_enabled', 
                                         'attachment_thumbnails', 'attachment_thumbnail_method', 'attachments_max_user_space', 
                                         'attachments_max_post_space', 'attachment_allow_guests', 'attachment_dir', 
                                         'attachment_mime_types', 'attachment_use_old_method', 'attachment_imagemagick_path', 
                                         'cache_dir', 'content_delivery_domains', 'forum_desc',  'forum_email', 
                                         'forum_keywords', 'forum_name', 'forum_noreply_email', 'forum_rules_enabled', 
                                         'forum_rules_message', 'forum_maintenance_function', 'forum_maintenance_schedule', 
                                         'forum_timezone', 'pm_system_prune_folders_last_run', 'thread_auto_prune_unread_data_last_run', 
                                         'sitemap_create_file_last_run', 'enable_google_analytics', 'allow_forum_google_analytics', 
                                         'google_analytics_code', 'guest_account_enabled', 'guest_show_recent', 'message_cache_enabled', 
                                         'messages_unread_cutoff', 'messages_unread_cutoff_custom', 'new_user_email_notify', 
                                         'new_user_mark_as_of_int', 'new_user_pm_notify_email', 'new_user_pm_notify', 
                                         'pm_allow_attachments', 'pm_auto_prune', 'pm_max_user_messages', 
                                         'require_email_confirmation', 'require_unique_email', 'require_user_approval', 
                                         'search_min_frequency', 'searchbots_show_active', 'searchbots_show_recent', 
                                         'send_new_user_email', 'session_cutoff', 'sitemap_enabled', 'sitemap_freq', 
                                         'showpopuponnewpm', 'show_pms', 'text_captcha_enabled', 'text_captcha_key',
                                         'text_captcha_dir', 'mail_function', 'sendmail_path', 'smtp_server', 'smtp_port', 
                                         'smtp_username', 'smtp_password', 'sphinx_search_enabled', 'sphinx_search_host', 
                                         'sphinx_search_port', 'use_minified_scripts', 'sfs_enabled', 'sfs_api_url', 
                                         'sfs_min_confidence', 'ajax_chat_enabled');

    return in_array($setting_name, $valid_global_forum_settings);
}

function forum_get_name($fid)
{
    if (!$db_forum_get_name = db_connect()) return false;

    if (!is_numeric($fid)) return "A Beehive Forum";

    $sql = "SELECT SVALUE AS FORUM_NAME FROM FORUM_SETTINGS ";
    $sql.= "WHERE SNAME = 'forum_name' AND FID = '$fid'";

    if (!$result = db_query($sql, $db_forum_get_name)) return false;

    if (db_num_rows($result) > 0) {

        list($forum_name) = db_fetch_array($result, DB_RESULT_NUM);
        return $forum_name;
    }

    return "A Beehive Forum";
}

function forum_get_webtag($fid)
{
    if (!$db_forum_get_webtag = db_connect()) return false;

    if (!is_numeric($fid)) return false;

    $sql = "SELECT WEBTAG FROM FORUMS WHERE FID = '$fid' AND ACCESS_LEVEL < 3";

    if (!$result = db_query($sql, $db_forum_get_webtag)) return false;

    if (db_num_rows($result) > 0) {

        list($forum_webtag) = db_fetch_array($result, DB_RESULT_NUM);
        return $forum_webtag;
    }

    return false;
}

function forum_get_table_prefix($fid)
{
    if (!$db_forum_get_table_prefix = db_connect()) return false;

    if (!is_numeric($fid)) return false;

    $sql = "SELECT CONCAT(DATABASE_NAME, '`.`', WEBTAG, '_') AS PREFIX, ";
    $sql.= "FID, WEBTAG FROM FORUMS WHERE FID = '$fid'";

    if (!$result = db_query($sql, $db_forum_get_table_prefix)) return false;

    if (db_num_rows($result) > 0) {

        $forum_data = db_fetch_array($result);
        return $forum_data;
    }

    return false;
}

function forum_get_setting($setting_name, $callback = false, $default = false)
{
    $forum_settings = (isset($GLOBALS['forum_settings'])) ? $GLOBALS['forum_settings'] : false;
    $forum_global_settings = (isset($GLOBALS['forum_global_settings'])) ? $GLOBALS['forum_global_settings'] : false;

    if (is_array($forum_settings) && isset($forum_settings[$setting_name])) {

        if ($callback !== false) {

            if (function_exists($callback) && is_callable($callback)) {

                return ($callback($forum_settings[$setting_name])) ? $forum_settings[$setting_name] : $default;

            }else if (is_string($callback)) {

                return mb_strtoupper($forum_settings[$setting_name]) == mb_strtoupper($callback);
            }
        }

        return $forum_settings[$setting_name];
    }

    if (is_array($forum_global_settings) && isset($forum_global_settings[$setting_name])) {

        if ($callback !== false) {

            if (function_exists($callback) && is_callable($callback)) {

                return ($callback($forum_global_settings[$setting_name])) ? $forum_global_settings[$setting_name] : $default;

            }else if (is_string($callback)) {

                return mb_strtoupper($forum_global_settings[$setting_name]) == mb_strtoupper($callback);
            }
        }

        return $forum_global_settings[$setting_name];
    }

    return $default;
}

function forum_get_global_setting($setting_name, $callback = false, $default = false)
{
    $forum_global_settings = (isset($GLOBALS['forum_global_settings'])) ? $GLOBALS['forum_global_settings'] : false;

    if (is_array($forum_global_settings) && isset($forum_global_settings[$setting_name])) {

        if ($callback !== false) {

            if (function_exists($callback) && is_callable($callback)) {

                return ($callback($forum_global_settings[$setting_name])) ? $forum_global_settings[$setting_name] : $default;

            }else if (is_string($callback)) {

                return mb_strtoupper($forum_global_settings[$setting_name]) == mb_strtoupper($callback);
            }
        }

        return $forum_global_settings[$setting_name];
    }

    return $default;
}

/**
* Get Unread Cutoff
*
* Retrieves and validates the current forum's unread cut-off value.
*
* @return mixed - False if unread messages are disabled or integer number of seconds the cut-off is set to.
* @param void
*/

function forum_get_unread_cutoff()
{
    // Array of valid Unread cutoff periods.
    $unread_cutoff_periods = array(THIRTY_DAYS_IN_SECONDS, SIXTY_DAYS_IN_SECONDS,
                                   NINETY_DAYS_IN_SECONDS, HUNDRED_EIGHTY_DAYS_IN_SECONDS,
                                   YEAR_IN_SECONDS);

    // Fetch the unread cutoff value
    $messages_unread_cutoff = forum_get_setting('messages_unread_cutoff');

    // If unread message support is disabled we return false.
    if ($messages_unread_cutoff == UNREAD_MESSAGES_DISABLED) return false;

    // If unread message support isn't disabled we should check that
    // It is a valid value and return it or return the default of one year.
    return in_array($messages_unread_cutoff, $unread_cutoff_periods) ? $messages_unread_cutoff : YEAR_IN_SECONDS;
}

/**
* Get Unread Cutoff Datetime
*
* Retrieves the current forum's unread cut-off formatted as MySQL datetime
* This function different from forum_get_unread_cutoff() as it returns the
* cutoff date and not the cutoff length.
*
* @return mixed - False if unread messages are disabled or integer number of seconds the cut-off is set to.
* @param void
*/

function forum_get_unread_cutoff_datetime()
{
    if (($unread_cutoff_stamp = forum_get_unread_cutoff()) !== false) {
        return date(MYSQL_DATETIME_MIDNIGHT, time() - $unread_cutoff_stamp);
    }

    return false;
}

/**
* Process Unread Cutoff
*
* Processes and validates a forum unread cut-off value saved in a settings array.
* Works the same as forum_get_unread_cutoff() but is intended to be passed an
* array of forum settings from forum_get_settings() or other.
*
* @return mixed - False if unread messages are disabled or integer number of seconds the cut-off is set to.
* @param void
*/

function forum_process_unread_cutoff($forum_settings)
{
    // Check the $forum_settings array.
    if (!is_array($forum_settings)) return YEAR_IN_SECONDS;

    // Array of valid Unread cutoff periods.
    $unread_cutoff_periods = array(THIRTY_DAYS_IN_SECONDS, SIXTY_DAYS_IN_SECONDS,
                                   NINETY_DAYS_IN_SECONDS, HUNDRED_EIGHTY_DAYS_IN_SECONDS,
                                   YEAR_IN_SECONDS);

    // Fetch the unread cutoff value from the settings array
    if (isset($forum_settings['messages_unread_cutoff'])) {
        $messages_unread_cutoff = $forum_settings['messages_unread_cutoff'];
    }else {
        $messages_unread_cutoff = YEAR_IN_SECONDS;
    }

    // If unread message support is disabled we return false.
    if ($messages_unread_cutoff == UNREAD_MESSAGES_DISABLED) return false;

    // If unread message support isn't disabled we should check that
    // It is a valid value and return it or return the default of one year.
    return in_array($messages_unread_cutoff, $unread_cutoff_periods) ? $messages_unread_cutoff : YEAR_IN_SECONDS;
}

function forum_update_unread_data($unread_cutoff_stamp)
{
    if (!$db_forum_update_unread_data = db_connect()) return false;

    if (!is_numeric($unread_cutoff_stamp)) return false;

    if ($unread_cutoff_stamp !== false) {

        $unread_cutoff_datetime = date(MYSQL_DATETIME_MIDNIGHT, time() - $unread_cutoff_stamp);

        if (($forum_prefix_array = forum_get_all_prefixes())) {

            foreach ($forum_prefix_array as $forum_prefix) {

                $sql = "DELETE QUICK FROM `{$forum_prefix}USER_THREAD` ";
                $sql.= "USING `{$forum_prefix}USER_THREAD` LEFT JOIN `{$forum_prefix}THREAD` ";
                $sql.= "ON (`{$forum_prefix}USER_THREAD`.`TID` = `{$forum_prefix}USER_THREAD`.`TID`) ";
                $sql.= "WHERE `{$forum_prefix}THREAD`.`MODIFIED` IS NOT NULL ";
                $sql.= "AND `{$forum_prefix}THREAD`.`MODIFIED` < CAST('$unread_cutoff_datetime' AS DATETIME) ";
                $sql.= "AND (`{$forum_prefix}USER_THREAD`.`INTEREST` IS NULL ";
                $sql.= "OR `{$forum_prefix}USER_THREAD`.`INTEREST` = 0)";

                if (!db_query($sql, $db_forum_update_unread_data)) return false;
            }
        }
    }

    return true;
}

function forum_create($webtag, $forum_name, $owner_uid, $database_name, $access, &$error_str = '')
{
    if (!is_numeric($owner_uid)) return false;
    if (!is_numeric($access)) return false;

    // Ensure the variables we've been given are valid
    if (!preg_match("/^[A-Z]{1}[A-Z0-9_]+$/Du", $webtag)) return false;

    $current_datetime = date(MYSQL_DATETIME, time());

    // Generate table prefix
    $forum_table_prefix = install_format_table_prefix($database_name, $webtag);

    if (!$db_forum_create = db_connect()) return false;

    // Check that the WEBTAG is unique.
    $sql = "SELECT FID FROM FORUMS WHERE WEBTAG = '$webtag'";

    if (!($result = @db_query($sql, $db_forum_create))) return false;

    if (db_num_rows($result) > 0) {

        $error_str = gettext("The selected webtag is already in use. Please choose another.");
        return false;
    }

    // Check for any conflicting tables.
    if (($conflicting_tables_array = install_check_table_conflicts($database_name, $webtag, true, false, false))) {

        $error_str = gettext("The selected database contains conflicting tables. Conflicting table names are:");
        $error_str.= sprintf("<p>%s</p>\n", implode(", ", $conflicting_tables_array));

        return false;
    }

    // Catch SQL Exceptions
    try {

        // Create the tables
        $sql = "CREATE TABLE `{$forum_table_prefix}ADMIN_LOG` (";
        $sql.= "  ID MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT,";
        $sql.= "  UID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  CREATED DATETIME DEFAULT NULL,";
        $sql.= "  ACTION MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  ENTRY TEXT,";
        $sql.= "  PRIMARY KEY  (ID)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table ADMIN_LOG');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}BANNED` (";
        $sql.= "  ID MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT, ";
        $sql.= "  BANTYPE TINYINT(4) NOT NULL DEFAULT '0', ";
        $sql.= "  BANDATA VARCHAR(255) NOT NULL DEFAULT '', ";
        $sql.= "  COMMENT VARCHAR(255) NOT NULL DEFAULT '', ";
        $sql.= "  EXPIRES DATETIME DEFAULT NULL, ";
        $sql.= "  PRIMARY KEY (ID), ";
        $sql.= "  KEY BANTYPE (BANTYPE, BANDATA)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table BANNED');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}FOLDER` (";
        $sql.= "  FID MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT, ";
        $sql.= "  TITLE VARCHAR(32) DEFAULT NULL, ";
        $sql.= "  DESCRIPTION VARCHAR(255) DEFAULT NULL, ";
        $sql.= "  CREATED datetime default NULL, ";
        $sql.= "  MODIFIED datetime default NULL, ";
        $sql.= "  PREFIX VARCHAR(16) DEFAULT NULL, ";
        $sql.= "  ALLOWED_TYPES TINYINT(3) DEFAULT NULL, ";
        $sql.= "  POSITION MEDIUMINT(8) UNSIGNED DEFAULT '0', ";
        $sql.= "  PERM INT(32) UNSIGNED DEFAULT NULL, ";
        $sql.= "  PRIMARY KEY (FID)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table FOLDER');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}FORUM_LINKS` (";
        $sql.= "  LID SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,";
        $sql.= "  POS MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  URI VARCHAR(255) DEFAULT NULL,";
        $sql.= "  TITLE VARCHAR(64) DEFAULT NULL,";
        $sql.= "  PRIMARY KEY  (LID)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table FORUM_LINKS');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}LINKS` (";
        $sql.= "  LID SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,";
        $sql.= "  FID SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  UID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  URI VARCHAR(255) NOT NULL DEFAULT '',";
        $sql.= "  TITLE VARCHAR(64) NOT NULL DEFAULT '',";
        $sql.= "  DESCRIPTION TEXT NOT NULL,";
        $sql.= "  CREATED DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',";
        $sql.= "  VISIBLE CHAR(1) NOT NULL DEFAULT 'N',";
        $sql.= "  CLICKS MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  PRIMARY KEY  (LID),";
        $sql.= "  KEY FID (FID)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table LINKS');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}LINKS_COMMENT` (";
        $sql.= "  CID SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,";
        $sql.= "  LID SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  UID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  CREATED DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',";
        $sql.= "  COMMENT TEXT NOT NULL,";
        $sql.= "  PRIMARY KEY  (CID)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table LINKS_COMMENT');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}LINKS_FOLDERS` (";
        $sql.= "  FID SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,";
        $sql.= "  PARENT_FID SMALLINT(5) UNSIGNED DEFAULT '1',";
        $sql.= "  NAME VARCHAR(32) NOT NULL DEFAULT '',";
        $sql.= "  VISIBLE CHAR(1) NOT NULL DEFAULT '',";
        $sql.= "  PRIMARY KEY  (FID),";
        $sql.= "  KEY PARENT_FID (PARENT_FID)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table LINKS_FOLDERS');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}LINKS_VOTE` (";
        $sql.= "  LID SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  UID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  RATING SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  VOTED DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',";
        $sql.= "  PRIMARY KEY  (LID,UID)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table LINKS_VOTE');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}POLL` (";
        $sql.= "  `TID` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  `CLOSES` DATETIME DEFAULT NULL,";
        $sql.= "  `CHANGEVOTE` TINYINT(1) NOT NULL DEFAULT '1',";
        $sql.= "  `POLLTYPE` TINYINT(1) NOT NULL DEFAULT '0',";
        $sql.= "  `SHOWRESULTS` TINYINT(1) NOT NULL DEFAULT '1',";
        $sql.= "  `VOTETYPE` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  `OPTIONTYPE` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  `ALLOWGUESTS` TINYINT(1) NOT NULL DEFAULT '0',";
        $sql.= "  PRIMARY KEY (`TID`)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table POLL');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}POLL_QUESTIONS` (";
        $sql.= "  `TID` MEDIUMINT(8) UNSIGNED NOT NULL,";
        $sql.= "  `QUESTION_ID` MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT,";
        $sql.= "  `QUESTION` VARCHAR(255) NOT NULL,";
        $sql.= "  `ALLOW_MULTI` CHAR(1) NOT NULL DEFAULT 'N',";
        $sql.= "  PRIMARY KEY (`TID`,`QUESTION_ID`)";
        $sql.= ") ENGINE=MYISAM DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table POLL_QUESTIONS');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}POLL_VOTES` (";
        $sql.= "  `TID` MEDIUMINT(8) UNSIGNED NOT NULL,";
        $sql.= "  `QUESTION_ID` MEDIUMINT(8) UNSIGNED NOT NULL,";
        $sql.= "  `OPTION_ID` MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT,";
        $sql.= "  `OPTION_NAME` VARCHAR(255) NOT NULL,";
        $sql.= "  PRIMARY KEY (`TID`,`QUESTION_ID`,`OPTION_ID`)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table POLL_VOTES');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}POST` (";
        $sql.= "  TID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  PID MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT,";
        $sql.= "  REPLY_TO_PID MEDIUMINT(8) UNSIGNED DEFAULT NULL,";
        $sql.= "  FROM_UID MEDIUMINT(8) UNSIGNED DEFAULT NULL,";
        $sql.= "  TO_UID MEDIUMINT(8) UNSIGNED DEFAULT NULL,";
        $sql.= "  VIEWED DATETIME DEFAULT NULL,";
        $sql.= "  CREATED DATETIME DEFAULT NULL,";
        $sql.= "  STATUS TINYINT(4) DEFAULT '0',";
        $sql.= "  APPROVED DATETIME DEFAULT NULL,";
        $sql.= "  APPROVED_BY MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  EDITED DATETIME DEFAULT NULL,";
        $sql.= "  EDITED_BY MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  IPADDRESS VARCHAR(255) DEFAULT NULL,";
        $sql.= "  MOVED_TID MEDIUMINT(8) UNSIGNED DEFAULT NULL,";
        $sql.= "  MOVED_PID MEDIUMINT(8) UNSIGNED DEFAULT NULL,";
        $sql.= "  PRIMARY KEY  (TID,PID),";
        $sql.= "  KEY TO_UID (TO_UID),";
        $sql.= "  KEY FROM_UID (FROM_UID),";
        $sql.= "  KEY IPADDRESS (IPADDRESS, FROM_UID),";
        $sql.= "  KEY APPROVED (APPROVED),";
        $sql.= "  KEY CREATED (CREATED)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!($result = db_query($sql, $db_forum_create))) {
            throw new Exception('Failed to create table POST');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}POST_CONTENT` (";
        $sql.= "  TID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  PID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  CONTENT TEXT,";
        $sql.= "  PRIMARY KEY  (TID,PID),";
        $sql.= "  FULLTEXT KEY CONTENT (CONTENT)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table POST_CONTENT');
        }
        
        $sql = "CREATE TABLE `{$forum_table_prefix}POST_SEARCH_ID` (";
        $sql.= "  SID MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT,";
        $sql.= "  TID MEDIUMINT(8) UNSIGNED NOT NULL,";
        $sql.= "  PID MEDIUMINT(8) UNSIGNED NOT NULL,";
        $sql.= "  INDEXED DATETIME DEFAULT NULL,";
        $sql.= "  PRIMARY KEY  (SID,TID,PID)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table POST_CONTENT');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}PROFILE_ITEM` (";
        $sql.= "  PIID MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT,";
        $sql.= "  PSID MEDIUMINT(8) UNSIGNED DEFAULT NULL,";
        $sql.= "  NAME VARCHAR(64) DEFAULT NULL,";
        $sql.= "  TYPE TINYINT(3) UNSIGNED DEFAULT '0',";
        $sql.= "  OPTIONS TEXT NOT NULL, ";
        $sql.= "  POSITION MEDIUMINT(3) UNSIGNED DEFAULT '0',";
        $sql.= "  PRIMARY KEY  (PIID),";
        $sql.= "  KEY PSID (PSID)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table PROFILE_ITEM');
        }

        $sql = "INSERT INTO `{$forum_table_prefix}PROFILE_ITEM` ";
        $sql.= "(PSID, NAME, TYPE, OPTIONS, POSITION) ";
        $sql.= "VALUES (1, 'Location', 0, '', 1)";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create location profile item');
        }

        $sql = "INSERT INTO `{$forum_table_prefix}PROFILE_ITEM` ";
        $sql.= "(PSID, NAME, TYPE, OPTIONS, POSITION) ";
        $sql.= "VALUES (1, 'Age', 0, '', 2)";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create age profile item');
        }

        $sql = "INSERT INTO `{$forum_table_prefix}PROFILE_ITEM` ";
        $sql.= "(PSID, NAME, TYPE, OPTIONS, POSITION) VALUES ";
        $sql.= "(1, 'Gender', 5, 'Male\nFemale\nUnspecified', 3)";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create gender profile item');
        }

        $sql = "INSERT INTO `{$forum_table_prefix}PROFILE_ITEM` ";
        $sql.= "(PSID, NAME, TYPE, OPTIONS, POSITION) ";
        $sql.= "VALUES (1, 'Quote', 0, '', 4)";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create quote profile item');
        }

        $sql = "INSERT INTO `{$forum_table_prefix}PROFILE_ITEM` ";
        $sql.= "(PSID, NAME, TYPE, OPTIONS, POSITION) ";
        $sql.= "VALUES (1, 'Occupation', 0, '', 5)";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create occupation profile item');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}PROFILE_SECTION` (";
        $sql.= "  PSID MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT,";
        $sql.= "  NAME VARCHAR(64) DEFAULT NULL,";
        $sql.= "  POSITION MEDIUMINT(3) UNSIGNED DEFAULT '0',";
        $sql.= "  PRIMARY KEY  (PSID)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table PROFILE_SECTION');
        }

        $sql = "INSERT INTO `{$forum_table_prefix}PROFILE_SECTION` ";
        $sql.= "(NAME, POSITION) VALUES ('Personal', 1)";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create first profile section.');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}RSS_FEEDS` (";
        $sql.= "  RSSID MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT,";
        $sql.= "  NAME VARCHAR(255) NOT NULL DEFAULT '',";
        $sql.= "  UID MEDIUMINT(8) UNSIGNED DEFAULT NULL,";
        $sql.= "  FID MEDIUMINT(8) UNSIGNED DEFAULT NULL,";
        $sql.= "  URL VARCHAR(255) DEFAULT NULL,";
        $sql.= "  PREFIX VARCHAR(16) DEFAULT NULL,";
        $sql.= "  FREQUENCY MEDIUMINT(8) UNSIGNED DEFAULT NULL,";
        $sql.= "  LAST_RUN DATETIME DEFAULT NULL,";
        $sql.= "  MAX_ITEM_COUNT MEDIUMINT(8) UNSIGNED DEFAULT NULL, ";
        $sql.= "  PRIMARY KEY  (RSSID)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table RSS_FEEDS');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}RSS_HISTORY` (";
        $sql.= "  RSSID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  LINK VARCHAR(255) DEFAULT NULL,";
        $sql.= "  KEY RSSID (RSSID)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table RSS_HISTORY');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}STATS` (";
        $sql.= "  ID MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT,";
        $sql.= "  MOST_USERS_DATE DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',";
        $sql.= "  MOST_USERS_COUNT MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  MOST_POSTS_DATE DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',";
        $sql.= "  MOST_POSTS_COUNT MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  PRIMARY KEY  (ID),";
        $sql.= "  KEY MOST_POSTS_COUNT (MOST_POSTS_COUNT), ";
        $sql.= "  KEY MOST_USERS_COUNT (MOST_USERS_COUNT) ";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table STATS');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}THREAD` (";
        $sql.= "  TID MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT, ";
        $sql.= "  FID MEDIUMINT(8) UNSIGNED DEFAULT NULL, ";
        $sql.= "  BY_UID MEDIUMINT(8) UNSIGNED DEFAULT NULL, ";
        $sql.= "  TITLE VARCHAR(64) DEFAULT NULL, ";
        $sql.= "  LENGTH MEDIUMINT(8) UNSIGNED DEFAULT NULL, ";
        $sql.= "  UNREAD_PID MEDIUMINT(8) UNSIGNED DEFAULT NULL, ";
        $sql.= "  POLL_FLAG CHAR(1) DEFAULT NULL, ";
        $sql.= "  CREATED DATETIME DEFAULT NULL, ";
        $sql.= "  MODIFIED DATETIME DEFAULT NULL, ";
        $sql.= "  CLOSED DATETIME DEFAULT NULL, ";
        $sql.= "  STICKY CHAR(1) DEFAULT NULL, ";
        $sql.= "  STICKY_UNTIL DATETIME DEFAULT NULL, ";
        $sql.= "  ADMIN_LOCK DATETIME DEFAULT NULL, ";
        $sql.= "  DELETED CHAR(1) NOT NULL DEFAULT 'N', ";
        $sql.= "  PRIMARY KEY (TID), ";
        $sql.= "  KEY STICKY (STICKY, MODIFIED, FID, LENGTH, DELETED), ";
        $sql.= "  KEY MODIFIED (MODIFIED, FID, LENGTH, DELETED), ";
        $sql.= "  FULLTEXT KEY TITLE (TITLE) ";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table THREAD');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}THREAD_STATS` (";
        $sql.= "  TID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  VIEWCOUNT MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  PRIMARY KEY  (TID)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table THREAD_STATS');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}THREAD_TRACK` (";
        $sql.= "  TID MEDIUMINT(8) NOT NULL DEFAULT '0', ";
        $sql.= "  NEW_TID MEDIUMINT(8) NOT NULL DEFAULT '0', ";
        $sql.= "  CREATED DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00', ";
        $sql.= "  TRACK_TYPE TINYINT(4) NOT NULL DEFAULT '0', ";
        $sql.= "  PRIMARY KEY (TID, NEW_TID), ";
        $sql.= "  KEY NEW_TID (NEW_TID)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table THREAD_TRACK');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}USER_FOLDER` (";
        $sql.= "  UID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0', ";
        $sql.= "  FID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0', ";
        $sql.= "  INTEREST TINYINT(4) DEFAULT '0', ";
        $sql.= "  PRIMARY KEY (UID, FID)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table USER_FOLDER');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}USER_PEER` (";
        $sql.= "  UID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0', ";
        $sql.= "  PEER_UID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0', ";
        $sql.= "  RELATIONSHIP TINYINT(4) DEFAULT NULL, ";
        $sql.= "  PEER_NICKNAME VARCHAR(32) DEFAULT NULL, ";
        $sql.= "  PRIMARY KEY (UID, PEER_UID)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table USER_PEER');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}USER_POLL_VOTES` (";
        $sql.= "  `VOTE_ID` MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT,";
        $sql.= "  `TID` MEDIUMINT(8) UNSIGNED NOT NULL,";
        $sql.= "  `QUESTION_ID` MEDIUMINT(8) UNSIGNED NOT NULL,";
        $sql.= "  `OPTION_ID` MEDIUMINT(8) UNSIGNED NOT NULL,";
        $sql.= "  `UID` MEDIUMINT(8) UNSIGNED NOT NULL,";
        $sql.= "  `VOTED` DATETIME NOT NULL,";
        $sql.= "  PRIMARY KEY (`VOTE_ID`),";
        $sql.= "  KEY `TID` (`TID`),";
        $sql.= "  KEY `QUESTION_ID` (`QUESTION_ID`),";
        $sql.= "  KEY `OPTION_ID` (`OPTION_ID`),";
        $sql.= "  KEY `UID` (`UID`)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table USER_POLL_VOTES');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}USER_PREFS` (";
        $sql.= "  UID MEDIUMINT(8) UNSIGNED NOT NULL,";
        $sql.= "  HOMEPAGE_URL VARCHAR(255) DEFAULT NULL,";
        $sql.= "  PIC_URL VARCHAR(255) DEFAULT NULL,";
        $sql.= "  EMAIL_NOTIFY CHAR(1) DEFAULT NULL,";
        $sql.= "  MARK_AS_OF_INT CHAR(1) DEFAULT NULL,";
        $sql.= "  POSTS_PER_PAGE CHAR(3) DEFAULT NULL,";
        $sql.= "  FONT_SIZE CHAR(2) DEFAULT NULL,";
        $sql.= "  STYLE VARCHAR(255) DEFAULT NULL,";
        $sql.= "  EMOTICONS VARCHAR(255) DEFAULT NULL,";
        $sql.= "  VIEW_SIGS CHAR(1) DEFAULT NULL,";
        $sql.= "  START_PAGE CHAR(3) DEFAULT NULL,";
        $sql.= "  LANGUAGE VARCHAR(32) DEFAULT NULL,";
        $sql.= "  SHOW_STATS CHAR(1) DEFAULT NULL,";
        $sql.= "  IMAGES_TO_LINKS CHAR(1) DEFAULT NULL,";
        $sql.= "  USE_WORD_FILTER CHAR(1) DEFAULT NULL,";
        $sql.= "  USE_ADMIN_FILTER CHAR(1) DEFAULT NULL,";
        $sql.= "  SHOW_THUMBS CHAR(2) DEFAULT NULL,";
        $sql.= "  ENABLE_WIKI_WORDS CHAR(1) DEFAULT NULL,";
        $sql.= "  USE_MOVER_SPOILER CHAR(1) DEFAULT NULL,";
        $sql.= "  USE_LIGHT_MODE_SPOILER CHAR(1) DEFAULT NULL,";
        $sql.= "  USE_OVERFLOW_RESIZE CHAR(1) DEFAULT NULL,";
        $sql.= "  PIC_AID VARCHAR(32) DEFAULT NULL,";
        $sql.= "  AVATAR_URL VARCHAR(255) DEFAULT NULL,";
        $sql.= "  AVATAR_AID VARCHAR(32) DEFAULT NULL,";
        $sql.= "  REPLY_QUICK CHAR(1) DEFAULT NULL,";
        $sql.= "  THREADS_BY_FOLDER CHAR(1) DEFAULT NULL,";
        $sql.= "  THREAD_LAST_PAGE CHAR(1) DEFAULT NULL,";
        $sql.= "  LEFT_FRAME_WIDTH SMALLINT(4) UNSIGNED DEFAULT NULL,";
        $sql.= "  SHOW_AVATARS CHAR(1) DEFAULT NULL,";
        $sql.= "  SHOW_SHARE_LINKS CHAR(1) DEFAULT NULL,";
        $sql.= "  PRIMARY KEY (UID)";
        $sql.= ") ENGINE=MYISAM DEFAULT CHARSET=utf8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table USER_PREFS');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}USER_PROFILE` (";
        $sql.= "  UID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  PIID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  ENTRY VARCHAR(255) DEFAULT NULL,";
        $sql.= "  PRIVACY TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  PRIMARY KEY  (UID,PIID)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table USER_PROFILE');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}USER_SIG` (";
        $sql.= "  UID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  CONTENT TEXT,";
        $sql.= "  HTML CHAR(1) DEFAULT NULL,";
        $sql.= "  PRIMARY KEY  (UID)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table USER_SIG');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}USER_THREAD` (";
        $sql.= "  UID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  TID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  LAST_READ MEDIUMINT(8) UNSIGNED DEFAULT NULL,";
        $sql.= "  LAST_READ_AT DATETIME DEFAULT NULL,";
        $sql.= "  INTEREST TINYINT(4) DEFAULT NULL,";
        $sql.= "  PRIMARY KEY  (UID,TID),";
        $sql.= "  KEY TID (TID),";
        $sql.= "  KEY LAST_READ (LAST_READ),";
        $sql.= "  KEY INTEREST (INTEREST)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table USER_THREAD');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}USER_TRACK` (";
        $sql.= "  UID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',";
        $sql.= "  DDKEY DATETIME DEFAULT NULL,";
        $sql.= "  LAST_POST DATETIME DEFAULT NULL,";
        $sql.= "  LAST_SEARCH DATETIME DEFAULT NULL,";
        $sql.= "  LAST_SEARCH_KEYWORDS TEXT DEFAULT NULL,";
        $sql.= "  LAST_SEARCH_SORT_BY TINYINT(3) UNSIGNED DEFAULT NULL, ";
        $sql.= "  LAST_SEARCH_SORT_DIR TINYINT(3) UNSIGNED DEFAULT NULL, ";
        $sql.= "  POST_COUNT MEDIUMINT(8) UNSIGNED DEFAULT NULL,";
        $sql.= "  USER_TIME_BEST DATETIME DEFAULT NULL,";
        $sql.= "  USER_TIME_TOTAL DATETIME DEFAULT NULL,";
        $sql.= "  USER_TIME_UPDATED DATETIME DEFAULT NULL,";
        $sql.= "  PRIMARY KEY  (UID)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table USER_TRACK');
        }

        $sql = "CREATE TABLE `{$forum_table_prefix}WORD_FILTER` (";
        $sql.= "  UID MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0', ";
        $sql.= "  FID MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT, ";
        $sql.= "  FILTER_NAME VARCHAR(255) NOT NULL DEFAULT '', ";
        $sql.= "  MATCH_TEXT TEXT NOT NULL, ";
        $sql.= "  REPLACE_TEXT TEXT NOT NULL, ";
        $sql.= "  FILTER_TYPE TINYINT(3) UNSIGNED NOT NULL DEFAULT '0', ";
        $sql.= "  FILTER_ENABLED TINYINT(3) UNSIGNED NOT NULL DEFAULT '0', ";
        $sql.= "  PRIMARY KEY (UID, FID)";
        $sql.= ") ENGINE=MYISAM  DEFAULT CHARSET=UTF8";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create table WORD_FILTER');
        }

        // Save Webtag, Database name and Access Level.
        $sql = "INSERT INTO FORUMS (WEBTAG, OWNER_UID, DATABASE_NAME, ACCESS_LEVEL) ";
        $sql.= "VALUES ('$webtag', '$owner_uid', '$database_name', $access)";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create FORUMS record');
        }

        // Get the new FID so we can save the settings
        if (!$forum_fid = db_insert_id($db_forum_create)) {
            throw new Exception('Failed to get new forum fid');
        }

        // Create General Folder
        $sql = "INSERT INTO `{$forum_table_prefix}FOLDER` (TITLE, CREATED, MODIFIED, ALLOWED_TYPES, POSITION, PERM) ";
        $sql.= "VALUES ('General', NOW(), NOW(), 3, 1, 14588)";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create first folder');
        }

        // Add some default forum links
        $sql = "INSERT INTO `{$forum_table_prefix}FORUM_LINKS` (POS, TITLE, URI) ";
        $sql.= "VALUES (2, 'Project Beehive Forum Home', 'http://www.beehiveforum.co.uk/')";
        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create Beehive Forum link');
        }

        $sql = "INSERT INTO `{$forum_table_prefix}FORUM_LINKS` (POS, TITLE, URI) ";
        $sql.= "VALUES (3, 'Project Beehive Forum on Facebook', 'http://www.facebook.com/pages/Project-Beehive-Forum/100468551205')";
        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create Beehive Forum Facebook link');
        }

        $sql = "INSERT INTO `{$forum_table_prefix}FORUM_LINKS` (POS, TITLE, URI) ";
        $sql.= "VALUES (2, 'Teh Forum', 'http://www.tehforum.co.uk/forum/')";
        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create Teh Forum forum link');
        }

        // Create user permissions for forum leader
        if (!perm_update_user_forum_permissions($forum_fid, $owner_uid, USER_PERM_ADMIN_TOOLS | USER_PERM_FOLDER_MODERATE)) {
            throw new Exception('Failed to set owner forum permissions');
        }

        // Create 'Welcome' Thread
        $sql = "INSERT INTO `{$forum_table_prefix}THREAD` (FID, BY_UID, TITLE, LENGTH, ";
        $sql.= "POLL_FLAG, CREATED, MODIFIED, CLOSED, STICKY, STICKY_UNTIL, ADMIN_LOCK) ";
        $sql.= "VALUES (1, '$owner_uid', 'Welcome', 1, 'N', CAST('$current_datetime' AS DATETIME), ";
        $sql.= "CAST('$current_datetime' AS DATETIME), NULL, 'N', NULL, NULL)";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create first thread');
        }

        // Get the Thread ID. It should be 1, but just in case.
        if (!$new_tid = db_insert_id($db_forum_create)) {
            throw new Exception('Failed to get first thread tid');
        }

        // Create the first post in the thread. Make it appear to be from
        // the Owner UID.
        $sql = "INSERT INTO `{$forum_table_prefix}POST` (TID, REPLY_TO_PID, FROM_UID, TO_UID, VIEWED, ";
        $sql.= "CREATED, STATUS, APPROVED, APPROVED_BY, EDITED, EDITED_BY, IPADDRESS) ";
        $sql.= "VALUES ('$new_tid', 0, '$owner_uid', 0, NULL, CAST('$current_datetime' AS DATETIME), ";
        $sql.= "0, CAST('$current_datetime' AS DATETIME), '$owner_uid', NULL, 0, '')";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create first post');
        }

        // Get the Post ID. Again should be 1, but trying to be tidy here.
        if (!$new_pid = db_insert_id($db_forum_create)) {
            throw new Exception('Fauled to fetch new post pid');
        }

        // First Post content.
        $sql = "INSERT INTO `{$forum_table_prefix}POST_CONTENT` (TID, PID, CONTENT) ";
        $sql.= "VALUES ('$new_tid', '$new_pid', 'Welcome to your new Beehive Forum')";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create first post content');
        }
        
        // First Post search ID
        $sql = "INSERT INTO `{$forum_table_prefix}POST_SEARCH_ID` (TID, PID) ";
        $sql.= "VALUES ('$new_tid', '$new_pid')";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create first post search index');
        }

        // Create Top Level Links Folder
        $sql = "INSERT INTO `{$forum_table_prefix}LINKS_FOLDERS` ";
        $sql.= "(PARENT_FID, NAME, VISIBLE) VALUES (NULL, 'Top Level', 'Y')";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to create top level links folder');
        }

        // Store Forum settings
        $forum_settings = array('wiki_integration_uri'    => 'http://en.wikipedia.org/wiki/[WikiWord]',
                                'enable_wiki_quick_links' => 'Y',
                                'enable_wiki_integration' => 'N',
                                'minimum_post_frequency'  => '0',
                                'maximum_post_length'     => '6226',
                                'post_edit_time'          => '0',
                                'allow_post_editing'      => 'Y',
                                'require_post_approval'   => 'N',
                                'forum_dl_saving'         => 'Y',
                                'forum_timezone'          => '27',
                                'default_language'        => 'en',
                                'default_emoticons'       => 'default',
                                'default_style'           => 'default',
                                'forum_keywords'          => 'A Beehive Forum, Beehive Forum, Project Beehive Forum',
                                'forum_desc'              => 'A Beehive Forum',
                                'forum_email'             => 'admin@beehiveforum.co.uk',
                                'forum_name'              => $forum_name,
                                'show_links'              => 'Y',
                                'allow_polls'             => 'Y',
                                'show_stats'              => 'Y',
                                'allow_search_spidering'  => 'Y',
                                'guest_account_enabled'   => 'Y',
                                'forum_links_top_link'    => 'Forum Links:');

        foreach ($forum_settings as $setting_name => $setting_value) {

            $setting_name = db_escape_string($setting_name);
            $setting_value = db_escape_string($setting_value);

            $sql = "INSERT INTO FORUM_SETTINGS (FID, SNAME, SVALUE) ";
            $sql.= "VALUES ($forum_fid, '$setting_name', '$setting_value')";

            if (!@db_query($sql, $db_forum_create)) {
                throw new Exception('Failed to save forum settings');
            }
        }

        // Make sure at least the current user can access the forum
        // even if its not protected.
        $sql = "INSERT INTO USER_FORUM (UID, FID, ALLOWED) VALUES('$owner_uid', $forum_fid, 1)";

        if (!@db_query($sql, $db_forum_create)) {
            throw new Exception('Failed to set user access permissions');
        }

    } catch (Exception $e) {

        $error_str = $e->getMessage();

        forum_delete_tables($webtag, $database_name);

        return false;
    }

    return $forum_fid;
}

function forum_update($fid, $forum_name, $owner_uid, $access_level)
{
    if (!$db_forum_update = db_connect()) return false;

    if (!is_numeric($fid)) return false;

    if (!is_numeric($owner_uid) || $owner_uid < 1) return false;

    if (!is_numeric($access_level)) return false;

    $forum_name = db_escape_string($forum_name);

    if (session_check_perm(USER_PERM_FORUM_TOOLS, 0)) {

        $sql = "UPDATE LOW_PRIORITY FORUMS SET ACCESS_LEVEL = '$access_level', ";
        $sql.= "OWNER_UID = '$owner_uid' WHERE FID = '$fid'";

        if (!db_query($sql, $db_forum_update)) return false;

        $sql = "INSERT IGNORE INTO FORUM_SETTINGS (FID, SNAME, SVALUE) ";
        $sql.= "VALUES ('$fid', 'forum_name', '$forum_name') ";
        $sql.= "ON DUPLICATE KEY UPDATE SVALUE = VALUES(SVALUE)";

        if (!db_query($sql, $db_forum_update)) return false;

        return true;
    }

    return false;
}

function forum_delete($fid)
{
    if (session_check_perm(USER_PERM_FORUM_TOOLS, 0)) {

        if (!$db_forum_delete = db_connect()) return false;

        if (!is_numeric($fid)) return false;

        $sql = "SELECT WEBTAG, DATABASE_NAME FROM FORUMS WHERE FID = '$fid'";

        if (!$result = db_query($sql, $db_forum_delete)) return false;

        if (db_num_rows($result) > 0) {

            list($webtag, $database_name) = db_fetch_array($result, DB_RESULT_NUM);

            $sql = "DELETE QUICK FROM FORUMS WHERE FID = '$fid'";

            if (!db_query($sql, $db_forum_delete)) return false;

            $sql = "DELETE QUICK FROM FORUM_SETTINGS WHERE FID = '$fid'";

            if (!db_query($sql, $db_forum_delete)) return false;

            $sql = "DELETE QUICK FROM SEARCH_RESULTS WHERE FID = '$fid'";

            if (!db_query($sql, $db_forum_delete)) return false;

            $sql = "DELETE QUICK FROM VISITOR_LOG WHERE FORUM = '$fid'";

            if (!db_query($sql, $db_forum_delete)) return false;

            $sql = "DELETE QUICK FROM POST_ATTACHMENT_FILES ";
            $sql.= "WHERE AID IN (SELECT AID FROM POST_ATTACHMENT_IDS ";
            $sql.= "WHERE FID = '$fid')";

            if (!db_query($sql, $db_forum_delete)) return false;

            $sql = "DELETE QUICK FROM POST_ATTACHMENT_IDS WHERE FID = '$fid'";

            if (!db_query($sql, $db_forum_delete)) return false;

            $sql = "DELETE QUICK FROM GROUP_PERMS WHERE FORUM = '$fid'";

            if (!db_query($sql, $db_forum_delete)) return false;

            if (forum_delete_tables($webtag, $database_name)) return true;
        }
    }

    return false;
}

function forum_delete_tables($webtag, $database_name)
{
    // Ensure the variables we've been given are valid
    if (!preg_match("/^[A-Z0-9_]+$/Du", $webtag)) return false;
    if (!preg_match("/^[A-Z0-9_]+$/Diu", $database_name)) return false;

    // Only users with acces to the forum tools can create / delete forums.
    if (session_check_perm(USER_PERM_FORUM_TOOLS, 0)) {

        if (!$db_forum_delete_tables = db_connect()) return false;

        $forum_table_prefix = install_format_table_prefix($database_name, $webtag);

        $table_array = array('ADMIN_LOG',       'BANNED',       'FOLDER',
                             'FORUM_LINKS',     'GROUPS',       'GROUP_PERMS',
                             'GROUP_USERS',     'LINKS',        'LINKS_COMMENT',
                             'LINKS_FOLDERS',   'LINKS_VOTE',   'POLL',
                             'POLL_VOTES',      'POST',         'POST_CONTENT',
                             'POST_SEARCH_ID',  'PROFILE_ITEM', 'PROFILE_SECTION', 
                             'RSS_FEEDS',       'RSS_HISTORY',  'STATS',
                             'THREAD',          'THREAD_STATS', 'THREAD_TRACK',
                             'USER_TRACK',      'USER_FOLDER',  'USER_PEER',
                             'USER_POLL_VOTES', 'USER_PREFS',   'USER_PROFILE',    
                             'USER_SIG',        'USER_THREAD',  'VISITOR_LOG',
                             'WORD_FILTER');

        foreach ($table_array as $table_name) {

            $sql = "DROP TABLE IF EXISTS `{$forum_table_prefix}{$table_name}`";

            if (!db_query($sql, $db_forum_delete_tables)) return false;
        }

        return true;
    }

    return false;
}

function forum_update_access($fid, $access)
{
    if (!is_numeric($fid)) return false;
    if (!is_numeric($access)) return false;

    if (session_check_perm(USER_PERM_ADMIN_TOOLS, 0) || session_check_perm(USER_PERM_FORUM_TOOLS, 0)) {

        if (($uid = session_get_value('UID')) === false) return false;

        if (!$db_forum_update_access = db_connect()) return false;

        $sql = "UPDATE LOW_PRIORITY FORUMS SET ACCESS_LEVEL = '$access' ";
        $sql.= "WHERE FID = '$fid'";

        if (!db_query($sql, $db_forum_update_access)) return false;

        $sql = "INSERT INTO USER_FORUM (UID, FID, ALLOWED) ";
        $sql.= "VALUES ('$uid', '$fid', 1) ON DUPLICATE KEY ";
        $sql.= "UPDATE ALLOWED = VALUES(ALLOWED)";

        if (!db_query($sql, $db_forum_update_access)) return false;

        return true;
    }

    return false;
}

function forum_update_password($fid, $password)
{
    if (!$db_forum_update_password = db_connect()) return false;

    if (!is_numeric($fid)) return false;

    if (session_check_perm(USER_PERM_ADMIN_TOOLS, 0) || session_check_perm(USER_PERM_FORUM_TOOLS, 0)) {

        $password = db_escape_string(md5($password));

        $sql = "UPDATE LOW_PRIORITY FORUMS SET FORUM_PASSWD = '$password' ";
        $sql.= "WHERE FID = '$fid'";

        if (!db_query($sql, $db_forum_update_password)) return false;

        return true;
    }

    return false;
}

function forum_get($fid)
{
    if (!is_numeric($fid)) return false;

    if (session_check_perm(USER_PERM_ADMIN_TOOLS, 0)) {

        if (!$db_forum_get = db_connect()) return false;

        $sql = "SELECT FID, WEBTAG, OWNER_UID, DATABASE_NAME, DEFAULT_FORUM, ";
        $sql.= "ACCESS_LEVEL, FORUM_PASSWD FROM FORUMS WHERE FID = '$fid'";

        if (!$result = db_query($sql, $db_forum_get)) return false;

        if (db_num_rows($result) > 0) {

            $forum_get_array = db_fetch_array($result);
            $forum_get_array['FORUM_SETTINGS'] = array();

            if (isset($forum_get_array['OWNER_UID']) && $forum_get_array['OWNER_UID'] > 0) {

                if (($forum_leader = user_get_logon($forum_get_array['OWNER_UID']))) {

                    $forum_get_array['FORUM_SETTINGS']['forum_leader'] = $forum_leader;
                }
            }

            $sql = "SELECT SNAME, SVALUE FROM FORUM_SETTINGS WHERE FID = '$fid'";

            if (!$result = db_query($sql, $db_forum_get)) return false;

            while (($forum_data = db_fetch_array($result))) {
                $forum_get_array['FORUM_SETTINGS'][$forum_data['SNAME']] = $forum_data['SVALUE'];
            }

            return $forum_get_array;
        }
    }

    return false;
}

function forum_get_permissions($fid, $offset = 0)
{
    if (!$db_forum_get_permissions = db_connect()) return false;

    if (!is_numeric($fid)) return false;
    if (!is_numeric($offset)) $offset = 0;

    $perms_user_array = array();

    $sql = "SELECT SQL_CALC_FOUND_ROWS USER.UID, USER.LOGON, USER.NICKNAME FROM USER USER ";
    $sql.= "LEFT JOIN USER_FORUM USER_FORUM ON (USER_FORUM.UID = USER.UID) ";
    $sql.= "WHERE USER_FORUM.FID = '$fid' AND USER_FORUM.ALLOWED = 1 ";
    $sql.= "LIMIT $offset, 20";

    if (!$result = db_query($sql, $db_forum_get_permissions)) return false;

    // Fetch the number of total results
    $sql = "SELECT FOUND_ROWS() AS ROW_COUNT";

    if (!$result_count = db_query($sql, $db_forum_get_permissions)) return false;

    list($perms_user_count) = db_fetch_array($result_count, DB_RESULT_NUM);

    if (db_num_rows($result) > 0) {

        while (($user_data = db_fetch_array($result))) {

            if (isset($user_data['LOGON']) && isset($user_data['PEER_NICKNAME'])) {
                if (!is_null($user_data['PEER_NICKNAME']) && strlen($user_data['PEER_NICKNAME']) > 0) {
                    $user_data['NICKNAME'] = $user_data['PEER_NICKNAME'];
                }
            }

            if (!isset($user_data['LOGON'])) $user_data['LOGON'] = gettext("Unknown user");
            if (!isset($user_data['NICKNAME'])) $user_data['NICKNAME'] = "";

            $perms_user_array[] = $user_data;
        }

    }else if ($perms_user_count > 0) {

        $offset = floor(($perms_user_count - 1) / 10) * 10;
        return forum_get_permissions($fid, $offset);
    }

    return array('user_count' => $perms_user_count,
                 'user_array' => $perms_user_array);
}

function forum_update_default($fid)
{
    if (!is_numeric($fid)) return false;

    if (!$db_forum_get_permissions = db_connect()) return false;

    $sql = "UPDATE LOW_PRIORITY FORUMS SET DEFAULT_FORUM = 0";

    if (!db_query($sql, $db_forum_get_permissions)) return false;

    if ($fid > 0) {

        $sql = "UPDATE LOW_PRIORITY FORUMS SET DEFAULT_FORUM = 1 WHERE FID = '$fid'";

        if (!db_query($sql, $db_forum_get_permissions)) return false;
    }

    return true;
}

function forum_search_array_clean($forum_search)
{
    return db_escape_string(trim(str_replace("%", "", $forum_search)));
}

function forum_search($forum_search, $offset, $sort_by, $sort_dir)
{
    if (!$db_forum_search = db_connect()) return false;

    if (!is_numeric($offset)) return false;

    $sort_by_array  = array('FORUM_NAME', 'FORUM_DESC', 'LAST_VISIT');
    $sort_dir_array = array('ASC', 'DESC');

    if (!in_array($sort_by, $sort_by_array)) $sort_by = 'LAST_VISIT';
    if (!in_array($sort_dir, $sort_dir_array)) $sort_dir = 'DESC';

    if (($uid = session_get_value('UID')) === false) return false;

    // Array to hold our forums in.
    $forums_array = array();

    if (strlen(trim($forum_search)) > 0) {

        $forum_search_array = explode(";", $forum_search);
        $forum_search_array = array_map('forum_search_array_clean', $forum_search_array);

        $forum_search_webtag = implode("%' OR FORUMS.WEBTAG LIKE '%", $forum_search_array);
        $forum_search_svalue = implode("%' OR FORUM_SETTINGS.SVALUE LIKE '%", $forum_search_array);

        $sql = "SELECT SQL_CALC_FOUND_ROWS CONCAT(FORUMS.DATABASE_NAME, '`.`', FORUMS.WEBTAG, '_') AS PREFIX, ";
        $sql.= "FORUM_SETTINGS_NAME.SVALUE AS FORUM_NAME, FORUM_SETTINGS_DESC.SVALUE AS FORUM_DESC, ";
        $sql.= "FORUMS.FID, FORUMS.WEBTAG, FORUMS.ACCESS_LEVEL, USER_FORUM.INTEREST FROM FORUMS ";
        $sql.= "LEFT JOIN FORUM_SETTINGS FORUM_SETTINGS ON (FORUM_SETTINGS.FID = FORUMS.FID) ";
        $sql.= "LEFT JOIN FORUM_SETTINGS FORUM_SETTINGS_NAME ON (FORUM_SETTINGS_NAME.FID = FORUMS.FID AND FORUM_SETTINGS_NAME.SNAME = 'forum_name') ";
        $sql.= "LEFT JOIN FORUM_SETTINGS FORUM_SETTINGS_DESC ON (FORUM_SETTINGS_DESC.FID = FORUMS.FID AND FORUM_SETTINGS_DESC.SNAME = 'forum_desc') ";
        $sql.= "LEFT JOIN USER_FORUM ON (USER_FORUM.FID = FORUMS.FID AND USER_FORUM.UID = '$uid') ";
        $sql.= "WHERE FORUMS.ACCESS_LEVEL > -1 AND (FORUMS.WEBTAG LIKE ";
        $sql.= "'%$forum_search_webtag%' OR FORUM_SETTINGS.SVALUE LIKE ";
        $sql.= "'%$forum_search_svalue%') GROUP BY FORUMS.FID ";
        $sql.= "ORDER BY $sort_by $sort_dir LIMIT $offset, 10";

        if (!$result_forums = db_query($sql, $db_forum_search)) return false;

        // Fetch the number of total results
        $sql = "SELECT FOUND_ROWS() AS ROW_COUNT";

        if (!$result_count = db_query($sql, $db_forum_search)) return false;

        list($forums_count) = db_fetch_array($result_count, DB_RESULT_NUM);

        if (db_num_rows($result_forums) > 0) {

            while (($forum_data = db_fetch_array($result_forums))) {

                $forum_fid = $forum_data['FID'];

                // Check the forum name is set. If it isn't set it to 'A Beehive Forum'
                if (!isset($forum_data['FORUM_NAME']) || strlen(trim($forum_data['FORUM_NAME'])) < 1) {
                    $forum_data['FORUM_NAME'] = "A Beehive Forum";
                }

                // Check the forum description is set.
                if (!isset($forum_data['FORUM_DESC']) || strlen(trim($forum_data['FORUM_DESC'])) < 1) {
                    $forum_data['FORUM_DESC'] = "";
                }

                // Check the LAST_VISIT column to make sure its OK.
                if (!isset($forum_data['LAST_VISIT']) || is_null($forum_data['LAST_VISIT'])) {
                    $forum_data['LAST_VISIT'] = 0;
                }

                // Unread cut-off stamp.
                $unread_cutoff_datetime = forum_get_unread_cutoff_datetime();

                // Get available folders for queries below
                $folders = folder_get_available_by_forum($forum_fid);

                // Get any unread messages
                if ($unread_cutoff_datetime !== false) {

                    $sql = "SELECT SUM(THREAD.LENGTH) - SUM(COALESCE(USER_THREAD.LAST_READ, 0)) AS UNREAD_MESSAGES ";
                    $sql.= "FROM `{$forum_data['PREFIX']}THREAD` THREAD LEFT JOIN `{$forum_data['PREFIX']}USER_THREAD` USER_THREAD ";
                    $sql.= "ON (USER_THREAD.TID = THREAD.TID AND USER_THREAD.UID = '$uid') WHERE THREAD.FID IN ($folders) ";
                    $sql.= "AND (THREAD.MODIFIED > CAST('$unread_cutoff_datetime' AS DATETIME)) ";

                    if (!$result_unread_count = db_query($sql, $db_forum_search)) return false;

                    list($unread_messages) = db_fetch_array($result_unread_count, DB_RESULT_NUM);

                    $forum_data['UNREAD_MESSAGES'] = $unread_messages;

                }else {

                    $forum_data['UNREAD_MESSAGES'] = 0;
                }

                // Total number of messages
                $sql = "SELECT SUM(THREAD.LENGTH) AS NUM_MESSAGES FROM `{$forum_data['PREFIX']}THREAD` THREAD ";
                $sql.= "WHERE THREAD.FID IN ($folders) ";

                if (!$result_messages_count = db_query($sql, $db_forum_search)) return false;

                $num_messages_data = db_fetch_array($result_messages_count);

                if (!isset($num_messages_data['NUM_MESSAGES']) || is_null($num_messages_data['NUM_MESSAGES'])) {
                    $forum_data['NUM_MESSAGES'] = 0;
                }else {
                    $forum_data['NUM_MESSAGES'] = $num_messages_data['NUM_MESSAGES'];
                }

                // Get unread to me message count
                $sql = "SELECT COUNT(POST.PID) AS UNREAD_TO_ME ";
                $sql.= "FROM `{$forum_data['PREFIX']}THREAD` THREAD ";
                $sql.= "LEFT JOIN `{$forum_data['PREFIX']}POST` POST ";
                $sql.= "ON (POST.TID = THREAD.TID) WHERE THREAD.FID IN ($folders) ";
                $sql.= "AND POST.TO_UID = '$uid' AND POST.VIEWED IS NULL ";

                if (!$result_unread_to_me = db_query($sql, $db_forum_search)) return false;

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

                $forums_array[] = $forum_data;
            }

        }else if ($forums_count > 0) {

            $offset = floor(($forums_count - 1) / 10) * 10;
            return forum_search($forum_search, $offset, $sort_by, $sort_dir);
        }
    }

    return array('forums_array' => $forums_array,
                 'forums_count' => $forums_count);
}

function forum_get_all_prefixes()
{
    if (!$db_forum_get_all_prefixes = db_connect()) return false;

    $sql = "SELECT CONCAT(DATABASE_NAME, '`.`', WEBTAG, '_') AS PREFIX, ";
    $sql.= "FID FROM FORUMS ";

    if (!$result = db_query($sql, $db_forum_get_all_prefixes)) return false;

    if (db_num_rows($result) > 0) {

        $prefix_array = array();

        while (($forum_data = db_fetch_array($result))) {
            $prefix_array[$forum_data['FID']] = $forum_data['PREFIX'];
        }

        return $prefix_array;
    }

    return false;
}

function forum_get_all_webtags()
{
    if (!$db_forum_get_all_webtags = db_connect()) return false;

    $sql = "SELECT FID, WEBTAG FROM FORUMS ";

    if (!$result = db_query($sql, $db_forum_get_all_webtags)) return false;

    if (db_num_rows($result) > 0) {

        $webtag_array = array();

        while (($forum_data = db_fetch_array($result))) {
            $webtag_array[$forum_data['FID']] = $forum_data['WEBTAG'];
        }

        return $webtag_array;
    }

    return false;
}

function forum_get_all_fids()
{
    if (!$db_forum_get_all_fids = db_connect()) return false;

    $sql = "SELECT FID FROM FORUMS";

    if (!$result = db_query($sql, $db_forum_get_all_fids)) return false;

    if (db_num_rows($result) > 0) {

        $fids_array = array();

        while (($forum_data = db_fetch_array($result))) {
            $fids_array[] = $forum_data['FID'];
        }

        return $fids_array;
    }

    return false;
}

function forum_update_last_visit($uid)
{
    if (!$db_forum_update_last_visit = db_connect()) return false;

    if (!is_numeric($uid)) return false;

    if (!$table_data = get_table_prefix()) return false;

    $current_datetime = date(MYSQL_DATETIME, time());

    $forum_fid = $table_data['FID'];

    if ($uid > 0) {

        $sql = "INSERT INTO USER_FORUM (UID, FID, LAST_VISIT) ";
        $sql.= "VALUES ('$uid', '$forum_fid', CAST('$current_datetime' AS DATETIME)) ";
        $sql.= "ON DUPLICATE KEY UPDATE LAST_VISIT = VALUES(LAST_VISIT)";

        if (!db_query($sql, $db_forum_update_last_visit)) return false;
    }

    return true;
}

function forums_get_available_dbs()
{
    if (!$db_forums_get_available_dbs = db_connect()) return false;

    $sql = "SHOW DATABASES";

    if (!$result = db_query($sql, $db_forums_get_available_dbs)) return false;

    if (db_num_rows($result) > 0) {

        $database_array = array();

        while (($database = db_fetch_array($result))) {

            if (!stristr('information_schema', $database['Database'])) {

                $database_array[$database['Database']] = $database['Database'];
            }
        }

        return $database_array;
    }

    return false;
}

function forums_get_available_count()
{
    if (!$db_forums_get_available_count = db_connect()) return false;

    if (($uid = session_get_value('UID')) === false) return 0;

    $sql = "SELECT COUNT(FORUMS.FID) FROM FORUMS FORUMS ";
    $sql.= "LEFT JOIN USER_FORUM USER_FORUM ON (USER_FORUM.FID = FORUMS.FID ";
    $sql.= "AND USER_FORUM.UID = '$uid') WHERE FORUMS.ACCESS_LEVEL = 0 ";
    $sql.= "OR FORUMS.ACCESS_LEVEL = 2 OR (FORUMS.ACCESS_LEVEL = 1 ";
    $sql.= "AND USER_FORUM.ALLOWED = 1) ";

    if (!$result = db_query($sql, $db_forums_get_available_count)) return false;

    list($forum_available_count) = db_fetch_array($result, DB_RESULT_NUM);

    return $forum_available_count;
}

function forum_get_content_delivery_path($file_path)
{
    // Current array index
    static $content_delivery_domain_index = -1;

    // Static to hold content delivery domains
    static $content_delivery_domains_array = false;

    // Static array to hold files so we don't duplicate filenames to different domains.
    static $content_delivery_files_array = array();

    // Check we haven't already loaded the list
    if (!is_array($content_delivery_domains_array)) {

        // Get the content delivery domains as an array.
        $content_delivery_domains_array = explode("\n", forum_get_setting('content_delivery_domains'));

        // Trim each entry in the array.
        $content_delivery_domains_array = array_map('trim', $content_delivery_domains_array);

        // Remove empty lines and reindex the array.
        $content_delivery_domains_array = array_values(array_filter($content_delivery_domains_array));
    }

    // Check we have something left to use.
    if (sizeof($content_delivery_domains_array) < 1) return false;

    // Check if the file path has been previously requested.
    if (isset($content_delivery_files_array[$file_path])) {
        return $content_delivery_files_array[$file_path];
    }

    // Increment the array index.
    $content_delivery_domain_index++;

    // If the array index exists, return it.
    if (isset($content_delivery_domains_array[$content_delivery_domain_index])) {

        // Cache the selected content delivery domain
        $content_delivery_files_array[$file_path] = $content_delivery_domains_array[$content_delivery_domain_index];

        // Return the selected domain.
        return preg_replace('/^http(s)?:\/\//', '', $content_delivery_files_array[$file_path]);
    }

    // Reset the array index
    $content_delivery_domain_index = -1;

    // Call self.
    return forum_get_content_delivery_path($file_path);
}

function forum_self_clean_check_ajax()
{
    if (isset($_SERVER['PHP_SELF']) && strlen(trim(stripslashes_array($_SERVER['PHP_SELF']))) > 0) {

        $script_filename = basename(trim(stripslashes_array($_SERVER['PHP_SELF'])));
        if (in_array($script_filename, array('ajax.php', 'json.php'))) return false;
    }

    return true;
}

// Forum self-preservation functions.
function forum_check_maintenance()
{
    // Array of functions that we run one at a time.
    $forum_maintenance_functions_array = array(
        'pm_system_prune_folders',
        'thread_auto_prune_unread_data',
        'sitemap_create_file'
    );

    // Array to hold the forum settings we need to update.
    $new_forum_settings = array();

    // Ajax and JSON requests shouldn't trigger forum self clean
    if (!forum_self_clean_check_ajax()) return;

    // Get the scheduled forum maintenance start hour (default 03:00)
    $forum_maintenance_hour = forum_get_setting('forum_maintenance_hour', 'is_numeric', 3);

    // Get the forum maintenance duration (default 1 hour)
    $forum_maintenance_duration = forum_get_setting('forum_maintenance_duration', 'is_numeric', 1);

    // Fetch index of the last function we ran.
    $forum_maintenance_function = forum_get_setting('forum_maintenance_function', 'is_numeric', 0);

    // Increment the $forum_maintenance_function variable
    $forum_maintenance_function++;

    // Check that the $forum_maintenance_function points to a valid function.
    if (!isset($forum_maintenance_functions_array[$forum_maintenance_function])) {
        $forum_maintenance_function = 0;
    }

    // Generate the variable name for the function's last run date.
    $forum_maintenance_date_var = sprintf("%s_last_run", $forum_maintenance_functions_array[$forum_maintenance_function]);

    // Get the functions last run time from the database.
    $forum_maintenance_last_run = forum_get_setting($forum_maintenance_date_var, 'is_numeric', 0);

    // If the function has been run previously in the last 24 hours skip it.
    if (((time() - $forum_maintenance_last_run) < DAY_IN_SECONDS)) return;

    // Check that the scheduled start time has passed.
    if ((time() < mktime($forum_maintenance_hour))) return;

    // Check that the maintenance window has not passed.
    if ((time() > mktime($forum_maintenance_hour + $forum_maintenance_duration))) return;

    // Check the function actually exists before we try and execute it.
    if (!function_exists($forum_maintenance_functions_array[$forum_maintenance_function])) return;

    // Prevent the HTTP request from being aborted if the user presses stop or reloads the page.
    ignore_user_abort(true);

    // Execute the maintenance function.
    $forum_maintenance_functions_array[$forum_maintenance_function]();

    // Update the last run time of the function.
    $new_forum_settings[$forum_maintenance_date_var] = time();

    // Update the last run forum_maintenance_function forum setting
    $new_forum_settings['forum_maintenance_function'] = $forum_maintenance_function;

    // Save the settings to the database.
    forum_save_default_settings($new_forum_settings);
}

?>