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

/* $Id: edit_signature.php,v 1.52 2005-04-04 02:32:56 tribalonline Exp $ */

// Constant to define where the include files are
define("BH_INCLUDE_PATH", "./include/");

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

// Fetch the forum settings
$forum_settings = forum_get_settings();

include_once(BH_INCLUDE_PATH. "attachments.inc.php");
include_once(BH_INCLUDE_PATH. "fixhtml.inc.php");
include_once(BH_INCLUDE_PATH. "form.inc.php");
include_once(BH_INCLUDE_PATH. "header.inc.php");
include_once(BH_INCLUDE_PATH. "html.inc.php");
include_once(BH_INCLUDE_PATH. "htmltools.inc.php");
include_once(BH_INCLUDE_PATH. "lang.inc.php");
include_once(BH_INCLUDE_PATH. "logon.inc.php");
include_once(BH_INCLUDE_PATH. "messages.inc.php");
include_once(BH_INCLUDE_PATH. "post.inc.php");
include_once(BH_INCLUDE_PATH. "session.inc.php");
include_once(BH_INCLUDE_PATH. "user.inc.php");

// Check we're logged in correctly

if (!$user_sess = bh_session_check()) {
    $request_uri = rawurlencode(get_request_uri(true));
    $webtag = get_webtag($webtag_search);
    header_redirect("./logon.php?webtag=$webtag&final_uri=$request_uri");
}

// Check we have a webtag

if (!$webtag = get_webtag($webtag_search)) {
    $request_uri = rawurlencode(get_request_uri(true));
    header_redirect("./forums.php?webtag_search=$webtag_search&final_uri=$request_uri");
}

// Load language file

$lang = load_language_file();

// Check that we have access to this forum

if (!forum_check_access_level()) {
    $request_uri = rawurlencode(get_request_uri(true));
    header_redirect("./forums.php?webtag_search=$webtag_search&final_uri=$request_uri");
}

if (bh_session_get_value('UID') == 0) {
    html_guest_error();
    exit;
}

$valid = true;

if (isset($_POST['submit']) || isset($_POST['preview'])) {

    if (isset($_POST['sig_content']) && strlen(trim(_stripslashes($_POST['sig_content']))) > 0) {
        $t_sig_content = trim(_stripslashes($_POST['sig_content']));
    }else {
        $t_sig_content = "";
    }

    if (isset($_POST['sig_html']) && $_POST['sig_html'] == "Y") {
        $t_sig_html = "Y";
    }else {
        $t_sig_html = "N";
    }

    if ($t_sig_html == "Y") {
        $t_sig_content = fix_html($t_sig_content);
    }

    if (attachment_embed_check($t_sig_content) && $t_sig_html == "Y") {
        $error_html.= "<h2>{$lang['notallowedembedattachmentsignature']}</h2>\n";
        $valid = false;
    }
}

if (isset($_POST['submit'])) {

    if ($valid) {

        // User's UID for updating with.

        $uid = bh_session_get_value('UID');

        // Update USER_SIG

        user_update_sig($uid, $t_sig_content, $t_sig_html);

        // Reinitialize the User's Session to save them having to logout and back in

        bh_session_init($uid, false);

        // IIS bug prevents redirect at same time as setting cookies.

        if (isset($_SERVER['SERVER_SOFTWARE']) && !strstr($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS')) {

            header_redirect("./edit_signature.php?webtag=$webtag&updated=true");

        }else {

            html_draw_top();

            // Try a Javascript redirect
            echo "<script language=\"javascript\" type=\"text/javascript\">\n";
            echo "<!--\n";
            echo "document.location.href = './edit_signature.php?webtag=$webtag&amp;updated=true';\n";
            echo "//-->\n";
            echo "</script>";

            // If they're still here, Javascript's not working. Give up, give a link.
            echo "<div align=\"center\"><p>&nbsp;</p><p>&nbsp;</p>";
            echo "<p>{$lang['preferencesupdated']}</p>";

            echo form_quick_button("./edit_signature.php", $lang['continue'], false, false, "_top");

            html_draw_bottom();
            exit;
        }
    }
}

// Get the User's Signature

user_get_sig(bh_session_get_value('UID'), $user_sig['SIG_CONTENT'], $user_sig['SIG_HTML']);

// Start Output Here

html_draw_top("onUnload=clearFocus()", "dictionary.js", "htmltools.js");

if (isset($_POST['preview'])) {

    if ($valid) {

        $preview_message['TLOGON'] = "ALL";
        $preview_message['TNICK'] = "ALL";

        $preview_tuser = user_get(bh_session_get_value('UID'));

        $preview_message['FLOGON']   = $preview_tuser['LOGON'];
        $preview_message['FNICK']    = $preview_tuser['NICKNAME'];
        $preview_message['FROM_UID'] = $preview_tuser['UID'];

        $preview_message['CONTENT'] = $lang['signaturepreview'];

        if ($t_sig_html == "Y") {
            $preview_message['CONTENT'].= "<div class=\"sig\">$t_sig_content</div>";
        }else {
            $preview_message['CONTENT'].= "<div class=\"sig\">". make_html($t_sig_content). "</div>";
        }

        $preview_message['CREATED'] = gmmktime();

        echo "<h1>{$lang['preview']}</h1>\n";

        echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"500\">\n";
        echo "    <tr>\n";
        echo "      <td>\n";

        message_display(0, $preview_message, 0, 0, true, false, false, false, true, true);
        echo "<br />\n";

        echo "      </td>\n";
        echo "    </tr>\n";
        echo "  </table>\n";
    }
}

echo "<h1>{$lang['editsignature']}</h1>\n";

// Any error messages to display?

if (!empty($error_html)) {
    echo $error_html;
}else if (isset($_GET['updated'])) {
    echo "<h2>{$lang['preferencesupdated']}</h2>\n";
}

$tools = new TextAreaHTML("prefs");

echo "<br />\n";
echo "<form name=\"prefs\" action=\"edit_signature.php\" method=\"post\" target=\"_self\">\n";
echo "  ", form_input_hidden('webtag', $webtag), "\n";
echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"500\">\n";
echo "    <tr>\n";
echo "      <td>\n";
echo "        <table class=\"box\">\n";
echo "          <tr>\n";
echo "            <td class=\"posthead\">\n";
echo "              <table class=\"posthead\" width=\"100%\">\n";
echo "                <tr>\n";
echo "                  <td class=\"subhead\">{$lang['signature']}</td>\n";
echo "                </tr>\n";
echo "                <tr>\n";
echo "                  <td>\n";

echo $tools->toolbar();

if (isset($t_sig_html)) {
        $sig_html = ($t_sig_html == "Y");
} else {
        $sig_html = ($user_sig['SIG_HTML'] == "Y");
}

if (isset($t_sig_content)) {
        $sig_code = _htmlentities($sig_html == "Y" ? tidy_html($t_sig_content, false) : $t_sig_content);
} else {
        $sig_code = _htmlentities($sig_html == "Y" ? tidy_html($user_sig['SIG_CONTENT'], false) : $user_sig['SIG_CONTENT']);
}

echo $tools->textarea("sig_content", $sig_code, 5, 75, "virtual", "tabindex=\"7\"", "signature_content"), "</td>\n";

echo $tools->js();

echo "                </tr>\n";
echo "                <tr>\n";
echo "                  <td align=\"right\">\n";

echo form_checkbox("sig_html", "Y", $lang['containsHTML'], $sig_html);

echo $tools->assign_checkbox("sig_html");

echo "                                  </td>\n";
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
echo "      <td align=\"center\">", form_submit("submit", $lang['save']), "&nbsp;", form_submit("preview", $lang['preview']), "</td>\n";
echo "    </tr>\n";
echo "  </table>\n";
echo "</form>\n";

html_draw_bottom();

?>