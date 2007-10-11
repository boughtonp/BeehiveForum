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

/* $Id: errorhandler.inc.php,v 1.93 2007-10-11 13:01:18 decoyduck Exp $ */

// We shouldn't be accessing this file directly.

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header("Request-URI: ../index.php");
    header("Content-Location: ../index.php");
    header("Location: ../index.php");
    exit;
}

include_once(BH_INCLUDE_PATH. "constants.inc.php");
include_once(BH_INCLUDE_PATH. "form.inc.php");
include_once(BH_INCLUDE_PATH. "format.inc.php");
include_once(BH_INCLUDE_PATH. "install.inc.php");
include_once(BH_INCLUDE_PATH. "messages.inc.php");
include_once(BH_INCLUDE_PATH. "session.inc.php");

// Define PHP 5.0's new E_STRICT constant here if it's not defined.
// This will be meaningless to PHP versions below 5.0 but it saves
// us doing some dodgy if checking against the version number later.

if (!defined("E_STRICT")) {
    define("E_STRICT", 2048);
}

// Set the error reporting level to report all error messages.
// If this is changed to include E_STRICT Beehive will probably
// not work.

error_reporting(E_ALL);

// Beehive Error Handler Function

function bh_error_handler($errno, $errstr, $errfile = '', $errline = 0)
{
    $show_friendly_errors = (isset($GLOBALS['show_friendly_errors'])) ? $GLOBALS['show_friendly_errors'] : false;

    // Bad Coding Practises Alert!!
    // We're going to ignore any E_STRICT error messages
    // which are caused by PHP/5.x because otherwise we'd
    // have to develop two seperate versions of Beehive
    // one for PHP/4.x and one for PHP/5.x.

    if (($errno & E_STRICT) > 0) return;

    // No REQUEST_URI in IIS.

    $request_uri = "{$_SERVER['PHP_SELF']}?";
    parse_array($_GET, "&amp;", $request_uri);

    // Now we can carry on with any other errors.

    if (error_reporting()) {

        if ((isset($show_friendly_errors) && $show_friendly_errors === false) || defined("BEEHIVEMODE_LIGHT")) {

            echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
            echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n";
            echo "<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"utf-8\" lang=\"en\" dir=\"ltr\">\n";
            echo "<head>\n";
            echo "<title>Beehive Forum - Error Handler</title>\n";
            echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />\n";
            echo "<link rel=\"icon\" href=\"images/favicon.ico\" type=\"image/ico\" />\n";
            echo "<link rel=\"stylesheet\" href=\"styles/default/style.css\" type=\"text/css\" />\n";
            echo "</head>\n";
            echo "<body>\n";
            echo "<form name=\"f_error\" method=\"post\" action=\"$request_uri\" target=\"_self\">\n";

            echo form_input_hidden_array(_stripslashes($_POST));

            echo "<p>An error has occured. Please wait a few minutes and then click the Retry button below.</p>\n";
            echo "<p><input type=\"submit\" name=\"", md5(uniqid(mt_rand())), "\" value=\"Retry\" /></p>\n";

            if (defined("BEEHIVE_INSTALL_NOWARN")) {

                switch ($errno) {

                    case E_USER_ERROR:

                        echo "<p><b>E_USER_ERROR</b> [$errno] $errstr</p>\n";
                        break;

                    case E_USER_WARNING:

                        echo "<p><b>E_USER_WARNING</b> [$errno] $errstr</p>\n";
                        break;

                    case E_USER_NOTICE:

                        echo "<p><b>E_USER_NOTICE</b> [$errno] $errstr</p>\n";
                        break;

                    default:

                        echo "<p><b>Unknown error</b> [$errno] $errstr</p>\n";
                        break;
                }

                if (strlen(trim(basename($errfile))) > 0) {
                    echo "<p>Error in line $errline of file ", basename($errfile), "</p>\n";
                }
            }

            echo "</form>\n";
            echo "</body>\n";
            echo "</html>\n";

            exit;
        }

        while (@ob_end_clean());
        ob_start("bh_gzhandler");
        ob_implicit_flush(0);

        if (($errno == ER_NO_SUCH_TABLE || $errno == ER_WRONG_COLUMN_NAME) && !defined("BEEHIVE_INSTALL_NOWARN")) {
            install_incomplete();
        }

        echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
        echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n";
        echo "<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"utf-8\" lang=\"en\" dir=\"ltr\">\n";
        echo "<head>\n";
        echo "<title>Beehive Forum - Error Handler</title>\n";
        echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />\n";
        echo "<link rel=\"icon\" href=\"images/favicon.ico\" type=\"image/ico\" />\n";
        echo "<link rel=\"stylesheet\" href=\"styles/default/style.css\" type=\"text/css\" />\n";
        echo "</head>\n";
        echo "<body>\n";
        echo "<div align=\"center\">\n";
        echo "<form name=\"f_error\" method=\"post\" action=\"$request_uri\" target=\"_self\">\n";
        echo "<table cellpadding=\"0\" cellspacing=\"0\" width=\"550\">\n";
        echo "  <tr>\n";
        echo "    <td align=\"left\">\n";
        echo "      <table border=\"0\" width=\"100%\">\n";
        echo "        <tr>\n";
        echo "          <td align=\"left\" class=\"postbody\">An error has occured. Please wait a few minutes and then click the Retry button below.</td>\n";
        echo "        </tr>\n";
        echo "        <tr>\n";
        echo "          <td align=\"left\">", form_input_hidden_array(_stripslashes($_POST)), "</td>\n";
        echo "        </tr>\n";
        echo "        <tr>\n";
        echo "          <td align=\"center\"><input class=\"button\" type=\"submit\" name=\"", md5(uniqid(mt_rand())), "\" value=\"Retry\" /></td>\n";
        echo "        </tr>\n";

        if (isset($_GET['retryerror']) && isset($_POST['t_content']) && strlen(trim(_stripslashes($_POST['t_content']))) > 0) {

            echo "        <tr>\n";
            echo "          <td align=\"left\">&nbsp;</td>\n";
            echo "        </tr>\n";
            echo "        <tr>\n";
            echo "          <td align=\"left\"><hr /></td>\n";
            echo "        </tr>\n";
            echo "        <tr>\n";
            echo "          <td align=\"left\" class=\"postbody\">This error has occured more than once while attempting to post/preview your message. For your convienience we have included your message text and if applicable the thread and message number you were replying to below. You may wish to save a copy of the text elsewhere until the forum is available again.</td>\n";
            echo "        </tr>\n";
            echo "        <tr>\n";
            echo "          <td align=\"left\">&nbsp;</td>\n";
            echo "        </tr>\n";
            echo "        <tr>\n";
            echo "          <td align=\"left\"><textarea class=\"bhtextarea\" rows=\"15\" name=\"t_content\" cols=\"85\">", _htmlentities(_stripslashes($_POST['t_content'])), "</textarea></td>\n";
            echo "        </tr>\n";

            if (isset($_GET['replyto']) && validate_msg($_GET['replyto'])) {

                echo "        <tr>\n";
                echo "          <td align=\"left\">&nbsp;</td>\n";
                echo "        </tr>\n";
                echo "        <tr>\n";
                echo "          <td align=\"left\" class=\"postbody\">Reply Message Number:</td>\n";
                echo "        </tr>\n";
                echo "        <tr>\n";
                echo "          <td align=\"left\"><input class=\"bhinputtext\" type=\"text\" name=\"t_request_url\" value=\"{$_GET['replyto']}\"></td>\n";
                echo "        </tr>\n";

            }
        }

        echo "        <tr>\n";
        echo "          <td align=\"left\">&nbsp;</td>\n";
        echo "        </tr>\n";

        if (defined("BEEHIVE_INSTALL_NOWARN")) {

            echo "        <tr>\n";
            echo "          <td align=\"left\"><hr /></td>\n";
            echo "        </tr>\n";
            echo "        <tr>\n";
            echo "          <td align=\"left\"><h2>Error Message for server admins and developers</h2></td>\n";
            echo "        </tr>\n";
            echo "        <tr>\n";
            echo "          <td align=\"left\" class=\"postbody\">\n";

            switch ($errno) {

                case E_USER_ERROR:

                    echo "            <p><b>E_USER_ERROR</b> [$errno] $errstr</p>\n";
                    break;

                case E_USER_WARNING:

                    echo "            <p><b>E_USER_WARNING</b> [$errno] $errstr</p>\n";
                    break;

                case E_USER_NOTICE:

                    echo "            <p><b>E_USER_NOTICE</b> [$errno] $errstr</p>\n";
                    break;

                default:

                    echo "            <p><b>Unknown error</b> [$errno] $errstr</p>\n";
                    break;
            }

            if (strlen(trim(basename($errfile))) > 0) {
                echo "<p>Error in line $errline of file ", basename($errfile), "</p>\n";
            }

            $version_strings = array();

            // Beehive Forum Version

            if (defined('BEEHIVE_VERSION')) {
               $version_strings[] = sprintf("Beehive Forum %s", BEEHIVE_VERSION);
            }

            // PHP Version

            if ($php_version = phpversion()) {
                $version_strings[] = "on PHP/$php_version";
            }

            // PHP OS (WINNT, Linux, etc)

            if (defined('PHP_OS')) {
                $version_strings[] = PHP_OS;
            }

            // PHP interface (CGI, APACHE, IIS, etc)

            if ($php_sapi = php_sapi_name()) {
                $version_strings[] = strtoupper($php_sapi);
            }

            // Join together the above strings into a single array index.

            if (isset($version_strings) && sizeof($version_strings) > 0) {
                $version_strings = array(implode(" ", $version_strings));
            }

            // Add the MySQL version if it's available.

            if (db_fetch_mysql_version($mysql_version)) {
                $version_strings[] = "MySQL/$mysql_version";
            }

            // Display the entire version string to the user.

            if (isset($version_strings) && sizeof($version_strings) > 0) {
                echo "            <p>", implode(", ", $version_strings), "</p>\n";
            }

            echo "          </td>\n";
            echo "        </tr>\n";
        }

        echo "      </table>\n";
        echo "    </td>\n";
        echo "  </tr>\n";
        echo "</table>\n";
        echo "</form>\n";
        echo "</div>\n";
        echo "</body>\n";
        echo "</html>\n";

        exit;
    }
}

set_error_handler("bh_error_handler");

?>