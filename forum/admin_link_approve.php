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
include_once(BH_INCLUDE_PATH. "fixhtml.inc.php");
include_once(BH_INCLUDE_PATH. "folder.inc.php");
include_once(BH_INCLUDE_PATH. "form.inc.php");
include_once(BH_INCLUDE_PATH. "format.inc.php");
include_once(BH_INCLUDE_PATH. "header.inc.php");
include_once(BH_INCLUDE_PATH. "html.inc.php");
include_once(BH_INCLUDE_PATH. "lang.inc.php");
include_once(BH_INCLUDE_PATH. "logon.inc.php");
include_once(BH_INCLUDE_PATH. "messages.inc.php");
include_once(BH_INCLUDE_PATH. "poll.inc.php");
include_once(BH_INCLUDE_PATH. "links.inc.php");
include_once(BH_INCLUDE_PATH. "session.inc.php");
include_once(BH_INCLUDE_PATH. "thread.inc.php");
include_once(BH_INCLUDE_PATH. "threads.inc.php");
include_once(BH_INCLUDE_PATH. "user.inc.php");
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

// Array to hold error messages
$error_msg_array = array();

// Page number
if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $page = ($_GET['page'] > 0) ? $_GET['page'] : 1;
} else {
    $page = 1;
}

$start = floor($page - 1) * 10;
if ($start < 0) $start = 0;

// Are we returning somewhere?
if (isset($_GET['ret']) && strlen(trim(stripslashes_array($_GET['ret']))) > 0) {
    $ret = href_cleanup_query_keys($_GET['ret']);
} else if (isset($_POST['ret']) && strlen(trim(stripslashes_array($_POST['ret']))) > 0) {
    $ret = href_cleanup_query_keys($_POST['ret']);
} else {
    $ret = "admin_link_approve.php?webtag=$webtag";
}

// validate the return to page
if (isset($ret) && strlen(trim($ret)) > 0) {

    $available_files = array('admin_link_approve.php', 'links_detail.php', 'links.php');
    $available_files_preg = implode("|^", array_map('preg_quote_callback', $available_files));

    if (!preg_match("/^$available_files_preg/u", $ret)) {
        $ret = "admin_link_approve.php?webtag=$webtag";
    }
}

if (isset($_POST['cancel'])) {
    header_redirect($ret);
}

// Check POST and GET for message ID and check it is valid.
if (isset($_POST['lid'])) {

    if (is_numeric($_POST['lid'])) {

        $lid = $_POST['lid'];

    } else {

        html_draw_top(sprintf("title=%s", gettext("Error")));
        html_error_msg(gettext("Invalid link id or link not found"), 'admin_link_approve.php', 'post', array('cancel' => gettext("Cancel")), array('ret' => $ret), '_self', 'center');
        html_draw_bottom();
        exit;
    }

} else if (isset($_GET['lid'])) {

    if (is_numeric($_GET['lid'])) {

        $lid = $_GET['lid'];

    } else {

        html_draw_top(sprintf("title=%s", gettext("Error")));
        html_error_msg(gettext("Invalid link id or link not found"), 'admin_link_approve.php', 'post', array('cancel' => gettext("Cancel")), array('ret' => $ret), '_self', 'center');
        html_draw_bottom();
        exit;
    }
}

if (isset($lid) && is_numeric($lid)) {

    if (!session_check_perm(USER_PERM_LINKS_MODERATE, 0)) {

        html_draw_top(sprintf("title=%s", gettext("Error")));
        html_error_msg(gettext("Cannot edit links"), 'admin_link_approve.php', 'post', array('cancel' => gettext("Cancel")), array('ret' => $ret), '_self', 'center');
        html_draw_bottom();
        exit;
    }
    
    if (($link = links_get_single($lid, false))) {
        
        if (isset($link['APPROVED']) && ($link['APPROVED'] > 0)) {

            html_draw_top(sprintf("title=%s", gettext("Error")));
            html_error_msg(gettext("Link does not require approval"), 'admin_link_approve.php', 'post', array('cancel' => gettext("Cancel")), array('ret' => $ret), '_self', 'center');
            html_draw_bottom();
            exit;
        }
        
        if (isset($_POST['approve'])) {

            if (links_approve($lid)) {

                admin_add_log_entry(APPROVED_LINK, array($lid));

                if (preg_match("/^links_detail.php/u", $ret) > 0) {

                    header_redirect("links_detail.php?webtag=$webtag&lid=$lid&link_approve_success=$lid");
                    exit;

                } else {

                    html_draw_top("title=", gettext("Approve Link"), "", 'class=window_title');
                    html_display_msg(gettext("Approve Link"), sprintf(gettext("Successfully approved link"), $lid), "admin_link_approve.php", 'get', array('back' => gettext("Back")), array('ret' => $ret), '_self', 'center');
                    html_draw_bottom();
                    exit;
                }

            } else {

                $error_msg_array[] = gettext("Link approval failed");
            }

        } else if (isset($_POST['delete'])) {

            if (links_delete($lid)) {

                if (session_check_perm(USER_PERM_FOLDER_MODERATE, 0) && ($link['UID'] != session_get_value('UID'))) {
                    admin_add_log_entry(DELETE_LINK, array($lid));
                }

                if (preg_match("/^links_detail.php/u", $ret) > 0) {

                    header_redirect("links_detail.php?webtag=$webtag&lid=$lid&link_approve_success=$lid");
                    exit;

                } else {

                    html_draw_top("title=", gettext("Approve Link"), "", 'class=window_title');
                    html_display_msg(gettext("Approve Link"), sprintf(gettext("Successfully deleted link"), $lid), "admin_link_approve.php", 'get', array('back' => gettext("Back")), array('ret' => $ret), '_self', 'center');
                    html_draw_bottom();
                    exit;
                }

            } else {

                $error_msg_array[] = gettext("Error deleting link");
            }
        }        
        
        html_draw_top("title=", gettext("Admin"), " - ", gettext("Approve Link"), "", 'class=window_title', "post.js", "resize_width=86%");
        
        echo "<h1>", gettext("Admin"), "<img src=\"", html_style_image('separator.png'), "\" alt=\"\" border=\"0\" />", gettext("Approve Link"), "</h1>\n";
        
        if (isset($error_msg_array) && sizeof($error_msg_array) > 0) {
            html_display_error_array($error_msg_array, '86%', 'left');
        }        
        
        echo "<br />\n";
        echo "<div align=\"center\">\n";
        echo "<form accept-charset=\"utf-8\" name=\"f_delete\" action=\"admin_link_approve.php\" method=\"post\" target=\"_self\">\n";
        echo "  ", form_input_hidden('webtag', htmlentities_array($webtag)), "\n";
        echo "  ", form_input_hidden('lid', htmlentities_array($lid)), "\n";
        echo "  ", form_input_hidden("ret", htmlentities_array($ret)), "\n";
        echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"700\">\n";
        echo "    <tr>\n";
        echo "      <td align=\"left\">\n";
        echo "        <table class=\"box\" width=\"100%\">\n";
        echo "          <tr>\n";
        echo "            <td align=\"left\" class=\"posthead\">\n";
        echo "              <table class=\"posthead\" width=\"100%\">\n";
        echo "                <tr>\n";
        echo "                  <td align=\"left\" class=\"subhead\" colspan=\"2\">", gettext("Link Details"), "</td>\n";
        echo "                </tr>\n";
        echo "                <tr>\n";
        echo "                  <td align=\"center\">\n";
        echo "                    <table class=\"posthead\" width=\"95%\">\n";
        echo "                      <tr>\n";
        echo "                        <td align=\"left\" style=\"white-space: nowrap\" valign=\"top\" width=\"120\">", gettext("Address"), ":</td>\n";
        echo "                        <td align=\"left\"><a href=\"links.php?webtag=$webtag&amp;lid=$lid&amp;action=go\" target=\"_blank\">", mb_strlen($link['URI']) > 35 ? htmlentities_array(mb_substr($link['URI'], 0, 35)) . '&hellip;' : htmlentities_array($link['URI']), "</a></td>\n";
        echo "                      </tr>\n";
        echo "                      <tr>\n";
        echo "                        <td align=\"left\" style=\"white-space: nowrap\" valign=\"top\">", gettext("Submitted by"), ":</td>\n";
        echo "                        <td align=\"left\">", (isset($link['LOGON']) ? word_filter_add_ob_tags(format_user_name($link['LOGON'], $link['NICKNAME']), true) : gettext("Unknown user")), "</td>\n";
        echo "                      </tr>\n";
        echo "                      <tr>\n";
        echo "                        <td align=\"left\" style=\"white-space: nowrap\" valign=\"top\">", gettext("Description"), ":</td>\n";
        echo "                        <td align=\"left\">", word_filter_add_ob_tags($link['DESCRIPTION'], true), "</td>\n";
        echo "                      </tr>\n";
        echo "                      <tr>\n";
        echo "                        <td align=\"left\" style=\"white-space: nowrap\" valign=\"top\">", gettext("Date"), ":</td>\n";
        echo "                        <td align=\"left\">", format_time($link['CREATED']), "</td>\n";
        echo "                      </tr>\n";
        echo "                      <tr>\n";
        echo "                        <td align=\"left\" colspan=\"3\">&nbsp;</td>\n";
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
        echo "      <td>&nbsp;</td>\n";
        echo "    </tr>\n";        
        echo "    <tr>\n";
        echo "      <td align=\"center\">", form_submit("approve", gettext("Approve")), "&nbsp;", form_submit("delete", gettext("Delete")), "&nbsp;", form_submit("cancel", gettext("Cancel")), "</td>\n";
        echo "    </tr>\n";        
        echo "  </table>\n";
        echo "</form>\n";
        echo "</div>\n";    
        
        html_draw_bottom();
    
    } else {        

        html_draw_top(sprintf("title=%s", gettext("Error")));
        html_error_msg(gettext("Invalid link ID!"), 'admin_link_approve.php', 'post', array('cancel' => gettext("Cancel")), array('ret' => $ret), '_self', 'center');
        html_draw_bottom();
        exit;
    }    

} else {

    if (!session_check_perm(USER_PERM_LINKS_MODERATE, 0)) {

        html_draw_top(sprintf("title=%s", gettext("Error")));
        html_error_msg(gettext("You do not have permission to use this section."));
        html_draw_bottom();
        exit;
    }

    html_draw_top("title=", gettext("Admin"), " - ", gettext("Link Approval Queue"), "", 'class=window_title');

    $link_approval_array = admin_get_link_approval_queue($start);

    echo "<h1>", gettext("Admin"), "<img src=\"", html_style_image('separator.png'), "\" alt=\"\" border=\"0\" />", gettext("Link Approval Queue"), "</h1>\n";

    if (sizeof($link_approval_array['link_array']) < 1) {
        html_display_warning_msg(gettext("No links are awaiting approval"), '86%', 'center');
    }

    echo "<br />\n";
    echo "<div align=\"center\">\n";
    echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"86%\">\n";
    echo "    <tr>\n";
    echo "      <td align=\"left\">\n";
    echo "        <table class=\"box\" width=\"100%\">\n";
    echo "          <tr>\n";
    echo "            <td align=\"left\" class=\"posthead\">\n";
    echo "              <table class=\"posthead\" width=\"100%\">\n";
    echo "                 <tr>\n";
    echo "                   <td class=\"subhead\" align=\"left\" width=\"20\">&nbsp;</td>\n";
    echo "                   <td class=\"subhead\" align=\"left\">", gettext("Name"), "</td>\n";
    echo "                   <td class=\"subhead\" align=\"left\">", gettext("Folder"), "</td>\n";
    echo "                   <td class=\"subhead\" align=\"left\" width=\"200\">", gettext("User"), "</td>\n";
    echo "                   <td class=\"subhead\" align=\"left\" width=\"200\">", gettext("Date/Time"), "</td>\n";
    echo "                 </tr>\n";

    if (sizeof($link_approval_array['link_array']) > 0) {

        foreach ($link_approval_array['link_array'] as $link_approval_entry) {

            echo "                 <tr>\n";
            echo "                   <td align=\"left\" width=\"20\">&nbsp;</td>\n";
            echo "                   <td align=\"left\"><a href=\"admin_link_approve.php?webtag=$webtag&lid={$link_approval_entry['LID']}\" target=\"_self\">", word_filter_add_ob_tags($link_approval_entry['TITLE'], true), "</a></td>\n";
            echo "                   <td align=\"left\">{$link_approval_entry['FOLDER_TITLE']}</td>\n";
            echo "                   <td align=\"left\"><a href=\"user_profile.php?webtag=$webtag&amp;uid={$link_approval_entry['UID']}\" target=\"_blank\" class=\"popup 650x500\">", word_filter_add_ob_tags(format_user_name($link_approval_entry['LOGON'], $link_approval_entry['NICKNAME']), true) . "</a></td>\n";
            echo "                   <td align=\"left\">", format_time($link_approval_entry['CREATED']), "</td>\n";
            echo "                 </tr>\n";
        }
    }

    echo "                 <tr>\n";
    echo "                   <td align=\"left\" colspan=\"3\">&nbsp;</td>\n";
    echo "                 </tr>\n";
    echo "               </table>\n";
    echo "             </td>\n";
    echo "           </tr>\n";
    echo "         </table>\n";
    echo "       </td>\n";
    echo "    </tr>\n";
    echo "    <tr>\n";
    echo "      <td align=\"left\">&nbsp;</td>\n";
    echo "    </tr>\n";
    echo "    <tr>\n";
    echo "      <td class=\"postbody\" align=\"center\">", page_links("admin_link_approve.php?webtag=$webtag&ret=$ret", $start, $link_approval_array['link_count'], 10), "</td>\n";
    echo "    </tr>\n";
    echo "  </table>\n";
    echo "</div>\n";

    html_draw_bottom();
}

?>