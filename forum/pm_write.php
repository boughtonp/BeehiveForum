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

include_once(BH_INCLUDE_PATH. "attachments.inc.php");
include_once(BH_INCLUDE_PATH. "constants.inc.php");
include_once(BH_INCLUDE_PATH. "email.inc.php");
include_once(BH_INCLUDE_PATH. "emoticons.inc.php");
include_once(BH_INCLUDE_PATH. "fixhtml.inc.php");
include_once(BH_INCLUDE_PATH. "form.inc.php");
include_once(BH_INCLUDE_PATH. "format.inc.php");
include_once(BH_INCLUDE_PATH. "header.inc.php");
include_once(BH_INCLUDE_PATH. "html.inc.php");
include_once(BH_INCLUDE_PATH. "htmltools.inc.php");
include_once(BH_INCLUDE_PATH. "lang.inc.php");
include_once(BH_INCLUDE_PATH. "logon.inc.php");
include_once(BH_INCLUDE_PATH. "pm.inc.php");
include_once(BH_INCLUDE_PATH. "post.inc.php");
include_once(BH_INCLUDE_PATH. "session.inc.php");
include_once(BH_INCLUDE_PATH. "user.inc.php");
include_once(BH_INCLUDE_PATH. "messages.inc.php");
include_once(BH_INCLUDE_PATH. "thread.inc.php");

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

// Initialise Locale
lang_init();

// Get the user's UID
$uid = session_get_value('UID');

// Guests can't access this page.
if (user_is_guest()) {

    html_guest_error();
    exit;
}

// Check that PM system is enabled
pm_enabled();

// Get the user's post page preferences.
$page_prefs = session_get_post_page_prefs();

// Prune old messages for the current user
pm_user_prune_folders();

// Get the Message ID (MID) if any.
if (isset($_GET['replyto']) && is_numeric($_GET['replyto'])) {

    $t_reply_mid = $_GET['replyto'];

}elseif (isset($_POST['replyto']) && is_numeric($_POST['replyto'])) {

    $t_reply_mid = $_POST['replyto'];

}elseif (isset($_GET['fwdmsg']) && is_numeric($_GET['fwdmsg'])) {

    $t_forward_mid = $_GET['fwdmsg'];

}elseif (isset($_POST['fwdmsg']) && is_numeric($_POST['fwdmsg'])) {

    $t_forward_mid = $_POST['fwdmsg'];

}elseif (isset($_GET['editmsg']) && is_numeric($_GET['editmsg'])) {

    $t_edit_mid = $_GET['editmsg'];

}elseif (isset($_POST['editmsg']) && is_numeric($_POST['editmsg'])) {

    $t_edit_mid = $_POST['editmsg'];
}

// Get the tid.pid if any.
if (isset($_GET['msg']) && validate_msg($_GET['msg'])) {

    list($tid, $pid) = explode('.', $_GET['msg']);

    if (is_numeric($tid) && is_numeric($pid)) {

        if (($thread_data = thread_get($tid))) {

            $thread_title = trim($thread_data['TITLE']);
            $thread_index = "[$tid.$pid]";

            if (mb_strlen($thread_title) > (55 - mb_strlen($thread_index))) {
                $thread_title = mb_substr($thread_title, 0, (55 - mb_strlen($thread_index))). '...';
            }

            $t_subject = "RE:$thread_title $thread_index";
        }
    }
}

// Default Folder
$folder = PM_FOLDER_INBOX;

if (isset($_GET['folder'])) {

    if ($_GET['folder'] == PM_FOLDER_SENT) {
        $folder = PM_FOLDER_SENT;
    }else if ($_GET['folder'] == PM_FOLDER_OUTBOX) {
        $folder = PM_FOLDER_OUTBOX;
    }else if ($_GET['folder'] == PM_FOLDER_SAVED) {
        $folder = PM_FOLDER_SAVED;
    }

}elseif (isset($_POST['folder'])) {

    if ($_POST['folder'] == PM_FOLDER_SENT) {
        $folder = PM_FOLDER_SENT;
    }else if ($_POST['folder'] == PM_FOLDER_OUTBOX) {
        $folder = PM_FOLDER_OUTBOX;
    }else if ($_POST['folder'] == PM_FOLDER_SAVED) {
        $folder = PM_FOLDER_SAVED;
    }
}

// Assume everything is correct (form input, etc)
$valid = true;

// Array to hold error messages
$error_msg_array = array();

// For future's sake, if we ever add an admin option for allowing/disallowing HTML PMs.
// Then just do something like $allow_html = forum_allow_html_pms() ? true : false
$allow_html = true;

// User clicked the emoticon panel toggle button
if (isset($_POST['emots_toggle'])) {

    if (isset($_POST['t_subject']) && strlen(trim(stripslashes_array($_POST['t_subject']))) > 0) {
        $t_subject = trim(stripslashes_array($_POST['t_subject']));
    }

    if (isset($_POST['t_content']) && strlen(trim(stripslashes_array($_POST['t_content']))) > 0) {
        $t_content = trim(stripslashes_array($_POST['t_content']));
    }

    if (isset($_POST['to_radio']) && strlen(trim(stripslashes_array($_POST['to_radio']))) > 0) {
        $to_radio = trim(stripslashes_array($_POST['to_radio']));
    }else {
        $to_radio = '';
    }

    if (isset($_POST['t_to_uid']) && is_numeric($_POST['t_to_uid'])) {
        $t_to_uid = $_POST['t_to_uid'];
    }else {
        $t_to_uid = 0;
    }

    if (isset($_POST['t_to_uid_others']) && strlen(trim(stripslashes_array($_POST['t_to_uid_others']))) > 0) {
        $t_to_uid_others = trim(stripslashes_array($_POST['t_to_uid_others']));
    }

    $page_prefs = (double) $page_prefs ^ POST_EMOTICONS_DISPLAY;

    $user_prefs = array('POST_PAGE' => $page_prefs);
    $user_prefs_global = array();

    if (!user_update_prefs($uid, $user_prefs, $user_prefs_global)) {

        $error_msg_array[] = gettext("Some or all of your user account details could not be updated. Please try again later.");
        $valid = false;
    }
}

// Some Options.
if (isset($_POST['t_post_emots'])) {

    if ($_POST['t_post_emots'] == "disabled") {
        $emots_enabled = false;
    }else {
        $emots_enabled = true;
    }

}else {

    $emots_enabled = true;
}

if (isset($_POST['t_post_links'])) {

   if ($_POST['t_post_links'] == "enabled") {
        $links_enabled = true;
   } else {
        $links_enabled = false;
   }

}else {

   $links_enabled = false;
}

if (isset($_POST['t_check_spelling'])) {

    if ($_POST['t_check_spelling'] == "enabled") {
        $spelling_enabled = true;
    } else {
        $spelling_enabled = false;
    }

}else {

    $spelling_enabled = ($page_prefs & POST_CHECK_SPELLING);
}

if (isset($_POST['t_post_html'])) {

    $t_post_html = $_POST['t_post_html'];

    if ($t_post_html == "enabled_auto") {
        $post_html = POST_HTML_AUTO;
    }else if ($t_post_html == "enabled") {
        $post_html = POST_HTML_ENABLED;
    }else {
        $post_html = POST_HTML_DISABLED;
    }

}else if (!isset($post_html)) {

    if (($page_prefs & POST_AUTOHTML_DEFAULT) > 0) {
        $post_html = POST_HTML_AUTO;
    }else if (($page_prefs & POST_HTML_DEFAULT) > 0) {
        $post_html = POST_HTML_ENABLED;
    }else {
        $post_html = POST_HTML_DISABLED;
    }

    if (($page_prefs & POST_EMOTICONS_DISABLED) > 0) {
        $emots_enabled = false;
    }else {
        $emots_enabled = true;
    }

    if (($page_prefs & POST_AUTO_LINKS) > 0) {
        $links_enabled = true;
    }else {
        $links_enabled = false;
    }
}

if (!isset($t_content)) $t_content = "";

$post = new MessageText($post_html, $t_content, $emots_enabled, $links_enabled);

$t_content = $post->getContent();

// Submit handling code
if (isset($_POST['send']) || isset($_POST['preview'])) {

    // User clicked the send or preview button - check the data that was submitted
    if (isset($_POST['t_subject']) && strlen(trim(stripslashes_array($_POST['t_subject']))) > 0) {

        $t_subject = trim(stripslashes_array($_POST['t_subject']));

    }else {

        $error_msg_array[] = gettext("Enter a subject for the message");
        $valid = false;
    }

    if (isset($_POST['t_content']) && strlen(trim(stripslashes_array($_POST['t_content']))) > 0) {

        $t_content = trim(stripslashes_array($_POST['t_content']));

        $post->setContent($t_content);

        $t_content = $post->getContent();

    }else {

        $error_msg_array[] = gettext("Enter some content for the message");
        $valid = false;
    }

    if (isset($_POST['to_radio']) && strlen(trim(stripslashes_array($_POST['to_radio']))) > 0) {
        $to_radio = trim(stripslashes_array($_POST['to_radio']));
    }else {
        $to_radio = '';
    }

    if (isset($_POST['t_to_uid']) && is_numeric($_POST['t_to_uid'])) {
        $t_to_uid = $_POST['t_to_uid'];
    }else {
        $t_to_uid = 0;
    }

    if ($to_radio == 'friends' && $t_to_uid == 0) {

        $error_msg_array[] = gettext("You must specify at least one recipient.");
        $valid = false;
    }

    if (isset($t_reply_mid) && is_numeric($t_reply_mid) && $t_reply_mid > 0) {

        if (($pm_data = pm_message_get($t_reply_mid))) {

            $pm_data['CONTENT'] = pm_get_content($t_reply_mid);

        }else {

            html_draw_top(sprintf("title=%s", gettext("Error")));
            pm_error_refuse();
            html_draw_bottom();
            exit;
        }
    }

    if (isset($_POST['t_to_uid_others']) && strlen(trim(stripslashes_array($_POST['t_to_uid_others']))) > 0) {

        $t_recipient_array = preg_split("/[;|,]/u", trim(stripslashes_array($_POST['t_to_uid_others'])));

        $t_new_recipient_array['TO_UID'] = array();
        $t_new_recipient_array['LOGON']  = array();
        $t_new_recipient_array['NICK']   = array();

        foreach ($t_recipient_array as $key => $t_recipient) {

            $to_logon = trim($t_recipient);

            if (($to_user = user_get_by_logon($to_logon))) {

                $peer_relationship = user_get_peer_relationship($to_user['UID'], $uid);

                if (!in_array($to_user['UID'], $t_new_recipient_array['TO_UID'])) {

                    $t_new_recipient_array['TO_UID'][] = $to_user['UID'];
                    $t_new_recipient_array['LOGON'][]  = $to_user['LOGON'];
                    $t_new_recipient_array['NICK'][]   = $to_user['NICKNAME'];
                }

                if ($to_radio == 'others') {

                    if ((($peer_relationship ^ USER_BLOCK_PM) && user_allow_pm($to_user['UID'])) || session_check_perm(USER_PERM_FOLDER_MODERATE, 0)) {

                        pm_user_prune_folders();

                        if (pm_get_free_space($uid) < sizeof($t_new_recipient_array['TO_UID'])) {

                            $error_msg_array[] = gettext("You do not have enough free space to send this message.");
                            $valid = false;
                        }

                    }else {

                        $error_msg_array[] = sprintf(gettext("%s has opted out of receiving personal messages"), $to_logon);
                        $valid = false;
                    }
                }

            }else {

                $error_msg_array[] = sprintf(gettext("User %s not found"), $to_logon);
                $valid = false;
            }
        }

        $t_to_uid_others = implode('; ', $t_new_recipient_array['LOGON']);

        if ($to_radio == 'others') {

            if ($valid && sizeof($t_new_recipient_array['TO_UID']) > 10) {

                $error_msg_array[] = gettext("There is a limit of 10 recipients per message. Please amend your recipient list.");
                $valid = false;
            }

            if ($valid && sizeof($t_new_recipient_array['TO_UID']) < 1) {

                $error_msg_array[] = gettext("You must specify at least one recipient.");
                $valid = false;
            }
        }

    }elseif ($to_radio == 'others') {

        $error_msg_array[] = gettext("You must specify at least one recipient.");
        $valid = false;
    }

}else if (isset($_POST['save'])) {

    // User click the save button - Check the data that was submitted.
    if (isset($_POST['t_subject']) && strlen(trim(stripslashes_array($_POST['t_subject']))) > 0) {

        $t_subject = trim(stripslashes_array($_POST['t_subject']));

    }else {

        $t_subject = "";
    }

    if (isset($_POST['t_content']) && strlen(trim(stripslashes_array($_POST['t_content']))) > 0) {

        $t_content = trim(stripslashes_array($_POST['t_content']));

        $post->setContent($t_content);

        $t_content = $post->getContent();

    }else {

        $t_content = "";
    }

    if (isset($_POST['to_radio']) && strlen(trim(stripslashes_array($_POST['to_radio']))) > 0) {
        $to_radio = trim(stripslashes_array($_POST['to_radio']));
    }else {
        $to_radio = '';
    }

    if (isset($_POST['t_to_uid']) && is_numeric($_POST['t_to_uid'])) {
        $t_to_uid = $_POST['t_to_uid'];
    }else {
        $t_to_uid = 0;
    }

    if (isset($_POST['aid']) && is_md5($_POST['aid'])) {
        $aid = $_POST['aid'];
    }else{
        $aid = md5(uniqid(mt_rand()));
    }

    if (isset($_POST['t_to_uid_others']) && strlen(trim(stripslashes_array($_POST['t_to_uid_others']))) > 0) {

        $t_recipient_array = preg_split("/[;|,]/u", trim(stripslashes_array($_POST['t_to_uid_others'])));

        if (sizeof($t_recipient_array) > 10) {

            $error_msg_array[] = gettext("There is a limit of 10 recipients per message. Please amend your recipient list.");
            $valid = false;
        }

        $t_to_uid_others = implode(';', $t_recipient_array);

    }else {

        $t_to_uid_others = "";
    }

}else if (isset($t_reply_mid) && is_numeric($t_reply_mid) && $t_reply_mid > 0) {

    if (!$t_to_uid_others = pm_get_user($t_reply_mid)) $t_to_uid_others = "";

    if (($pm_data = pm_message_get($t_reply_mid))) {

        $pm_data['CONTENT'] = pm_get_content($t_reply_mid);

        $t_subject = preg_replace('/^(RE:)?/iu', 'RE:', $pm_data['SUBJECT']);

        $message_author = htmlentities_array(format_user_name($pm_data['FLOGON'], $pm_data['FNICK']));

        if (session_get_value('PM_INCLUDE_REPLY') == 'Y') {

            if ($page_prefs & POST_TINYMCE_DISPLAY) {

                $t_content = "<div class=\"quotetext\" id=\"quote\">";
                $t_content.= "<b>quote: </b>$message_author</div>";
                $t_content.= "<div class=\"quote\">";
                $t_content.= trim(strip_tags(strip_paragraphs($pm_data['CONTENT'])));
                $t_content.= "</div><p>&nbsp;</p>";

            }else {

                $t_content = "<quote source=\"$message_author\" url=\"\">";
                $t_content.= trim(strip_tags(strip_paragraphs($pm_data['CONTENT'])));
                $t_content.= "</quote>\n\n";
            }

            // Set the HTML mode to 'with automatic line breaks' so
            // the quote is handled correctly when the user previews
            // the message.
            $post->setHTML(POST_HTML_AUTO);

            $t_content = $post->getContent();

            $post_html = POST_HTML_AUTO;
        }

    }else {

        html_draw_top(sprintf("title=%s", gettext("Error")));
        pm_error_refuse();
        html_draw_bottom();
        exit;
    }

}else if (isset($t_forward_mid) && is_numeric($t_forward_mid) && $t_forward_mid > 0) {

    if (($pm_data = pm_message_get($t_forward_mid))) {

        $pm_data['CONTENT'] = pm_get_content($t_forward_mid);

        $t_subject = preg_replace('/^(FWD:)?/iu', 'FWD:', $pm_data['SUBJECT']);

        $message_author = htmlentities_array(format_user_name($pm_data['FLOGON'], $pm_data['FNICK']));

        if ($page_prefs & POST_TINYMCE_DISPLAY) {

            $t_content = "<div class=\"quotetext\" id=\"quote\">";
            $t_content.= "<b>quote: </b>$message_author</div>";
            $t_content.= "<div class=\"quote\">";
            $t_content.= trim(strip_tags(strip_paragraphs($pm_data['CONTENT'])));
            $t_content.= "</div><p>&nbsp;</p>";

        }else {

            $t_content = "<quote source=\"$message_author\" url=\"\">";
            $t_content.= trim(strip_tags(strip_paragraphs($pm_data['CONTENT'])));
            $t_content.= "</quote>\n\n";
        }

        // Set the HTML mode to 'with automatic line breaks' so
        // the quote is handled correctly when the user previews
        // the message.
        $post->setHTML(POST_HTML_AUTO);

        $t_content = $post->getContent();

        $post_html = POST_HTML_AUTO;

    }else {

        html_draw_top(sprintf("title=%s", gettext("Error")));
        pm_error_refuse();
        html_draw_bottom();
        exit;
    }

}else if (isset($t_edit_mid) && is_numeric($t_edit_mid) && $t_edit_mid > 0) {

    if (($pm_data = pm_message_get($t_edit_mid))) {

        $pm_data['CONTENT'] = pm_get_content($t_edit_mid);

        $t_subject = $pm_data['SUBJECT'];

        $parsed_message = new MessageTextParse($pm_data['CONTENT'], $emots_enabled, $links_enabled);

        $emots_enabled = $parsed_message->getEmoticons();
        $links_enabled = $parsed_message->getLinks();

        $t_content = $parsed_message->getMessage();
        $post_html = $parsed_message->getMessageHTML();

        $post->setHTML($allow_html ? $post_html : POST_HTML_DISABLED);

        $post->setContent($t_content);
        $post->setEmoticons($emots_enabled);
        $post->setLinks($links_enabled);

        $post->diff = false;

        $t_content = $post->getContent();

        $t_subject = $pm_data['SUBJECT'];

        $t_to_uid = $pm_data['TO_UID'];

        $t_to_uid_others = $pm_data['RECIPIENTS'];

        if (strlen($t_to_uid_others) > 0) {
            $to_radio = 'others';
        }elseif ($t_to_uid > 0) {
            $to_radio = 'friends';
        }

        $aid = $pm_data['AID'];

    }else {

        html_draw_top(sprintf("title=%s", gettext("Error")));
        pm_error_refuse();
        html_draw_bottom();
        exit;
    }
}

// Check the message length.
if (mb_strlen($t_content) >= 65535) {

    $error_msg_array[] = sprintf(gettext("Message length must be under 65,535 characters (currently: %s)"), number_format(mb_strlen($t_content)));
    $valid = false;
}

// Attachment Unique ID
if (isset($_POST['aid']) && is_md5($_POST['aid'])) {
    $aid = $_POST['aid'];
}else if (!isset($aid)) {
    $aid = md5(uniqid(mt_rand()));
}

// De-dupe key
if (isset($_POST['t_dedupe']) && is_numeric($_POST['t_dedupe'])) {
    $t_dedupe = $_POST['t_dedupe'];
}else{
    $t_dedupe = time();
}

// Send the PM
if ($valid && isset($_POST['send'])) {

    if (post_check_ddkey($t_dedupe)) {

        if (isset($to_radio) && $to_radio == 'friends') {

            if (($new_mid = pm_send_message($t_to_uid, $uid, $t_subject, $t_content, $aid))) {

                email_send_pm_notification($t_to_uid, $new_mid, $uid);

            }else {

                $error_msg_array[] = gettext("Error creating PM! Please try again in a few minutes");
                $valid = false;
            }

            if (isset($t_edit_mid) && is_numeric($t_edit_mid)) {
                pm_delete_message($t_edit_mid);
            }

        }else {

            foreach ($t_new_recipient_array['TO_UID'] as $t_to_uid) {

                if (($new_mid = pm_send_message($t_to_uid, $uid, $t_subject, $t_content, $aid))) {

                    email_send_pm_notification($t_to_uid, $new_mid, $uid);

                }else {

                    $error_msg_array[] = gettext("Error creating PM! Please try again in a few minutes");
                    $valid = false;
                }
            }

            if (isset($t_edit_mid) && is_numeric($t_edit_mid)) {
                pm_delete_message($t_edit_mid);
            }
        }
    }

    if ($valid) {

        header_redirect("pm.php?webtag=$webtag&message_sent=true");
        exit;
    }

}else if ($valid && isset($_POST['save'])) {

    if (isset($t_edit_mid) && is_numeric($t_edit_mid)) {

        if (pm_update_saved_message($t_edit_mid, $t_subject, $t_content, $t_to_uid, $t_to_uid_others)) {

            header_redirect("pm.php?webtag=$webtag&mid=$t_edit_mid&message_saved=true");
            exit;

        }else {

            $error_msg_array[] = gettext("Could not save message. Make sure you have enough available free space.");
            $valid = false;
        }

    }else {

        if (($saved_mid = pm_save_message($t_subject, $t_content, $t_to_uid, $t_to_uid_others))) {

            pm_save_attachment_id($saved_mid, $aid);

            header_redirect("pm.php?webtag=$webtag&mid=$saved_mid&message_saved=true");
            exit;

        }else {

            $error_msg_array[] = gettext("Could not save message. Make sure you have enough available free space.");
            $valid = false;
        }
    }
}

html_draw_top("title=", gettext("Private Messages"), " - ", gettext("Send New PM"), "", "onUnload=clearFocus()", "resize_width=720", "pm.js", "attachments.js", "dictionary.js", "htmltools.js", "emoticons.js", "search_popup.js", "basetarget=_blank", 'class=window_title');

echo "<h1>", gettext("Private Messages"), "<img src=\"", html_style_image('separator.png'), "\" alt=\"\" border=\"0\" />", gettext("Send New PM"), "</h1>\n";

if (isset($error_msg_array) && sizeof($error_msg_array) > 0) {
    html_display_error_array($error_msg_array, '720', 'left');
}

echo "<br />\n";
echo "<form accept-charset=\"utf-8\" name=\"f_post\" action=\"pm_write.php\" method=\"post\" target=\"_self\">\n";
echo "  ", form_input_hidden('webtag', htmlentities_array($webtag)), "\n";
echo "  ", form_input_hidden('folder', htmlentities_array($folder)), "\n";
echo "  ", form_input_hidden("t_dedupe", htmlentities_array($t_dedupe));
echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"720\" class=\"max_width\">\n";
echo "    <tr>\n";
echo "      <td align=\"left\">\n";
echo "        <table class=\"box\" width=\"100%\">\n";
echo "          <tr>\n";
echo "            <td align=\"left\" class=\"posthead\">\n";

// preview message
if ($valid && isset($_POST['preview'])) {

    echo "              <table class=\"posthead\" width=\"720\">\n";
    echo "                <tr>\n";
    echo "                  <td align=\"left\" class=\"subhead\" colspan=\"3\">", gettext("Message Preview"), "</td>\n";
    echo "                </tr>\n";

    if (isset($to_radio) && $to_radio == 'friends') {

        $preview_tuser = user_get($t_to_uid);

        $pm_preview_array['TLOGON'] = $preview_tuser['LOGON'];
        $pm_preview_array['TNICK']  = $preview_tuser['NICKNAME'];
        $pm_preview_array['TO_UID'] = $preview_tuser['UID'];

    }else {

        $pm_preview_array['TLOGON'] = $t_new_recipient_array['LOGON'];
        $pm_preview_array['TNICK']  = $t_new_recipient_array['NICK'];
        $pm_preview_array['TO_UID'] = $t_new_recipient_array['TO_UID'];
    }

    $preview_fuser = user_get($uid);

    $pm_preview_array['FLOGON'] = $preview_fuser['LOGON'];
    $pm_preview_array['FNICK']  = $preview_fuser['NICKNAME'];
    $pm_preview_array['FROM_UID'] = $preview_fuser['UID'];

    $pm_preview_array['SUBJECT'] = $t_subject;
    $pm_preview_array['CREATED'] = time();
    $pm_preview_array['AID'] = $aid;

    $pm_preview_array['CONTENT'] = $t_content;

    echo "                <tr>\n";
    echo "                  <td align=\"left\">&nbsp;</td>\n";
    echo "                  <td align=\"left\" width=\"690\"><br />", pm_display($pm_preview_array, PM_FOLDER_OUTBOX, true), "</td>\n";
    echo "                  <td align=\"left\">&nbsp;</td>\n";
    echo "                </tr>\n";
    echo "                <tr>\n";
    echo "                  <td align=\"left\" colspan=\"3\">&nbsp;</td>\n";
    echo "                </tr>\n";
    echo "              </table>\n";
}

echo "              <table width=\"720\" class=\"posthead\">\n";
echo "                <tr>\n";
echo "                  <td align=\"left\" class=\"subhead\" colspan=\"2\">", gettext("Write Message"), "</td>\n";
echo "                </tr>\n";
echo "                <tr>\n";
echo "                  <td align=\"left\" valign=\"top\" width=\"210\">\n";
echo "                    <table class=\"posthead\" width=\"210\">\n";
echo "                      <tr>\n";
echo "                        <td align=\"left\"><h2>", gettext("Subject"), "</h2></td>\n";
echo "                      </tr>\n";
echo "                      <tr>\n";
echo "                        <td align=\"left\">", form_input_text("t_subject", isset($t_subject) ? htmlentities_array($t_subject) : "", 42, false, false, "thread_title"), "</td>\n";
echo "                      </tr>\n";
echo "                      <tr>\n";
echo "                        <td align=\"left\"><h2>", gettext("To"), "</h2></td>\n";
echo "                      </tr>\n";

if (($friends_array = pm_user_get_friends())) {

    if (isset($_GET['uid']) && is_numeric($_GET['uid'])) {

        $to_user = user_get($_GET['uid']);

        if (in_array($to_user['UID'], array_keys($friends_array))) {

            $t_to_uid = $to_user['UID'];
            $to_radio = 'friends';

        }else {

            $t_to_uid_others = $to_user['LOGON'];
        }
    }

    echo "                      <tr>\n";
    echo "                        <td align=\"left\">", form_radio("to_radio", "friends", gettext("Friends"), (isset($to_radio) && $to_radio == "friends")), "</td>\n";
    echo "                      </tr>\n";
    echo "                      <tr>\n";
    echo "                        <td align=\"left\">", form_dropdown_array("t_to_uid", $friends_array, (isset($t_to_uid) ? $t_to_uid : 0), "", "friends_dropdown"), "</td>\n";
    echo "                      </tr>\n";
    echo "                      <tr>\n";
    echo "                        <td align=\"left\">", form_radio("to_radio", "others", gettext("Others"), (isset($to_radio) && $to_radio == "others") ? true : (!isset($to_radio))), "</td>\n";
    echo "                      </tr>\n";
    echo "                      <tr>\n";
    echo "                        <td align=\"left\" style=\"white-space: nowrap\">", form_input_text_search("t_to_uid_others", isset($t_to_uid_others) ? htmlentities_array($t_to_uid_others) : "", false, false, SEARCH_LOGON, true, sprintf('title="%s"', gettext("Separate recipients by semi-colon or comma")), "post_to_others"), "</td>\n";
    echo "                      </tr>\n";

}else {

    if (isset($_GET['uid']) && is_numeric($_GET['uid'])) {

        $to_user = user_get($_GET['uid']);
        $t_to_uid_others = $to_user['LOGON'];
    }

    echo "                      <tr>\n";
    echo "                        <td align=\"left\" style=\"white-space: nowrap\">", form_input_text_search("t_to_uid_others", isset($t_to_uid_others) ? htmlentities_array($t_to_uid_others) : "", false, false, SEARCH_LOGON, true, sprintf('title="%s"', gettext("Separate recipients by semi-colon or comma")), "post_to_others"), "</td>\n";
    echo "                      </tr>\n";
}

if (!is_array($friends_array) && forum_check_webtag_available($webtag)) {

    echo "                      <tr>\n";
    echo "                        <td align=\"left\">&nbsp;</td>\n";
    echo "                      </tr>\n";
    echo "                      <tr>\n";
    echo "                        <td align=\"left\"><h2>", gettext("Hint"), "</h2><span class=\"smalltext\">", gettext("Add users to your friends list to have them appear in a drop down on the PM Write Message Page."), "</span></td>\n";
    echo "                      </tr>\n";
}

echo "                      <tr>\n";
echo "                        <td align=\"left\">&nbsp;</td>\n";
echo "                      </tr>\n";
echo "                      <tr>\n";
echo "                        <td align=\"left\"><h2>", gettext("Message options"), "</h2>\n";

echo "                          ".form_checkbox("t_post_links", "enabled", gettext("Automatically parse URLs"), $links_enabled)."<br />\n";
echo "                          ".form_checkbox("t_check_spelling", "enabled", gettext("Automatically check spelling"), $spelling_enabled)."<br />\n";
echo "                          ".form_checkbox("t_post_emots", "disabled", gettext("Disable emoticons"), !$emots_enabled)."<br />\n";

echo "                        </td>\n";
echo "                      </tr>\n";

if (($user_emoticon_pack = session_get_value('EMOTICONS')) === false) {
    $user_emoticon_pack = forum_get_setting('default_emoticons', false, 'default');
}

if (($emoticon_preview_html = emoticons_preview($user_emoticon_pack))) {

    echo "                      <tr>\n";
    echo "                        <td align=\"left\">&nbsp;</td>\n";
    echo "                      </tr>\n";
    echo "                      <tr>\n";
    echo "                        <td align=\"left\">\n";
    echo "                          <table width=\"196\" class=\"messagefoot\" cellspacing=\"0\">\n";
    echo "                            <tr>\n";
    echo "                              <td align=\"left\" class=\"subhead\">", gettext("Emoticons"), "</td>\n";

    if (($page_prefs & POST_EMOTICONS_DISPLAY) > 0) {
        echo "                              <td class=\"subhead\" align=\"right\">", form_submit_image('hide.png', 'emots_toggle', 'hide', '', 'button_image toggle_button'), "&nbsp;</td>\n";
    } else {
        echo "                              <td class=\"subhead\" align=\"right\">", form_submit_image('show.png', 'emots_toggle', 'show', '', 'button_image toggle_button'), "&nbsp;</td>\n";
    }

    echo "                            </tr>\n";
    echo "                            <tr>\n";
    echo "                              <td align=\"left\" colspan=\"2\">\n";

    if (($page_prefs & POST_EMOTICONS_DISPLAY) > 0) {
        echo "                                <div class=\"emots_toggle\">{$emoticon_preview_html}</div>\n";
    } else {
        echo "                                <div class=\"emots_toggle\" style=\"display: none\">{$emoticon_preview_html}</div>\n";
    }

    echo "                              </td>\n";
    echo "                            </tr>\n";
    echo "                          </table>\n";
    echo "                        </td>\n";
    echo "                      </tr>\n";
}

echo "                    </table>\n";
echo "                  </td>\n";
echo "                  <td align=\"left\" width=\"500\" valign=\"top\">\n";
echo "                    <table border=\"0\" class=\"posthead\" width=\"100%\">\n";
echo "                      <tr>\n";
echo "                        <td align=\"left\">";
echo "                         <h2>", gettext("Message"), "</h2>\n";

$tools = new TextAreaHTML("f_post");

$t_content = $post->getTidyContent();

$tool_type = POST_TOOLBAR_DISABLED;

if ($page_prefs & POST_TOOLBAR_DISPLAY) {
    $tool_type = POST_TOOLBAR_SIMPLE;
} else if ($page_prefs & POST_TINYMCE_DISPLAY) {
    $tool_type = POST_TOOLBAR_TINYMCE;
}

if ($allow_html == true && $tool_type <> POST_TOOLBAR_DISABLED) {
    echo $tools->toolbar(false, form_submit('send', gettext("Send")));
} else {
    $tools->set_tinymce(false);
}

echo $tools->textarea("t_content", $t_content, 20, 75, true, 'tabindex="1"', 'post_content'), "\n";

echo "                        </td>\n";
echo "                      </tr>\n";

if ($post->isDiff()) {

    echo "                      <tr>\n";
    echo "                        <td align=\"left\">\n";
    echo "                          ", $tools->compare_original("t_content", $post), "\n";
    echo "                        </td>\n";
    echo "                      </tr>\n";
}

echo "                      <tr>\n";
echo "                        <td align=\"left\">\n";

if ($allow_html == true) {

    if (($tools->get_tinymce())) {

        echo form_input_hidden("t_post_html", "enabled");

    } else {

        echo "              <h2>", gettext("HTML in message"), "</h2>\n";

        $tph_radio = $post->getHTML();

        echo form_radio("t_post_html", "disabled", gettext("Disabled"), $tph_radio == POST_HTML_DISABLED, "tabindex=\"6\"")." \n";
        echo form_radio("t_post_html", "enabled_auto", gettext("Enabled with auto-line-breaks"), $tph_radio == POST_HTML_AUTO)." \n";
        echo form_radio("t_post_html", "enabled", gettext("Enabled"), $tph_radio == POST_HTML_ENABLED)." \n";
        echo "              <br />";
    }

} else {

        echo form_input_hidden("t_post_html", "disabled");
}

echo "              <br />\n";

echo "&nbsp;", form_submit('send', gettext("Send"), "tabindex=\"2\"");
echo "&nbsp;", form_submit('save', gettext("Save"), "tabindex=\"3\"");
echo "&nbsp;", form_submit('preview', gettext("Preview"), "tabindex=\"4\"");

if (isset($t_reply_mid) && is_numeric($t_reply_mid) && $t_reply_mid > 0) {

    echo "&nbsp;<a href=\"pm.php?webtag=$webtag&mid=$t_reply_mid\" class=\"button\" target=\"_self\"><span>", gettext("Cancel"), "</span></a>";

} else if (isset($t_forward_mid) && is_numeric($t_forward_mid)  && $t_forward_mid > 0) {

    echo "&nbsp;<a href=\"pm.php?webtag=$webtag&mid=$t_forward_mid\" class=\"button\" target=\"_self\"><span>", gettext("Cancel"), "</span></a>";

} else if (isset($t_edit_mid) && is_numeric($t_edit_mid) && $t_edit_mid > 0) {

    echo "&nbsp;<a href=\"pm.php?webtag=$webtag&mid=$t_edit_mid\" class=\"button\" target=\"_self\"><span>", gettext("Cancel"), "</span></a>";

}else {

    echo "&nbsp;<a href=\"pm.php?webtag=$webtag\" class=\"button\" target=\"_self\"><span>", gettext("Cancel"), "</span></a>";
}

if (forum_get_setting('attachments_enabled', 'Y') && forum_get_setting('pm_allow_attachments', 'Y')) {

    echo "&nbsp;<a href=\"attachments.php?webtag=$webtag&amp;aid=$aid\" class=\"button popup 660x500\" id=\"attachments\"><span>", gettext("Attachments"), "</span></a>\n";
    echo form_input_hidden("aid", htmlentities_array($aid));
}

echo "                        </td>\n";
echo "                      </tr>\n";
echo "                    </table>\n";
echo "                  </td>\n";
echo "                </tr>\n";
echo "                <tr>\n";
echo "                  <td align=\"left\" colspan=\"2\">&nbsp;</td>\n";
echo "                </tr>\n";
echo "              </table>\n";

if (isset($t_reply_mid) && is_numeric($t_reply_mid) && $t_reply_mid > 0) {

    echo form_input_hidden("replyto", htmlentities_array($t_reply_mid)), "\n";

}elseif (isset($t_forward_mid) && is_numeric($t_forward_mid) && $t_forward_mid > 0) {

    echo form_input_hidden("fwdmsg", htmlentities_array($t_forward_mid)), "\n";

}elseif (isset($t_edit_mid) && is_numeric($t_edit_mid) && $t_edit_mid > 0) {

    echo form_input_hidden("editmsg", htmlentities_array($t_edit_mid)), "\n";
}

if (isset($pm_data) && is_array($pm_data) && isset($t_reply_mid) && is_numeric($t_reply_mid) && $t_reply_mid > 0) {

    echo "              <table class=\"posthead\" width=\"720\">\n";
    echo "                <tr>\n";
    echo "                  <td align=\"left\" class=\"subhead\" colspan=\"3\">", gettext("In reply to"), "</td>\n";
    echo "                </tr>";
    echo "                <tr>\n";
    echo "                  <td align=\"left\">&nbsp;</td>\n";
    echo "                  <td align=\"left\" width=\"690\"><br />", pm_display($pm_data, PM_FOLDER_INBOX, true), "</td>\n";
    echo "                  <td align=\"left\">&nbsp;</td>\n";
    echo "                </tr>\n";
    echo "                <tr>\n";
    echo "                  <td align=\"left\" colspan=\"3\">&nbsp;</td>\n";
    echo "                </tr>\n";
    echo "              </table>\n";
}

echo "            </td>\n";
echo "          </tr>\n";
echo "        </table>\n";
echo "      </td>\n";
echo "    </tr>\n";
echo "  </table>\n";
echo "</form>\n";

html_draw_bottom();

?>
