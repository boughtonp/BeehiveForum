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

// We shouldn't be accessing this file directly.
if (basename($_SERVER['SCRIPT_NAME']) == basename(__FILE__)) {
    header("Request-URI: ../index.php");
    header("Content-Location: ../index.php");
    header("Location: ../index.php");
    exit;
}

include_once(BH_INCLUDE_PATH. "form.inc.php");
include_once(BH_INCLUDE_PATH. "format.inc.php");
include_once(BH_INCLUDE_PATH. "html.inc.php");
include_once(BH_INCLUDE_PATH. "lang.inc.php");

/**
* Redirect client to another page.
*
* Redirect client to another page. For Apache and other servers sends
* appropriate HTTP headers to correctly redirect the client to the
* specified address. For IIS we use Javascript and a backup form
* button to click.
*
* @return none - Functions exits code execution.
* @param string $uri - Address to redirect the client to.
* @param string $reason - Option text message advising the client why they're being redirected
*/

function header_redirect($uri, $reason = false)
{
    // Microsoft-IIS bug prevents redirect at same time as setting cookies.
    if (!isset($_SERVER['SERVER_SOFTWARE']) || strstr($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') === false) {

        header("Request-URI: $uri");
        header("Content-Location: $uri");
        header("Location: $uri");
        exit;

    }else {

        defined('BEEHIVEMODE_LIGHT') ? light_html_draw_top() : html_draw_top();

        // Try a Javascript redirect
        echo "<script language=\"javascript\" type=\"text/javascript\">\n";
        echo "<!--\n";
        echo "document.location.href = '$uri';\n";
        echo "//-->\n";
        echo "</script>";

        // If they're still here, Javascript's not working. Give up, give a link.
        echo "<div align=\"center\">\n";

        if (is_string($reason)) {
            echo "<p>$reason</p>";
        }
        
        if (defined('BEEHIVEMODE_LIGHT')) {
            echo light_form_quick_button($uri, gettext("Continue"), false, '_top');
        } else {
            echo form_quick_button($uri, gettext("Continue"), false, "_top");    
        }

        echo "</div>\n";

        defined('BEEHIVEMODE_LIGHT') ? light_html_draw_bottom() : html_draw_bottom();
        exit;
    }
}

function header_status($status, $message)
{
    if (headers_sent()) return false;
    
    if (substr(php_sapi_name(), 0, 3) == 'cgi') {
        header(sprintf('Status: %s %s', $status, $message), true);
    } else if (isset($_SERVER['SERVER_PROTOCOL'])) {
        header(sprintf('%s %s %s', $_SERVER['SERVER_PROTOCOL'], $status, $message), true);
    } else {
        header(sprintf('HTTP/1.1 %s %s', $_SERVER['SERVER_PROTOCOL'], $status, $message), true);
    }
}

?>