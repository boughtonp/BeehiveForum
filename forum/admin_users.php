<?php

/*======================================================================
Copyright Project BeehiveForum 2002

This file is part of BeehiveForum.

BeehiveForum is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

BeehiveForum is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Beehive; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307
USA
======================================================================*/

/* $Id: admin_users.php,v 1.123 2006-11-20 22:10:23 decoyduck Exp $ */

// Constant to define where the include files are
define("BH_INCLUDE_PATH", "./include/");

// Compress the output
include_once(BH_INCLUDE_PATH. "gzipenc.inc.php");

// Enable the error handler
include_once(BH_INCLUDE_PATH. "errorhandler.inc.php");

// Installation checking functions
include_once(BH_INCLUDE_PATH. "install.inc.php");

// Server checking functions
include_once(BH_INCLUDE_PATH. "server.inc.php");

// Check that Beehive is installed correctly
check_install();

// Multiple forum support
include_once(BH_INCLUDE_PATH. "forum.inc.php");

// Fetch the forum settings
$forum_settings = forum_get_settings();

include_once(BH_INCLUDE_PATH. "admin.inc.php");
include_once(BH_INCLUDE_PATH. "constants.inc.php");
include_once(BH_INCLUDE_PATH. "db.inc.php");
include_once(BH_INCLUDE_PATH. "format.inc.php");
include_once(BH_INCLUDE_PATH. "header.inc.php");
include_once(BH_INCLUDE_PATH. "html.inc.php");
include_once(BH_INCLUDE_PATH. "lang.inc.php");
include_once(BH_INCLUDE_PATH. "logon.inc.php");
include_once(BH_INCLUDE_PATH. "perm.inc.php");
include_once(BH_INCLUDE_PATH. "session.inc.php");

// Check we're logged in correctly

if (!$user_sess = bh_session_check()) {
    $request_uri = rawurlencode(get_request_uri());
    $webtag = get_webtag($webtag_search);
    header_redirect("./logon.php?webtag=$webtag&final_uri=$request_uri");
}

// Check to see if the user is banned.

if (bh_session_user_banned()) {

    html_user_banned();
    exit;
}

// Check we have a webtag

if (!$webtag = get_webtag($webtag_search)) {
    $request_uri = rawurlencode(get_request_uri());
    header_redirect("./forums.php?webtag_search=$webtag_search&final_uri=admin.php%3Fpage%3D$request_uri");
}

// Load language file

$lang = load_language_file();

html_draw_top("openprofile.js");

if (!(bh_session_check_perm(USER_PERM_ADMIN_TOOLS, 0))) {
    echo "<h1>{$lang['accessdenied']}</h1>\n";
    echo "<p>{$lang['accessdeniedexp']}</p>";
    html_draw_bottom();
    exit;
}

// Friendly display names for column sorting

$sort_by_array = array('USER.UID'               => 'User ID',
                       'USER.LOGON'             => 'Logon',
                       'VISITOR_LOG.LAST_LOGON' => 'Last Logon',
                       'SESSIONS.REFERER'        => 'Referer');

// Column sorting stuff

if (isset($_GET['sort_by'])) {
    if ($_GET['sort_by'] == "LOGON") {
        $sort_by = "USER.LOGON";
    } elseif ($_GET['sort_by'] == "LAST_LOGON") {
        $sort_by = "VISITOR_LOG.LAST_LOGON";
    } elseif ($_GET['sort_by'] == "REFERER") {
        $sort_by = "SESSIONS.REFERER";
    } else {
        $sort_by = "VISITOR_LOG.LAST_LOGON";
    }
} else {
    $sort_by = "VISITOR_LOG.LAST_LOGON";
}

if (isset($_GET['sort_dir'])) {
    if ($_GET['sort_dir'] == "DESC") {
        $sort_dir = "DESC";
    } else {
        $sort_dir = "ASC";
    }
} else {
    $sort_dir = "DESC";
}

if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $page = ($_GET['page'] > 0) ? $_GET['page'] : 1;
}else {
    $page = 1;
}

if (isset($_GET['usersearch']) && strlen(trim(_stripslashes($_GET['usersearch']))) > 0) {
    $usersearch = trim(_stripslashes($_GET['usersearch']));
}elseif (isset($_POST['usersearch']) && strlen(trim(_stripslashes($_POST['usersearch']))) > 0) {
    $usersearch = trim(_stripslashes($_POST['usersearch']));
}else {
    $usersearch = "";
}

if (isset($_GET['reset'])) {
    $usersearch = "";
}

if (isset($_GET['filter']) && is_numeric($_GET['filter'])) {
    $filter = $_GET['filter'];
}elseif (isset($_POST['filter']) && is_numeric($_POST['filter'])) {
    $filter = $_POST['filter'];
}else {
    $filter = 0;
}

// Draw the form
echo "<h1>{$lang['admin']} &raquo; ", (isset($forum_settings['forum_name']) ? $forum_settings['forum_name'] : 'A Beehive Forum'), " &raquo; {$lang['manageusers']}</h1>\n";

if (bh_session_check_perm(USER_PERM_ADMIN_TOOLS, 0, 0)) {

    if (isset($_POST['t_kick'])) {

        list($user_uid) = array_keys($_POST['t_kick']);

        if (admin_session_end($user_uid)) {

            $user_logon = user_get_logon($user_uid);

            admin_add_log_entry(END_USER_SESSION, $user_logon);
            echo "<p><b>{$lang['sessionsuccessfullyended']}: <a href=\"javascript:void(0)\" onclick=\"openProfile($user_uid, '$webtag')\" target=\"_self\">$user_logon</a></b></p>\n";
        }
    }
}

echo "<p>{$lang['manageusersexp_1']} '{$sort_by_array[$sort_by]}'. {$lang['manageusersexp_2']}</p>\n";
echo "<div align=\"center\">\n";
echo "<form action=\"admin_users.php\" method=\"post\">\n";
echo "  ", form_input_hidden('webtag', $webtag), "\n";
echo "  ", form_input_hidden('usersearch', $usersearch), "\n";
echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"86%\">\n";
echo "    <tr>\n";
echo "      <td align=\"left\" colspan=\"3\">\n";
echo "        <table class=\"box\" width=\"100%\">\n";
echo "          <tr>\n";
echo "            <td align=\"left\" class=\"posthead\">\n";
echo "              <table class=\"posthead\" width=\"100%\">\n";
echo "                 <tr>\n";
echo "                   <td class=\"subhead\" width=\"20\">&nbsp;</td>\n";

if ($sort_by == 'USER.LOGON' && $sort_dir == 'ASC') {
    echo "                   <td class=\"subhead\" align=\"left\"><a href=\"admin_users.php?webtag=$webtag&amp;sort_by=LOGON&amp;sort_dir=DESC&amp;usersearch=$usersearch&amp;page=$page&amp;filter=$filter\">{$lang['user']}&nbsp;<img src=\"", style_image("sort_asc.png"), "\" border=\"0\" alt=\"{$lang['sortasc']}\" title=\"{$lang['sortasc']}\" /></a></td>\n";
}elseif ($sort_by == 'USER.LOGON' && $sort_dir == 'DESC') {
    echo "                   <td class=\"subhead\" align=\"left\"><a href=\"admin_users.php?webtag=$webtag&amp;sort_by=LOGON&amp;sort_dir=ASC&amp;usersearch=$usersearch&amp;page=$page&amp;filter=$filter\">{$lang['user']}&nbsp;<img src=\"", style_image("sort_desc.png"), "\" border=\"0\" alt=\"{$lang['sortdesc']}\" title=\"{$lang['sortdesc']}\" /></a></td>\n";
}elseif ($sort_dir == 'ASC') {
    echo "                   <td class=\"subhead\" align=\"left\"><a href=\"admin_users.php?webtag=$webtag&amp;sort_by=LOGON&amp;sort_dir=ASC&amp;usersearch=$usersearch&amp;page=$page&amp;filter=$filter\">{$lang['user']}</a></td>\n";
}else {
    echo "                   <td class=\"subhead\" align=\"left\"><a href=\"admin_users.php?webtag=$webtag&amp;sort_by=LOGON&amp;sort_dir=DESC&amp;usersearch=$usersearch&amp;page=$page&amp;filter=$filter\">{$lang['user']}</a></td>\n";
}

if ($sort_by == 'VISITOR_LOG.LAST_LOGON' && $sort_dir == 'ASC') {
    echo "                   <td class=\"subhead\" align=\"left\"><a href=\"admin_users.php?webtag=$webtag&amp;sort_by=LAST_LOGON&amp;sort_dir=DESC&amp;usersearch=$usersearch&amp;page=$page&amp;filter=$filter\">{$lang['lastlogon']}&nbsp;<img src=\"", style_image("sort_asc.png"), "\" border=\"0\" alt=\"{$lang['sortasc']}\" title=\"{$lang['sortasc']}\" /></a></td>\n";
}elseif ($sort_by == 'VISITOR_LOG.LAST_LOGON' && $sort_dir == 'DESC') {
    echo "                   <td class=\"subhead\" align=\"left\"><a href=\"admin_users.php?webtag=$webtag&amp;sort_by=LAST_LOGON&amp;sort_dir=ASC&amp;usersearch=$usersearch&amp;page=$page&amp;filter=$filter\">{$lang['lastlogon']}&nbsp;<img src=\"", style_image("sort_desc.png"), "\" border=\"0\" alt=\"{$lang['sortdesc']}\" title=\"{$lang['sortdesc']}\" /></a></td>\n";
}elseif ($sort_dir == 'ASC') {
    echo "                   <td class=\"subhead\" align=\"left\"><a href=\"admin_users.php?webtag=$webtag&amp;sort_by=LAST_LOGON&amp;sort_dir=ASC&amp;usersearch=$usersearch&amp;page=$page&amp;filter=$filter\">{$lang['lastlogon']}</a></td>\n";
}else {
    echo "                   <td class=\"subhead\" align=\"left\"><a href=\"admin_users.php?webtag=$webtag&amp;sort_by=LAST_LOGON&amp;sort_dir=DESC&amp;usersearch=$usersearch&amp;page=$page&amp;filter=$filter\">{$lang['lastlogon']}</a></td>\n";
}

if ($sort_by == 'SESSIONS.REFERER' && $sort_dir == 'ASC') {
    echo "                   <td class=\"subhead\" align=\"left\"><a href=\"admin_users.php?webtag=$webtag&amp;sort_by=REFERER&amp;sort_dir=DESC&amp;usersearch=$usersearch&amp;page=$page&amp;filter=$filter\">{$lang['sessionreferer']}&nbsp;<img src=\"", style_image("sort_asc.png"), "\" border=\"0\" alt=\"{$lang['sortasc']}\" title=\"{$lang['sortasc']}\" /></a></td>\n";
}elseif ($sort_by == 'SESSIONS.REFERER' && $sort_dir == 'DESC') {
    echo "                   <td class=\"subhead\" align=\"left\"><a href=\"admin_users.php?webtag=$webtag&amp;sort_by=REFERER&amp;sort_dir=ASC&amp;usersearch=$usersearch&amp;page=$page&amp;filter=$filter\">{$lang['sessionreferer']}&nbsp;<img src=\"", style_image("sort_desc.png"), "\" border=\"0\" alt=\"{$lang['sortdesc']}\" title=\"{$lang['sortdesc']}\" /></a></td>\n";
}elseif ($sort_dir == 'ASC') {
    echo "                   <td class=\"subhead\" align=\"left\"><a href=\"admin_users.php?webtag=$webtag&amp;sort_by=REFERER&amp;sort_dir=ASC&amp;usersearch=$usersearch&amp;page=$page&amp;filter=$filter\">{$lang['sessionreferer']}</a></td>\n";
}else {
    echo "                   <td class=\"subhead\" align=\"left\"><a href=\"admin_users.php?webtag=$webtag&amp;sort_by=REFERER&amp;sort_dir=DESC&amp;usersearch=$usersearch&amp;page=$page&amp;filter=$filter\">{$lang['sessionreferer']}</a></td>\n";
}

echo "                   <td class=\"subhead\" align=\"left\">{$lang['active']}</td>\n";
echo "                 </tr>\n";

$start = floor($page - 1) * 20;
if ($start < 0) $start = 0;

if (isset($usersearch) && strlen($usersearch) > 0) {
    $admin_user_array = admin_user_search($usersearch, $sort_by, $sort_dir, $filter, $start);
}else {
    $admin_user_array = admin_user_get_all($sort_by, $sort_dir, $filter, $start);
}

if (sizeof($admin_user_array['user_array']) > 0) {

    foreach ($admin_user_array['user_array'] as $user) {

        echo "                 <tr>\n";
        echo "                   <td align=\"center\">", form_checkbox("user_update[{$user['UID']}]", "Y", ""), "</td>\n";
        echo "                   <td class=\"posthead\" align=\"left\">&nbsp;<a href=\"admin_user.php?webtag=$webtag&amp;uid=", $user['UID'], "\">", add_wordfilter_tags(format_user_name($user['LOGON'], $user['NICKNAME'])), "</a></td>\n";

        if (isset($user['LAST_LOGON']) && $user['LAST_LOGON'] > 0) {
            echo "                   <td class=\"posthead\" align=\"left\">&nbsp;", format_time($user['LAST_LOGON'], 1), "</td>\n";
        }else {
            echo "                   <td class=\"posthead\" align=\"left\">&nbsp;{$lang['unknown']}</td>\n";
        }

        if (isset($user['REFERER']) && strlen(trim($user['REFERER'])) > 0) {

            $user['REFERER_FULL'] = $user['REFERER'];

            if (!$user['REFERER'] = split_url($user['REFERER'])) {
                if (strlen($user['REFERER_FULL']) > 25) {
                    $user['REFERER'] = substr($user['REFERER_FULL'], 0, 25);
                    $user['REFERER'].= "&hellip;";
                }
            }

            if (referer_is_banned($user['REFERER'])) {
                echo "                   <td class=\"posthead\" align=\"left\">&nbsp;<a href=\"admin_banned.php?unban_referer=", rawurlencode($user['REFERER_FULL']), "&amp;ret=admin_users.php\" title=\"{$user['REFERER_FULL']}\">{$user['REFERER']}</a> ({$lang['banned']})</td>\n";
            }else {
                echo "                   <td class=\"posthead\" align=\"left\">&nbsp;<a href=\"admin_banned.php?ban_referer=", rawurlencode($user['REFERER_FULL']), "&amp;ret=admin_users.php\" title=\"{$user['REFERER_FULL']}\">{$user['REFERER']}</a></td>\n";
            }

        }else {

            echo "                   <td class=\"posthead\" align=\"left\">&nbsp;{$lang['unknown']}</td>\n";
        }

        if (isset($user['HASH']) && is_md5($user['HASH'])) {
            echo "                   <td class=\"posthead\" align=\"left\">&nbsp;<b>{$lang['yes']}</b></td>\n";
        }else {
            echo "                   <td class=\"posthead\" align=\"left\">&nbsp;{$lang['no']}</td>\n";
        }

        echo "                 </tr>\n";
    }

}else {

    if (isset($usersearch) && strlen($usersearch) > 0) {

        echo "                 <tr>\n";
        echo "                   <td class=\"posthead\" colspan=\"5\" align=\"left\">&nbsp;{$lang['yoursearchdidnotreturnanymatches']}</td>\n";
        echo "                 </tr>\n";

    }else {

        // Shouldn't happen ever, after all how did you get here if there are no user accounts?

        echo "                 <tr>\n";
        echo "                   <td class=\"posthead\" colspan=\"5\" align=\"left\">&nbsp;{$lang['nouseraccountsmatchingfilter']}</td>\n";
        echo "                 </tr>\n";
    }
}

echo "                 <tr>\n";
echo "                   <td align=\"left\" colspan=\"5\">&nbsp;</td>\n";
echo "                 </tr>\n";
echo "               </table>\n";
echo "             </td>\n";
echo "           </tr>\n";
echo "         </table>\n";
echo "      </td>\n";
echo "    </tr>\n";
echo "    <tr>\n";
echo "      <td align=\"left\">&nbsp;</td>\n";
echo "      <td align=\"left\">&nbsp;</td>\n";
echo "      <td align=\"left\">&nbsp;</td>\n";
echo "    </tr>\n";
echo "    <tr>\n";
echo "      <td align=\"left\" width=\"33%\">", bh_session_check_perm(USER_PERM_ADMIN_TOOLS, 0, 0) ? form_submit("kick_submit", "Kick selected"). "&nbsp;" : "", forum_get_setting('require_user_approval', 'Y') && bh_session_check_perm(USER_PERM_ADMIN_TOOLS, 0) ? form_submit("approve_submit", "Approve selected") : "", "</td>\n";
echo "      <td class=\"postbody\" align=\"center\">", page_links("admin_users.php?webtag=$webtag&sort_by=UID&sort_dir=DESC&usersearch=$usersearch&filter=$filter", $start, $admin_user_array['user_count'], 20), "</td>\n";
echo "      <td align=\"right\" width=\"33%\">{$lang['userfilter']}: ", form_dropdown_array("filter", range(0, 4), array($lang['all'], $lang['onlineusers'], $lang['offlineusers'], $lang['usersawaitingapproval'], $lang['bannedusers']), $filter), "&nbsp;", form_submit("submit_filter", $lang['go']), "</td>\n";
echo "    </tr>\n";
echo "    <tr>\n";
echo "      <td align=\"left\">&nbsp;</td>\n";
echo "      <td align=\"left\">&nbsp;</td>\n";
echo "      <td align=\"left\">&nbsp;</td>\n";
echo "    </tr>\n";
echo "  </table>\n";
echo "</form>\n";

echo "<form action=\"admin_users.php\" method=\"get\">\n";
echo "  ", form_input_hidden("webtag", $webtag), "\n";
echo "  ", form_input_hidden("sort_by", $sort_by), "\n";
echo "  ", form_input_hidden("sort_dir", $sort_dir), "\n";
echo "  ", form_input_hidden("filter", $filter), "\n";
echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"86%\">\n";
echo "    <tr>\n";
echo "      <td align=\"left\">\n";
echo "        <table class=\"box\" width=\"100%\">\n";
echo "          <tr>\n";
echo "            <td align=\"left\" class=\"posthead\">\n";
echo "              <table class=\"posthead\" width=\"100%\">\n";
echo "                <tr>\n";
echo "                  <td class=\"subhead\" align=\"left\">{$lang['searchforusernotinlist']}:</td>\n";
echo "                </tr>\n";
echo "                <tr>\n";
echo "                  <td class=\"posthead\" align=\"left\">\n";
echo "                    &nbsp;{$lang['username']}: ", form_input_text('usersearch', $usersearch, 30, 64), " ", form_submit('submit', $lang['search']), " ", form_submit('reset', $lang['clear']), "\n";
echo "                  </td>\n";
echo "                </tr>\n";
echo "                <tr>\n";
echo "                  <td align=\"left\" colspan=\"6\">&nbsp;</td>\n";
echo "                </tr>\n";
echo "              </table>\n";
echo "            </td>\n";
echo "          </tr>\n";
echo "        </table>\n";
echo "      </td>\n";
echo "    </tr>\n";
echo "  </table>\n";
echo "</form>\n";
echo "</div>\n";

html_draw_bottom();

?>