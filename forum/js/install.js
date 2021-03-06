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

$(document).ready(function() {

    $('#install_button').bind('click', function() {

        var confirm_text = '';

        switch($('#install_method').val()) {

            case '1':

                confirm_text = 'Are you sure you want to perform a reinstall? Any existing Beehive Forum tables and their data will be permanently lost!\n\n';
                confirm_text+= 'Please perform a backup of your database and files before proceeding.';
                break;

            case '2':

                confirm_text = 'Are you sure you want to perform a reconnect? Any customised values in your config.inc.php file will be lost!\n\n';
                confirm_text+= 'Please perform a backup of your database and files before proceeding.';
                break;

            case '3':

                confirm_text = 'Are you sure you want to perform an upgrade?\n\n';
                confirm_text+= 'Please make sure you have selected the correct upgrade path. The upgrade scripts are very\n';
                confirm_text+= 'primitive and will not check the currently installed version before upgrading. If you have\n';
                confirm_text+= 'selected the wrong upgrade path your forum will become unusable and you will have to\n';
                confirm_text+= 'restore from backup before you can start the upgrade again.\n\n';
                confirm_text+= 'Please perform a backup of your database and files before proceeding.';
                break;
        }

        if ((confirm_text.length > 0) && !window.confirm(confirm_text)) {
             return false;
        }

        $(this).attr('disabled', true);
        
        $('#install_form').submit();

        return true;
    });

    $('.install_help_icon').bind('click', function() {

        var topic_text = '';

        switch ($(this).attr('id')) {

            case 'help_basic':

                topic_text = 'For new installations please select \'New Install\' from the drop down and enter a webtag.\n\n';
                topic_text+= 'Your webtag can be anything you want as long as it only contains the characters A-Z, 0-9 and underscore. If you enter any other characters an error will occur.\n\n';
                topic_text+= 'For reinstalls enter a webtag as above. Any existing Beehive Forum tables will be automatically removed and all data within them will be permanently lost.\n\n';
                topic_text+= 'For reconnects the database setup is skipped and the installation simply rewrites your config.inc.php file. Use this if for example you\'re moving hosts. The webtag field is ignored.\n\n';
                topic_text+= 'For upgrades please select the correct upgrade process. The webtag field is ignored.';
                break;

            case 'help_database':

                topic_text = 'These are the MySQL database details required by to install and run your Beehive Forum.\n\n';
                topic_text+= 'Hostname: The address of the MySQL server. This may be an IP or a DNS for example 127.0.0.1 or localhost or mysql.myhosting.com\n\n';
                topic_text+= 'Database name: The name of the database you want your Beehive Forum to use. The database must already exist and you must have at least SELECT, INSERT, UPDATE, CREATE, ALTER, INDEX and DROP privileges on the database for the installation and your Beehive Forum to work correctly.\n\n';
                topic_text+= 'Username: The username needed to connect to the MySQL server.\n';
                topic_text+= 'Password: The password needed to connect to the MySQL server.\n\n';
                topic_text+= 'If you do not know what these settings are please contact your hosting provider.';
                break;

            case 'help_admin':

                topic_text = 'The credentials of the user you want to have initial Admin access. This information is only required for new installations. Upgrades will leave the existing user accounts intact.';
                break;

            case 'help_advanced':

                topic_text = 'USE THESE OPTIONS WITH EXTREME CAUTION!\n\n';
                topic_text+= 'These options are recommended for advanced users only. There use can have a detrimental effect on the functionality of your Beehive Forum AND other software you may have installed.\n\n';
                topic_text+= '\'Automatically remove tables\' permanently removes tables that would have conflicted with those used by Beehive Forum. If other tables exist which conflict with those used by the Beehive Forum software then enabling this option may cause any other scripts or software which rely on them to fail.\n\n';
                topic_text+= '\'Skip dictionary setup\' will force the installation to skip the process which populates the dictionary table. If you have problems with the installation not completing for example blank pages after clicking submit or PHP error messages try enabling this option.\n\n';
                topic_text+= 'HINT: Enabling FILE permission on the MySQL database server for the user account used for your Beehive Forum will allow the installer to populate the dictionary much quicker. If you can\'t grant this permission yourself please contact your server administrator to arrange this for you.\n\n';
                topic_text+= '\'Enable error reports\' enables verbose error reporting for your Beehive Forum and sends these reports to the Admin User\'s Email Address. These error reports can be useful for helping to diagnose problems and find bugs that you can submit back to Project Beehive Forum. This option is only available when performing a new installation. To enable post-install please see readme.txt for instructions.\n\n';
                break;
        }

        if (topic_text.length > 0) {
             window.alert(topic_text);
        }

        return true;
    });
});