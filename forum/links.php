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

// Enable the error handler
require_once("./include/errorhandler.inc.php");

// Compress the output
require_once("./include/gzipenc.inc.php");

// Links catalogue thingy

require_once("./include/html.inc.php");
require_once("./include/links.inc.php");
require_once("./include/session.inc.php");
require_once("./include/header.inc.php");
require_once("./include/format.inc.php");
require_once("./include/form.inc.php");
require_once("./include/perm.inc.php");
require_once("./include/config.inc.php");

if(!bh_session_check()){
    $uri = "./index.php?final_uri=". urlencode(get_request_uri());
    header_redirect($uri);
}

if (!$show_links) {
    html_draw_top();
    echo "<h2>You may not access this section.</h2>\n";
    html_draw_bottom();
    exit;
}

if (isset($HTTP_GET_VARS['action'])) {
    if (perm_is_moderator() && $HTTP_GET_VARS['action'] == "hide") {
        links_change_visibility($HTTP_GET_VARS['lid'], false);
    } elseif (perm_is_moderator() && $HTTP_GET_VARS['action'] == "show") {
        links_change_visibility($HTTP_GET_VARS['lid'], true);
    } elseif (perm_is_moderator() && $HTTP_GET_VARS['action'] == "del") {
        links_delete($HTTP_GET_VARS['lid']);
    } elseif (perm_is_moderator() && $HTTP_GET_VARS['action'] == "folderhide") {
        links_folder_change_visibility($HTTP_GET_VARS['fid'], false);
        $fid = $HTTP_GET_VARS['new_fid'];
    } elseif (perm_is_moderator() && $HTTP_GET_VARS['action'] == "foldershow") {
        links_folder_change_visibility($HTTP_GET_VARS['fid'], true);
        $fid = $HTTP_GET_VARS['new_fid'];
    } elseif (perm_is_moderator() && $HTTP_GET_VARS['action'] == "folderdel") {
        $folders = links_folders_get(perm_is_moderator());
        if (count(links_get_subfolders($HTTP_GET_VARS['fid'], $folders)) == 0) links_folder_delete($HTTP_GET_VARS['fid']);
        $fid = $HTTP_GET_VARS['new_fid'];
    } elseif ($HTTP_GET_VARS['action'] == "go") {
        links_click($HTTP_GET_VARS['lid']);
        exit;
    }
}

$folders = links_folders_get(perm_is_moderator());

// if the LINKS_FOLDERS database is empty, add a 'Top Level' folder

if (!is_array($folders)) {
  links_add_folder(1, 'Top Level', true);
  $folders = links_folders_get(perm_is_moderator());
}

if (isset($HTTP_GET_VARS['fid']) && !isset($fid)) { // default to top level folder if no other valid folder specified
    if (is_array($folders) && array_key_exists($HTTP_GET_VARS['fid'], $folders)) {
        $fid = $HTTP_GET_VARS['fid'];
    } else {
        $fid = 1;
    }
} elseif (!isset($fid)) {
    $fid = 1;
}

html_draw_top();
echo "<h1>Links</h1>\n";

// work out where we are in the folder hierarchy and display links to all the higher levels

echo "<h2>" . links_display_folder_path($fid, $folders) . "</h2>\n";
if ($folders[$fid]['VISIBLE'] == "N") echo "<p class=\"threadtime\">This folder is hidden. <a href=\"" . $HTTP_SERVER_VARS['PHP_SELF'] . "?fid=$fid&amp;action=foldershow\">[unhide]</a></p>";

$subfolders = links_get_subfolders($fid, $folders);

$new_folder_link = $HTTP_COOKIE_VARS['bh_sess_uid'] ? "[<a href=\"links_add.php?mode=folder&amp;fid=$fid\">Add new folder</a>]" : "";
if (count($subfolders) == 0) {
    echo "<p><span class=\"threadtime\">No subfolders in this category. $new_folder_link</span></p>\n";
} else {
    if (count($subfolders) == 1) {
       echo "<p><span class=\"threadtime\">1 subfolder in this category: $new_folder_link</span></p>\n";
    } else {
       echo "<p><span class=\"threadtime\">" . count($subfolders) . " subfolders in this category: $new_folder_link</span></p>\n";
    }
    echo "<table>\n";
    // create list of subfolders
    while (list($key, $val) = each($subfolders)) {
            echo "<tr><td class=\"postbody\"><img src=\"" . style_image("folder.png") . "\" alt=\"folder\" /></td><td class=\"postbody\"><a href=\"" . $HTTP_SERVER_VARS['PHP_SELF'] . "?fid=$val\""; if ($folders[$val]['VISIBLE'] == "N") echo "style=\"color: gray;\""; echo ">" . _stripslashes($folders[$val]['NAME']) . "</a>";
            if (perm_is_moderator() && $folders[$val]['VISIBLE'] == "Y") {
                echo "&nbsp;<a href=\"" . $HTTP_SERVER_VARS['PHP_SELF'] . "?fid=$val&amp;action=folderhide&amp;new_fid=$fid\" class=\"threadtime\">[hide]</a>\n";
            } elseif (perm_is_moderator() && $folders[$val]['VISIBLE'] == "N") {
                echo "&nbsp;<a href=\"" . $HTTP_SERVER_VARS['PHP_SELF'] . "?fid=$val&amp;action=foldershow&amp;new_fid=$fid\" class=\"threadtime\">[unhide]</a>\n";
            }
            if (perm_is_moderator() && count(links_get_subfolders($val, $folders)) == 0) echo "<a href=\"" . $HTTP_SERVER_VARS['PHP_SELF'] . "?fid=$val&amp;action=folderdel&amp;new_fid=$fid\" class=\"threadtime\">[delete]</a>\n";
            echo "</td></tr>\n";
    }
    echo "</table>\n";
    if (perm_is_moderator()) echo "<p class=\"threadtime\">Entries in a deleted folder will be moved to the parent folder. Only folders which do not contain subfolders may be deleted.</p>";
    //echo "</p>\n";
}

if (isset($HTTP_GET_VARS['sort_by'])) {// this seems slightly wasteful, but it's for security - just passing $HTTP_GET_VARS['sort_by'] straight to the SQL query is not a good idea (what might happen if you tried to search by "TITLE; DROP DATABASE beehive;"?)
    if ($HTTP_GET_VARS['sort_by'] == "TITLE") {
        $sort_by = "TITLE";
    } elseif ($HTTP_GET_VARS['sort_by'] == "DESCRIPTION") {
        $sort_by = "DESCRIPTION";
    } elseif ($HTTP_GET_VARS['sort_by'] == "NICKNAME") {
        $sort_by = "NICKNAME";
    } elseif ($HTTP_GET_VARS['sort_by'] == "CREATED") {
        $sort_by = "CREATED";
    } elseif ($HTTP_GET_VARS['sort_by'] == "CLICKS") {
        $sort_by = "CLICKS";
    } elseif ($HTTP_GET_VARS['sort_by'] == "RATING") {
        $sort_by = "RATING";
    }
} else {
    $sort_by = "TITLE";
}

if (isset($HTTP_GET_VARS['sort_dir'])) {
    if ($HTTP_GET_VARS['sort_dir'] == "DESC") {
        $sort_dir = "DESC";
    } else {
        $sort_dir = "ASC";
    }
} else {
    $sort_dir = "ASC";
}

$links = links_get_in_folder($fid, perm_is_moderator(), $sort_by, $sort_dir);

echo "<table width=\"95%\" align=\"center\">\n";
echo "  <tr>\n";

echo "    <td class=\"posthead\">";
if ($sort_by == "TITLE" && $sort_dir == "ASC") {
    echo "<a href=\"links.php?fid=$fid&amp;sort_by=TITLE&amp;sort_dir=DESC\">Name</a>";
} else {
    echo "<a href=\"links.php?fid=$fid&amp;sort_by=TITLE&amp;sort_dir=ASC\">Name</a>";
}
echo "</td>\n";

echo "    <td class=\"posthead\" width=\"250\">";
if ($sort_by == "DESCRIPTION" && $sort_dir == "ASC") {
    echo "<a href=\"links.php?fid=$fid&amp;sort_by=DESCRIPTION&amp;sort_dir=DESC\">Description</a>";
} else {
    echo "<a href=\"links.php?fid=$fid&amp;sort_by=DESCRIPTION&amp;sort_dir=ASC\">Description</a>";
}
echo "</td>\n";

echo "    <td class=\"posthead\">";
if ($sort_by == "NICKNAME" && $sort_dir == "ASC") {
    echo "<a href=\"links.php?fid=$fid&amp;sort_by=NICKNAME&amp;sort_dir=DESC\">Submitted by</a>";
} else {
    echo "<a href=\"links.php?fid=$fid&amp;sort_by=NICKNAME&amp;sort_dir=ASC\">Submitted by</a>";
}
echo "</td>\n";

echo "    <td class=\"posthead\">";
if ($sort_by == "CREATED" && $sort_dir == "ASC") {
    echo "<a href=\"links.php?fid=$fid&amp;sort_by=CREATED&amp;sort_dir=DESC\">Date</a>";
} else {
    echo "<a href=\"links.php?fid=$fid&amp;sort_by=CREATED&amp;sort_dir=ASC\">Date</a>";
}
echo "</td>\n";

echo "    <td class=\"posthead\">";
if ($sort_by == "CLICKS" && $sort_dir == "DESC") {
    echo "<a href=\"links.php?fid=$fid&amp;sort_by=CLICKS&amp;sort_dir=ASC\">Clicks</a>";
} else {
    echo "<a href=\"links.php?fid=$fid&amp;sort_by=CLICKS&amp;sort_dir=DESC\">Clicks</a>";
}
echo "</td>\n";

echo "    <td class=\"posthead\">";
if ($sort_by == "RATING" && $sort_dir == "DESC") {
    echo "<a href=\"links.php?fid=$fid&amp;sort_by=RATING&amp;sort_dir=ASC\">Rating</a>";
} else {
    echo "<a href=\"links.php?fid=$fid&amp;sort_by=RATING&amp;sort_dir=DESC\">Rating</a>";
}
echo "</td>\n";

if (perm_is_moderator()) echo "    <td class=\"posthead\">Moderation</td>";
echo "    <td class=\"posthead\">Comments / Vote</td>\n";
echo "  </tr>\n";

if (sizeof($links) > 0 ) {
      while (list($key, $link) = each($links)) {
      echo "  <tr" ; if ($link['VISIBLE'] == "N") echo " style=\"color: gray\""; echo ">\n";
      echo "    <td class=\"postbody\" valign=\"top\"><a href=\"", $HTTP_SERVER_VARS['PHP_SELF'], "?lid=$key&amp;action=go\" target=\"_blank\""; if ($link['VISIBLE'] == "N") echo " style=\"color: gray\""; echo ">". _stripslashes($link['TITLE']) . "</a></td>\n";
      echo "    <td class=\"postbody\" width=\"250\">", _stripslashes($link['DESCRIPTION']), "</td>\n";
      echo "    <td class=\"postbody\" valign=\"top\">", format_user_name($link['LOGON'], $link['NICKNAME']), "</td>\n";
      echo "    <td class=\"postbody\" valign=\"top\">", format_time($link['CREATED']), "</td>\n";
      echo "    <td class=\"postbody\" valign=\"top\">", $link['CLICKS'], "</td>\n";
      echo "    <td class=\"postbody\" valign=\"top\">";
      if (isset($link['RATING']) && $link['RATING'] != "") echo round($link['RATING'], 1);
      echo "</td>\n";
      if (perm_is_moderator()) {
          echo "    <td class=\"threadtime\" valign=\"top\">";
          if ($link['VISIBLE'] == "Y") { echo "<a href=\"" . $HTTP_SERVER_VARS['PHP_SELF'] . "?fid=$fid&amp;action=hide&amp;lid=$key\">[hide]</a>"; } else { echo "<a href=\"" . $HTTP_SERVER_VARS['PHP_SELF'] . "?fid=$fid&amp;action=show&amp;lid=$key\">[unhide]</a>"; }
          echo "</td>\n";
      }
      echo "    <td class=\"postbody\" valign=\"top\"><a href=\"links_detail.php?lid=$key\" class=\"threadtime\">[view]</a></td>\n";
      echo "  </tr>\n";
      }
} else {
      echo "  <tr>\n    <td colspan=\"5\" class=\"postbody\">No links in this folder.</td>\n  </tr>\n";
}

echo $HTTP_COOKIE_VARS['bh_sess_uid'] ? "  <tr>\n    <td class=\"postbody\">&nbsp;</td>\n  </tr>\n  <tr>\n    <td class=\"postbody\"><a href=\"links_add.php?mode=link&amp;fid=$fid\"><b>Add link here</b></a></td>\n  </tr>\n" : "";
echo "</table>\n";
html_draw_bottom();
?>
