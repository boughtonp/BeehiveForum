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

include_once(BH_INCLUDE_PATH. "constants.inc.php");
include_once(BH_INCLUDE_PATH. "html.inc.php");

/**
* browser_check
*
* Allows testing of browsers by bitwise constants.
* Based on code from Wordpress, but changed to not
* polute global namespace with needless variables
*
* @param mixed $browser_check
* @return bool.
*/
function browser_check($browser_check)
{
    $browser = BROWSER_UNKNOWN;

    if (isset($_SERVER['HTTP_USER_AGENT']) && strlen(trim($_SERVER['HTTP_USER_AGENT'])) > 0) {

        if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'aol') !== false) {

            $browser = $browser | BROWSER_AOL;

        } elseif (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'lynx') !== false) {

            $browser = $browser | BROWSER_LYNX;

        } elseif (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'chrome') !== false) {

            $browser = $browser | BROWSER_CHROME;

        } elseif (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'safari') !== false) {

            $browser = $browser | BROWSER_SAFARI;

        } elseif (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'konqueror') !== false) {

            $browser = $browser | BROWSER_KONQUEROR;

        } elseif (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'gecko') !== false) {

            $browser = $browser | BROWSER_GECKO;

        } elseif ((strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'msie') !== false)) {

            $browser = $browser | BROWSER_MSIE;

        } elseif (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'opera') !== false) {

            $browser = $browser | BROWSER_OPERA;

        } elseif (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'nav') !== false) {

            if (strpos($_SERVER['HTTP_USER_AGENT'], 'Mozilla/4.') !== false) {

                $browser = $browser | BROWSER_NETSCAPE4;
            }
        }

        if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'webkit') !== false) {
            $browser = $browser | BROWSER_WEBKIT;
        }

        if ((($browser & BROWSER_SAFARI) > 0) && strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'mobile') !== false) {
            $browser = $browser | BROWSER_IPHONE;
        }

        if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'msie 7') !== false) {
            $browser = $browser | BROWSER_MSIE7;
        }

        if (($browser & BROWSER_MSIE) > 0) {

            if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'win') !== false) {

                $browser = $browser | BROWSER_MSIE_WIN;

            } elseif (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'mac') !== false) {

                $browser = $browser | BROWSER_MSIE_MAC;
            }
        }
    }

    return ($browser & $browser_check) > 0;
}

/**
* browser_mobile
*
* Check if a browser looks like a mobile browser
* by testing various aspects of the HTTP request
* including user-agent, WAP support, HTTP Profile
* header, etc.
*
* @param void
* @return boolean
*/
function browser_mobile()
{
    $mobile_browser = 0;

    if ((isset($_SERVER['HTTP_ACCEPT'])) && (strpos(strtolower($_SERVER['HTTP_ACCEPT']),'application/vnd.wap.xhtml+xml') !== false)) {
        $mobile_browser++;
    }

    if (isset($_SERVER['HTTP_X_WAP_PROFILE'])) {
        $mobile_browser++;
    }

    if (isset($_SERVER['HTTP_PROFILE'])) {
        $mobile_browser++;
    }

    // User Agent from https://code.google.com/p/the-devices-detection/source/list
    $mobile_agents = array('iPhone', 'iPad', 'iPod', 'incognito', 'webmate', 'dream', 
                           'CUPCAKE', 'webOS', 's8000', 'Googlebot-Mobile', 'Palm', 
                           'EudoraWeb', 'Blazer', 'AvantGo', 'Android', 'Windows CE', 
                           'Cellphone', 'Small', 'MMEF20', 'Danger', 'hiptop', 'Proxinet', 
                           'ProxiNet', 'Newt', 'PalmOS', 'NetFront', 'SHARP-TQ-GX10', 
                           'SonyEricsson', 'SymbianOS', 'UP.Browser', 'UP.Link', 
                           'TS21i-10', 'MOT-V', 'portalmmm', 'DoCoMo', 'Opera Mini', 
                           'Palm', 'Handspring', 'Nokia', 'Kyocera', 'Samsung', 
                           'Motorola', 'Mot', 'Smartphone', 'Blackberry', 'WAP', 
                           'SonyEricsson', 'PlayStation Portable', 'LG', 'MMP', 
                           'OPWV', 'Symbian', 'EPOC');

    $mobile_agents_preg = implode('|', array_map('preg_quote_callback', $mobile_agents));

    if ((isset($_SERVER['HTTP_USER_AGENT'])) && preg_match("/($mobile_agents_preg)/u", $_SERVER['HTTP_USER_AGENT'])) {
        $mobile_browser++;
    }

    if ((isset($_SERVER['ALL_HTTP'])) && (strpos(strtolower($_SERVER['ALL_HTTP']), 'operamini') !== false)) {
        $mobile_browser++;
    }

    // Exclude Windows desktop browsers
    if ((isset($_SERVER['ALL_HTTP'])) && (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'windows') !== false)) {
        $mobile_browser = 0;
    }

    // Windows Phone 7
    if ((isset($_SERVER['ALL_HTTP'])) && (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'windows phone') !== false)) {
        $mobile_browser++;
    }

    if (html_get_cookie('view', 'full')) {
        $mobile_browser = 0;
    }

    if (session_is_search_engine() || html_get_cookie('view', 'mobile')) {
        $mobile_browser++;
    }

    return $mobile_browser > 0;
}

?>