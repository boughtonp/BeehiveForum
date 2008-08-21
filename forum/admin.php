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

/* $Id: admin.php,v 1.109 2008-08-21 20:46:12 decoyduck Exp $ */

// Constant to define where the include files are
define("BH_INCLUDE_PATH", "include/");

// Server checking functions
include_once(BH_INCLUDE_PATH. "server.inc.php");

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
include_once(BH_INCLUDE_PATH. "format.inc.php");
include_once(BH_INCLUDE_PATH. "header.inc.php");
include_once(BH_INCLUDE_PATH. "html.inc.php");
include_once(BH_INCLUDE_PATH. "lang.inc.php");
include_once(BH_INCLUDE_PATH. "logon.inc.php");
include_once(BH_INCLUDE_PATH. "session.inc.php");

// Don't cache this page - fixes problems with Opera.

header_no_cache();

// Get Webtag

$webtag = get_webtag();

// Check we're logged in correctly

if (!$user_sess = bh_session_check()) {
    $request_uri = rawurlencode(get_request_uri());
    header_redirect("logon.php?webtag=$webtag&final_uri=$request_uri");
}

// Check to see if the user is banned.

if (bh_session_user_banned()) {

    html_user_banned();
    exit;
}

// Check we have a webtag

$webtag = get_webtag();

// Load language file

$lang = load_language_file();

if ((!bh_session_check_perm(USER_PERM_ADMIN_TOOLS, 0) && !bh_session_check_perm(USER_PERM_FORUM_TOOLS, 0, 0) && !bh_session_get_folders_by_perm(USER_PERM_FOLDER_MODERATE)) || (bh_session_get_folders_by_perm(USER_PERM_FOLDER_MODERATE) && $webtag === false)) {

    html_draw_top();
    html_error_msg($lang['accessdeniedexp']);
    html_draw_bottom();
    exit;
}

if (isset($_GET['page']) && strlen(trim(_stripslashes($_GET['page']))) > 0) {

    $requested_page = trim(_stripslashes($_GET['page']));

    $available_pages = get_available_admin_files();
    $available_pages_preg = implode("|^", array_map('preg_quote_callback', $available_pages));

    if (preg_match("/^$available_pages_preg/u", basename($requested_page)) > 0) {

        html_draw_top('body_tag=false', 'frames=true');

        $requested_page = href_cleanup_query_keys($requested_page);

        echo "<frameset cols=\"250,*\" framespacing=\"4\" border=\"4\">\n";
        echo "  <frame src=\"admin_menu.php?webtag=$webtag\" name=\"", html_get_frame_name('left'), "\" frameborder=\"0\" />\n";
        echo "  <frame src=\"$requested_page\" name=\"", html_get_frame_name('right'), "\" frameborder=\"0\" />\n";
        echo "</frameset>\n";

        html_draw_bottom(false);
        exit;
    }
}

html_draw_top('body_tag=false', 'frames=true');

echo "<frameset cols=\"250,*\" framespacing=\"4\" border=\"4\">\n";
echo "  <frame src=\"admin_menu.php?webtag=$webtag\" name=\"", html_get_frame_name('left'), "\" frameborder=\"0\" />\n";
echo "  <frame src=\"admin_main.php?webtag=$webtag\" name=\"", html_get_frame_name('right'), "\" frameborder=\"0\" />\n";
echo "</frameset>\n";

html_draw_bottom(false);

?>