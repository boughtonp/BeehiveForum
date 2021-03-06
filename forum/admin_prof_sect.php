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
include_once(BH_INCLUDE_PATH. "form.inc.php");
include_once(BH_INCLUDE_PATH. "format.inc.php");
include_once(BH_INCLUDE_PATH. "header.inc.php");
include_once(BH_INCLUDE_PATH. "html.inc.php");
include_once(BH_INCLUDE_PATH. "lang.inc.php");
include_once(BH_INCLUDE_PATH. "logon.inc.php");
include_once(BH_INCLUDE_PATH. "perm.inc.php");
include_once(BH_INCLUDE_PATH. "profile.inc.php");
include_once(BH_INCLUDE_PATH. "session.inc.php");
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

    html_draw_top();
    html_error_msg(gettext("You do not have permission to use this section."));
    html_draw_bottom();
    exit;
}

if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $page = ($_GET['page'] > 0) ? $_GET['page'] : 1;
}elseif (isset($_POST['page']) && is_numeric($_POST['page'])) {
    $page = ($_POST['page'] > 0) ? $_POST['page'] : 1;
}else {
    $page = 1;
}

$start = floor($page - 1) * 10;
if ($start < 0) $start = 0;

// Array for holding error messages
$error_msg_array = array();

// Cancel button clicked.
if (isset($_POST['cancel'])) {

    header_redirect("admin_prof_sect.php?webtag=$webtag");
    exit;
}

if (isset($_POST['delete_sections'])) {

    $valid = true;

    if (isset($_POST['delete_section']) && is_array($_POST['delete_section'])) {

        foreach ($_POST['delete_section'] as $psid => $delete_section) {

            if ($valid && $delete_section == "Y" && $profile_name = profile_section_get_name($psid)) {

                if (profile_section_delete($psid)) {

                    admin_add_log_entry(DELETE_PROFILE_SECT, array($profile_name));

                }else {

                    $error_msg_array[] = gettext("Failed to remove profile sections");
                    $valid = false;
                }
            }
        }

        if ($valid) {

            header_redirect("admin_prof_sect.php?webtag=$webtag&deleted=true");
            exit;
        }
    }

}elseif (isset($_POST['addsectionsubmit'])) {

    $valid = true;

    if (isset($_POST['t_name_new']) && strlen(trim(stripslashes_array($_POST['t_name_new']))) > 0) {
        $t_name_new = trim(stripslashes_array($_POST['t_name_new']));
    }else {
        $error_msg_array[] = gettext("Must specify a profile section name");
        $valid = false;
    }

    if ($valid) {

        if (($new_psid = profile_section_create($t_name_new))) {

            header_redirect("admin_prof_sect.php?webtag=$webtag&added=true");
            exit;
        }
    }

}elseif (isset($_POST['editfeedsubmit'])) {

    $valid = true;

    if (isset($_POST['psid']) && is_numeric($_POST['psid'])) {
        $psid = $_POST['psid'];
    }else {
        $error_msg_array[] = gettext("Must specify a profile section ID");
        $valid = false;
    }

    if (isset($_POST['t_name_new']) && strlen(trim(stripslashes_array($_POST['t_name_new']))) > 0) {
        $t_new_name = trim(stripslashes_array($_POST['t_name_new']));
    }else {
        $error_msg_array[] = gettext("Must specify a profile section name");
        $valid = false;
    }

    if ($valid) {

        if (profile_section_update($psid, $t_new_name)) {

            $t_section_name = profile_section_get_name($psid);

            if ($t_new_name != $t_section_name) {
                admin_add_log_entry(CHANGE_PROFILE_SECT, array($t_section_name, $t_new_name));
            }

            header_redirect("admin_prof_sect.php?webtag=$webtag&edited=true");
            exit;
        }
    }

}elseif (isset($_POST['addsection'])) {

    $redirect = "admin_prof_sect.php?webtag=$webtag&page=$page&addsection=true";
    header_redirect($redirect);
    exit;

}elseif (isset($_POST['viewitems']) && is_array($_POST['viewitems'])) {

    list($psid) = array_keys($_POST['viewitems']);
    $redirect = "admin_prof_items.php?webtag=$webtag&psid=$psid&sect_page=$page";
    header_redirect($redirect);
    exit;
}

if (isset($_POST['move_up']) && is_array($_POST['move_up'])) {

    list($psid) = array_keys($_POST['move_up']);
    profile_section_move_up($psid);
}

if (isset($_POST['move_down']) && is_array($_POST['move_down'])) {

    list($psid) = array_keys($_POST['move_down']);
    profile_section_move_down($psid);
}

if (isset($_GET['addsection']) || isset($_POST['addsection'])) {

    html_draw_top("title=", gettext("Admin"), " - ", gettext("Manage Profile Sections"), " - ", gettext("Add new profile section"), "", 'class=window_title');

    echo "<h1>", gettext("Admin"), "<img src=\"", html_style_image('separator.png'), "\" alt=\"\" border=\"0\" />", gettext("Manage Profile Sections"), "<img src=\"", html_style_image('separator.png'), "\" alt=\"\" border=\"0\" />", gettext("Add new profile section"), "</h1>\n";

    if (isset($error_msg_array) && sizeof($error_msg_array) > 0) {
        html_display_error_array($error_msg_array, '500', 'center');
    }

    echo "<br />\n";
    echo "<div align=\"center\">\n";
    echo "  <form accept-charset=\"utf-8\" name=\"thread_options\" action=\"admin_prof_sect.php\" method=\"post\" target=\"_self\">\n";
    echo "  ", form_input_hidden('webtag', htmlentities_array($webtag)), "\n";
    echo "  ", form_input_hidden('addsection', 'true'), "\n";
    echo "  ", form_input_hidden('page', htmlentities_array($page)), "\n";
    echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"500\">\n";
    echo "    <tr>\n";
    echo "      <td align=\"left\">\n";
    echo "        <table class=\"box\" width=\"100%\">\n";
    echo "          <tr>\n";
    echo "            <td align=\"left\" class=\"posthead\">\n";
    echo "              <table class=\"posthead\" width=\"100%\">\n";
    echo "                <tr>\n";
    echo "                  <td align=\"left\" class=\"subhead\">", gettext("Section Name"), "</td>\n";
    echo "                </tr>\n";
    echo "                <tr>\n";
    echo "                  <td align=\"center\">\n";
    echo "                    <table class=\"posthead\" width=\"95%\">\n";
    echo "                      <tr>\n";
    echo "                        <td align=\"left\" width=\"150\" class=\"posthead\">", gettext("Section Name"), ":</td>\n";
    echo "                        <td align=\"left\">", form_input_text("t_name_new", (isset($_POST['t_name_new']) ? htmlentities_array(stripslashes_array($_POST['t_name_new'])) : ""), 32, 64), "</td>\n";
    echo "                      </tr>\n";
    echo "                      <tr>\n";
    echo "                        <td align=\"left\">&nbsp;</td>\n";
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
    echo "      <td align=\"center\">", form_submit("addsectionsubmit", gettext("Add")), "&nbsp;", form_submit("cancel", gettext("Cancel")), "</td>\n";
    echo "    </tr>\n";
    echo "  </table>\n";
    echo "  </form>\n";
    echo "</div>\n";

    html_draw_bottom();

}elseif (isset($_POST['psid']) || isset($_GET['psid'])) {

    if (isset($_POST['psid']) && is_numeric($_POST['psid'])) {

        $psid = $_POST['psid'];

    }elseif (isset($_GET['psid']) && is_numeric($_GET['psid'])) {

        $psid = $_GET['psid'];

    }else {

        html_draw_top(sprintf("title=%s", gettext("Error")));
        html_error_msg(gettext("Invalid profile section ID or section not found"), 'admin_prof_sect.php', 'get', array('back' => gettext("Back")));
        html_draw_bottom();
        exit;
    }

    if (!$profile_section = profile_get_section($psid)) {

        html_draw_top(sprintf("title=%s", gettext("Error")));
        html_error_msg(gettext("Invalid profile section ID or section not found"), 'admin_prof_sect.php', 'get', array('back' => gettext("Back")));
        html_draw_bottom();
        exit;
    }

    html_draw_top("title=", gettext("Admin"), " - ", gettext("Manage Profile Sections"), " - {$profile_section['NAME']}", 'class=window_title');

    echo "<h1>", gettext("Admin"), "<img src=\"", html_style_image('separator.png'), "\" alt=\"\" border=\"0\" />", gettext("Manage Profile Sections"), "<img src=\"", html_style_image('separator.png'), "\" alt=\"\" border=\"0\" />", word_filter_add_ob_tags($profile_section['NAME'], true), "</h1>\n";

    if (isset($error_msg_array) && sizeof($error_msg_array) > 0) {
        html_display_error_array($error_msg_array, '500', 'center');
    }

    echo "<br />\n";
    echo "<div align=\"center\">\n";
    echo "  <form accept-charset=\"utf-8\" name=\"thread_options\" action=\"admin_prof_sect.php\" method=\"post\" target=\"_self\">\n";
    echo "  ", form_input_hidden('webtag', htmlentities_array($webtag)), "\n";
    echo "  ", form_input_hidden('psid', htmlentities_array($psid)), "\n";
    echo "  ", form_input_hidden('page', htmlentities_array($page)), "\n";
    echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"500\">\n";
    echo "    <tr>\n";
    echo "      <td align=\"left\">\n";
    echo "        <table class=\"box\" width=\"100%\">\n";
    echo "          <tr>\n";
    echo "            <td align=\"left\" class=\"posthead\">\n";
    echo "              <table class=\"posthead\" width=\"100%\">\n";
    echo "                <tr>\n";
    echo "                  <td align=\"left\" class=\"subhead\">", gettext("Section Name"), "</td>\n";
    echo "                </tr>\n";
    echo "                <tr>\n";
    echo "                  <td align=\"center\">\n";
    echo "                    <table class=\"posthead\" width=\"95%\">\n";
    echo "                      <tr>\n";
    echo "                        <td align=\"left\" width=\"150\" class=\"posthead\">", gettext("Section Name"), ":</td>\n";
    echo "                        <td align=\"left\">", form_input_text("t_name_new", (isset($_POST['t_name_new']) ? htmlentities_array(stripslashes_array($_POST['t_name_new'])) : htmlentities_array($profile_section['NAME'])), 32, 64), "</td>\n";
    echo "                      </tr>\n";
    echo "                      <tr>\n";
    echo "                        <td align=\"left\">&nbsp;</td>\n";
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
    echo "      <td align=\"center\">", form_submit("editfeedsubmit", gettext("Save")), "&nbsp;", form_submit("viewitems[$psid]", gettext("View items")), "&nbsp;", form_submit("cancel", gettext("Back")), "</td>\n";
    echo "    </tr>\n";
    echo "  </table>\n";
    echo "  </form>\n";
    echo "</div>\n";

    html_draw_bottom();

}else {

    html_draw_top("title=", gettext("Admin"), " - ", gettext("Manage Profile Sections"), "", 'class=window_title');

    $profile_sections = profile_sections_get_by_page($start);

    echo "<h1>", gettext("Admin"), "<img src=\"", html_style_image('separator.png'), "\" alt=\"\" border=\"0\" />", gettext("Manage Profile Sections"), "</h1>\n";

    if (isset($error_msg_array) && sizeof($error_msg_array) > 0) {

        html_display_error_array($error_msg_array, '500', 'center');

    }else if (isset($_GET['added'])) {

        html_display_success_msg(gettext("Successfully added profile section"), '500', 'center');

    }else if (isset($_GET['edited'])) {

        html_display_success_msg(gettext("Successfully edited profile section"), '500', 'center');

    }else if (isset($_GET['deleted'])) {

        html_display_success_msg(gettext("Successfully removed selected profile sections"), '500', 'center');

    }else if (sizeof($profile_sections['profile_sections_array']) < 1) {

        html_display_warning_msg(gettext("No existing profile sections found. To add a profile section click the 'Add New' button below."), '500', 'center');
    }

    echo "<br />\n";
    echo "<div align=\"center\">\n";
    echo "<form accept-charset=\"utf-8\" name=\"f_sections\" action=\"admin_prof_sect.php\" method=\"post\">\n";
    echo "  ", form_input_hidden('webtag', htmlentities_array($webtag)), "\n";
    echo "  ", form_input_hidden('page', htmlentities_array($page)), "\n";
    echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"500\">\n";
    echo "    <tr>\n";
    echo "      <td align=\"left\">\n";
    echo "        <table class=\"box\" width=\"100%\">\n";
    echo "          <tr>\n";
    echo "            <td align=\"left\" class=\"posthead\">\n";
    echo "              <table class=\"posthead\" width=\"100%\">\n";
    echo "                <tr>\n";
    echo "                  <td class=\"subhead\" align=\"left\" width=\"25\">&nbsp;</td>\n";
    echo "                  <td class=\"subhead\" align=\"left\">", gettext("Section Name"), "</td>\n";
    echo "                  <td class=\"subhead\" align=\"left\">&nbsp;</td>\n";
    echo "                  <td class=\"subhead\" align=\"center\">", gettext("Items"), "</td>\n";
    echo "                </tr>\n";

    if (sizeof($profile_sections['profile_sections_array']) > 0) {

        $profile_index = 0;

        foreach ($profile_sections['profile_sections_array'] as $profile_section) {

            $profile_index++;

            echo "                <tr>\n";
            echo "                  <td valign=\"top\" align=\"center\" width=\"1%\">", form_checkbox("delete_section[{$profile_section['PSID']}]", "Y", false), "</td>\n";

            if ($profile_sections['profile_sections_count'] == 1) {

                echo "                  <td valign=\"top\" align=\"left\" width=\"450\"><a href=\"admin_prof_sect.php?webtag=$webtag&amp;page=$page&amp;psid={$profile_section['PSID']}\">", word_filter_add_ob_tags($profile_section['NAME'], true), "</a></td>\n";
                echo "                  <td align=\"right\" width=\"40\">&nbsp;</td>\n";

            }elseif ($profile_index == $profile_sections['profile_sections_count']) {

                echo "                  <td valign=\"top\" align=\"left\" width=\"450\"><a href=\"admin_prof_sect.php?webtag=$webtag&amp;page=$page&amp;psid={$profile_section['PSID']}\">", word_filter_add_ob_tags($profile_section['NAME'], true), "</a></td>\n";
                echo "                  <td align=\"right\" width=\"40\" style=\"white-space: nowrap\">", form_submit_image('move_up.png', "move_up[{$profile_section['PSID']}]", "Move Up", "title=\"Move Up\"", "move_up_ctrl"), form_submit_image('move_down.png', "move_down_disabled", "Move Down", "title=\"Move Down\"", "move_down_ctrl_disabled"), "</td>\n";

            }elseif ($profile_index > 1) {

                echo "                  <td valign=\"top\" align=\"left\" width=\"450\"><a href=\"admin_prof_sect.php?webtag=$webtag&amp;page=$page&amp;psid={$profile_section['PSID']}\">", word_filter_add_ob_tags($profile_section['NAME'], true), "</a></td>\n";
                echo "                  <td align=\"right\" width=\"40\" style=\"white-space: nowrap\">", form_submit_image('move_up.png', "move_up[{$profile_section['PSID']}]", "Move Up", "title=\"Move Up\"", "move_up_ctrl"), form_submit_image('move_down.png', "move_down[{$profile_section['PSID']}]", "Move Down", "title=\"Move Down\"", "move_down_ctrl"), "</td>\n";

            }else {

                echo "                  <td valign=\"top\" align=\"left\" width=\"450\"><a href=\"admin_prof_sect.php?webtag=$webtag&amp;page=$page&amp;psid={$profile_section['PSID']}\">", word_filter_add_ob_tags($profile_section['NAME'], true), "</a></td>\n";
                echo "                  <td align=\"right\" width=\"40\" style=\"white-space: nowrap\">", form_submit_image('move_up.png', "move_up_disabled", "Move Up", "title=\"Move Up\"", "move_up_ctrl_disabled"), form_submit_image('move_down.png', "move_down[{$profile_section['PSID']}]", "Move Down", "title=\"Move Down\"", "move_down_ctrl"), "</td>\n";
            }

            echo "                  <td valign=\"top\" align=\"center\" width=\"100\"><a href=\"admin_prof_items.php?webtag=$webtag&amp;psid={$profile_section['PSID']}&amp;sect_page=$page&amp;viewitems=yes\">", htmlentities_array($profile_section['ITEM_COUNT']), "</a></td>\n";
            echo "                </tr>\n";
        }
    }

    echo "                <tr>\n";
    echo "                  <td align=\"left\" colspan=\"4\">&nbsp;</td>\n";
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
    echo "      <td class=\"postbody\" align=\"center\">", page_links("admin_prof_sect.php?webtag=$webtag", $start, $profile_sections['profile_sections_count'], 10), "</td>\n";
    echo "    </tr>\n";
    echo "    <tr>\n";
    echo "      <td align=\"left\">&nbsp;</td>\n";
    echo "    </tr>\n";
    echo "    <tr>\n";
    echo "      <td align=\"center\">", form_submit("addsection", gettext("Add New")), "&nbsp;", form_submit("delete_sections", gettext("Delete Selected")), "</td>\n";
    echo "    </tr>\n";
    echo "  </table>\n";
    echo "</form>\n";
    echo "</div>\n";

    html_draw_bottom();
}

?>