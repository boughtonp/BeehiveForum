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
include_once(BH_INCLUDE_PATH. "format.inc.php");
include_once(BH_INCLUDE_PATH. "header.inc.php");
include_once(BH_INCLUDE_PATH. "html.inc.php");
include_once(BH_INCLUDE_PATH. "lang.inc.php");
include_once(BH_INCLUDE_PATH. "logon.inc.php");
include_once(BH_INCLUDE_PATH. "perm.inc.php");
include_once(BH_INCLUDE_PATH. "session.inc.php");

// Get webtag
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

html_draw_top();

echo "<table border=\"0\" width=\"100%\">\n";
echo "  <tr>\n";
echo "    <td align=\"left\" class=\"subhead\">", gettext("Menu"), "</td>\n";
echo "  </tr>\n";
echo "  <tr>\n";
echo "    <td align=\"left\" class=\"postbody\"><img src=\"", html_style_image('bullet.png'), "\" border=\"0\" alt=\"\" />&nbsp;<a href=\"edit_prefs.php?webtag=$webtag\" target=\"", html_get_frame_name('right'), "\">", gettext("User Details"), "</a></td>\n";
echo "  </tr>\n";
echo "  <tr>\n";
echo "    <td align=\"left\" class=\"postbody\"><img src=\"", html_style_image('bullet.png'), "\" border=\"0\" alt=\"\" />&nbsp;<a href=\"edit_profile.php?webtag=$webtag\" target=\"", html_get_frame_name('right'), "\">", gettext("Edit Profile"), "</a></td>\n";
echo "  </tr>\n";
echo "  <tr>\n";
echo "    <td align=\"left\" class=\"postbody\"><img src=\"", html_style_image('bullet.png'), "\" border=\"0\" alt=\"\" />&nbsp;<a href=\"edit_password.php?webtag=$webtag\" target=\"", html_get_frame_name('right'), "\">", gettext("Change Password"), "</a></td>\n";
echo "  </tr>\n";
echo "  <tr>\n";
echo "    <td align=\"left\" class=\"postbody\"><hr /></td>\n";
echo "  </tr>\n";
echo "  <tr>\n";
echo "    <td align=\"left\" class=\"postbody\"><img src=\"", html_style_image('bullet.png'), "\" border=\"0\" alt=\"\" />&nbsp;<a href=\"edit_email.php?webtag=$webtag\" target=\"", html_get_frame_name('right'), "\">", htmlentities_array(gettext("Email & Privacy")), "</a></td>\n";
echo "  </tr>\n";
echo "  <tr>\n";
echo "    <td align=\"left\" class=\"postbody\"><img src=\"", html_style_image('bullet.png'), "\" border=\"0\" alt=\"\" />&nbsp;<a href=\"forum_options.php?webtag=$webtag\" target=\"", html_get_frame_name('right'), "\">", gettext("Forum Options"), "</a></td>\n";
echo "  </tr>\n";
echo "  <tr>\n";
echo "    <td align=\"left\" class=\"postbody\"><img src=\"", html_style_image('bullet.png'), "\" border=\"0\" alt=\"\" />&nbsp;<a href=\"pm_options.php?webtag=$webtag\" target=\"", html_get_frame_name('right'), "\">", gettext("Private Message Options"), "</a></td>\n";
echo "  </tr>\n";
echo "  <tr>\n";
echo "    <td align=\"left\" class=\"postbody\"><img src=\"", html_style_image('bullet.png'), "\" border=\"0\" alt=\"\" />&nbsp;<a href=\"edit_attachments.php?webtag=$webtag\" target=\"", html_get_frame_name('right'), "\">", gettext("Attachments"), "</a></td>\n";
echo "  </tr>\n";
echo "  <tr>\n";
echo "    <td align=\"left\" class=\"postbody\"><img src=\"", html_style_image('bullet.png'), "\" border=\"0\" alt=\"\" />&nbsp;<a href=\"edit_signature.php?webtag=$webtag\" target=\"", html_get_frame_name('right'), "\">", gettext("Signature"), "</a></td>\n";
echo "  </tr>\n";
echo "  <tr>\n";
echo "    <td align=\"left\" class=\"postbody\"><img src=\"", html_style_image('bullet.png'), "\" border=\"0\" alt=\"\" />&nbsp;<a href=\"edit_relations.php?webtag=$webtag\" target=\"", html_get_frame_name('right'), "\">", gettext("Relationships"), "</a></td>\n";
echo "  </tr>\n";
echo "  <tr>\n";
echo "    <td align=\"left\" class=\"postbody\"><img src=\"", html_style_image('bullet.png'), "\" border=\"0\" alt=\"\" />&nbsp;<a href=\"edit_wordfilter.php?webtag=$webtag\" target=\"", html_get_frame_name('right'), "\">", gettext("Word Filter"), "</a></td>\n";
echo "  </tr>\n";
echo "  <tr>\n";
echo "    <td align=\"left\" class=\"postbody\"><img src=\"", html_style_image('bullet.png'), "\" border=\"0\" alt=\"\" />&nbsp;<a href=\"edit_subscriptions.php?webtag=$webtag\" target=\"", html_get_frame_name('right'), "\">", gettext("Thread Subscriptions"), "</a></td>\n";
echo "  </tr>\n";
echo "  <tr>\n";
echo "    <td align=\"left\" class=\"postbody\"><img src=\"", html_style_image('bullet.png'), "\" border=\"0\" alt=\"\" />&nbsp;<a href=\"folder_subscriptions.php?webtag=$webtag\" target=\"", html_get_frame_name('right'), "\">", gettext("Folder Subscriptions"), "</a></td>\n";
echo "  </tr>\n";
echo "</table>\n";

html_draw_bottom();

?>