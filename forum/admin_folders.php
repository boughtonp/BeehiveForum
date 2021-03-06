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
include_once(BH_INCLUDE_PATH. "session.inc.php");
include_once(BH_INCLUDE_PATH. "stats.inc.php");
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

// Check we have a webtag
if (!forum_check_webtag_available($webtag)) {
    $request_uri = rawurlencode(get_request_uri(false));
    header_redirect("forums.php?webtag_error&final_uri=$request_uri");
}

// Initialise Locale
lang_init();

if (!(session_check_perm(USER_PERM_ADMIN_TOOLS, 0))) {

    html_draw_top(sprintf("title=%s", gettext("Error")));
    html_error_msg(gettext("You do not have permission to use this section."));
    html_draw_bottom();
    exit;
}

if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $page = ($_GET['page'] > 0) ? $_GET['page'] : 1;
}else if (isset($_POST['page']) && is_numeric($_POST['page'])) {
    $page = ($_POST['page'] > 0) ? $_POST['page'] : 1;
}else {
    $page = 1;
}

$start = floor($page - 1) * 10;
if ($start < 0) $start = 0;

// Array to hold error messages
$error_msg_array = array();

// Delete folders.
if (isset($_POST['delete'])) {

    $valid = true;

    if (isset($_POST['t_delete']) && is_array($_POST['t_delete'])) {

        foreach ($_POST['t_delete'] as $fid => $delete_folder) {

            if ($valid && $delete_folder == "Y" && $folder_data = folder_get($fid)) {

                if ($folder_data['THREAD_COUNT'] < 1) {

                    if (folder_delete($fid)) {

                        admin_add_log_entry(DELETE_FOLDER, array($folder_data['TITLE']));

                    }else {

                        $error_msg_array[] = gettext("Failed to delete folder.");
                        $valid = false;
                    }

                }else {

                    $error_msg_array[] = gettext("Cannot delete folders that still contain threads.");
                    $valid = false;
                }
            }
        }

        if ($valid) {

            header_redirect("admin_folders.php?webtag=$webtag&page=$page&deleted=true");
            exit;
        }
    }

}

if (isset($_POST['addnew'])) {

    header_redirect("admin_folder_add.php?webtag=$webtag&page=$page");
    exit;
}

if (isset($_POST['move_up']) && is_array($_POST['move_up'])) {

    list($fid) = array_keys($_POST['move_up']);

    if (folder_move_up($fid)) {

        header_redirect("admin_folders.php?webtag=$webtag&page=$page");
        exit;
    }
}

if (isset($_POST['move_down']) && is_array($_POST['move_down'])) {

    list($fid) = array_keys($_POST['move_down']);

    if (folder_move_down($fid)) {

        header_redirect("admin_folders.php?webtag=$webtag&page=$page");
        exit;
    }
}

if (isset($_POST['move_up_disabled']) || isset($_POST['move_down_disabled'])) {

    header_redirect("admin_folders.php?webtag=$webtag&page=$page");
    exit;
}

html_draw_top("title=", gettext("Admin"), " - ", gettext("Manage Folders"), "", 'class=window_title');

$folder_array = folder_get_all_by_page($start);

echo "<h1>", gettext("Admin"), "<img src=\"", html_style_image('separator.png'), "\" alt=\"\" border=\"0\" />", gettext("Manage Folders"), "</h1>\n";

if (isset($error_msg_array) && sizeof($error_msg_array) > 0) {

    html_display_error_array($error_msg_array, '600', 'center');

}else if (isset($_GET['added'])) {

    html_display_success_msg(gettext("Successfully added new folder"), '600', 'center');

}else if (isset($_GET['edited'])) {

    html_display_success_msg(gettext("Successfully edited folder"), '600', 'center');

}else if (isset($_GET['deleted'])) {

    html_display_success_msg(gettext("Successfully removed selected folders"), '600', 'center');

}else if (sizeof($folder_array['folder_array']) < 1) {

    html_display_warning_msg(gettext("No existing folders found. To add a folder click the 'Add New' button below."), '600', 'center');

}else {

    html_display_warning_msg(gettext("Folder order only applies when user has enabled 'Sort Thread List by folders' in Forum Options."), '600', 'center');
}

echo "<br />\n";
echo "<div align=\"center\">\n";
echo "<form accept-charset=\"utf-8\" name=\"f_folders\" action=\"admin_folders.php\" method=\"post\">\n";
echo "  ", form_input_hidden('webtag', htmlentities_array($webtag)), "\n";
echo "  ", form_input_hidden('page', htmlentities_array($page)), "\n";
echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"600\">\n";
echo "    <tr>\n";
echo "      <td align=\"left\">\n";
echo "        <table class=\"box\" width=\"100%\">\n";
echo "          <tr>\n";
echo "            <td align=\"left\" class=\"posthead\">\n";
echo "              <table class=\"posthead\" width=\"100%\">\n";
echo "                <tr>\n";
echo "                  <td class=\"subhead\" align=\"left\" style=\"white-space: nowrap\">&nbsp;</td>\n";
echo "                  <td class=\"subhead\" align=\"left\" style=\"white-space: nowrap\">", gettext("Folder name"), "</td>\n";
echo "                  <td class=\"subhead\" align=\"left\" style=\"white-space: nowrap\">&nbsp;</td>\n";
echo "                  <td class=\"subhead\" align=\"center\" style=\"white-space: nowrap\">", gettext("Thread Count"), "</td>\n";
echo "                  <td class=\"subhead\" align=\"left\" style=\"white-space: nowrap\">", gettext("Permissions"), "</td>\n";
echo "                </tr>\n";

if (sizeof($folder_array['folder_array']) > 0) {

    $folder_index = $start;

    foreach ($folder_array['folder_array'] as $key => $folder) {

        $folder_index++;

        echo "                <tr>\n";
        echo "                  <td valign=\"top\" align=\"center\" width=\"1%\">", form_checkbox("t_delete[{$folder['FID']}]", "Y", false), "</td>\n";

        if ($folder_array['folder_count'] == 1) {

            echo "                  <td align=\"left\" width=\"150\"><a href=\"admin_folder_edit.php?webtag=$webtag&amp;page=$page&amp;fid={$folder['FID']}\" title=\"Click To Edit Folder Details\">", word_filter_add_ob_tags($folder['TITLE'], true), "</a></td>\n";
            echo "                  <td align=\"right\" width=\"40\">&nbsp;</td>\n";

        }elseif ($folder_index == $folder_array['folder_count']) {

            echo "                  <td align=\"left\" width=\"150\"><a href=\"admin_folder_edit.php?webtag=$webtag&amp;page=$page&amp;fid={$folder['FID']}\" title=\"", gettext("Click To Edit Folder"), "\">", word_filter_add_ob_tags($folder['TITLE'], true), "</a></td>\n";
            echo "                  <td align=\"right\" width=\"40\" style=\"white-space: nowrap\">", form_submit_image('move_up.png', "move_up[{$folder['FID']}]", "Move Up", "title=\"Move Up\"", "move_up_ctrl"), form_submit_image('move_down.png', "move_down_disabled", "Move Down", "title=\"Move Down\"", "move_down_ctrl_disabled"), "</td>\n";

        }elseif ($folder_index > 1) {

            echo "                  <td align=\"left\" width=\"150\"><a href=\"admin_folder_edit.php?webtag=$webtag&amp;page=$page&amp;fid={$folder['FID']}\" title=\"", gettext("Click To Edit Folder"), "\">", word_filter_add_ob_tags($folder['TITLE'], true), "</a></td>\n";
            echo "                  <td align=\"right\" width=\"40\" style=\"white-space: nowrap\">", form_submit_image('move_up.png', "move_up[{$folder['FID']}]", "Move Up", "title=\"Move Up\"", "move_up_ctrl"), form_submit_image('move_down.png', "move_down[{$folder['FID']}]", "Move Down", "title=\"Move Down\"", "move_down_ctrl"), "</td>\n";

        }else {

            echo "                  <td align=\"left\" width=\"150\"><a href=\"admin_folder_edit.php?webtag=$webtag&amp;page=$page&amp;fid={$folder['FID']}\" title=\"", gettext("Click To Edit Folder"), "\">", word_filter_add_ob_tags($folder['TITLE'], true), "</a></td>\n";
            echo "                  <td align=\"right\" width=\"40\" style=\"white-space: nowrap\">", form_submit_image('move_up.png', "move_up_disabled", "Move Up", "title=\"Move Up\"", "move_up_ctrl_disabled"), form_submit_image('move_down.png', "move_down[{$folder['FID']}]", "Move Down", "title=\"Move Down\"", "move_down_ctrl"), "</td>\n";
        }

        if (isset($folder['THREAD_COUNT']) && $folder['THREAD_COUNT'] > 0) {
            echo "                  <td align=\"center\">{$folder['THREAD_COUNT']}</td>\n";
        }else {
            echo "                  <td align=\"center\">0</td>\n";
        }

        if (isset($folder['FOLDER_PERMS']) && $folder['FOLDER_PERMS'] > 0) {
            echo "                  <td align=\"left\">", perm_display_list($folder['FOLDER_PERMS']), "</td>\n";
        }else {
            echo "                  <td align=\"left\">", gettext("none"), "</td>\n";
        }

        echo "                </tr>\n";
    }
}

echo "                <tr>\n";
echo "                  <td align=\"left\" colspan=\"8\">&nbsp;</td>\n";
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
echo "      <td class=\"postbody\" align=\"center\">", page_links("admin_folders.php?webtag=$webtag", $start, $folder_array['folder_count'], 10), "</td>\n";
echo "    </tr>\n";
echo "    <tr>\n";
echo "      <td align=\"left\">&nbsp;</td>\n";
echo "    </tr>\n";
echo "    <tr>\n";
echo "      <td align=\"center\">", form_submit("addnew", gettext("Add New")), "&nbsp;", form_submit("delete", gettext("Delete Selected")), "</td>\n";
echo "    </tr>\n";
echo "  </table>\n";
echo "</form>\n";
echo "<br />\n";
echo "<table cellpadding=\"0\" cellspacing=\"0\" width=\"600\">\n";
echo "  <tr>\n";
echo "    <td align=\"left\">\n";
echo "      <table class=\"box\" width=\"100%\">\n";
echo "        <tr>\n";
echo "          <td align=\"left\" class=\"posthead\">\n";
echo "            <table class=\"posthead\" width=\"100%\">\n";
echo "              <tr>\n";
echo "                <td colspan=\"4\" class=\"subhead\" align=\"left\" style=\"white-space: nowrap\">Permissions Key</td>\n";
echo "              </tr>\n";
echo "              <tr>\n";
echo "                <td align=\"center\">\n";
echo "                  <table width=\"95%\">\n";
echo "                    <tr>\n";
echo "                      <td align=\"left\" valign=\"top\" width=\"50%\">\n";
echo "                        <table width=\"100%\">\n";
echo "                          <tr>\n";
echo "                            <td align=\"left\" class=\"postbody\"><b>PR</b></td>\n";
echo "                            <td align=\"left\" class=\"postbody\">", gettext("Post Reading allowed"), "</td>\n";
echo "                          </tr>\n";
echo "                          <tr>\n";
echo "                            <td align=\"left\" class=\"postbody\"><b>TC</b></td>\n";
echo "                            <td align=\"left\" class=\"postbody\">", gettext("Thread Creation allowed"), "</td>\n";
echo "                          </tr>\n";
echo "                          <tr>\n";
echo "                            <td align=\"left\" class=\"postbody\"><b>PD</b></td>\n";
echo "                            <td align=\"left\" class=\"postbody\">", gettext("Post Deletion allowed"), "</td>\n";
echo "                          </tr>\n";
echo "                          <tr>\n";
echo "                            <td align=\"left\" class=\"postbody\"><b>HP</b></td>\n";
echo "                            <td align=\"left\" class=\"postbody\">", gettext("HTML Posting allowed"), "</td>\n";
echo "                          </tr>\n";
echo "                          <tr>\n";
echo "                            <td align=\"left\" class=\"postbody\"><b>GA</b></td>\n";
echo "                            <td align=\"left\" class=\"postbody\">", gettext("Guest Access allowed"), "</td>\n";
echo "                          </tr>\n";
echo "                          <tr>\n";
echo "                            <td align=\"left\" class=\"postbody\"><b>TM</b></td>\n";
echo "                            <td align=\"left\" class=\"postbody\">", gettext("Move threads to folder"), "</td>\n";
echo "                          </tr>\n";
echo "                        </table>\n";
echo "                      </td>\n";
echo "                      <td align=\"left\" valign=\"top\" width=\"50%\">\n";
echo "                        <table width=\"100%\">\n";
echo "                          <tr>\n";
echo "                            <td align=\"left\" class=\"postbody\"><b>PC</b></td>\n";
echo "                            <td align=\"left\" class=\"postbody\">", gettext("Post Creation allowed"), "</td>\n";
echo "                          </tr>\n";
echo "                          <tr>\n";
echo "                            <td align=\"left\" class=\"postbody\"><b>PE</b></td>\n";
echo "                            <td align=\"left\" class=\"postbody\">", gettext("Post Editing allowed"), "</td>\n";
echo "                          </tr>\n";
echo "                          <tr>\n";
echo "                            <td align=\"left\" class=\"postbody\"><b>UA</b></td>\n";
echo "                            <td align=\"left\" class=\"postbody\">", gettext("Uploading Attachments allowed"), "</td>\n";
echo "                          </tr>\n";
echo "                          <tr>\n";
echo "                            <td align=\"left\" class=\"postbody\"><b>US</b></td>\n";
echo "                            <td align=\"left\" class=\"postbody\">", gettext("User Signature allowed"), "</td>\n";
echo "                          </tr>\n";
echo "                          <tr>\n";
echo "                            <td align=\"left\" class=\"postbody\"><b>PA</b></td>\n";
echo "                            <td align=\"left\" class=\"postbody\">", gettext("Post Approval required"), "</td>\n";
echo "                          </tr>\n";
echo "                        </table>\n";
echo "                      </td>\n";
echo "                    </tr>\n";
echo "                  </table>\n";
echo "                </td>\n";
echo "              </tr>\n";
echo "              <tr>\n";
echo "                <td align=\"left\" colspan=\"8\">&nbsp;</td>\n";
echo "              </tr>\n";
echo "            </table>\n";
echo "          </td>\n";
echo "        </tr>\n";
echo "      </table>\n";
echo "    </td>\n";
echo "  </tr>\n";
echo "</table>\n";
echo "</div>\n";

html_draw_bottom();

?>