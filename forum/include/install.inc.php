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

/* $Id: install.inc.php,v 1.6 2004-05-11 17:32:28 decoyduck Exp $ */

if (@file_exists("./include/config.inc.php")) {
    include_once("./include/config.inc.php");
}

include_once("./include/html.inc.php");

function check_install()
{
    if (!defined("BEEHIVE_INSTALLED")) {
        header_redirect("./install/install.php");
    }

    if (file_exists('install') && !defined("BEEHIVE_INSTALL_NOWARN")) {

        html_draw_top();

        echo "<h1>BeehiveForum Installation Error</h1>\n";
        echo "<br />\n";
        echo "<div align=\"center\">\n";
        echo "  <table cellpadding=\"0\" cellspacing=\"0\" width=\"400\">\n";
        echo "    <tr>\n";
        echo "      <td>\n";
        echo "        <table class=\"box\">\n";
        echo "          <tr>\n";
        echo "            <td class=\"posthead\">\n";
        echo "              <table class=\"posthead\" width=\"500\">\n";
        echo "                <tr>\n";
        echo "                  <td colspan=\"2\" class=\"subhead\">Installation Incomplete</td>\n";
        echo "                </tr>\n";
        echo "                <tr>\n";
        echo "                  <td>Your BeehiveForum would appear to be already installed, but you have not removed the install folder. You must delete the 'install' directory before your Beehive Forum can be used.</td>\n";
        echo "                </tr>\n";
        echo "                <tr>\n";
        echo "                  <td>&nbsp;</td>\n";
        echo "                </tr>\n";
        echo "              </table>\n";
        echo "            </td>\n";
        echo "          </tr>\n";
        echo "        </table>\n";
        echo "      </td>\n";
        echo "    </tr>\n";
        echo "  </table>\n";
        echo "</div>\n";

        html_draw_bottom();
        exit;
    }
}

?>