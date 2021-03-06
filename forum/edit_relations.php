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

include_once(BH_INCLUDE_PATH. "constants.inc.php");
include_once(BH_INCLUDE_PATH. "fixhtml.inc.php");
include_once(BH_INCLUDE_PATH. "form.inc.php");
include_once(BH_INCLUDE_PATH. "format.inc.php");
include_once(BH_INCLUDE_PATH. "header.inc.php");
include_once(BH_INCLUDE_PATH. "html.inc.php");
include_once(BH_INCLUDE_PATH. "lang.inc.php");
include_once(BH_INCLUDE_PATH. "logon.inc.php");
include_once(BH_INCLUDE_PATH. "post.inc.php");
include_once(BH_INCLUDE_PATH. "session.inc.php");
include_once(BH_INCLUDE_PATH. "user.inc.php");
include_once(BH_INCLUDE_PATH. "user_rel.inc.php");
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

if (user_is_guest()) {

    html_guest_error();
    exit;
}

// Array to hold error messages
$error_msg_array = array();

// Start output here
html_draw_top("title=", gettext("My Controls"), " - ", gettext("User Relationships"), "", "basetarget=_blank", 'class=window_title');

echo "<h1>", gettext("User Relationships"), "</h1>\n";

$uid = session_get_value('UID');

if (isset($_GET['main_page']) && is_numeric($_GET['main_page'])) {
    $main_page = $_GET['main_page'];
    $start_main = floor($main_page - 1) * 10;
}else if (isset($_POST['main_page']) && is_numeric($_POST['main_page'])) {
    $main_page = $_POST['main_page'];
    $start_main = floor($main_page - 1) * 10;
}else {
    $main_page = 1;
    $start_main = 0;
}

if (isset($_GET['search_page']) && is_numeric($_GET['search_page'])) {
    $search_page = $_GET['search_page'];
    $start_search = floor($search_page - 1) * 10;
}else if (isset($_POST['search_page']) && is_numeric($_POST['search_page'])) {
    $search_page = $_POST['search_page'];
    $start_search = floor($search_page - 1) * 10;
}else {
    $search_page = 1;
    $start_search = 0;
}

if (isset($_POST['user_search']) && strlen(trim(stripslashes_array($_POST['user_search']))) > 0) {
    $user_search = trim(stripslashes_array($_POST['user_search']));
}elseif (isset($_GET['user_search']) && strlen(trim(stripslashes_array($_GET['user_search']))) > 0) {
    $user_search = trim(stripslashes_array($_GET['user_search']));
}else {
    $user_search = "";
}

if (isset($_POST['clear_search'])) {
    $user_search = "";
}

if (isset($_POST['delete'])) {

    $valid = true;

    if (isset($_POST['delete_relationships']) && is_array($_POST['delete_relationships'])) {

        foreach ($_POST['delete_relationships'] as $peer_uid => $delete_relationship) {

            if (($delete_relationship == "Y")) {

                if (!user_rel_update($uid, $peer_uid, 0)) {

                    $valid = false;
                    $error_msg_array[] = gettext("Failed to remove selected relationship");
                }
            }
        }

        if ($valid) {

            $redirect = "edit_relations.php?webtag=$webtag&relupdated=true";
            header_redirect($redirect, gettext("Relationships Updated!"));
            exit;
        }
    }
}

// Check if we're searching for a user or simply listing the existing relationships.
if (isset($user_search) && strlen(trim($user_search)) > 0) {
    $user_peers_array = user_search_relationships($user_search, $start_search, $uid);
}else {
    $user_peers_array = user_get_relationships($uid, $start_main);
}

// Output any messages.
if (isset($error_msg_array) && sizeof($error_msg_array) > 0) {

    html_display_error_array($error_msg_array, '600', 'left');

}else if (isset($_GET['relupdated'])) {

    html_display_success_msg(gettext("Relationships Updated!"), '600', 'left');

}else if (sizeof($user_peers_array['user_array']) < 1) {

    if (isset($user_search) && strlen(trim($user_search)) > 0) {

        html_display_warning_msg(gettext("Search Returned No Results"), '600', 'left');

    }else {

        html_display_warning_msg(gettext("You have no user relationships set up. Add a new user by searching below."), '600', 'left');
    }
}

echo "<br />\n";
echo "<form accept-charset=\"utf-8\" name=\"prefs\" action=\"edit_relations.php\" method=\"post\" target=\"_self\">\n";
echo "  ", form_input_hidden('webtag', htmlentities_array($webtag)), "\n";
echo "  ", form_input_hidden("main_page", htmlentities_array($main_page)), "\n";
echo "  ", form_input_hidden("search_page", htmlentities_array($search_page)), "\n";
echo "  ", form_input_hidden("user_search", htmlentities_array($user_search)), "\n";
echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"600\">\n";
echo "    <tr>\n";
echo "      <td align=\"left\">\n";
echo "        <table class=\"box\" width=\"100%\">\n";
echo "          <tr>\n";
echo "            <td align=\"left\" class=\"posthead\">\n";
echo "              <table class=\"posthead\" width=\"100%\">\n";
echo "                <tr>\n";
echo "                  <td align=\"left\" class=\"subhead\" width=\"20\">&nbsp;</td>\n";
echo "                  <td align=\"left\" class=\"subhead\" width=\"200\">", gettext("User"), "</td>\n";
echo "                  <td align=\"center\" class=\"subhead\">", gettext("Relationship"), "</td>\n";
echo "                  <td align=\"center\" class=\"subhead\">", gettext("Signature"), "</td>\n";
echo "                  <td align=\"center\" class=\"subhead\">", gettext("Personal Messages"), "</td>\n";
echo "                </tr>\n";

if (sizeof($user_peers_array['user_array']) > 0) {

    foreach ($user_peers_array['user_array'] as $user_peer) {

        echo "                <tr>\n";
        echo "                  <td align=\"center\">", form_checkbox("delete_relationships[{$user_peer['UID']}]", "Y", false), "</td>\n";
        echo "                  <td align=\"left\">&nbsp;<a href=\"user_rel.php?webtag=$webtag&amp;uid={$user_peer['UID']}&amp;ret=edit_relations.php%3Fwebtag%3D$webtag\" target=\"_self\">", word_filter_add_ob_tags(format_user_name($user_peer['LOGON'], $user_peer['PEER_NICKNAME']), true), "</a></td>\n";

        if ($user_peer['RELATIONSHIP'] & USER_FRIEND) {

            echo "                  <td align=\"center\"><img src=\"", html_style_image("friend.png"), "\" alt=\"", gettext("Friend"), "\" title=\"", gettext("Friend"), "\" /></td>\n";

        }elseif ($user_peer['RELATIONSHIP'] & USER_IGNORED) {

            echo "                  <td align=\"center\"><img src=\"", html_style_image("enemy.png"), "\" alt=\"", gettext("Ignored"), "\" title=\"", gettext("Ignored"), "\" /></td>\n";

        }elseif ($user_peer['RELATIONSHIP'] & USER_IGNORED_COMPLETELY) {

            echo "                  <td align=\"center\"><img src=\"", html_style_image("enemy.png"), "\" alt=\"", gettext("Ignored Completely"), "\" title=\"", gettext("Ignored Completely"), "\" /><img src=\"", html_style_image("enemy.png"), "\" alt=\"", gettext("Ignored Completely"), "\" title=\"", gettext("Ignored Completely"), "\" /></td>\n";

        }else {

            echo "                  <td align=\"center\">", gettext("Normal"), "</td>\n";
        }

        if ($user_peer['RELATIONSHIP'] & USER_IGNORED_SIG) {

            echo "                  <td align=\"center\"><img src=\"", html_style_image("enemy.png"), "\" alt=\"", gettext("Ignored"), "\" title=\"", gettext("Ignored"), "\" /></td>\n";

        }else {

            echo "                  <td align=\"center\"><img src=\"", html_style_image("friend.png"), "\" alt=\"", gettext("Display"), "\" title=\"", gettext("Display"), "\" /></td>\n";
        }

        if ($user_peer['RELATIONSHIP'] & USER_BLOCK_PM) {

            echo "                  <td align=\"center\"><img src=\"", html_style_image("enemy.png"), "\" alt=\"", gettext("Block"), "\" title=\"", gettext("Block"), "\" /></td>\n";

        }else {

            echo "                  <td align=\"center\"><img src=\"", html_style_image("friend.png"), "\" alt=\"", gettext("Allow"), "\" title=\"", gettext("Allow"), "\" /></td>\n";
        }

        echo "                </tr>\n";
    }
}

echo "                <tr>\n";
echo "                  <td align=\"left\">&nbsp;</td>\n";
echo "                </tr>\n";
echo "              </table>\n";
echo "            </td>\n";
echo "          </tr>\n";
echo "        </table>\n";
echo "      </td>\n";
echo "    </tr>\n";

if (sizeof($user_peers_array['user_array']) > 0) {

    echo "    <tr>\n";
    echo "      <td align=\"left\">&nbsp;</td>\n";
    echo "    </tr>\n";
    echo "    <tr>\n";
    echo "      <td class=\"postbody\" align=\"center\">", page_links("edit_relations.php?webtag=$webtag&user_search=$user_search&search_page=$search_page", $start_main, $user_peers_array['user_count'], 10, "main_page"), "</td>\n";
    echo "    </tr>\n";
    echo "    <tr>\n";
    echo "      <td align=\"left\">&nbsp;</td>\n";
    echo "    </tr>\n";
    echo "    <tr>\n";
    echo "      <td colspan=\"2\" align=\"center\">", form_submit("delete", gettext("Delete Selected")), "</td>\n";
    echo "    </tr>\n";
}

echo "  </table>\n";
echo "</form>\n";
echo "<br />\n";
echo "<form accept-charset=\"utf-8\" method=\"post\" action=\"edit_relations.php\" target=\"_self\">\n";
echo "  ", form_input_hidden('webtag', htmlentities_array($webtag)), "\n";
echo "  ", form_input_hidden("main_page", htmlentities_array($main_page)), "\n";
echo "  ", form_input_hidden("search_page", htmlentities_array($search_page)), "\n";
echo "  ", form_input_hidden("main_page", htmlentities_array($main_page)), "\n";
echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"600\">\n";
echo "    <tr>\n";
echo "      <td align=\"left\" class=\"posthead\">\n";
echo "        <table class=\"box\" width=\"100%\">\n";
echo "          <tr>\n";
echo "            <td align=\"left\" class=\"posthead\">\n";
echo "              <table class=\"posthead\" width=\"100%\">\n";
echo "                <tr>\n";
echo "                  <td class=\"subhead\" align=\"left\">", gettext("Search"), "</td>\n";
echo "                </tr>\n";
echo "              </table>\n";
echo "              <table class=\"posthead\" width=\"100%\">\n";
echo "                <tr>\n";
echo "                  <td align=\"center\">\n";
echo "                    <table class=\"posthead\" width=\"95%\">\n";
echo "                      <tr>\n";
echo "                        <td class=\"posthead\" align=\"left\">", gettext("Username"), ": ", form_input_text('user_search', htmlentities_array($user_search), 30, 64), " ", form_submit('search', gettext("Search")), " ", form_submit('clear_search', gettext("Clear")), "</td>\n";
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
echo "  </table>\n";
echo "</form>\n";

html_draw_bottom();

?>
