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

/* $Id: admin.js,v 1.5 2007-03-30 00:54:49 decoyduck Exp $ */

var search_logon = false;

function openLogonSearch(webtag, obj_name)
{
    if (typeof search_thread == 'object' && !search_thread.closed) {

        search_logon.focus();

    }else {

        var form_obj = getFormObjByName(obj_name);
        search_logon = window.open('search_popup.php?webtag=' + webtag + '&type=1&search_query=' + form_obj.value + '&obj_name=' + obj_name, 'search_logon', 'width=500, height=300, toolbar=0, location=0, directories=0, status=0, menubar=0, resizable=yes, scrollbars=yes');
    }

    return false;
}

function returnSearchResult(obj_name, content)
{
    var form_obj = getFormObjByName(obj_name);
    form_obj.value = content;
}