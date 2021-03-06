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

// Set the default timezone
date_default_timezone_set('UTC');

// Constant to define where the include files are
define("BH_INCLUDE_PATH", "./forum/include/");

// Mimic Lite Mode
define("BEEHIVEMODE_LIGHT", true);

// Beehive Config
include_once(BH_INCLUDE_PATH. "config.inc.php");

// Development configuration
if (@file_exists(BH_INCLUDE_PATH. "config-dev.inc.php")) {
    include_once(BH_INCLUDE_PATH. "config-dev.inc.php");
}

// Constants
include_once(BH_INCLUDE_PATH. "constants.inc.php");

// Database functions.
include_once(BH_INCLUDE_PATH. "db.inc.php");

/**
* get_svn_log_data
*
* Fetches the SVN Log data. The main workhorse of this script.
*
* @return mixed - False on failure, SVN LOG as string on success.
* @param mixed $date - Date to limit the SVN LOG command by.
* @param string $file - File to get the SVN LOG data for.
*/
function get_svn_log_data($date)
{
    $svn_log_cmd = sprintf("svn log --xml -r {%s}:{%s}", $date, date('Y-m-d', time() + 86400));

    if (($log_handle = popen($svn_log_cmd, 'r'))) {

        $log_contents = '';

        while (!feof($log_handle)) {
            $log_contents.= fgets($log_handle);
        }

        pclose($log_handle);
        return $log_contents;
    }

    return false;
}

/**
* svn_mysql_prepare_table
*
* Prepares a MySQL table for logging data from SVN LOG command.
* If the table doesn't exist it creates a new one, if it already
* exists it is emptied.
*
* @return bool
* @param string $log_file - Path and filename of svn log output.
*/
function svn_mysql_prepare_table($truncate_table = true)
{
    if (!$db_svn_mysql_prepare_table = db_connect()) return false;

    $sql = "CREATE TABLE IF NOT EXISTS BEEHIVE_SVN_LOG (";
    $sql.= "  LOG_ID MEDIUMINT( 8 ) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,";
    $sql.= "  DATE DATETIME NOT NULL,";
    $sql.= "  AUTHOR VARCHAR( 255 ) NOT NULL,";
    $sql.= "  COMMENTS TEXT NOT NULL";
    $sql.= ") TYPE = MYISAM";

    if (!db_query($sql, $db_svn_mysql_prepare_table)) return false;

    if ($truncate_table == true) {

        $sql = "TRUNCATE TABLE BEEHIVE_SVN_LOG";
        if (!db_query($sql, $db_svn_mysql_prepare_table)) return false;
    }

    return true;
}

/**
* svn_mysql_parse
*
* Parses the output of svn log command that has been outputted to a file
* into a MySQL database table comprising DATE, AUTHOR, COMMENTS columns.
*
* @return bool
* @param string $log_file - Path and filename of svn log output.
*/
function svn_mysql_parse($svn_log_contents)
{
    if (!$db_svn_log_parse = db_connect()) return false;

    $svn_xml_data = new SimpleXMLElement($svn_log_contents);

    foreach ($svn_xml_data as $svn_log_xml) {

        $svn_log_entry_author = trim((string)$svn_log_xml->author);

        $svn_log_entry_date = strtotime((string)$svn_log_xml->date);

        $svn_log_entry_comment = trim((string)$svn_log_xml->msg);

        if ((strlen($svn_log_entry_comment) > 0) && ($svn_log_entry_comment != '*** empty log message ***')) {

            $sql = sprintf("SELECT LOG_ID FROM BEEHIVE_SVN_LOG WHERE AUTHOR = '%s' AND COMMENTS = '%s'", db_escape_string($svn_log_entry_author),
                                                                                                         db_escape_string($svn_log_entry_comment));

            if (!$result = db_query($sql, $db_svn_log_parse)) return false;

            if (db_num_rows($result) < 1) {

                $sql = sprintf("INSERT INTO BEEHIVE_SVN_LOG (DATE, AUTHOR, COMMENTS)
                                     VALUES(FROM_UNIXTIME('%s'), '%s', '%s')", db_escape_string($svn_log_entry_date),
                                                                               db_escape_string($svn_log_entry_author),
                                                                               db_escape_string($svn_log_entry_comment));

                if (!$result = db_query($sql, $db_svn_log_parse)) return false;
            }
        }
    }

    return true;
}

/**
* svn_mysql_output_log
*
* Output the SVN log data saved in the MySQL database
* to the specified filename.
*
* @param mixed $log_filename
* @return mixed
*/
function svn_mysql_output_log($log_filename = null)
{
    if (!$db_svn_mysql_output_log = db_connect()) return false;

    $sql = "SELECT UNIX_TIMESTAMP(DATE) AS DATE, AUTHOR, COMMENTS ";
    $sql.= "FROM BEEHIVE_SVN_LOG GROUP BY DATE ORDER BY DATE DESC";

    if (!$result = db_query($sql, $db_svn_mysql_output_log)) return false;

    if (db_num_rows($result) > 0) {

        $svn_log_entry_author = '';
        $svn_log_entry_date = '';

        ob_start();

        printf("Project Beehive Forum Change Log (Generated: %s)\r\n\r\n", gmdate('D, d M Y H:i:s'));

        while (($svn_log_entry_array = db_fetch_array($result, DB_RESULT_ASSOC))) {

            $svn_log_entry = '';

            if ($svn_log_entry_author != $svn_log_entry_array['AUTHOR']) {

                $svn_log_entry_author = $svn_log_entry_array['AUTHOR'];
                printf("Author: %s\r\n", $svn_log_entry_author);

                if ($svn_log_entry_date != $svn_log_entry_array['DATE']) {

                    $svn_log_entry_date = $svn_log_entry_array['DATE'];
                    printf("Date: %s\r\n", gmdate('D, d M Y H:i:s', $svn_log_entry_date));
                }

                echo "-----------------------\r\n";

            } else if ($svn_log_entry_date != $svn_log_entry_array['DATE']) {

                $svn_log_entry_date = $svn_log_entry_array['DATE'];
                printf("Date: %s\r\n-----------------------\r\n", gmdate('D, d M Y H:i:s', $svn_log_entry_date));
            }

            if (preg_match('/^(Fixed:|Changed:|Added:)(.+)/i', $svn_log_entry_array['COMMENTS'], $svn_log_entry_matches) > 0) {

                $svn_log_comment = trim(preg_replace("/(\r\n|\n|\r)/", '', $svn_log_entry_matches[2]));

                $svn_log_comment_array = explode("\r\n", wordwrap($svn_log_comment, 91, "\r\n"));

                foreach ($svn_log_comment_array as $line => $svn_log_comment_line) {

                    echo $line == 0 ? str_pad($svn_log_entry_matches[1], 9, ' ', STR_PAD_RIGHT) : str_repeat(' ', 9);
                    echo $svn_log_comment_line, "\r\n";
                }

                echo "\r\n";
            }
        }

        if (isset($log_filename)) {
            file_put_contents($log_filename, ob_get_clean());
        }

    }else {

        echo "Table BEEHIVE_SVN_LOG is empty. No Changelog generated.\r\n";
    }

    return true;
}

// Prevent time out
set_time_limit(0);

// Output the content as text.
header('Content-Type: text/plain');

// Check to see if we have a date on the command line and
// that it is in the valid format YYYY-MM-DD.
if (isset($_SERVER['argv'][1]) && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/u', $_SERVER['argv'][1]) > 0) {
    $modified_date = $_SERVER['argv'][1];
} else if (isset($_GET['date']) && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/u', $_GET['date']) > 0) {
    $modified_date = $_GET['date'];
}

if (isset($modified_date)) {

    if (svn_mysql_prepare_table()) {

        if (!isset($_GET['output'])) echo "Fetching SVN Log Data...\r\n";

        if (($svn_log_contents = get_svn_log_data($modified_date))) {

            if (!isset($_GET['output'])) echo "Parsing SVN Log Data...\r\n";

            if (!svn_mysql_parse($svn_log_contents)) {

                echo "Error while fetching or parsing SVN log contents\r\n";
                exit;
            }

        }else {

            echo "Error while fetching SVN log\r\n";
            exit;
        }

        if (isset($_SERVER['argv'][2]) && strlen(trim($_SERVER['argv'][2])) > 0) {

            $output_log_filename = trim($_SERVER['argv'][2]);
            echo "Generating Change Log. Saving to $output_log_filename\r\n";
            svn_mysql_output_log($output_log_filename);

        } else if (isset($_GET['output'])) {

            svn_mysql_output_log();
        }

    }else {

        echo "Error while preparing MySQL Database table";
        exit;
    }

}else if (isset($_SERVER['argv'][1]) && strlen(trim($_SERVER['argv'][1])) > 0) {

    $output_log_filename = trim($_SERVER['argv'][1]);

    if (svn_mysql_prepare_table(false)) {

        echo "Generating Change Log. Saving to $output_log_filename\r\n";
        svn_mysql_output_log($output_log_filename);

    }else {

        echo "Error while preparing MySQL Database table";
        exit;
    }

}else if (isset($_GET['output'])) {

    if (svn_mysql_prepare_table(false)) {

        svn_mysql_output_log();

    }else {

        echo "Error while preparing MySQL Database table";
        exit;
    }

}else {

    echo "Generate changelog.txt from SVN comments\r\n\r\n";
    echo "Usage: php-bin bh_svn_log_parse.php [YYYY-MM-DD] [FILE]\r\n";
    echo "   OR: bh_svn_log_parse.php?date=YYYY-MM-DD[&output]\r\n\r\n";
    echo "Examples:\r\n";
    echo "  php-bin bh_svn_log_parse.php 2007-01-01\r\n";
    echo "  php-bin bh_svn_log_parse.php 2007-01-01 changelog.txt\r\n";
    echo "  php-bin bh_svn_log_parse.php changelog.txt\r\n\r\n";
    echo "[FILE] specifies the output filename for the changelog.\r\n";
    echo "       Only available when run from a shell.\r\n\r\n";
    echo "[YYYY-MM-DD] specifies the date the changelog should start from\r\n\r\n";
    echo "Both arguments can be combined or used separatly to achieve\r\n";
    echo "different results.\r\n\r\n";
    echo "Specifying the date on it's own will save the results from the\r\n";
    echo "SVN comments to a MySQL database named BEEHIVE_SVN_LOG using the\r\n";
    echo "connection details from your Beehive Forum config.inc.php\r\n\r\n";
    echo "Specifying only the output filename will take any saved results\r\n";
    echo "in the BEEHIVE_SVN_LOG table and generate a changelog from them.\r\n\r\n";
    echo "Using them together will both save the results to the BEEHIVE_SVN_LOG\r\n";
    echo "table and generate the specified changelog.\r\n\r\n";
    echo "Subsequent runs using the date argument will truncate the database\r\n";
    echo "table before generating the changelog.\r\n";
}

?>