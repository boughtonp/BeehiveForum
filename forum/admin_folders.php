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

/* $Id: admin_folders.php,v 1.73 2004-05-04 23:04:22 decoyduck Exp $ */

// Compress the output
include_once("./include/gzipenc.inc.php");

// Enable the error handler
include_once("./include/errorhandler.inc.php");

// Multiple forum support
include_once("./include/forum.inc.php");

// Fetch the forum settings
$forum_settings = get_forum_settings();

include_once("./include/admin.inc.php");
include_once("./include/constants.inc.php");
include_once("./include/db.inc.php");
include_once("./include/folder.inc.php");
include_once("./include/form.inc.php");
include_once("./include/format.inc.php");
include_once("./include/header.inc.php");
include_once("./include/html.inc.php");
include_once("./include/lang.inc.php");
include_once("./include/logon.inc.php");
include_once("./include/perm.inc.php");
include_once("./include/session.inc.php");

if (!$user_sess = bh_session_check()) {

    html_draw_top();

    if (isset($_POST['user_logon']) && isset($_POST['user_password']) && isset($_POST['user_passhash'])) {

        if (perform_logon(false)) {

            $lang = load_language_file();
            $webtag = get_webtag($webtag_search);

            echo "<h1>{$lang['loggedinsuccessfully']}</h1>";
            echo "<div align=\"center\">\n";
            echo "<p><b>{$lang['presscontinuetoresend']}</b></p>\n";

            $request_uri = get_request_uri();

            echo "<form method=\"post\" action=\"$request_uri\" target=\"_self\">\n";
            echo form_input_hidden('webtag', $webtag);

            foreach($_POST as $key => $value) {
                echo form_input_hidden($key, _htmlentities(_stripslashes($value)));
            }

            echo form_submit(md5(uniqid(rand())), $lang['continue']), "&nbsp;";
            echo form_button(md5(uniqid(rand())), $lang['cancel'], "onclick=\"self.location.href='$request_uri'\""), "\n";
            echo "</form>\n";

            html_draw_bottom();
            exit;
        }
    }

    draw_logon_form(false);
    html_draw_bottom();
    exit;
}

// Load language file

$lang = load_language_file();

// Check we have a webtag

if (!$webtag = get_webtag($webtag_search)) {
    $request_uri = rawurlencode(get_request_uri(true));
    header_redirect("./forums.php?webtag_search=$webtag_search&final_uri=$request_uri");
}

html_draw_top();

if (!(bh_session_get_value('STATUS')&USER_PERM_SOLDIER)) {
    echo "<h1>{$lang['accessdenied']}</h1>\n";
    echo "<p>{$lang['accessdeniedexp']}</p>";
    html_draw_bottom();
    exit;
}

if (isset($_POST['t_more'])) {

    list($fid) = array_keys($_POST['t_more']);
    header_redirect("./admin_folder_edit.php?webtag=$webtag&fid=$fid");
}

if (isset($_POST['addnew'])) {
    header_redirect("./admin_folder_Add.php?webtag=$webtag");
}

if (isset($_POST['t_permissions'])) {

    list($fid) = array_keys($_POST['t_permissions']);
    header_redirect("./admin_folder_access.php?webtag=$webtag&fid=$fid");
}

// Do updates
if (isset($_POST['submit'])) {

    if (isset($_POST['t_fid'])) {

        foreach($_POST['t_fid'] as $fid => $value) {

            $folder_data = folder_get($fid);

            if (isset($_POST['t_position'][$fid]) && is_numeric($_POST['t_position'][$fid])) {
                $folder_data['POSITION'] = $_POST['t_position'][$fid];
            }

            if (isset($_POST['t_access'][$fid]) && is_numeric($_POST['t_access'][$fid])) {
                $folder_data['ACCESS_LEVEL'] = $_POST['t_access'][$fid];
            }

            folder_update($fid, $folder_data);
            admin_addlog(0, $fid, 0, 0, 0, 0, 7);
        }
    }
}

// Draw the form
echo "<h1>{$lang['admin']} : {$lang['managefolders']}</h1>\n";
echo "<br />\n";
echo "<div align=\"center\">\n";
echo "<form name=\"f_folders\" action=\"admin_folders.php\" method=\"post\">\n";
echo "  ", form_input_hidden('webtag', $webtag), "\n";
echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"70%\">\n";
echo "    <tr>\n";
echo "      <td>\n";
echo "        <table class=\"box\" width=\"100%\">\n";
echo "          <tr>\n";
echo "            <td class=\"posthead\">\n";
echo "              <table class=\"posthead\" width=\"100%\">\n";
echo "                <tr>\n";
echo "                  <td class=\"subhead\" align=\"left\" nowrap=\"nowrap\">&nbsp;{$lang['position']}</td>\n";
echo "                  <td class=\"subhead\" align=\"left\" nowrap=\"nowrap\">&nbsp;{$lang['foldername']}</td>\n";
echo "                  <td class=\"subhead\" align=\"left\" nowrap=\"nowrap\">&nbsp;{$lang['accesslevel']}</td>\n";
echo "                  <td class=\"subhead\" align=\"left\" nowrap=\"nowrap\">&nbsp;{$lang['threadcount']}</td>\n";
echo "                  <td class=\"subhead\" align=\"left\" nowrap=\"nowrap\">&nbsp;{$lang['more']}</td>\n";
echo "                  <td class=\"subhead\" align=\"left\" nowrap=\"nowrap\">&nbsp;{$lang['permissions']}</td>\n";
echo "                  <td class=\"subhead\" align=\"left\" nowrap=\"nowrap\">&nbsp;{$lang['delete']}</td>\n";
echo "                </tr>\n";

$folder_array = folder_get_all();

if ($folder_array = folder_get_all()) {

    for ($i = 0; $i < sizeof($folder_array); $i++) {

        if (!isset($folder_array[$i]['DESCRIPTION']) || is_null($folder_array[$i]['DESCRIPTION'])) $folder_array[$i]['DESCRIPTION'] = "";
        if (!isset($folder_array[$i]['ALLOWED_TYPES']) || is_null($folder_array[$i]['ALLOWED_TYPES'])) $folder_array[$i]['ALLOWED_TYPES'] = FOLDER_ALLOW_ALL_THREAD;

        echo "                <tr>\n";
        echo "                  <td align=\"left\">", form_dropdown_array("t_position[{$folder_array[$i]['FID']}]", range(1, sizeof($folder_array) + 1), range(1, sizeof($folder_array) + 1), $i + 1), form_input_hidden("t_old_position[{$folder_array[$i]['FID']}]", $i), form_input_hidden("t_fid[{$folder_array[$i]['FID']}]", $folder_array[$i]['FID']), "</td>\n";
        echo "                  <td align=\"left\"><a href=\"admin_folder_edit.php?webtag=$webtag&amp;fid={$folder_array[$i]['FID']}\">{$folder_array[$i]['TITLE']}</a></td>\n";

        // Draw the ACCESS_LEVEL dropdown
        echo "                  <td align=\"left\">".form_dropdown_array("t_access[{$folder_array[$i]['FID']}]", array(-1, 0, 1, 2), array($lang['closed'], $lang['open'], $lang['restricted'], $lang['locked']), $folder_array[$i]['ACCESS_LEVEL']);
        echo form_input_hidden("t_old_access[{$folder_array[$i]['FID']}]", $folder_array[$i]['ACCESS_LEVEL']). "</td>\n";

        echo "                  <td align=\"left\">". $folder_array[$i]['THREAD_COUNT']. "</td>\n";
        echo "                  <td align=\"left\">", form_submit("t_more[{$folder_array[$i]['FID']}]", $lang['more']), "</td>\n";

        if ($folder_array[$i]['ACCESS_LEVEL'] > 0) {
            echo "                  <td align=\"left\" width=\"100\">", form_submit("t_permissions[{$folder_array[$i]['FID']}]", $lang['change']), "</td>\n";
        }else {
            echo "                  <td align=\"left\" width=\"100\">&nbsp;</td>\n";
        }

        if ($folder_array[$i]['THREAD_COUNT'] < 1) {
            echo "                  <td align=\"left\" width=\"100\">", form_submit("t_delete[{$folder_array[$i]['FID']}]", $lang['delete']), "</td>\n";
        }else {
            echo "                  <td align=\"left\" width=\"100\">&nbsp;</td>\n";
        }

        echo "                </tr>\n";
    }
}

echo "                <tr>\n";
echo "                  <td colspan=\"8\">&nbsp;</td>\n";
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
echo "      <td align=\"center\">", form_submit("submit", $lang['save']), "&nbsp;", form_submit("addnew", $lang['addnewfolder']), "</td>\n";
echo "    </tr>\n";
echo "  </table>\n";
echo "</form>\n";;
echo "</div>\n";

html_draw_bottom();

?>