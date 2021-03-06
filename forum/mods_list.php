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

include_once(BH_INCLUDE_PATH. "folder.inc.php");
include_once(BH_INCLUDE_PATH. "form.inc.php");
include_once(BH_INCLUDE_PATH. "format.inc.php");
include_once(BH_INCLUDE_PATH. "header.inc.php");
include_once(BH_INCLUDE_PATH. "html.inc.php");
include_once(BH_INCLUDE_PATH. "lang.inc.php");
include_once(BH_INCLUDE_PATH. "logon.inc.php");
include_once(BH_INCLUDE_PATH. "mods_list.inc.php");
include_once(BH_INCLUDE_PATH. "session.inc.php");
include_once(BH_INCLUDE_PATH. "threads.inc.php");
include_once(BH_INCLUDE_PATH. "word_filter.inc.php");

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

// Check to see if the user has been approved.
if (!session_user_approved()) {

    html_user_require_approval();
    exit;
}

// Check we have a webtag
if (!forum_check_webtag_available($webtag)) {
    $request_uri = rawurlencode(get_request_uri(false));
    header_redirect("forums.php?webtag_error&final_uri=$request_uri");
}

// Initialise Locale
lang_init();

// Check that we have access to this forum
if (!forum_check_access_level()) {
    $request_uri = rawurlencode(get_request_uri());
    header_redirect("forums.php?webtag_error&final_uri=$request_uri");
}

if (isset($_GET['fid']) && is_numeric($_GET['fid'])) {

    $fid = $_GET['fid'];

}elseif (isset($_POST['fid']) && is_numeric($_POST['fid'])) {

    $fid = $_POST['fid'];

}else {

    html_draw_top("title=", gettext("Error"), "", 'pm_popup_disabled');
    html_error_msg(gettext("Cannot display folder moderators"));
    html_draw_bottom();
    exit;
}

$folder_title = folder_get_title($fid);

html_draw_top("title={$folder_title} - ", gettext("Moderator list:"), "", 'pm_popup_disabled', 'class=window_title');

echo "<div align=\"center\">\n";
echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"550\">\n";
echo "    <tr>\n";
echo "      <td align=\"left\">\n";
echo "        <table class=\"box\" width=\"100%\">\n";
echo "          <tr>\n";
echo "            <td align=\"left\" class=\"posthead\">\n";
echo "              <table class=\"posthead\" width=\"100%\">\n";
echo "                <tr>\n";
echo "                  <td align=\"left\" class=\"subhead\" colspan=\"1\">", gettext("Moderators for folder"), " '{$folder_title}'</td>\n";
echo "                </tr>\n";
echo "                <tr>\n";
echo "                  <td align=\"center\">\n";
echo "                    <table width=\"90%\" class=\"posthead\">\n";
echo "                      <tr>\n";
echo "                        <td align=\"left\">\n";
echo "                          <h2>", gettext("Forum leaders:"), "</h2>\n";
echo "                          <ul>\n";

if (($forum_mods_array = mods_list_get_mods(0))) {

    foreach ($forum_mods_array as $forum_mod) {

        echo "                            <li><a href=\"user_profile.php?webtag=$webtag&amp;uid={$forum_mod['UID']}\" target=\"_blank\" class=\"popup 650x500\">";
        echo word_filter_add_ob_tags(format_user_name($forum_mod['LOGON'], $forum_mod['NICKNAME']), true), "</a></li>\n";
    }

}else {

    echo "                            <li>", gettext("No moderators found"), "</li>\n";
}

echo "                          </ul>\n";
echo "                          <h2>", gettext("Folder moderators:"), "</h2>";
echo "                          <ul>\n";

if (($folder_mods_array = mods_list_get_mods($fid))) {

    foreach ($folder_mods_array as $folder_mod) {

        echo "                            <li><a href=\"user_profile.php?webtag=$webtag&amp;uid={$folder_mod['UID']}\" target=\"_blank\" class=\"popup 650x500\">";
        echo word_filter_add_ob_tags(format_user_name($folder_mod['LOGON'], $folder_mod['NICKNAME']), true), "</a></li>\n";
    }

}else {

    echo "                            <li>", gettext("No moderators found"), "</li>\n";
}

echo "                          </ul>\n";
echo "                        </td>\n";
echo "                      </tr>\n";
echo "                    </table>\n";
echo "                  </td>\n";
echo "                </tr>\n";
echo "                <tr>\n";
echo "                  <td align=\"left\">&nbsp;</td>\n";
echo "                </tr>\n";
echo "              </table>\n";
echo "            </td>\n";
echo "          </tr>\n";
echo "        </table>\n";
echo "      </td>\n";
echo "    </tr>\n";
echo "  </table>\n";
echo "  <br />\n";
echo "  <form accept-charset=\"utf-8\" method=\"post\" action=\"mods_list.php\" target=\"_self\">\n";
echo "    ", form_input_hidden('webtag', htmlentities_array($webtag)), "\n";
echo "    ", form_input_hidden('fid', htmlentities_array($fid)), "\n";
echo "    ". form_button('close_popup', gettext("Close")). "\n";
echo "  </form>\n";
echo "</div>\n";

html_draw_bottom();

?>