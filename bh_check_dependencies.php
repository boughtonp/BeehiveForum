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

// Constant to define where the include files are
define("BH_INCLUDE_PATH", "./forum/include/");

include_once(BH_INCLUDE_PATH. "format.inc.php");

// Prevent time out
set_time_limit(0);

// Output the content as text.
header('Content-Type: text/plain');

// Array to hold source files
$source_files_array = array();

// Array of functions and constants
$include_files_functions_array = array('lang.inc.php' => array('$lang'));
$include_files_constants_array = array();

// List of exceptions that we should ignore
$ignore_functions_array = array();
$ignore_constants_array = array('BH_INCLUDE_PATH', 'BEEHIVE_DEVELOPER_MODE', 'E_STRICT', 'BEEHIVE_LIGHT_INCLUDE', 'BEEHIVEMODE_LIGHT');

// Path to source files.
$source_files_dir_array = array('forum', 'forum/include');

// Prevent time out
set_time_limit(0);

// Output the content as text.
header('Content-Type: text/plain');

echo "Getting list of functions...\n";

foreach ($source_files_dir_array as $include_file_dir) {

    if (($dir = opendir($include_file_dir))) {

        while (($file = readdir($dir)) !== false) {

            $path_info_array = pathinfo("$include_file_dir/$file");

            if (isset($path_info_array['extension']) && $path_info_array['extension'] == 'php') {

                $source_files_array[] = "$include_file_dir/$file";
                $source_file_contents = file_get_contents("$include_file_dir/$file");

                $ignore_functions = implode("|", array_map('preg_quote_callback', $ignore_functions_array));
                $ignore_constants = implode("|", array_map('preg_quote_callback', $ignore_constants_array));

                $function_matches = array();

                if (preg_match_all('/function\s([a-z1-9-_]+)[\s]?\(/iu', $source_file_contents, $function_matches)) {

                    if (!isset($include_files_functions_array[$file])) {

                        if (sizeof($ignore_functions_array) > 0) {

                            $function_matches = preg_grep("/$ignore_functions/u", $function_matches[1], PREG_GREP_INVERT);
                            $include_files_functions_array[$file] = $function_matches;

                        }else {

                            $include_files_functions_array[$file] = $function_matches[1];
                        }

                    }else {

                        if (sizeof($ignore_functions_array) > 0) {

                            $include_files_functions_array[$file] = array_merge($include_files_functions_array[$file], $function_matches[1]);
                            $include_files_functions_array = preg_grep("/$ignore_functions/u", $include_files_functions_array[$file], PREG_GREP_INVERT);

                        }else {

                            $include_files_functions_array[$file] = array_merge($include_files_functions_array[$file], $function_matches[1]);
                        }
                    }
                }

                $constant_matches = array();

                if (preg_match_all('/define[\s]?\(["|\']?([a-z1-9-_]+)/iu', $source_file_contents, $constant_matches)) {

                    if (!isset($include_files_constants_array[$file])) {

                        if (sizeof($ignore_constants_array) > 0) {

                            $constant_matches = preg_grep("/$ignore_constants/u", $constant_matches[1], PREG_GREP_INVERT);

                            if (sizeof($constant_matches) > 0) {

                                $include_files_constants_array[$file] = $constant_matches;
                            }

                        }else {

                            $include_files_constants_array[$file] = $constant_matches[1];
                        }

                    }else {

                        if (sizeof($ignore_constants_array) > 0) {

                            $include_files_constants_array[$file] = array_merge($include_files_constants_array[$file], $constant_matches[1]);
                            $include_files_constants_array = preg_grep("/$ignore_constants/u", $include_files_constants_array[$file], PREG_GREP_INVERT);

                        }else {

                            $include_files_constants_array[$file] = array_merge($include_files_constants_array[$file], $constant_matches[1]);
                        }
                    }
                }
            }
        }
    }
}

echo "Processing files...\n\n";

foreach ($source_files_array as $source_file) {

    $include_files_required_array = array();

    $source_file_contents = file_get_contents($source_file);

    echo "$source_file:\n", str_repeat("-", strlen($source_file) + 1), "\n";

    foreach ($include_files_functions_array as $include_file => $function_names_array) {

        if ($include_file !== basename($source_file)) {

            $include_file_line = "include_once(BH_INCLUDE_PATH. \"$include_file\")";
            $include_file_line_preg = preg_quote($include_file_line, "/");

            if (preg_match("/$include_file_line_preg/u", $source_file_contents) < 1) {

                $function_names_preg = implode('\s?\(|[\s|\.|,]+', array_map('preg_quote_callback', $function_names_array));
                $function_names_preg = sprintf('/[\s|\.|,]%s\s?\(/u', $function_names_preg);

                if (preg_match($function_names_preg, $source_file_contents) > 0) {

                    if (!in_array($include_file, $include_files_required_array)) {

                        $include_files_required_array[] = $include_file_line;
                    }
                }
            }
        }
    }

    foreach ($include_files_constants_array as $include_file => $constant_names_array) {

        if ($include_file !== basename($source_file)) {

            $include_file_line = "include_once(BH_INCLUDE_PATH. \"$include_file\")";
            $include_file_line_preg = preg_quote($include_file_line, "/");

            if (preg_match("/$include_file_line_preg/u", $source_file_contents) < 1) {

                $constant_names_preg = implode("|", array_map('preg_quote_callback', $constant_names_array));

                if (preg_match("/{$constant_names_preg}/u", $source_file_contents) > 0) {

                    if (!in_array($include_file, $include_files_required_array)) {

                        $include_files_required_array[] = $include_file_line;
                    }
                }
            }
        }
    }

    echo implode(";\n", $include_files_required_array);
    echo (sizeof($include_files_required_array) > 0) ? ";\n\n" : "\n";
}

?>