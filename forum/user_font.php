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

include_once(BH_INCLUDE_PATH. "format.inc.php");
include_once(BH_INCLUDE_PATH. "header.inc.php");
include_once(BH_INCLUDE_PATH. "html.inc.php");
include_once(BH_INCLUDE_PATH. "lang.inc.php");
include_once(BH_INCLUDE_PATH. "logon.inc.php");
include_once(BH_INCLUDE_PATH. "messages.inc.php");
include_once(BH_INCLUDE_PATH. "session.inc.php");
include_once(BH_INCLUDE_PATH. "user.inc.php");

// Get webtag
$webtag = get_webtag();

// Check we're logged in correctly
$user_sess = session_check();

// Initialise Locale
lang_init();

// User's UID
$uid = session_get_value('UID');

// Check we have a valid request
if (!user_is_guest() && isset($_GET['fontsize'])) {

    // Check for message ID
    if (isset($_GET['msg']) && validate_msg($_GET['msg'])) {
        list($tid, $pid) = explode('.', $_GET['msg']);
    } else {
        $tid = 1; $pid = 1;
    }

    // Load the user prefs
    $user_prefs = user_get_prefs($uid);

    // Calculate the new font size.
    switch ($_GET['fontsize']) {

        case 'smaller':

            $user_prefs = array('FONT_SIZE' => $user_prefs['FONT_SIZE'] - 1);
            break;

        case 'larger':

            $user_prefs = array('FONT_SIZE' => $user_prefs['FONT_SIZE'] + 1);
            break;
    }

    // Check the font size is not lower than 5
    if ($user_prefs['FONT_SIZE'] < 5) $user_prefs['FONT_SIZE'] = 5;

    // Check the font size is not greater than 15
    if ($user_prefs['FONT_SIZE'] > 15) $user_prefs['FONT_SIZE'] = 15;

    // Apply the font size to this forum only.
    $user_prefs_global = array('FONT_SIZE' => false);

    // Update the user prefs.
    if (user_update_prefs($uid, $user_prefs, $user_prefs_global)) {

        header_redirect("messages.php?webtag=$webtag&msg=$tid.$pid&font_resize=1");

    } else {

        html_draw_top();
        html_error_msg(gettext("Some or all of your user account details could not be updated. Please try again later."), 'messages.php', 'get', array('back' => gettext("Back")), array('msg' => "$tid.$pid"));
        html_draw_bottom();
    }

} else {

    html_guest_error();
    exit;
}

?>
