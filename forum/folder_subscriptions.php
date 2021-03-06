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
include_once(BH_INCLUDE_PATH. "folder.inc.php");
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

// Array to store error messages.
$error_msg_array = array();

// User pressed Save button
if (isset($_POST['save'])) {

    $valid = true;

    if (isset($_POST['set_interest']) && is_array($_POST['set_interest'])) {

        foreach ($_POST['set_interest'] as $folder) {

            if ($valid && is_numeric($folder) && ($folder_title = folder_get_title($folder))) {

                if (!user_set_folder_interest($folder, 0)) {

                    $error_msg_array[] = sprintf("", gettext("Could not update interest on folder '%s'"), "", $folder_title);

                    $valid = false;
                }
            }
        }

        if ($valid) {

            header_redirect("folder_subscriptions.php?webtag=$webtag&updated=true");
            exit;
        }
    }
}

// Page links.
if (isset($_GET['main_page']) && is_numeric($_GET['main_page'])) {
    $main_page = $_GET['main_page'];
    $start_main = floor($main_page - 1) * 20;
}else if (isset($_POST['main_page']) && is_numeric($_POST['main_page'])) {
    $main_page = $_POST['main_page'];
    $start_main = floor($main_page - 1) * 20;
}else {
    $main_page = 1;
    $start_main = 0;
}

// Search links.
if (isset($_GET['search_page']) && is_numeric($_GET['search_page'])) {
    $search_page = $_GET['search_page'];
    $start_search = floor($search_page - 1) * 20;
}else if (isset($_POST['search_page']) && is_numeric($_POST['search_page'])) {
    $search_page = $_POST['search_page'];
    $start_search = floor($search_page - 1) * 20;
}else {
    $search_page = 1;
    $start_search = 0;
}

// Folder search keywords.
if (isset($_GET['folder_search']) && strlen(trim(stripslashes_array($_GET['folder_search']))) > 0) {
    $folder_search = trim(stripslashes_array($_GET['folder_search']));
}else if (isset($_POST['folder_search']) && strlen(trim(stripslashes_array($_POST['folder_search']))) > 0) {
    $folder_search = trim(stripslashes_array($_POST['folder_search']));
}else {
    $folder_search = "";
}

// View filter
if (isset($_GET['view_filter']) && is_numeric($_GET['view_filter'])) {
    $view_filter = $_GET['view_filter'];
}else if (isset($_POST['view_filter']) && is_numeric($_POST['view_filter'])) {
    $view_filter = $_POST['view_filter'];
}else {
    $view_filter = FOLDER_SUBSCRIBED;
}

// Clear search?
if (isset($_POST['clear'])) {
    $folder_search = "";
}

// User UID
$uid = session_get_value('UID');

// Save button text and header text change depending on view selected.
$header_text_array = array(FOLDER_IGNORED => gettext("Ignored Folders"), FOLDER_SUBSCRIBED => gettext("Subscribed Folders"));

$interest_level_array = array(FOLDER_IGNORED => gettext("Ignored"), FOLDER_SUBSCRIBED => gettext("Subscribed"));

// Check if we're searching or displaying the existing subscriptions.
if (isset($folder_search) && strlen(trim($folder_search)) > 0) {
    $folder_subscriptions = folders_search_user_subscriptions($folder_search, $view_filter, $start_search);
}else {
    $folder_subscriptions = folders_get_user_subscriptions($view_filter, $start_main);
}

// Start output here
html_draw_top("title=", gettext("My Controls"), " - ", gettext("Folder Subscriptions"), " - {$header_text_array[$view_filter]}", 'edit_subscriptions.js', 'class=window_title');

echo "<h1>", gettext("Folder Subscriptions"), "<img src=\"", html_style_image('separator.png'), "\" alt=\"\" border=\"0\" />{$header_text_array[$view_filter]}</h1>\n";

if (isset($error_msg_array) && sizeof($error_msg_array) > 0) {

    html_display_error_array($error_msg_array, '600', 'left');

}else if (isset($_GET['updated'])) {

    html_display_success_msg(gettext("Folder interests updated successfully"), '600', 'left');

}else if (sizeof($folder_subscriptions['folder_array']) < 1) {

    if (isset($folder_search) && strlen(trim($folder_search)) > 0) {

        html_display_warning_msg(gettext("Search Returned No Results"), '600', 'left');

    }else if ($view_filter == FOLDER_IGNORED) {

        html_display_warning_msg(gettext("You are not ignoring any folders."), '600', 'left');

    }else {

        html_display_warning_msg(gettext("You are not subscribed to any folders."), '600', 'left');
    }
}

echo "<br />\n";
echo "<form accept-charset=\"utf-8\" name=\"subscriptions\" action=\"folder_subscriptions.php\" method=\"post\" target=\"_self\">\n";
echo "  ", form_input_hidden('webtag', htmlentities_array($webtag)), "\n";
echo "  ", form_input_hidden("main_page", htmlentities_array($main_page)), "\n";
echo "  ", form_input_hidden("search_page", htmlentities_array($search_page)), "\n";
echo "  ", form_input_hidden("folder_search", htmlentities_array($folder_search)), "\n";
echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"600\">\n";
echo "    <tr>\n";
echo "      <td align=\"left\" colspan=\"3\">\n";
echo "        <table class=\"box\" width=\"100%\">\n";
echo "          <tr>\n";
echo "            <td align=\"left\" class=\"posthead\">\n";
echo "              <table class=\"posthead\" width=\"100%\">\n";

if (sizeof($folder_subscriptions['folder_array']) > 0) {

    echo "                <tr>\n";
    echo "                  <td align=\"center\" class=\"subhead_checkbox\" width=\"1%\">", form_checkbox("toggle_all", "toggle_all"), "</td>\n";
    echo "                  <td align=\"left\" class=\"subhead\" width=\"450\">", gettext("Folder title"), "</td>\n";
    echo "                  <td align=\"center\" class=\"subhead\" width=\"150\">", gettext("Current Interest"), "</td>\n";
    echo "                </tr>\n";

    foreach ($folder_subscriptions['folder_array'] as $folder) {

        echo "                <tr>\n";
        echo "                  <td align=\"center\" style=\"white-space: nowrap\">", form_checkbox('set_interest[]', $folder['FID'], ''), "</td>\n";
        echo "                  <td align=\"left\"><a href=\"index.php?webtag=$webtag&amp;folder={$folder['FID']}\" target=\"_blank\">", word_filter_add_ob_tags($folder['TITLE'], true), "</a></td>\n";

        if (isset($interest_level_array[$folder['INTEREST']])) {
            echo "                  <td align=\"center\">{$interest_level_array[$folder['INTEREST']]}</td>\n";
        }else {
            echo "                  <td align=\"center\">", gettext("Normal"), "</td>\n";
        }

        echo "                </tr>\n";
    }

}else {

    echo "                <tr>\n";
    echo "                  <td align=\"left\" class=\"subhead\" width=\"20\">&nbsp;</td>\n";
    echo "                  <td align=\"left\" class=\"subhead\" width=\"450\">", gettext("Folder title"), "</td>\n";
    echo "                  <td align=\"center\" class=\"subhead\" width=\"150\">", gettext("Current Interest"), "</td>\n";
    echo "                </tr>\n";
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
echo "    <tr>\n";
echo "      <td align=\"left\">&nbsp;</td>\n";
echo "    </tr>\n";
echo "    <tr>\n";
echo "      <td align=\"left\" width=\"33%\">&nbsp;</td>\n";
echo "      <td class=\"postbody\" align=\"center\">", page_links("folder_subscriptions.php?webtag=$webtag&folder_search=$folder_search&search_page=$search_page&view_filter=$view_filter", $start_main, $folder_subscriptions['folder_count'], 20, "main_page"), "</td>\n";
echo "      <td align=\"right\" width=\"33%\">", gettext("View"), ":&nbsp;", form_dropdown_array('view_filter', array(FOLDER_IGNORED => gettext("Ignored"), FOLDER_SUBSCRIBED => gettext("Subscribed")), $view_filter), "&nbsp;", form_submit("view_submit", gettext("Go!")), "</td>\n";
echo "    </tr>\n";

if (sizeof($folder_subscriptions['folder_array']) > 0) {

    echo "    <tr>\n";
    echo "      <td align=\"left\">&nbsp;</td>\n";
    echo "    </tr>\n";
    echo "    <tr>\n";
    echo "      <td align=\"center\" colspan=\"3\">", form_submit("save", gettext("Reset Selected")), "</td>\n";
    echo "    </tr>\n";
}

echo "  </table>\n";
echo "</form>\n";
echo "<br />\n";
echo "<form accept-charset=\"utf-8\" method=\"post\" action=\"folder_subscriptions.php\" target=\"_self\">\n";
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
echo "                        <td class=\"posthead\" align=\"left\">\n";
echo "                          ", gettext("Folder title"), ": ", form_input_text("folder_search", isset($folder_search) ? htmlentities_array($folder_search) : "", 30, 64), " ", form_submit('search', gettext("Search")), "&nbsp;", form_submit('clear', gettext("Clear")), "\n";
echo "                        </td>\n";
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
