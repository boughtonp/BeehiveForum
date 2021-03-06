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

// Set the default timezone
date_default_timezone_set('UTC');

// Constant to define where the include files are
define("BH_INCLUDE_PATH", "include/");

// Server checking functions
include_once(BH_INCLUDE_PATH. "server.inc.php");

// Caching functions
include_once(BH_INCLUDE_PATH. "cache.inc.php");

// Disable PHP's register_globals
unregister_globals();

// Correctly set server protocol
set_server_protocol();

// Disable caching if on AOL
cache_disable_aol();

// Disable caching if proxy server detected.
cache_disable_proxy();

// Compress the output
include_once(BH_INCLUDE_PATH. "gzipenc.inc.php");

// Enable the error handler
include_once(BH_INCLUDE_PATH. "errorhandler.inc.php");

// Installation checking functions
include_once(BH_INCLUDE_PATH. "install.inc.php");

// Check that Beehive is installed correctly
check_install();

// Multiple forum support
include_once(BH_INCLUDE_PATH. "forum.inc.php");

// Fetch Forum Settings
$forum_settings = forum_get_settings();

// Fetch Global Forum Settings
$forum_global_settings = forum_get_global_settings();

include_once(BH_INCLUDE_PATH. "admin.inc.php");
include_once(BH_INCLUDE_PATH. "constants.inc.php");
include_once(BH_INCLUDE_PATH. "db.inc.php");
include_once(BH_INCLUDE_PATH. "folder.inc.php");
include_once(BH_INCLUDE_PATH. "form.inc.php");
include_once(BH_INCLUDE_PATH. "format.inc.php");
include_once(BH_INCLUDE_PATH. "header.inc.php");
include_once(BH_INCLUDE_PATH. "html.inc.php");
include_once(BH_INCLUDE_PATH. "lang.inc.php");
include_once(BH_INCLUDE_PATH. "logon.inc.php");
include_once(BH_INCLUDE_PATH. "perm.inc.php");
include_once(BH_INCLUDE_PATH. "post.inc.php");
include_once(BH_INCLUDE_PATH. "session.inc.php");

// Get Webtag
$webtag = get_webtag();

// Check we're logged in correctly
if (!$user_sess = session_check()) {
    $request_uri = rawurlencode(get_request_uri());
    header_redirect("logon.php?webtag=$webtag&final_uri=$request_uri");
}

// Check to see if the user is banned.
if (session_user_banned()) {

    html_user_banned();
    exit;
}

// Check we have a webtag
if (!forum_check_webtag_available($webtag)) {
    $request_uri = rawurlencode(get_request_uri(false));
    header_redirect("forums.php?webtag_error&final_uri=$request_uri");
}

// Initialise Locale
lang_init();

if (!(session_check_perm(USER_PERM_ADMIN_TOOLS, 0)) || (forum_get_setting('access_level', false, 0) == FORUM_DISABLED)) {

    html_draw_top(sprintf("title=%s", gettext("Error")));
    html_error_msg(gettext("You do not have permission to use this section."));
    html_draw_bottom();
    exit;
}

if (!$forum_fid = forum_get_setting('fid')) {

    html_draw_top(sprintf("title=%s", gettext("Error")));
    html_error_msg(gettext("You do not have permission to use this section."));
    html_draw_bottom();
    exit;
}

if (isset($_GET['ret']) && strlen(trim(stripslashes_array($_GET['ret']))) > 0) {
    $ret = rawurldecode(trim(stripslashes_array($_GET['ret'])));
}elseif (isset($_POST['ret']) && strlen(trim(stripslashes_array($_POST['ret']))) > 0) {
    $ret = trim(stripslashes_array($_POST['ret']));
}else {
    $ret = "admin_forums.php?webtag=$webtag";
}

// Array to hold error messages
$error_msg_array = array();

// validate the return to page
if (isset($ret) && strlen(trim($ret)) > 0) {

    $available_files = get_available_files();
    $available_files_preg = implode("|^", array_map('preg_quote_callback', $available_files));

    if (preg_match("/^$available_files_preg/u", basename($ret)) < 1) {
        $ret = "admin_forums.php?webtag=$webtag";
    }
}

if (isset($_POST['back'])) {
    header_redirect($ret);
}

if (isset($_POST['enable'])) {

    if (forum_update_access($forum_fid, FORUM_PASSWD_PROTECTED)) {

        header_redirect("admin_forum_set_passwd.php?webtag=$webtag");
        exit;
    }
}

if (!forum_get_setting('access_level', 2, false)) {

    html_draw_top(sprintf("title=%s", gettext("Error")));
    html_error_msg(gettext("Forum is not set to Password Protected Mode. Do you want to enable it now?"), 'admin_forum_set_passwd.php', 'post', array('enable' => gettext("Enable"), 'back' => gettext("Back")), array('ret' => $ret), false, 'center');
    html_draw_bottom();
    exit;
}

if (isset($_POST['save'])) {

    $valid = true;

    if (($forum_passhash = forum_get_password($forum_settings['fid']))) {

        if (isset($_POST['current_passwd']) && strlen(trim(stripslashes_array($_POST['current_passwd']))) > 0) {
            $t_current_passhash = md5(trim(stripslashes_array($_POST['current_passwd'])));
        }else {
            $error_msg_array[] = gettext("Current Password is required");
            $valid = false;
        }

        if ($valid) {

            if (strcmp($t_current_passhash, $forum_passhash) <> 0) {

                $error_msg_array[] = gettext("Current Password does not match saved password");
                $valid = false;
            }
        }
    }

    if (isset($_POST['new_passwd']) && strlen(trim(stripslashes_array($_POST['new_passwd']))) > 0) {
        $t_new_passwd = trim(stripslashes_array($_POST['new_passwd']));
    }else {
        $error_msg_array[] = gettext("New Password is required");
        $valid = false;
    }

    if (isset($_POST['confirm_passwd']) && strlen(trim(stripslashes_array($_POST['confirm_passwd']))) > 0) {
        $t_confirm_passwd = trim(stripslashes_array($_POST['confirm_passwd']));
    }else {
        $error_msg_array[] = gettext("Confirm Password is required");
        $valid = false;
    }

    if ($valid) {

        if (strcmp($t_new_passwd, $t_confirm_passwd) <> 0) {

            $error_msg_array[] = gettext("Passwords do not match");
            $valid = false;
        }

        if (mb_strlen($t_new_passwd) < 6) {

            $error_msg_array[] = gettext("Password must be a minimum of 6 characters long");
            $valid = false;
        }

        if (htmlentities_array($t_new_passwd) != $t_new_passwd) {

            $error_msg_array[] = gettext("Password must not contain HTML tags");
            $valid = false;
        }

        if ($valid) {

            if (forum_update_password($forum_fid, $t_new_passwd)) {

                header_redirect("admin_forum_set_passwd.php?webtag=$webtag&ret=$ret&updated=true");
                exit;
            }
        }
    }
}

html_draw_top("title=", gettext("Admin"), " - ", gettext("Change Password"), "", 'class=window_title');

echo "<h1>", gettext("Admin"), "<img src=\"", html_style_image('separator.png'), "\" alt=\"\" border=\"0\" />", gettext("Change Password"), "</h1>\n";

if (isset($error_msg_array) && sizeof($error_msg_array) > 0) {

    html_display_error_array($error_msg_array, '450', 'center');

}else if (isset($_GET['updated'])) {

    html_display_success_msg(gettext("Password changed"), '450', 'center');
}

echo "<br />\n";
echo "<div align=\"center\">\n";
echo "<form accept-charset=\"utf-8\" name=\"passwd\" action=\"admin_forum_set_passwd.php\" method=\"post\" target=\"_self\">\n";
echo "  ", form_input_hidden('webtag', htmlentities_array($webtag)), "\n";
echo "  ", form_input_hidden('ret', htmlentities_array($ret)), "\n";
echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"450\">\n";
echo "    <tr>\n";
echo "      <td align=\"left\">\n";
echo "        <table class=\"box\" width=\"100%\">\n";
echo "          <tr>\n";
echo "            <td align=\"left\" class=\"posthead\">\n";
echo "              <table class=\"posthead\" width=\"100%\">\n";
echo "                <tr>\n";
echo "                  <td align=\"left\" class=\"subhead\">", gettext("Change Password"), "</td>\n";
echo "                </tr>\n";
echo "                <tr>\n";
echo "                  <td align=\"center\">\n";
echo "                    <table class=\"posthead\" width=\"95%\">\n";

if (forum_get_password($forum_settings['fid'])) {

    echo "                      <tr>\n";
    echo "                        <td align=\"left\">", gettext("Current Password"), ":</td>\n";
    echo "                        <td align=\"left\">", form_input_password("current_passwd", "", 27, 0, "autocomplete=\"off\""), "&nbsp;</td>\n";
    echo "                      </tr>\n";
    echo "                      <tr>\n";
    echo "                        <td align=\"left\">", gettext("New Password"), ":</td>\n";
    echo "                        <td align=\"left\">", form_input_password("new_passwd", "", 27, 0, "autocomplete=\"off\""), "&nbsp;</td>\n";
    echo "                      </tr>\n";

}else {

    echo "                      <tr>\n";
    echo "                        <td align=\"left\">", gettext("Password"), ":</td>\n";
    echo "                        <td align=\"left\">", form_input_password("new_passwd", "", 27, 0, "autocomplete=\"off\""), "&nbsp;</td>\n";
    echo "                      </tr>\n";
}

echo "                      <tr>\n";
echo "                        <td align=\"left\">", gettext("Confirm Password"), ":</td>\n";
echo "                        <td align=\"left\">", form_input_password("confirm_passwd", "", 27, 0, "autocomplete=\"off\""), "&nbsp;</td>\n";
echo "                      </tr>\n";
echo "                      <tr>\n";
echo "                        <td align=\"left\">&nbsp;</td>\n";
echo "                      </tr>\n";
echo "                    </table>\n";
echo "                  </td>\n";
echo "                </tr>\n";
echo "              </table>\n";
echo "            </td>\n";
echo "          </tr>\n";
echo "        </table>\n";
echo "      </td>\n";
echo "    </tr>\n";
echo "    <tr>\n";
echo "      <td align=\"left\">&nbsp;</td>\n";
echo "    </tr>\n";
echo "    <tr>\n";
echo "      <td align=\"center\">", form_submit("save", gettext("Save")), "&nbsp;", form_submit("back", gettext("Back")), "</td>\n";
echo "    </tr>\n";
echo "  </table>\n";
echo "</form>\n";
echo "</div>\n";

html_draw_bottom();

?>