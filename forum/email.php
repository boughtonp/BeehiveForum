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

include_once(BH_INCLUDE_PATH. "email.inc.php");
include_once(BH_INCLUDE_PATH. "form.inc.php");
include_once(BH_INCLUDE_PATH. "format.inc.php");
include_once(BH_INCLUDE_PATH. "header.inc.php");
include_once(BH_INCLUDE_PATH. "html.inc.php");
include_once(BH_INCLUDE_PATH. "lang.inc.php");
include_once(BH_INCLUDE_PATH. "logon.inc.php");
include_once(BH_INCLUDE_PATH. "session.inc.php");
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

// Forum name
$forum_name = forum_get_setting('forum_name', false, 'A Beehive Forum');

// Check that we have access to this forum
if (!forum_check_access_level()) {

    $request_uri = rawurlencode(get_request_uri());
    header_redirect("forums.php?webtag_error&final_uri=$request_uri");
}

if (user_is_guest()) {

    html_guest_error();
    exit;
}

// Array to hold error messages
$error_msg_array = array();

// User UID to send email to.
if (isset($_GET['uid']) && is_numeric($_GET['uid'])) {

    $to_uid = $_GET['uid'];

}else if (isset($_POST['to_uid']) && is_numeric($_POST['to_uid'])) {

    $to_uid = $_POST['to_uid'];

}else {

    html_draw_top("title=", gettext("Error"), "", 'pm_popup_disabled');
    html_error_msg(gettext("No user specified for emailing."));
    html_draw_bottom();
    exit;
}

$uid = session_get_value('UID');

$to_user = user_get($to_uid);

$from_user = user_get($uid);

if (isset($_POST['send'])) {

    $valid = true;

    if (isset($_POST['t_subject']) && strlen(trim(stripslashes_array($_POST['t_subject']))) > 0) {

        $subject = trim(stripslashes_array($_POST['t_subject']));

    }else {

        $error_msg_array[] = gettext("Enter a subject for the message");
        $valid = false;
    }

    if (isset($_POST['t_message']) && strlen(trim(stripslashes_array($_POST['t_message']))) > 0) {

        $message = trim(stripslashes_array($_POST['t_message']));

    }else {

        $error_msg_array[] = gettext("Enter some content for the message");
        $valid = false;
    }

    if (isset($_POST['t_use_email_addr']) && $_POST['t_use_email_addr'] == 'Y') {
        $use_email_addr = true;
    } else {
        $use_email_addr = false;
    }

    if (!user_allow_email($to_user['UID'])) {

        $error_msg_array[] = sprintf(gettext("%s has opted out of email contact"), word_filter_add_ob_tags(format_user_name($to_user['LOGON'], $to_user['NICKNAME']), true));
        $valid = false;
    }

    if (!email_address_valid($to_user['EMAIL'])) {

        $error_msg_array[] = sprintf(gettext("%s has an invalid email address"), word_filter_add_ob_tags(format_user_name($to_user['LOGON'], $to_user['NICKNAME']), true));
        $valid = false;
    }

    if ($valid) {

        if (email_send_message_to_user($to_uid, $uid, $subject, $message, $use_email_addr)) {

            html_draw_top("title=", gettext("Email result"), "", 'pm_popup_disabled', 'class=window_title');
            html_display_msg(gettext("Message sent"), gettext("Message sent successfully."), 'email.php', 'post', array('close' => gettext("Close")), array('to_uid' => $to_uid), false, 'center');
            html_draw_bottom();
            exit;

        }else {

            html_draw_top("title=", gettext("Email result"), "", 'pm_popup_disabled', 'class=window_title');
            html_error_msg(gettext("Mail system failure. Message not sent."), 'email.php', 'post', array('close' => gettext("Close")), array('to_uid' => $to_uid), false, 'center');
            html_draw_bottom();
            exit;
        }
    }
}

html_draw_top("title=". sprintf(gettext("Send Email to %s"), htmlentities_array(format_user_name($to_user['LOGON'], $to_user['NICKNAME']))), 'pm_popup_disabled', 'class=window_title');

echo "<h1>", sprintf(gettext("Send Email to %s"), htmlentities_array(format_user_name($to_user['LOGON'], $to_user['NICKNAME']))), "</h1>\n";
echo "<br />";
echo "<div align=\"center\">\n";
echo "<form accept-charset=\"utf-8\" name=\"f_email\" action=\"email.php\" method=\"post\">\n";
echo "  ", form_input_hidden('webtag', htmlentities_array($webtag)), "\n";
echo "  ", form_input_hidden("to_uid", htmlentities_array($to_uid)), "\n";

if (isset($error_msg_array) && sizeof($error_msg_array) > 0) {
    html_display_error_array($error_msg_array, '480', 'center');
}

echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"480\">\n";
echo "    <tr>\n";
echo "      <td align=\"left\">\n";
echo "        <table class=\"box\">\n";
echo "          <tr>\n";
echo "            <td align=\"left\" class=\"posthead\">\n";
echo "              <table class=\"posthead\" width=\"480\">\n";
echo "                <tr>\n";
echo "                  <td align=\"left\" class=\"subhead\" colspan=\"2\">", sprintf(gettext("Send Email to %s"), htmlentities_array(format_user_name($to_user['LOGON'], $to_user['NICKNAME']))), "</td>\n";
echo "                </tr>\n";
echo "                <tr>\n";
echo "                  <td align=\"center\">\n";
echo "                    <table class=\"posthead\" width=\"95%\">\n";
echo "                      <tr>\n";
echo "                        <td align=\"left\" width=\"25%\">", gettext("From"), ":</td>\n";
echo "                        <td align=\"left\">", word_filter_add_ob_tags($from_user['NICKNAME'], true), "</td>\n";
echo "                      </tr>\n";
echo "                      <tr>\n";
echo "                        <td align=\"left\">", gettext("Subject"), ":</td>\n";
echo "                        <td align=\"left\">", form_input_text("t_subject", (isset($subject) ? htmlentities_array($subject) : ''), 54, 128), "</td>\n";
echo "                      </tr>\n";
echo "                      <tr>\n";
echo "                        <td align=\"left\" valign=\"top\">", gettext("Message"), ":</td>\n";
echo "                        <td align=\"left\">", form_textarea("t_message", (isset($message) ? htmlentities_array($message) : ''), 12, 51), "</td>\n";
echo "                      </tr>\n";
echo "                      <tr>\n";
echo "                        <td align=\"left\" valign=\"top\">&nbsp;</td>\n";
echo "                        <td align=\"left\">", form_checkbox('t_use_email_addr', 'Y', gettext("Use my real email address to send this message"), (isset($use_email_addr) ? $use_email_addr : session_get_value('USE_EMAIL_ADDR') == 'Y')), "</td>\n";
echo "                      </tr>\n";
echo "                      <tr>\n";
echo "                        <td align=\"left\" colspan=\"2\">&nbsp;</td>\n";
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
echo "      <td align=\"center\">", form_submit("send", gettext("Send")), "&nbsp;", form_button("close_popup", gettext("Cancel")), "</td>\n";
echo "    </tr>\n";
echo "  </table>\n";
echo "</form>\n";
echo "</div>\n";

html_draw_bottom();

?>