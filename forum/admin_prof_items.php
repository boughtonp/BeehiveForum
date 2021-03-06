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

    html_draw_top(sprintf("title=%s", gettext("Error")));
    html_error_msg(gettext("You do not have permission to use this section."));
    html_draw_bottom();
    exit;
}

if (isset($_GET['sect_page']) && is_numeric($_GET['sect_page'])) {
    $sect_page = ($_GET['sect_page'] > 0) ? $_GET['sect_page'] : 1;
}elseif (isset($_POST['sect_page']) && is_numeric($_POST['sect_page'])) {
    $sect_page = ($_POST['sect_page'] > 0) ? $_POST['sect_page'] : 1;
}else {
    $sect_page = 1;
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

if (isset($_GET['psid']) && is_numeric($_GET['psid'])) {

    $psid = $_GET['psid'];

}elseif (isset($_POST['psid']) && is_numeric($_POST['psid'])) {

    $psid = $_POST['psid'];

}else {

    html_draw_top(sprintf("title=%s", gettext("Error")));
    html_error_msg(gettext("No Profile section specified."), 'admin_prof_sect.php', 'get', array('back' => gettext("Back")));
    html_draw_bottom();
    exit;
}

// Array to hold error messages
$error_msg_array = array();

// Array of valid profile item types
$profile_item_valid_types = array(PROFILE_ITEM_LARGE_TEXT,
                                  PROFILE_ITEM_MEDIUM_TEXT,
                                  PROFILE_ITEM_SMALL_TEXT,
                                  PROFILE_ITEM_MULTI_TEXT,
                                  PROFILE_ITEM_RADIO,
                                  PROFILE_ITEM_DROPDOWN,
                                  PROFILE_ITEM_HYPERLINK);

// Array of profile item type descriptions.
$item_types_array = array(PROFILE_ITEM_LARGE_TEXT => gettext("Text Field"),
                          PROFILE_ITEM_MULTI_TEXT => gettext("Multi-line Text Field"),
                          PROFILE_ITEM_RADIO      => gettext("Radio Buttons"),
                          PROFILE_ITEM_DROPDOWN   => gettext("Drop Down List"),
                          PROFILE_ITEM_HYPERLINK  => gettext("Clickable Hyperlink"));
// View type
if (isset($_GET['viewitems'])) {
    $viewitems = "yes";
}elseif (isset($_POST['viewitems'])) {
    $viewitems = "yes";
}

if (isset($_POST['delete'])) {

    $valid = true;

    if (isset($_POST['delete_item']) && is_array($_POST['delete_item'])) {

        foreach ($_POST['delete_item'] as $piid => $delete_item) {

            if ($valid && $delete_item == "Y" && $profile_item_name = profile_item_get_name($piid)) {

                if (($section_name = profile_section_get_name($_POST['psid']))) {

                    if (profile_item_delete($piid)) {

                        admin_add_log_entry(DELETE_PROFILE_ITEM, array($section_name, $profile_item_name));

                    }else {

                        $error_msg_array[] = gettext("Failed to remove profile items");
                        $valid = false;
                    }
                }
            }
        }

        if ($valid) {

            header_redirect("admin_prof_items.php?webtag=$webtag&psid=$psid&deleted=true");
            exit;
        }
    }
}

if (isset($_POST['cancel'])) {

    header_redirect("admin_prof_items.php?webtag=$webtag&psid=$psid");
    exit;
}

if (isset($_POST['back'])) {

    if (isset($viewitems)) {

        $redirect = "admin_prof_sect.php?webtag=$webtag&page=$sect_page";
        header_redirect($redirect);

    }else {

        $redirect = "admin_prof_sect.php?webtag=$webtag&psid=$psid&page=$sect_page";
        header_redirect($redirect);
    }
}

if (isset($_POST['additemsubmit'])) {

    $valid = true;

    if (isset($_POST['t_name_new']) && strlen(trim(stripslashes_array($_POST['t_name_new']))) > 0) {

        $t_new_name = trim(stripslashes_array($_POST['t_name_new']));

    }else {

        $error_msg_array[] = gettext("You must enter a profile item name");
        $valid = false;
    }

    if (isset($_POST['t_type_new']) && in_array($_POST['t_type_new'], $profile_item_valid_types)) {

        $t_type_new = $_POST['t_type_new'];

    }else {

        $error_msg_array[] = gettext("Invalid profile item type selected");
        $valid = false;
    }

    if (isset($_POST['t_options_new']) && strlen(trim(stripslashes_array($_POST['t_options_new']))) > 0) {

        $t_options_new = trim(stripslashes_array($_POST['t_options_new']));

        if ($valid && ($t_type_new == PROFILE_ITEM_RADIO || $t_type_new == PROFILE_ITEM_DROPDOWN)) {

            if (sizeof(explode("\n", $t_options_new)) < 1) {

                $error_msg_array[] = gettext("You must enter more than one option for selected profile item type");
                $valid = false;
            }

        }else if ($valid && $t_type_new == PROFILE_ITEM_HYPERLINK) {

            $check_url = parse_url($t_options_new);

            if (!isset($check_url['scheme']) || $check_url['scheme'] != "http") {

                $valid = false;
                $error_msg_array[] = gettext("Profile item hyperlinks support HTTP URLs only");
            }

            if ($valid && (!isset($check_url['host']) || strlen(trim($check_url['host'])) < 1)) {

                $valid = false;
                $error_msg_array[] = gettext("Profile item hyperlink format invalid");
            }

            if (preg_match('/\[ProfileEntry\]/iu', $t_options_new) < 1) {

                $error_msg_array[] = sprintf(gettext("You must include <i>%s</i> in the URL of clickable hyperlinks"), '[ProfileEntry]');
                $valid = false;
            }
        }

    }else if ($valid && ($t_type_new == PROFILE_ITEM_RADIO || $t_type_new == PROFILE_ITEM_DROPDOWN || $t_type_new == PROFILE_ITEM_HYPERLINK)) {

        $error_msg_array[] = gettext("You must enter some options for selected profile item type");
        $valid = false;

    }else {

        $t_options_new = "";
    }

    if ($valid) {

        if (($new_piid = profile_item_create($psid, $t_new_name, $t_type_new, $t_options_new))) {

            $t_section_name = profile_section_get_name($psid);

            admin_add_log_entry(ADDED_PROFILE_ITEM, array($t_section_name, $t_new_name));
            header_redirect("admin_prof_items.php?webtag=$webtag&psid=$psid&added=true");
            exit;

        }else {

            $error_msg_error[] = gettext("Failed to create new profile item");
            $valid = false;
        }
    }

}elseif (isset($_POST['edititemsubmit'])) {

    $valid = true;

    if (isset($_POST['piid']) && is_numeric($_POST['piid'])) {

        $piid = $_POST['piid'];

    }else {

        $error_msg_array[] = gettext("Invalid profile item ID or item not found");
        $valid = false;
    }

    if (isset($_POST['t_name_new']) && strlen(trim(stripslashes_array($_POST['t_name_new']))) > 0) {

        $t_name_new = trim(stripslashes_array($_POST['t_name_new']));

    }else {

        $error_msg_array[] = gettext("You must enter a profile item name");
        $valid = false;
    }

    if (isset($_POST['t_type_new']) && in_array($_POST['t_type_new'], $profile_item_valid_types)) {

        $t_type_new = $_POST['t_type_new'];

    }else {

        $error_msg_array[] = gettext("Invalid profile item type selected");
        $valid = false;
    }

    if (isset($_POST['t_options_new']) && strlen(trim(stripslashes_array($_POST['t_options_new']))) > 0) {

        $t_options_new = trim(stripslashes_array($_POST['t_options_new']));

        if ($valid && ($t_type_new == PROFILE_ITEM_RADIO || $t_type_new == PROFILE_ITEM_DROPDOWN)) {

            if (sizeof(explode("\n", $t_options_new)) < 1) {

                $error_msg_array[] = gettext("You must enter more than one option for selected profile item type");
                $valid = false;
            }

        }else if ($valid && $t_type_new == PROFILE_ITEM_HYPERLINK) {

            $check_url = parse_url($t_options_new);

            if (!isset($check_url['scheme']) || $check_url['scheme'] != "http") {

                $valid = false;
                $error_msg_array[] = gettext("Profile item hyperlinks support HTTP URLs only");
            }

            if ($valid && (!isset($check_url['host']) || strlen(trim($check_url['host'])) < 1)) {

                $valid = false;
                $error_msg_array[] = gettext("Profile item hyperlink format invalid");
            }

            if (preg_match('/\[ProfileEntry\]/iu', $t_options_new) < 1) {

                $error_msg_array[] = sprintf(gettext("You must include <i>%s</i> in the URL of clickable hyperlinks"), '[ProfileEntry]');
                $valid = false;
            }
        }

    }else if ($valid && ($t_type_new == PROFILE_ITEM_RADIO || $t_type_new == PROFILE_ITEM_DROPDOWN || $t_type_new == PROFILE_ITEM_HYPERLINK)) {

        $error_msg_array[] = gettext("You must enter some options for selected profile item type");
        $valid = false;

    }else {

        $t_options_new = "";
    }

    if (isset($_POST['t_section_new']) && is_numeric($_POST['t_section_new'])) {

        $t_section_new = $_POST['t_section_new'];

    }else {

        $error_msg_array[] = gettext("Invalid profile section ID or section not found");
        $valid = false;
    }

    if ($valid) {

        if (profile_item_update($piid, $t_section_new, $t_type_new, $t_name_new, $t_options_new)) {

            $profile_item = profile_get_item($piid);

            if (($t_name_new != $profile_item['NAME']) || ($t_type_new != $profile_item['TYPE']) || ($t_section_new != $psid) || ($t_options_new != $profile_item['OPTIONS'])) {

                $log_data = array($t_name_new, $profile_item['NAME'], $t_type_new, $profile_item['TYPE'], $t_section_new, $psid);
                admin_add_log_entry(CHANGE_PROFILE_ITEM, $log_data);
            }

            header_redirect("admin_prof_items.php?webtag=$webtag&psid=$psid&edited=true");
            exit;

        }else {

            $error_msg_array[] = gettext("Failed to update profile item");
            $valid = false;
        }
    }

}elseif (isset($_POST['additem'])) {

    $redirect = "admin_prof_items.php?webtag=$webtag&psid=$psid&additem=true&sect_page=$sect_page";
    header_redirect($redirect);
    exit;
}

if (isset($_POST['move_up']) && is_array($_POST['move_up'])) {

    list($piid) = array_keys($_POST['move_up']);
    profile_item_move_up($psid, $piid);
}

if (isset($_POST['move_down']) && is_array($_POST['move_down'])) {

    list($piid) = array_keys($_POST['move_down']);
    profile_item_move_down($psid, $piid);
}

if (isset($_GET['additem']) || isset($_POST['additem'])) {

    html_draw_top("title=", gettext("Admin"), " - ", gettext("Manage Profile Sections"), " - ". profile_section_get_name($psid). " - ", gettext("Add new item"), "", 'class=window_title');

    echo "<h1>", gettext("Admin"), "<img src=\"", html_style_image('separator.png'), "\" alt=\"\" border=\"0\" />", gettext("Manage Profile Sections"), "<img src=\"", html_style_image('separator.png'), "\" alt=\"\" border=\"0\" />", profile_section_get_name($psid), "<img src=\"", html_style_image('separator.png'), "\" alt=\"\" border=\"0\" />", gettext("Add new item"), "</h1>\n";

    if (isset($error_msg_array) && sizeof($error_msg_array) > 0) {
        html_display_error_array($error_msg_array, '500', 'center');
    }

    echo "<br />\n";
    echo "<div align=\"center\">\n";
    echo "<form accept-charset=\"utf-8\" name=\"f_sections\" action=\"admin_prof_items.php\" method=\"post\">\n";
    echo "  ", form_input_hidden('webtag', htmlentities_array($webtag)), "\n";
    echo "  ", form_input_hidden("psid", htmlentities_array($psid)), "\n";
    echo "  ", form_input_hidden("sect_page", htmlentities_array($sect_page)), "\n";

    if (isset($viewitems)) echo "  ", form_input_hidden("viewitems", "yes"), "\n";

    echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"500\">\n";
    echo "    <tr>\n";
    echo "      <td align=\"left\">\n";
    echo "        <table class=\"box\" width=\"100%\">\n";
    echo "          <tr>\n";
    echo "            <td align=\"left\" class=\"posthead\">\n";
    echo "              <table class=\"posthead\" width=\"100%\">\n";
    echo "                <tr>\n";
    echo "                  <td class=\"subhead\" align=\"left\" colspan=\"2\">", gettext("Add new item"), "</td>\n";
    echo "                </tr>\n";
    echo "                <tr>\n";
    echo "                  <td align=\"center\">\n";
    echo "                    <table class=\"posthead\" width=\"95%\">\n";
    echo "                      <tr>\n";
    echo "                        <td align=\"left\" width=\"150\">", gettext("Type"), ":</td>\n";
    echo "                        <td align=\"left\">", form_dropdown_array("t_type_new", $item_types_array), "</td>\n";
    echo "                      </tr>\n";
    echo "                      <tr>\n";
    echo "                        <td align=\"left\" width=\"150\">", gettext("Item Name"), ":</td>\n";
    echo "                        <td align=\"left\">", form_input_text("t_name_new", (isset($_POST['t_name_new']) ? htmlentities_array(stripslashes_array($_POST['t_name_new'])) : ""), 48, 64), "</td>\n";
    echo "                      </tr>\n";
    echo "                      <tr>\n";
    echo "                        <td align=\"left\" width=\"150\" valign=\"top\">", gettext("Options"), ":</td>\n";
    echo "                        <td align=\"left\">", form_textarea("t_options_new", (isset($_POST['t_options_new']) ? htmlentities_array(stripslashes_array($_POST['t_options_new'])) : ""), 4, 45), "</td>\n";
    echo "                      </tr>\n";
    echo "                      <tr>\n";
    echo "                        <td align=\"left\" colspan=\"4\">&nbsp;</td>\n";
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
    echo "      <td align=\"center\">", form_submit("additemsubmit", gettext("Add")), "&nbsp;", form_submit("cancel", gettext("Cancel")), "</td>\n";
    echo "    </tr>\n";
    echo "  </table>\n";

    html_display_warning_msg(gettext("To create Radio Buttons or a Drop Down List you need to enter each individual value on a separate line in the Options field."), '500', 'center');

    html_display_warning_msg(gettext("To create clickable links enter the URL in the Options field and use <i>[ProfileEntry]</i> where the entry from the user's profile should appear. Examples: <p>MySpace: <i>http://www.myspace.com/[ProfileEntry]</i><br />Xbox LIVE: <i>http://profile.mygamercard.net/[ProfileEntry]</i></p>"), '500', 'center');

    echo "</form>\n";
    echo "</div>\n";

    html_draw_bottom();

}elseif (isset($_GET['piid']) || isset($_POST['piid'])) {

    if (isset($_POST['piid']) && is_numeric($_POST['piid'])) {

        $piid = $_POST['piid'];

    }elseif (isset($_GET['piid']) && is_numeric($_GET['piid'])) {

        $piid = $_GET['piid'];

    }else {

        html_draw_top(sprintf("title=%s", gettext("Error")));
        html_error_msg(gettext("Invalid profile item ID or item not found"), 'admin_prof_sect.php', 'get', array('back' => gettext("Back")));
        html_draw_bottom();
        exit;
    }

    if (!$profile_item = profile_get_item($piid)) {

        html_draw_top(sprintf("title=%s", gettext("Error")));
        html_error_msg(gettext("Invalid profile item ID or item not found"), 'admin_prof_sect.php', 'get', array('back' => gettext("Back")));
        html_draw_bottom();
        exit;
    }

    html_draw_top("title=", gettext("Admin"), " - ", gettext("Manage Profile Sections"), " - ". profile_section_get_name($psid). " - ", gettext("Edit item"), " - {$profile_item['NAME']}", 'class=window_title');

    echo "<h1>", gettext("Admin"), "<img src=\"", html_style_image('separator.png'), "\" alt=\"\" border=\"0\" />", gettext("Manage Profile Sections"), "<img src=\"", html_style_image('separator.png'), "\" alt=\"\" border=\"0\" />", profile_section_get_name($psid), "<img src=\"", html_style_image('separator.png'), "\" alt=\"\" border=\"0\" />", gettext("Edit item"), "<img src=\"", html_style_image('separator.png'), "\" alt=\"\" border=\"0\" />", word_filter_add_ob_tags($profile_item['NAME'], true), "</h1>\n";

    if (isset($error_msg_array) && sizeof($error_msg_array) > 0) {
        html_display_error_array($error_msg_array, '500', 'center');
    }

    echo "<br />\n";
    echo "<div align=\"center\">\n";
    echo "<form accept-charset=\"utf-8\" name=\"f_sections\" action=\"admin_prof_items.php\" method=\"post\">\n";
    echo "  ", form_input_hidden('webtag', htmlentities_array($webtag)), "\n";
    echo "  ", form_input_hidden("psid", htmlentities_array($psid)), "\n";
    echo "  ", form_input_hidden("piid", htmlentities_array($piid)), "\n";
    echo "  ", form_input_hidden("sect_page", htmlentities_array($sect_page)), "\n";
    echo "  ", form_input_hidden("delete_item[$piid]", "Y"), "\n";

    if (isset($viewitems)) echo "  ", form_input_hidden("viewitems", "yes"), "\n";

    echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"500\">\n";
    echo "    <tr>\n";
    echo "      <td align=\"left\">\n";
    echo "        <table class=\"box\" width=\"100%\">\n";
    echo "          <tr>\n";
    echo "            <td align=\"left\" class=\"posthead\">\n";
    echo "              <table class=\"posthead\" width=\"100%\">\n";
    echo "                <tr>\n";
    echo "                  <td class=\"subhead\" align=\"left\" colspan=\"2\">", gettext("Edit item"), "</td>\n";
    echo "                </tr>\n";
    echo "                <tr>\n";
    echo "                  <td align=\"center\">\n";
    echo "                    <table class=\"posthead\" width=\"95%\">\n";
    echo "                      <tr>\n";
    echo "                        <td align=\"left\" width=\"150\">", gettext("Type"), ":</td>\n";
    echo "                        <td align=\"left\">", form_dropdown_array("t_type_new", $item_types_array, (isset($_POST['t_type_new']) && is_numeric($_POST['t_type_new']) ? $_POST['t_type_new'] : $profile_item['TYPE'])), "</td>\n";
    echo "                      </tr>\n";
    echo "                      <tr>\n";
    echo "                        <td align=\"left\" width=\"150\">", gettext("Section Name"), ":</td>\n";
    echo "                        <td align=\"left\">", profile_section_dropdown($psid, "t_section_new"), "</td>\n";
    echo "                      </tr>\n";
    echo "                      <tr>\n";
    echo "                        <td align=\"left\" width=\"150\">", gettext("Item Name"), ":</td>\n";
    echo "                        <td align=\"left\">", form_input_text("t_name_new", (isset($_POST['t_name_new']) ? htmlentities_array(stripslashes_array($_POST['t_name_new'])) : htmlentities_array($profile_item['NAME'])), 48, 64), "</td>\n";
    echo "                      </tr>\n";
    echo "                      <tr>\n";
    echo "                        <td align=\"left\" width=\"150\" valign=\"top\">", gettext("Options"), ":</td>\n";
    echo "                        <td align=\"left\">", form_textarea("t_options_new", (isset($_POST['t_options_new']) ? htmlentities_array(stripslashes_array($_POST['t_options_new'])) : htmlentities_array($profile_item['OPTIONS'])), 4, 45), "</td>\n";
    echo "                      </tr>\n";
    echo "                      <tr>\n";
    echo "                        <td align=\"left\" colspan=\"4\">&nbsp;</td>\n";
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
    echo "      <td align=\"center\">", form_submit("edititemsubmit", gettext("Save")), "&nbsp;", form_submit("delete", gettext("Delete")), "&nbsp;", form_submit("cancel", gettext("Cancel")), "</td>\n";
    echo "    </tr>\n";
    echo "  </table>\n";

    html_display_warning_msg(gettext("To create Radio Buttons or a Drop Down List you need to enter each individual value on a separate line in the Options field."), '500', 'center');

    html_display_warning_msg(gettext("To create clickable links enter the URL in the Options field and use <i>[ProfileEntry]</i> where the entry from the user's profile should appear. Examples: <p>MySpace: <i>http://www.myspace.com/[ProfileEntry]</i><br />Xbox LIVE: <i>http://profile.mygamercard.net/[ProfileEntry]</i></p>"), '500', 'center');

    echo "</form>\n";
    echo "</div>\n";

    html_draw_bottom();

}else {

    html_draw_top("title=", gettext("Admin"), " - ", gettext("Manage Profile Sections"), " - ". profile_section_get_name($psid). " - ", gettext("View items"), "", 'class=window_title');

    $profile_items = profile_items_get_by_page($psid, $start);

    echo "<h1>", gettext("Admin"), "<img src=\"", html_style_image('separator.png'), "\" alt=\"\" border=\"0\" />", gettext("Manage Profile Sections"), "<img src=\"", html_style_image('separator.png'), "\" alt=\"\" border=\"0\" />", profile_section_get_name($psid), "<img src=\"", html_style_image('separator.png'), "\" alt=\"\" border=\"0\" />", gettext("View items"), "</h1>\n";

    if (isset($error_msg_array) && sizeof($error_msg_array) > 0) {

        html_display_error_array($error_msg_array, '500', 'center');

    }else if (isset($_GET['added'])) {

        html_display_success_msg(gettext("Successfully added new profile item"), '500', 'center');

    }else if (isset($_GET['edited'])) {

        html_display_success_msg(gettext("Successfully edited profile item"), '500', 'center');

    }else if (isset($_GET['deleted'])) {

        html_display_success_msg(gettext("Successfully removed selected profile items"), '500', 'center');

    }else if (sizeof($profile_items['profile_items_array']) < 1) {

        html_display_warning_msg(gettext("There are no existing profile items in this section. To add an item click the 'Add New' button below."), '500', 'center');
    }

    echo "<br />\n";
    echo "<div align=\"center\">\n";
    echo "<form accept-charset=\"utf-8\" name=\"f_sections\" action=\"admin_prof_items.php\" method=\"post\">\n";
    echo "  ", form_input_hidden('webtag', htmlentities_array($webtag)), "\n";
    echo "  ", form_input_hidden("psid", htmlentities_array($psid)), "\n";
    echo "  ", form_input_hidden("sect_page", htmlentities_array($sect_page)), "\n";

    if (isset($viewitems)) echo "  ", form_input_hidden("viewitems", "yes"), "\n";

    echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"500\">\n";
    echo "    <tr>\n";
    echo "      <td align=\"left\">\n";
    echo "        <table class=\"box\" width=\"100%\">\n";
    echo "          <tr>\n";
    echo "            <td align=\"left\" class=\"posthead\">\n";
    echo "              <table class=\"posthead\" width=\"100%\">\n";
    echo "                <tr>\n";
    echo "                  <td class=\"subhead\" align=\"left\">&nbsp;</td>\n";
    echo "                  <td class=\"subhead\" align=\"left\">", gettext("Item Name"), "</td>\n";
    echo "                  <td class=\"subhead\" align=\"left\">&nbsp;</td>\n";
    echo "                  <td class=\"subhead\" align=\"left\">", gettext("Type"), "</td>\n";
    echo "                </tr>\n";

    if (sizeof($profile_items['profile_items_array']) > 0) {

        $profile_index = 0;

        foreach ($profile_items['profile_items_array'] as $profile_item) {

            $profile_index++;

            echo "                <tr>\n";
            echo "                  <td valign=\"top\" align=\"center\" width=\"1%\">", form_checkbox("delete_item[{$profile_item['PIID']}]", "Y", false), "</td>\n";

            if ($profile_items['profile_items_count'] == 1) {

                echo "                  <td valign=\"top\" align=\"left\" colspan=\"2\"><a href=\"admin_prof_items.php?webtag=$webtag&amp;psid=$psid&amp;piid={$profile_item['PIID']}&amp;sect_page=$sect_page\">", word_filter_add_ob_tags($profile_item['NAME'], true), "</a></td>\n";
                echo "                  <td align=\"right\">&nbsp;</td>\n";

            }elseif ($profile_index == $profile_items['profile_items_count']) {

                echo "                  <td valign=\"top\" align=\"left\"><a href=\"admin_prof_items.php?webtag=$webtag&amp;psid=$psid&amp;piid={$profile_item['PIID']}&amp;sect_page=$sect_page\">", word_filter_add_ob_tags($profile_item['NAME'], true), "</a></td>\n";
                echo "                  <td align=\"right\" width=\"40\" style=\"white-space: nowrap\">", form_submit_image('move_up.png', "move_up[{$profile_item['PIID']}]", "Move Up", "title=\"Move Up\"", "move_up_ctrl"), form_submit_image('move_down.png', "move_down_disabled", "Move Down", "title=\"Move Down\"", "move_down_ctrl_disabled"), "</td>\n";

            }elseif ($profile_index > 1) {

                echo "                  <td valign=\"top\" align=\"left\"><a href=\"admin_prof_items.php?webtag=$webtag&amp;psid=$psid&amp;piid={$profile_item['PIID']}&amp;sect_page=$sect_page\">", word_filter_add_ob_tags($profile_item['NAME'], true), "</a></td>\n";
                echo "                  <td align=\"right\" width=\"40\" style=\"white-space: nowrap\">", form_submit_image('move_up.png', "move_up[{$profile_item['PIID']}]", "Move Up", "title=\"Move Up\"", "move_up_ctrl"), form_submit_image('move_down.png', "move_down[{$profile_item['PIID']}]", "Move Down", "title=\"Move Down\"", "move_down_ctrl"), "</td>\n";

            }else {

                echo "                  <td valign=\"top\" align=\"left\"><a href=\"admin_prof_items.php?webtag=$webtag&amp;psid=$psid&amp;piid={$profile_item['PIID']}&amp;sect_page=$sect_page\">", word_filter_add_ob_tags($profile_item['NAME'], true), "</a></td>\n";
                echo "                  <td align=\"right\" width=\"40\" style=\"white-space: nowrap\">", form_submit_image('move_up.png', "move_up_disabled", "Move Up", "title=\"Move Up\"", "move_up_ctrl_disabled"), form_submit_image('move_down.png', "move_down[{$profile_item['PIID']}]", "Move Down", "title=\"Move Down\"", "move_down_ctrl"), "</td>\n";
            }

            if (isset($item_types_array[$profile_item['TYPE']])) {

                echo "                  <td valign=\"top\" align=\"left\">{$item_types_array[$profile_item['TYPE']]}</td>\n";

            }else {

                echo "                  <td valign=\"top\" align=\"left\">", gettext("Text Field"), "</td>\n";
            }

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
    echo "      <td class=\"postbody\" align=\"center\">", page_links("admin_prof_items.php?webtag=$webtag&psid=$psid&sect_page=$sect_page", $start, $profile_items['profile_items_count'], 10), "</td>\n";
    echo "    </tr>\n";
    echo "    <tr>\n";
    echo "      <td align=\"left\">&nbsp;</td>\n";
    echo "    </tr>\n";
    echo "    <tr>\n";
    echo "      <td align=\"center\">", form_submit("additem", gettext("Add New")), "&nbsp;", form_submit("delete", gettext("Delete Selected")), "&nbsp;", form_submit("back", gettext("Back")), "</td>\n";
    echo "    </tr>\n";
    echo "  </table>\n";
    echo "</form>\n";
    echo "</div>\n";

    html_draw_bottom();
}

?>