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
include_once(BH_INCLUDE_PATH. "dictionary.inc.php");
include_once(BH_INCLUDE_PATH. "emoticons.inc.php");
include_once(BH_INCLUDE_PATH. "form.inc.php");
include_once(BH_INCLUDE_PATH. "format.inc.php");
include_once(BH_INCLUDE_PATH. "forum.inc.php");
include_once(BH_INCLUDE_PATH. "html.inc.php");
include_once(BH_INCLUDE_PATH. "lang.inc.php");
include_once(BH_INCLUDE_PATH. "post.inc.php");
include_once(BH_INCLUDE_PATH. "session.inc.php");
include_once(BH_INCLUDE_PATH. "swift.inc.php");
include_once(BH_INCLUDE_PATH. "text_captcha.inc.php");

class TextAreaHTML
{
    private $textareas = array();

    private $toolbar_count = 0;

    public $allowed_toolbars = 1;

    public $tinymce = false;

    public $emoticons = false;

    public function __construct()
    {
        if (@file_exists("tiny_mce/tiny_mce.js")) {

            $page_prefs = session_get_post_page_prefs();

            if (($page_prefs & POST_TINYMCE_DISPLAY) && !defined('BEEHIVEMODE_LIGHT')) {
                $this->tinymce = true;
            }
        }
    }

    public function get_tinymce()
    {
        return $this->tinymce;
    }

    public function set_tinymce($bool)
    {
        $this->tinymce = ($bool === true) ? true : false;
    }

    public function set_allowed_toolbars($num)
    {
        if (is_numeric($num)) {
            $this->allowed_toolbars = $num > 0 ? $num : 0;
        }
    }

    public function toolbar($emoticons = true, $custom_html = '')
    {
        if ($this->tinymce || $this->toolbar_count >= $this->allowed_toolbars) return '';

        $this->toolbar_count = $this->toolbar_count + 1;

        $dictionary = new dictionary();

        $str = sprintf("<div id=\"bh_tb%d\" class=\"tools\">\n", $this->toolbar_count);

        $str.= $this->toolbar_img(gettext("Bold"), 'bold');
        $str.= $this->toolbar_img(gettext("Italic"), 'italic');
        $str.= $this->toolbar_img(gettext("Underline"), 'underline');
        $str.= $this->toolbar_img(gettext("Strikethrough"), 'strikethrough');
        $str.= $this->toolbar_img(gettext("Superscript"), 'superscript');
        $str.= $this->toolbar_img(gettext("Subscript"), 'subscript');
        $str.= $this->toolbar_img(gettext("Left-align"), 'leftalign');
        $str.= $this->toolbar_img(gettext("Center"), 'center');
        $str.= $this->toolbar_img(gettext("Right-align"), 'rightalign');
        $str.= $this->toolbar_img(gettext("Numbered list"), 'numberedlist');
        $str.= $this->toolbar_img(gettext("List"), 'list');
        $str.= $this->toolbar_img(gettext("Indent text"), 'indenttext');
        $str.= $this->toolbar_img(gettext("Code"), 'code');
        $str.= $this->toolbar_img(gettext("Quote"), 'quote');
        $str.= $this->toolbar_img(gettext("Spoiler"), 'spoiler');
        $str.= $this->toolbar_img(gettext("Horizontal rule"), 'horizontalrule');
        $str.= $this->toolbar_img(gettext("Image"), 'image');
        $str.= $this->toolbar_img(gettext("Youtube Video"), 'youtubevideo');
        $str.= $this->toolbar_img(gettext("Flash"), 'flash');
        $str.= $this->toolbar_img(gettext("Hyperlink"), 'hyperlink');

        if ($dictionary->is_installed()) {
            $str.= $this->toolbar_img(gettext("Spell check"), 'spellcheck');
        }

        $str.= $this->toolbar_img(gettext("Disable emoticons"), 'noemoticons');

        if ($emoticons == true) {
            $str.= $this->toolbar_img(gettext("Emoticons"), 'emoticons');
        }

        $str.= "    <br />\n";
        $str.= "    <select class=\"bhselect\" name=\"font_face\">\n";
        $str.= "        <option value=\"\" selected=\"selected\">". gettext("Font Face"). "</option>\n";
        $str.= "        <option value=\"Arial\">Arial</option>\n";
        $str.= "        <option value=\"Times New Roman\">Times New Roman</option>\n";
        $str.= "        <option value=\"Verdana\">Verdana</option>\n";
        $str.= "        <option value=\"Tahoma\">Tahoma</option>\n";
        $str.= "        <option value=\"Courier New\">Courier New</option>\n";
        $str.= "        <option value=\"Trebuchet MS\">Trebuchet MS</option>\n";
        $str.= "        <option value=\"Microsoft Sans Serif\">Microsoft Sans Serif</option>\n";
        $str.= "    </select>\n";
        $str.= "    <select class=\"bhselect\" name=\"font_size\">\n";
        $str.= "        <option value=\"\" selected=\"selected\">". gettext("Size"). "</option>\n";
        $str.= "        <option value=\"1\">1</option>\n";
        $str.= "        <option value=\"2\">2</option>\n";
        $str.= "        <option value=\"3\">3</option>\n";
        $str.= "        <option value=\"4\">4</option>\n";
        $str.= "        <option value=\"5\">5</option>\n";
        $str.= "        <option value=\"6\">6</option>\n";
        $str.= "        <option value=\"7\">7</option>\n";
        $str.= "    </select>\n";
        $str.= "    <select class=\"bhselect\" name=\"font_colour\">\n";
        $str.= "        <option value=\"\" selected=\"selected\">". gettext("Colour"). "</option>\n";
        $str.= "        <option value=\"#FF0000\" style=\"color: #FF0000;\">". gettext("Red"). "</option>\n";
        $str.= "        <option value=\"#FFA500\" style=\"color: #FFA500;\">". gettext("Orange"). "</option>\n";
        $str.= "        <option value=\"#FFFF00\" style=\"color: #FFFF00;\">". gettext("Yellow"). "</option>\n";
        $str.= "        <option value=\"#008000\" style=\"color: #008000;\">". gettext("Green"). "</option>\n";
        $str.= "        <option value=\"#0000FF\" style=\"color: #0000FF;\">". gettext("Blue"). "</option>\n";
        $str.= "        <option value=\"#4B0082\" style=\"color: #4B0082;\">". gettext("Indigo"). "</option>\n";
        $str.= "        <option value=\"#EE82EE\" style=\"color: #EE82EE;\">". gettext("Violet"). "</option>\n";
        $str.= "        <option value=\"#000000\" style=\"color: #000000;\">". gettext("Black"). "</option>\n";
        $str.= "        <option value=\"#808080\" style=\"color: #808080;\">". gettext("Grey"). "</option>\n";
        $str.= "        <option value=\"#FFFFFF\" style=\"color: #FFFFFF; background-color: #000000;\">". gettext("White"). "</option>\n";
        $str.= "        <option value=\"#FFA8CF\" style=\"color: #FFA8CF;\">". gettext("Pink"). "</option>\n";
        $str.= "        <option value=\"#80FF80\" style=\"color: #80FF80;\">". gettext("Light green"). "</option>\n";
        $str.= "        <option value=\"#00FFFF\" style=\"color: #00FFFF;\">". gettext("Light blue"). "</option>\n";
        $str.= "    </select>\n";
        $str.= "    $custom_html\n";
        $str.= "</div>\n";

        return $str;
    }

    public function toolbar_reduced($emoticons = true)
    {
        if ($this->tinymce || $this->toolbar_count >= $this->allowed_toolbars) return '';

        $this->toolbar_count = $this->toolbar_count + 1;

        $str = sprintf("<div id=\"bh_tb%d\" class=\"tools\">\n", $this->toolbar_count);

        $str.= $this->toolbar_img(gettext("Bold"), 'bold');
        $str.= $this->toolbar_img(gettext("Italic"), 'italic');
        $str.= $this->toolbar_img(gettext("Underline"), 'underline');

        if ($emoticons == true) {
            $str.= $this->toolbar_img(gettext("Emoticons"), 'emoticons');
        }

        $str.= "</div>\n";

        return $str;
    }

    public function textarea($name, $value = false, $rows = false, $cols = false, $auto_focus = false, $custom_html = "", $class = "bhinputtext")
    {
        $this->textareas[] = $name;

        $str = '';

        if ($auto_focus) {
            $class.= ' auto_focus';
        }

        if ($this->tinymce) {

            if ($this->toolbar_count < $this->allowed_toolbars) {

                $this->toolbar_count = $this->toolbar_count + 1;

                if ($rows < 7) $rows = 7;

                $rows+= 5;

                $class.= ' tinymce_editor';
            }

        }else {

            $class.= ' htmltools';

            $str = "<div style=\"display: none\">&#9999;&#9999;&#9999;&#9999;&#9999;&#9999;&#9999;&#9999;&#9999;&#9999;</div>";
        }

        $str.= form_textarea($name, $value, $rows, $cols, $custom_html, $class);

        return $str;
    }

    public function compare_original($textarea, MessageText $text)
    {
        $str = form_radio("co_{$textarea}_rb", $text->getTidyContent(), gettext("Corrected code"), true, false, 'bhinputradio fix_html_compare'). "\n";
        $str.= form_radio("co_{$textarea}_rb", htmlentities_array($text->getOriginalContent()), gettext("Submitted code"), false, false, 'bhinputradio fix_html_compare'). "\n";
        $str.= "&nbsp;[<a class=\"fix_html_help\">?</a>]\n";

        return $str;
    }

    protected function toolbar_img($title, $action, $image_name = "blank.png")
    {
        return "<a class=\"toolbar_button button_$action\"><img src=\"". html_style_image($image_name). "\" alt=\"{$title}\" title=\"{$title}\" class=\"tools_up\" /></a>";
    }
}

?>