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

function format_user_name($u_logon,$u_nickname)
{
    if($u_nickname != ""){
        $fmt = $u_nickname . " (" . $u_logon . ")";
    } else {
        $fmt = $u_logon;
    }
    
    return $fmt;
}

function format_url2link($html)
{
    $fhtml = preg_replace("/\b((http(s?):\/\/)|(www\.))([\w\.]+)([\/\w+\.]+)\b/i",
        "<a href=\"http$3://$4$5$6\" target=\"_blank\">$2$4$5$6</a>", $html);
    return $fhtml;
}

function format_time($time)
{
	if (date("j", $time) == date("j") && date("n", $time) == date("n") && date("Y", $time) == date("Y")) {
		$fmt = gmdate("H:i", $time);
	} else {
		$fmt = gmdate("j M", $time);
	}
    return $fmt;
}

function timestamp_to_date($timestamp)
{
    $year=substr($timestamp,0,4);
    $month=substr($timestamp,4,2);
    $day=substr($timestamp,6,2);
    $hour=substr($timestamp,8,2);
    $minute=substr($timestamp,10,2);
    $second=substr($timestamp,12,2);
    $newdate=mktime($hour,$minute,$second,$month,$day,$year);
    return ($newdate);
}  
?>
