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
include_once(BH_INCLUDE_PATH. "fixhtml.inc.php");
include_once(BH_INCLUDE_PATH. "form.inc.php");
include_once(BH_INCLUDE_PATH. "format.inc.php");
include_once(BH_INCLUDE_PATH. "header.inc.php");
include_once(BH_INCLUDE_PATH. "html.inc.php");
include_once(BH_INCLUDE_PATH. "lang.inc.php");
include_once(BH_INCLUDE_PATH. "logon.inc.php");
include_once(BH_INCLUDE_PATH. "messages.inc.php");
include_once(BH_INCLUDE_PATH. "perm.inc.php");
include_once(BH_INCLUDE_PATH. "session.inc.php");
include_once(BH_INCLUDE_PATH. "user.inc.php");
include_once(BH_INCLUDE_PATH. "user_rel.inc.php");
include_once(BH_INCLUDE_PATH. "word_filter.inc.php");

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

// User's UID
$uid = session_get_value('UID');

// Check that we have access to this forum
if (!forum_check_access_level()) {
    $request_uri = rawurlencode(get_request_uri());
    header_redirect("forums.php?webtag_error&final_uri=$request_uri");
}

if (user_is_guest()) {

    html_guest_error();
    exit;
}

// Are we returning somewhere?
if (isset($_GET['msg']) && validate_msg($_GET['msg'])) {
    $ret = "messages.php?webtag=$webtag&msg={$_GET['msg']}";
}elseif (isset($_GET['ret']) && strlen(trim(stripslashes_array($_GET['ret']))) > 0) {
    $ret = rawurldecode(trim(stripslashes_array($_GET['ret'])));
}elseif (isset($_POST['ret']) && strlen(trim(stripslashes_array($_POST['ret']))) > 0) {
    $ret = trim(stripslashes_array($_POST['ret']));
}else {
    $ret = "edit_relations.php?webtag=$webtag";
}

// validate the return to page
if (isset($ret) && strlen(trim($ret)) > 0) {

    $available_pages = array('edit_relations.php', 'messages.php', 'user_profile.php');
    $available_pages_preg = implode("|^", array_map('preg_quote_callback', $available_pages));

    if (preg_match("/^$available_pages_preg/u", basename($ret)) < 1) {
        $ret = "messages.php?webtag=$webtag";
    }
}

// Return to the page we came from.
if (isset($_POST['cancel'])) {
    header_redirect($ret);
}

// Check the provided peer UID.
if (isset($_GET['uid']) && is_numeric($_GET['uid'])) {

    $peer_uid = $_GET['uid'];

    if (!$user_peer = user_get($peer_uid)) {

        html_draw_top(sprintf("title=%s", gettext("Invalid username!")));
        html_error_msg(gettext("Invalid username!"));
        html_draw_bottom();
        exit;
    }

}elseif (isset($_POST['uid']) && is_numeric($_POST['uid'])) {

    $peer_uid = $_POST['uid'];

    if (!$user_peer = user_get($peer_uid)) {

        html_draw_top(sprintf("title=%s", gettext("Invalid username!")));
        html_error_msg(gettext("Invalid username!"));
        html_draw_bottom();
        exit;
    }

}else {

    html_draw_top(sprintf("title=%s", gettext("No user specified.")));
    html_error_msg(gettext("No user specified."));
    html_draw_bottom();
    exit;
}

// Cannot modify relationship settings for the current account
if ($peer_uid == session_get_value('UID')) {

    html_draw_top(sprintf("title=%s", gettext("Error")));
    html_error_msg(gettext("You cannot change user relationship for your own user account"));
    html_draw_bottom();
    exit;
}

// Fetch the perms of the peer
$peer_perms = perm_get_user_permissions($peer_uid);

// Array to hold error messages.
$error_msg_array = array();

// Form submt code
if (isset($_POST['save'])) {

    $peer_user_status = (double) (isset($_POST['peer_user_status'])) ? $_POST['peer_user_status'] : 0;
    $peer_sig_display = (double) (isset($_POST['peer_sig_display'])) ? $_POST['peer_sig_display'] : 0;
    $peer_block_pm    = (double) (isset($_POST['peer_block_pm']))    ? $_POST['peer_block_pm']    : 0;

    $peer_relationship = (double) $peer_user_status | $peer_sig_display | $peer_block_pm;

    if (isset($_POST['nickname']) && strlen(trim(stripslashes_array($_POST['nickname']))) > 0) {

        $peer_nickname = strip_tags(trim(stripslashes_array($_POST['nickname'])));

    }else {

        if (!$peer_nickname = user_get_nickname($peer_uid)) $peer_nickname = "";
    }

    if (($peer_perms & USER_PERM_FOLDER_MODERATE) && !(session_check_perm(USER_PERM_CAN_IGNORE_ADMIN, 0))) {
        $peer_relationship = ($peer_relationship & USER_IGNORED) ? USER_NORMAL : $peer_relationship;
    }

    if (user_rel_update($uid, $peer_uid, $peer_relationship, $peer_nickname)) {

        header_redirect("$ret&relupdated=true");
        exit;

    }else {

        $error_msg_array[] = gettext("Relationship updated failed!");
        $valid = false;
    }
}

if (isset($_POST['reset_nickname_x']) || isset($_POST['reset_nickname_y'])) {

    $peer_nickname = user_get_nickname($peer_uid);
    $peer_relationship = user_get_peer_relationship($uid, $peer_uid);

    user_rel_update($uid, $peer_uid, $peer_relationship, $peer_nickname);
}

$peer_user_display = format_user_name($user_peer['LOGON'], $user_peer['NICKNAME']);

html_draw_top("title=", gettext("User Relationship"), " - $peer_user_display", 'class=window_title');

$peer_relationship = user_get_relationship($uid, $peer_uid);

$peer_nickname = user_get_peer_nickname($uid, $peer_uid);

echo "<h1>", gettext("User Relationship"), "<img src=\"", html_style_image('separator.png'), "\" alt=\"\" border=\"0\" /><a href=\"user_profile.php?webtag=$webtag&amp;uid=$peer_uid\" target=\"_blank\" class=\"popup 650x500\">", word_filter_add_ob_tags($peer_user_display, true), "</a></h1>\n";

if (isset($error_msg_array) && sizeof($error_msg_array) > 0) {

    html_display_error_array($error_msg_array, '600', 'left');

}else if (($peer_perms & USER_PERM_FOLDER_MODERATE) && !(session_check_perm(USER_PERM_CAN_IGNORE_ADMIN, 0))) {

    html_display_warning_msg(gettext("You cannot ignore this user, as they are a moderator."), '600', 'left');
}

if (isset($_POST['preview_signature'])) {

    $t_sig_content = '';
    $t_sig_html = 'N';

    if (user_get_sig($peer_uid, $t_sig_content, $t_sig_html)) {

        $preview_message['TLOGON'] = gettext("ALL");
        $preview_message['TNICK'] = gettext("ALL");

        $preview_tuser = user_get($peer_uid);

        $preview_message['FLOGON']   = $preview_tuser['LOGON'];
        $preview_message['FNICK']    = $preview_tuser['NICKNAME'];
        $preview_message['FROM_UID'] = $preview_tuser['UID'];

        $preview_message['CONTENT'] = gettext("Signature Preview");

        if ($t_sig_html == "Y") {
            $preview_message['CONTENT'].= "<div class=\"sig\">$t_sig_content</div>";
        }else {
            $preview_message['CONTENT'].= "<div class=\"sig\">". make_html($t_sig_content). "</div>";
        }

        $preview_message['CREATED'] = time();

        echo "  <br />\n";
        echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"600\">\n";
        echo "    <tr>\n";
        echo "      <td align=\"left\">\n";
        echo "        <table class=\"box\" width=\"100%\">\n";
        echo "          <tr>\n";
        echo "            <td align=\"left\" class=\"posthead\">\n";
        echo "              <table class=\"posthead\" width=\"100%\">\n";
        echo "                <tr>\n";
        echo "                  <td align=\"left\" class=\"subhead\">", gettext("Preview"), "</td>\n";
        echo "                </tr>\n";
        echo "              </table>\n";
        echo "              <table class=\"posthead\" width=\"100%\">\n";
        echo "                <tr>\n";
        echo "                  <td align=\"center\">\n";
        echo "                    <table class=\"posthead\" width=\"100%\">\n";
        echo "                      <tr>\n";
        echo "                        <td align=\"left\">\n";
        echo "                          <table cellpadding=\"0\" cellspacing=\"0\" width=\"100%\">\n";
        echo "                            <tr>\n";
        echo "                              <td align=\"left\"><br />", message_display(0, $preview_message, 0, 0, 0, false, false, false, false, true, true), "</td>\n";
        echo "                            </tr>\n";
        echo "                          </table>\n";
        echo "                        </td>\n";
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
        echo "  </table>\n";
        echo "</form>\n";
    }
}

echo "<br />\n";
echo "<form accept-charset=\"utf-8\" name=\"relationship\" action=\"user_rel.php\" method=\"post\" target=\"_self\">\n";
echo "  ", form_input_hidden('webtag', htmlentities_array($webtag)), "\n";
echo "  ", form_input_hidden("uid", htmlentities_array($peer_uid)), "\n";
echo "  ", form_input_hidden("ret", htmlentities_array($ret)), "\n";
echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"600\">\n";
echo "    <tr>\n";
echo "      <td align=\"left\">\n";
echo "        <table class=\"box\" width=\"100%\">\n";
echo "          <tr>\n";
echo "            <td align=\"left\" class=\"posthead\">\n";
echo "              <table class=\"posthead\" width=\"100%\">\n";
echo "                <tr>\n";
echo "                  <td align=\"left\" class=\"subhead\" colspan=\"2\">", gettext("Nickname"), "</td>\n";
echo "                </tr>\n";
echo "                <tr>\n";
echo "                  <td align=\"center\">\n";
echo "                    <table width=\"95%\">\n";
echo "                      <tr>\n";
echo "                        <td align=\"left\" width=\"200\" valign=\"top\">", gettext("Nickname"), "</td>\n";
echo "                        <td align=\"left\" width=\"400\">", form_input_text("nickname", htmlentities_array($peer_nickname), 32), "&nbsp;", form_submit_image('reload.png', "reset_nickname", "Y", sprintf('title="%s"', gettext("Restore User's Nickname"))), "</td>\n";
echo "                      </tr>\n";
echo "                    </table>\n";
echo "                  </td>\n";
echo "                </tr>\n";
echo "                <tr>\n";
echo "                  <td align=\"left\" colspan=\"2\">&nbsp;</td>\n";
echo "                </tr>\n";
echo "              </table>\n";
echo "            </td>\n";
echo "          </tr>\n";
echo "        </table>\n";
echo "      </td>\n";
echo "    </tr>\n";
echo "  </table>\n";
echo "  <br />\n";
echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"600\">\n";
echo "    <tr>\n";
echo "      <td align=\"left\">\n";
echo "        <table class=\"box\" width=\"100%\">\n";
echo "          <tr>\n";
echo "            <td align=\"left\" class=\"posthead\">\n";
echo "              <table class=\"posthead\" width=\"100%\">\n";
echo "                <tr>\n";
echo "                  <td align=\"left\" class=\"subhead\" colspan=\"2\">", gettext("Relationship"), "</td>\n";
echo "                </tr>\n";
echo "                <tr>\n";
echo "                  <td align=\"center\">\n";
echo "                    <table class=\"posthead\" width=\"95%\">\n";
echo "                      <tr>\n";
echo "                        <td align=\"left\" width=\"150\">", form_radio('peer_user_status', USER_FRIEND, gettext("Friend"), $peer_relationship & USER_FRIEND ? true : false), "</td>\n";
echo "                        <td align=\"left\" width=\"400\">: ", gettext("User's posts marked with a &quot;Friend&quot; icon."), "</td>\n";
echo "                      </tr>\n";
echo "                      <tr>\n";
echo "                        <td align=\"left\" width=\"150\">", form_radio('peer_user_status', USER_NORMAL, gettext("Normal"), !(($peer_relationship & USER_IGNORED) || ($peer_relationship & USER_FRIEND) || ($peer_relationship & USER_IGNORED_COMPLETELY)) ? true : false), "</td>\n";
echo "                        <td align=\"left\" width=\"400\">: ", gettext("User's posts appear as normal."), "</td>\n";
echo "                      </tr>\n";

if ((($peer_perms & USER_PERM_FOLDER_MODERATE) && (session_check_perm(USER_PERM_CAN_IGNORE_ADMIN, 0))) || !($peer_perms & USER_PERM_FOLDER_MODERATE)) {

    echo "                      <tr>\n";
    echo "                        <td align=\"left\" width=\"150\">", form_radio('peer_user_status', USER_IGNORED, gettext("Ignored"), $peer_relationship & USER_IGNORED ? true : false), "</td>\n";
    echo "                        <td align=\"left\" width=\"400\">: ", gettext("User's posts are hidden."), "</td>\n";
    echo "                      </tr>\n";
    echo "                      <tr>\n";
    echo "                        <td align=\"left\" width=\"150\">", form_radio('peer_user_status', USER_IGNORED_COMPLETELY, gettext("Ignored Completely"), $peer_relationship & USER_IGNORED_COMPLETELY ? true : false), "</td>\n";
    echo "                        <td align=\"left\" width=\"400\">: ", gettext("Threads and posts to or from user will appear deleted."), "</td>\n";
    echo "                      </tr>\n";
}

echo "                    </table>\n";
echo "                  </td>\n";
echo "                </tr>\n";
echo "                <tr>\n";
echo "                  <td align=\"left\" colspan=\"2\">&nbsp;</td>\n";
echo "                </tr>\n";
echo "              </table>\n";
echo "            </td>\n";
echo "          </tr>\n";
echo "        </table>\n";
echo "      </td>\n";
echo "    </tr>\n";
echo "  </table>\n";
echo "  <br />\n";
echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"600\">\n";
echo "    <tr>\n";
echo "      <td align=\"left\">\n";
echo "        <table class=\"box\" width=\"100%\">\n";
echo "          <tr>\n";
echo "            <td align=\"left\" class=\"posthead\">\n";
echo "              <table class=\"posthead\" width=\"100%\">\n";
echo "                <tr>\n";
echo "                  <td align=\"left\" class=\"subhead\" colspan=\"2\">", gettext("Signature"), "</td>\n";
echo "                </tr>\n";
echo "                <tr>\n";
echo "                  <td align=\"center\">\n";
echo "                    <table class=\"posthead\" width=\"95%\">\n";
echo "                      <tr>\n";
echo "                        <td align=\"left\" width=\"150\">", form_radio('peer_sig_display', USER_NORMAL, gettext("Display"), $peer_relationship ^ USER_IGNORED_SIG ? true : false), "</td>\n";
echo "                        <td align=\"left\" width=\"400\">: ", gettext("User's signature is displayed on their posts."), "</td>\n";
echo "                      </tr>\n";
echo "                      <tr>\n";
echo "                        <td align=\"left\" width=\"150\">", form_radio('peer_sig_display', USER_IGNORED_SIG, gettext("Ignore"), $peer_relationship & USER_IGNORED_SIG ? true : false), "</td>\n";
echo "                        <td align=\"left\" width=\"400\">: ", gettext("User's signature is hidden on their posts."), "</td>\n";
echo "                      </tr>\n";
echo "                    </table>\n";
echo "                  </td>\n";
echo "                </tr>\n";
echo "                <tr>\n";
echo "                  <td align=\"left\" colspan=\"2\">&nbsp;</td>\n";
echo "                </tr>\n";
echo "              </table>\n";
echo "            </td>\n";
echo "          </tr>\n";
echo "        </table>\n";
echo "      </td>\n";
echo "    </tr>\n";
echo "  </table>\n";
echo "  <br />\n";
echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"600\">\n";
echo "    <tr>\n";
echo "      <td align=\"left\">\n";
echo "        <table class=\"box\" width=\"100%\">\n";
echo "          <tr>\n";
echo "            <td align=\"left\" class=\"posthead\">\n";
echo "              <table class=\"posthead\" width=\"100%\">\n";
echo "                <tr>\n";
echo "                  <td align=\"left\" class=\"subhead\" colspan=\"2\">", gettext("Personal Messages"), "</td>\n";
echo "                </tr>\n";
echo "                <tr>\n";
echo "                  <td align=\"center\">\n";
echo "                    <table class=\"posthead\" width=\"95%\">\n";
echo "                      <tr>\n";
echo "                        <td align=\"left\" width=\"150\">", form_radio('peer_block_pm', USER_NORMAL, gettext("Allow"), $peer_relationship ^ USER_BLOCK_PM ? true : false), "</td>\n";
echo "                        <td align=\"left\" width=\"400\">: ", gettext("Allow user to send personal messages to me"), "</td>\n";
echo "                      </tr>\n";
echo "                      <tr>\n";
echo "                        <td align=\"left\" width=\"150\">", form_radio('peer_block_pm', USER_BLOCK_PM, gettext("Block"), $peer_relationship & USER_BLOCK_PM ? true : false), "</td>\n";
echo "                        <td align=\"left\" width=\"400\">: ", gettext("Block user from sending personal messages to me"), "</td>\n";
echo "                      </tr>\n";
echo "                    </table>\n";
echo "                  </td>\n";
echo "                </tr>\n";
echo "                <tr>\n";
echo "                  <td align=\"left\" colspan=\"2\">&nbsp;</td>\n";
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
echo "      <td align=\"center\"><p>", form_submit("save", gettext("Save")), "&nbsp;", form_submit("preview_signature", gettext("Preview Signature")), "&nbsp;", form_submit("cancel", gettext("Cancel")), "</p></td>\n";
echo "    </tr>\n";
echo "  </table>\n";
echo "</form>\n";

html_draw_bottom();

?>
