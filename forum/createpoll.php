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

//Check logged in status

require_once("./include/session.inc.php");
require_once("./include/header.inc.php");

if(!bh_session_check()) {

    $uri = "http://".$HTTP_SERVER_VARS['HTTP_HOST'];
    $uri.= dirname($HTTP_SERVER_VARS['PHP_SELF']);
    $uri.= "/logon.php?final_uri=";
    $uri.= urlencode(get_request_uri());
    header_redirect($uri);
    
}

if($HTTP_COOKIE_VARS['bh_sess_uid'] == 0) {
    html_guest_error();
    exit;
}

require_once("./include/html.inc.php");
require_once("./include/constants.inc.php");
require_once("./include/folder.inc.php");
require_once("./include/form.inc.php");
require_once("./include/post.inc.php");
require_once("./include/poll.inc.php");

if (isset($HTTP_POST_VARS['cancel'])) {

  $uri = "http://".$HTTP_SERVER_VARS['HTTP_HOST'];
  $uri.= dirname($HTTP_SERVER_VARS['PHP_SELF']);
  $uri.= "/discussion.php";
  if(isset($HTTP_POST_VARS['t_rpid'])) $uri.= "?msg=". $HTTP_POST_VARS['t_tid']. ".". $HTTP_POST_VARS['t_rpid'];
  header_redirect($uri);      
    
}elseif (isset($HTTP_POST_VARS['preview']) || isset($HTTP_POST_VARS['submit'])) {

  $valid = true;

  if (empty($HTTP_POST_VARS['question'])) {
    $error_html = "<h2>You must enter a poll question</h2>";
    $valid = false;
  }
  
  if ($valid && !isset($HTTP_POST_VARS['t_fid'])) {
    $error_html = "<h2>Please select a folder</h2>";
    $valid = false;
  }
  
  if ($valid && empty($HTTP_POST_VARS['answers'][0])) {
    $error_html = "<h2>You must specify values for answers 1 and 2</h2>";
    $valid = false;
  }

  if ($valid && empty($HTTP_POST_VARS['answers'][1])) {
    $error_html = "<h2>You must specify values for answers 1 and 2</h2>";
    $valid = false;
  }
  
}
  
if ($valid && isset($HTTP_POST_VARS['submit'])) {
  
  // Work out when the poll will close.
    
  if ($HTTP_POST_VARS['closepoll'] == 0) {
    $poll_closes = mktime() + DAY_IN_SECONDS;
  }elseif ($HTTP_POST_VARS['closepoll'] == 1) {
    $poll_closes = mktime() + (DAY_IN_SECONDS * 3);
  }elseif ($HTTP_POST_VARS['closepoll'] == 2) {        
    $poll_closes = mktime() + (DAY_IN_SECONDS * 7);
  }elseif ($HTTP_POST_VARS['closepoll'] == 3) {        
    $poll_closes = mktime() + (DAY_IN_SECONDS * 30);
  }elseif ($HTTP_POST_VARS['closepoll'] == 4) {        
    $poll_closes = 0;
  }
    
  // Check HTML tick box, innit.
    
  for ($i = 0; $i < 5; $i++) {
    if ($HTTP_POST_VARS['t_post_html'] == 'Y') {
      $HTTP_POST_VARS['answers'][$i] = fix_html(stripslashes($HTTP_POST_VARS['answers'][$i]));
    }else {
      $HTTP_POST_VARS['answers'][$i] = make_html(stripslashes($HTTP_POST_VARS['answers'][$i]));
    }
  }
    
  $HTTP_POST_VARS['question'] = trim($HTTP_POST_VARS['question']);
    
  // Create the poll thread with the poll_flag set to Y

  $tid = post_create_thread($HTTP_POST_VARS['t_fid'], $HTTP_POST_VARS['question'], 'Y');
  $pid = post_create($tid, 0, $HTTP_COOKIE_VARS['bh_sess_uid'], 0, '');    
  poll_create($tid, $HTTP_POST_VARS['answers'], $poll_closes, $HTTP_POST_VARS['changevote'], $HTTP_POST_VARS['polltype'], $HTTP_POST_VARS['showresults']);
    
  $uri = "http://".$HTTP_SERVER_VARS['HTTP_HOST'];
  $uri.= dirname($HTTP_SERVER_VARS['PHP_SELF']);
  $uri.= "/discussion.php?msg=$tid.1";
    
  header_redirect($uri);
    
}

html_draw_top();

if ($valid && isset($HTTP_POST_VARS['preview'])) {
  
  // draw the preview
    
}

if(isset($error_html)) echo $error_html. "\n";

?>
<form name="f_poll" action="<?php echo $HTTP_SERVER_VARS['PHP_SELF']; ?>" method="POST" target="_self">
  <table border="0" cellpadding="0" cellspacing="0" width="500">
    <tr>
      <td><h2>Select folder</h2></td>
    </tr>
    <tr>
      <td><?php echo folder_draw_dropdown($t_fid); ?></td>
    </tr>
    <tr>
      <td><h2>Poll Question</h2></td>
    </tr>
    <tr>
      <td><?php echo form_input_text("question", htmlspecialchars(stripslashes($HTTP_POST_VARS['question'])), 30, 64); ?></td>
    </tr>
    <tr>
      <td>&nbsp;</td>
    </tr>
  </table>
  <table class="box" cellpadding="0" cellspacing="0" width="500">
    <tr>
      <td>
        <table border="0" class="posthead">
          <tr>
            <td><h2>Possible Answers</h2></td>
          </tr>
          <tr>
            <td>Enter up to five answers for your poll question.. If your poll is a "yes/no" question, simply enter "Yes" for Answer 1 and "No" for Answer 2.</td>
          </tr>
          <tr>
            <td>&nbsp;</td>
          </tr>          
          <tr>
            <td>1. <?php echo form_input_text("answers[]", htmlspecialchars(stripslashes($HTTP_POST_VARS['answers'][0])), 40, 64); ?></td>
          </tr>
          <tr>
            <td>2. <?php echo form_input_text("answers[]", htmlspecialchars(stripslashes($HTTP_POST_VARS['answers'][1])), 40, 64); ?></td>
          </tr>
          <tr>
            <td>3. <?php echo form_input_text("answers[]", htmlspecialchars(stripslashes($HTTP_POST_VARS['answers'][2])), 40, 64); ?></td>
          </tr>
          <tr>
            <td>4. <?php echo form_input_text("answers[]", htmlspecialchars(stripslashes($HTTP_POST_VARS['answers'][3])), 40, 64); ?></td>
          </tr>
          <tr>
            <td>5. <?php echo form_input_text("answers[]", htmlspecialchars(stripslashes($HTTP_POST_VARS['answers'][4])), 40, 64); ?></td>
          </tr>
          <tr>
            <td><?php echo form_checkbox("t_post_html", "Y", "Contains HTML (not including signature)", ($t_post_html == "Y")); ?></td>
          </tr>           
          <tr>
            <td>&nbsp;</td>
          </tr>
          <tr>
            <td><h2>Vote Changing</h2></td>
          </tr>
          <tr>
            <td>Can a person change his or her vote?</td>
          </tr>
          <tr>
            <td>
              <table border="0" width="300">
                <tr>
                  <td><?php echo form_radio('changevote', '1', 'Yes', true); ?></td>
                  <td><?php echo form_radio('changevote', '0', 'No', false); ?></td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td>&nbsp;</td>
          </tr>          
          <tr>
            <td><h2>Poll Results</h2></td>
          </tr>
          <tr>
            <td>How would you like to display the results of your poll?</td>
          </tr>
          <tr>
            <td>
              <table border="0" width="300">
                <tr>
                  <td><?php echo form_radio('polltype', '1', 'Horizontal Bar graph', true); ?></td>
                  <td><?php echo form_radio('polltype', '0', 'Vertical Bar graph', false); ?></td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td>&nbsp;</td>
          </tr>          
          <tr>
            <td><h2>Expiration</h2></td>
          </tr>
          <tr>
            <td>Do you want to show results while the poll is open?</td>
          </tr>
          <tr>
            <td>
              <table border="0" width="300">
                <tr>
                  <td><?php echo form_radio('showresults', '1', 'Yes', true); ?></td>
                  <td><?php echo form_radio('showresults', '0', 'No', false); ?></td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td>&nbsp;</td>
          </tr>          
          <tr>
            <td>When would you like your poll to automatically close?</td>
          </tr>
          <tr>
            <td><?php echo form_dropdown_array('closepoll', range(0, 4), array('One Day', 'Three Days', 'Seven Days', 'Thirty Days', 'Never'), 4); ?></td>
          </tr>
          <tr>
            <td>&nbsp;</td>
          </tr>         
        </table>
      </td>
    </tr>   
  </table>
<?php 

    echo form_submit("submit", "Post"). "&nbsp;". form_submit("preview", "Preview"). "&nbsp;". form_submit("cancel", "Cancel");

    if ($attachments_enabled) {
  
      echo "&nbsp;".form_button("attachments", "Attachments", "onclick=\"window.open('attachments.php?aid=". $aid. "', 'attachments', 'width=640, height=480, toolbar=0, location=0, directories=0, status=0, menubar=0, resizable=0, scrollbars=yes');\"");
      echo form_input_hidden("aid", $aid);
     
    }
    
    echo "</form>\n";
    
    html_draw_bottom();
  
?>