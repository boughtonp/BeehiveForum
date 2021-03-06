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

/**
* sitemap.inc.php - sitemap functions
*
* Contains sitemap related functions.
*/

/**
*
*/

// We shouldn't be accessing this file directly.
if (basename($_SERVER['SCRIPT_NAME']) == basename(__FILE__)) {
    header("Request-URI: ../index.php");
    header("Content-Location: ../index.php");
    header("Location: ../index.php");
    exit;
}

include_once(BH_INCLUDE_PATH. "admin.inc.php");
include_once(BH_INCLUDE_PATH. "constants.inc.php");
include_once(BH_INCLUDE_PATH. "db.inc.php");
include_once(BH_INCLUDE_PATH. "folder.inc.php");
include_once(BH_INCLUDE_PATH. "forum.inc.php");
include_once(BH_INCLUDE_PATH. "html.inc.php");
include_once(BH_INCLUDE_PATH. "server.inc.php");

/**
* Get Available Forums
*
* Gets available forums for use in sitemap. Password protected and restricted forums are excluded.
*
* return mixed
* param void
*/

function sitemap_get_available_forums()
{
    if (!$db_sitemap_get_available_forums = db_connect()) return false;

    // Query the database to get list of available forums.
    $sql = "SELECT FID, WEBTAG FROM FORUMS WHERE ACCESS_LEVEL = '0'";

    if (!$result = db_query($sql, $db_sitemap_get_available_forums)) return false;

    if (db_num_rows($result) == 0) return false;
    
    return $result;
}

/**
* Get Forum Threads
*
* Gets all threads accessible to Guests.
*
* return mixed
* param string $webtag - Forum Webtag to fetch the threads for.
*/

function sitemap_forum_get_threads($forum_fid)
{
    if (!$db_sitemap_forum_get_threads = db_connect()) return false;

    // If there are any problems with the function arguments we bail out.
    if (!is_numeric($forum_fid)) return false;

    // Constant for Guest access.
    $user_perm_guest_access = USER_PERM_GUEST_ACCESS;

    // Get the table prefix from the forum fid
    if (!($table_data = forum_get_table_prefix($forum_fid))) return false;

    $sql = "SELECT THREAD.TID, UNIX_TIMESTAMP(THREAD.MODIFIED) AS MODIFIED ";
    $sql.= "FROM `{$table_data['PREFIX']}THREAD` THREAD ";
    $sql.= "INNER JOIN `{$table_data['PREFIX']}FOLDER` FOLDER ";
    $sql.= "ON (FOLDER.FID = THREAD.FID) ";
    $sql.= "WHERE FOLDER.PERM & $user_perm_guest_access > 0 ";
    $sql.= "ORDER BY THREAD.TID";

    if (!($result = db_query($sql, $db_sitemap_forum_get_threads))) return false;
    
    if (db_num_rows($result) == 0) return false;

    return $result;
}

/**
* Get sitemap path
*
* Finds the sitemap path and checks that the sitemap file
* exists and is writable by PHP
*
* return boolean
* param void
*/

function sitemap_get_dir()
{
    // Get the real forum directory by getting the parent directory of this
    // files current directory.
    $forum_directory = rtrim(dirname(dirname(__FILE__)), DIRECTORY_SEPARATOR);

    // Once we have the forum directory we can find our sitemaps directory.
    $sitemap_path = $forum_directory. DIRECTORY_SEPARATOR. 'sitemaps';

    // Disable sitemap for get_attachment.php as that can cause problems.
    if (strstr($_SERVER['SCRIPT_NAME'], 'get_attachment.php')) return false;

    // Check to make sure the $sitemap_path exists and is writable.
    mkdir_recursive($sitemap_path, 0755);

    // Check that it actually is a directory.
    if (!@is_dir($sitemap_path)) return false;

    // Check that the main index file is writable.
    if (is_writable($sitemap_path)) return $sitemap_path;

    // If the write check failed return false;
    return false;
}

/**
* Generate sitemap
*
* Generates the sitemap file!
*
* return boolean
* param void
*/

function sitemap_create_file()
{
    // This can take a long time so we'll stop PHP timing out.
    set_time_limit(0);

    // Header for the sitemap index file
    $sitemap_index_header = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $sitemap_index_header.= "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

    // Sitemap index entry
    $sitemap_index_entry = "<sitemap>\n";
    $sitemap_index_entry.= "<loc>%s/sitemaps/sitemap%s.xml</loc>\n";
    $sitemap_index_entry.= "<lastmod>%s</lastmod>\n";
    $sitemap_index_entry.= "</sitemap>\n";

    // Sitemap index footer.
    $sitemap_index_footer = "</sitemapindex>";

    // Header for the sitemap file
    $sitemap_header = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $sitemap_header.= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

    // Sitemap URL entry
    $sitemap_url_entry = "<url>\n";
    $sitemap_url_entry.= "<loc>%s/index.php?webtag=%s&amp;msg=%s.1</loc>\n";
    $sitemap_url_entry.= "<lastmod>%s</lastmod>\n";
    $sitemap_url_entry.= "<changefreq>%s</changefreq>\n";
    $sitemap_url_entry.= "</url>\n";

    // Footer for the sitemap file.
    $sitemap_footer = "</urlset>";

    // Sitemap file count
    $sitemap_file_count = 1;

    // Forum URL
    $forum_location = html_get_forum_uri();

    // Check that search engine spidering is enabled
    if (forum_get_setting('allow_search_spidering', 'N')) return __LINE__;

    // Check that the sitemap setting is enabled.
    if (forum_get_setting('sitemap_enabled', 'N')) return __LINE__;

    // Fetch the sitemap path.
    if (!$sitemap_path = sitemap_get_dir()) return __LINE__;

    // Get the sitemap update frequencey (default: 24 hours)
    $sitemap_freq = forum_get_setting('sitemap_freq', false, DAY_IN_SECONDS);

    // Clear the stat cache so we don't get any stale results.
    clearstatcache();

    // Check that the file is older than the update frequency.
    if (@file_exists("$sitemap_path/sitemap.xml")) {

        if ((@$file_modified = filemtime("$sitemap_path/sitemap.xml"))) {

            if ((time() - $file_modified) < $sitemap_freq) return __LINE__;
        }
    }

    // Number of bytes written to file
    $bytes_written = 0;

    // Open the index file for writing.
    if (!(@$fp_index = fopen("{$sitemap_path}/sitemap.xml", 'w'))) return __LINE__;

    // Write the sitemap index header to the index file
    fwrite($fp_index, $sitemap_index_header);

    // Open the sitemap file for writing.
    if (!(@$fp = fopen("{$sitemap_path}/sitemap{$sitemap_file_count}.xml", 'w'))) return __LINE__;

    // Write the header to the file
    $bytes_written+= fwrite($fp, $sitemap_header);

    // Query the database to find available forums.
    if (!($result_forums = sitemap_get_available_forums())) return __LINE__;
    
    // Iterate over each of the forums.
    while (($forum_data = db_fetch_array($result_forums))) {

        // Get the MySQL result set for the current forum's threads.
        if (!($result_threads = sitemap_forum_get_threads($forum_data['FID']))) return __LINE__;
        
        // Iterate over the threads and add them to the sitemap file.
        while (($thread_data = db_fetch_array($result_threads))) {

            $thread_last_modified = date(MYSQL_DATE, $thread_data['MODIFIED']);

            if ($thread_last_modified < time() - (90 * DAY_IN_SECONDS)) {

                $change_frequency = "yearly";

            }else if ($thread_last_modified < time() - (30 * DAY_IN_SECONDS)) {

                $change_frequency = "monthly";

            }else if ($thread_last_modified < time() - (4 * DAY_IN_SECONDS)) {

                $change_frequency = "weekly";

            }else {

                $change_frequency = "daily";
            }

            // Generate the sitemap entry and write it to the file.
            $sitemap_entry = sprintf($sitemap_url_entry, $forum_location, $forum_data['WEBTAG'], $thread_data['TID'], $thread_last_modified, $change_frequency);

            // If the sitemap file is going to be larger than the 10MB max file size
            // We need to close the current file and open the next in sequence.
            if ($bytes_written + ((mb_strlen($sitemap_entry) + mb_strlen($sitemap_footer))) >= 10000000) {

                // Write the footer to the file
                fwrite($fp, $sitemap_footer);

                // Close the file
                fclose($fp);

                // Generate an index entry
                $sitemap_index = sprintf($sitemap_index_entry, $forum_location, $sitemap_file_count, date(MYSQL_DATE));

                // Write that to the index file.
                fwrite($fp_index, $sitemap_index);

                // Next sitemap file.
                $sitemap_file_count++;

                // Reset the written byte count
                $bytes_written = 0;

                // Try and open the file. If we fail write the footer to the index file, close and return false.
                if (!@$fp = fopen("{$sitemap_path}/sitemap{$sitemap_file_count}.xml", 'w')) {

                    fwrite($fp_index, $sitemap_index_footer);

                    fclose($fp_index);

                    return __LINE__;
                }
            }

            $bytes_written+= fwrite($fp, $sitemap_entry);
        }
    }

    // Write the footer to the file
    fwrite($fp, $sitemap_footer);

    // Close the file
    fclose($fp);

    // Generate an index entry
    $sitemap_index = sprintf($sitemap_index_entry, $forum_location, $sitemap_file_count, date(MYSQL_DATE));

    // Write that to the index file.
    fwrite($fp_index, $sitemap_index);

    // Write the footer
    fwrite($fp_index, $sitemap_index_footer);

    // Close the file.
    fclose($fp_index);

    // Hurrah!
    return true;
}

?>