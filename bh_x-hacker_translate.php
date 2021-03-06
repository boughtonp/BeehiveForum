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

// Creates an X-Hacker (L33t SpEak) language file from the en.inc.php
// Derived from the L33t-5p34K G3n3r@t0r v3r510N 0.6 found at :
// http://www.geocities.com/mnstr_2000/translate.html
// Outputs to STDOUT.
function htmlentities_decode($text)
{
    $trans_tbl = get_html_translation_table (HTML_ENTITIES);
    $trans_tbl = array_flip ($trans_tbl);
    $ret = strtr ($text, $trans_tbl);
    return preg_replace('/&amp;#(\d+);/meu', 'chr(\1)', $ret);
}

function rn($r)
{
    srand((double)microtime()*1000000);
    return rand(1, $r);
}

function translate($matches)
{
    $string_translate = $matches[1];

    $sprintf_chars = array('b', 'c', 'd', 'e', 'u', 'f', 'F', 'o', 's', 'x', 'X');

    $string_parts = preg_split('/([<|>])/u', $string_translate, -1, PREG_SPLIT_DELIM_CAPTURE);

    // Initialize the variables we need.
    $str_translated = '';
    $str_new = '';
    $str_out = '';

    // Process Specific Words
    for ($i = 0; $i < sizeof($string_parts); $i++) {

        if (!($i % 4)) {

            $str_words = explode(' ', strtolower($string_parts[$i]));

            for ($j = 0; $j < sizeof($str_words); $j++) {

                $str_word = htmlentities_decode($str_words[$j]);

                if ($str_word == 'am' && isset($str_words[$i + 1]) && $str_words[$i + 1] == 'good') {
                    $str_word = 'ownz0r';
                    $i++;
                }

                if ($str_word == 'is' && isset($str_words[$i + 1]) && $str_words[$i + 1] == 'good') {
                    $str_word = 'ownz0rz';
                    $i++;
                }

                if ($str_word == 'the' && rn(10) > 6) $str_word = 'teh';
                if ($str_word == 'you') $str_word = 'j00';

                $str_translated = $str_translated. $str_word. ' ';
            }

        }else {

            $str_translated = $str_translated. trim($string_parts[$i]);

        }
    }

    $string_parts = preg_split('/([<|>])/u', $str_translated, -1, PREG_SPLIT_DELIM_CAPTURE);

    //Process Individual Chars
    for ($i = 0; $i < sizeof($string_parts); $i++) {

        if (!($i % 4)) {

            for ($j = 0; $j < strlen($string_parts[$i]); $j++) {

                $char = substr($string_parts[$i], $j, 1);

                if (!(in_array($char, $sprintf_chars) && isset($string_parts[$i][$j - 1]) && $string_parts[$i][$j - 1] == '%') && (isset($string_parts[$i][$j - 1]) && $string_parts[$i][$j - 1] != '\\')) {

                    if ($char == 'a' && rn(10) > 7) $char = '@';
                    if ($char == 'a' && rn(10) > 2) $char = '4';
                    if ($char == 'b' && rn(10) > 5) $char = '8';
                    if ($char == 'd' && rn(10) > 10) $char = '|)';
                    if ($char == 'e' && rn(10) > 5) $char = '3';
                    if ($char == 'f' && rn(10) > 5) $char = 'ph';
                    if ($char == 'g' && rn(10) > 5) $char = '9';
                    if ($char == 'h' && rn(10) > 10) $char = '|-|';
                    if ($char == 'i' && rn(10) > 5) $char = '1';
                    if ($char == 'k' && rn(10) > 10) $char = '|&gt;';
                    if ($char == 'm' && rn(10) > 10) $char = '|\/|';
                    if ($char == 'n' && rn(10) > 10) $char = '|\|';
                    if ($char == 'o' && rn(10) > 5) $char = '0';

                    if ($char == 'q' && isset($string_parts[$i + 1]) && $string_parts[$i + 1] == 'u') {
                        $char = 'kw';
                        $i++;
                    }

                    if ($char == 's' && rn(10) > 7) $char = '\$';
                    if ($char == 's' && rn(10) > 7) $char = '5';
                    if ($char == 't' && rn(10) > 5) $char = '+';
                    if ($char == 'v' && rn(10) > 10) $char = '\/';
                    if ($char == 'w' && rn(10) > 10) $char = '\/\/';
                    if ($char == 'x' && rn(10) > 10) $char = '&gt;&lt;';

                    $str_new = $str_new. $char;

                }else {

                    $str_new = $str_new. $char;
                }
            }

        }else {

            $str_new = $str_new. $string_parts[$i];
        }
    }

    // Randomize case
    $string_parts = preg_split('/([<|>])/u', $str_new, -1, PREG_SPLIT_DELIM_CAPTURE);

    for ($i = 0; $i < sizeof($string_parts); $i++) {

        if (!($i % 4)) {

            for ($j = 0; $j < strlen($string_parts[$i]); $j++) {

                $char = substr($string_parts[$i], $j, 1);

                if (!(in_array($char, $sprintf_chars) && isset($string_parts[$i][$j - 1]) && $string_parts[$i][$j - 1] == '%') && (isset($string_parts[$i][$j - 1]) && $string_parts[$i][$j - 1] != '\\')) {

                    if (rn(10) > 5) $char = strtoupper($char);
                    $str_out = $str_out. htmlentities_array($char);

                }else {

                    $str_out = $str_out. $char;
                }
            }

        }else {

            $str_out = $str_out. $string_parts[$i];

        }
    }

    $string_result = trim(str_replace(' <', '<', $str_out));
    return sprintf('"%s";', $string_result);
}

define("BH_INCLUDE_PATH", "./forum/include/");

include_once(BH_INCLUDE_PATH. "format.inc.php");

// Prevent time out
set_time_limit(0);

// Output the content as text.
header('Content-Type: text/plain');

// Start here
if (($langfile = file('./forum/include/languages/en.inc.php'))) {

    if (($fp = fopen('./forum/include/languages/x-hacker.inc.php', 'w'))) {

        foreach ($langfile as $line) {

            if (!preg_match('/^\$lang\[\'_/u', $line)) {

                $translated_line = preg_replace_callback('/"([^"]+)";/u', 'translate', $line);
                fwrite($fp, $translated_line);

            }else {

                fwrite($fp, $line);
            }
        }

        fclose($fp);

        echo 'Translation of en.inc.php into x-hacker.inc.php has completed successfully.';

    }else {

        echo 'Could not open x-hacker.inc.php for writing.';
    }

}else {

    echo "Could not open en.inc.php for reading.\n";
}

?>
