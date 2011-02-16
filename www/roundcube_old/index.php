<?php
/*
 +-------------------------------------------------------------------------+
 | RoundCube Webmail IMAP Client                                           |
 | Version 0.2-stable                                                      |
 |                                                                         |
 | Copyright (C) 2005-2008, RoundCube Dev. - Switzerland                   |
 |                                                                         |
 | This program is free software; you can redistribute it and/or modify    |
 | it under the terms of the GNU General Public License version 2          |
 | as published by the Free Software Foundation.                           |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 |                                                                         |
 | You should have received a copy of the GNU General Public License along |
 | with this program; if not, write to the Free Software Foundation, Inc., |
 | 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.             |
 |                                                                         |
 +-------------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                          |
 +-------------------------------------------------------------------------+

 $Id: index.php 2201 2008-12-30 14:33:28Z thomasb $

*/

// include environment

require_once 'program/include/iniset.php';
require_once '../include/session.php';

// init application and start session with requested task
$RCMAIL = rcmail::get_instance();

// init output class
$OUTPUT = !empty($_REQUEST['_remote']) ? $RCMAIL->init_json() : $RCMAIL->load_gui(!empty($_REQUEST['_framed']));

// set output buffering
if ($RCMAIL->action != 'get' && $RCMAIL->action != 'viewsource') {
  // use gzip compression if supported
  if (function_exists('ob_gzhandler')
      && !ini_get('zlib.output_compression')
      && ini_get('output_handler') != 'ob_gzhandler') {
    ob_start('ob_gzhandler');
  }
  else {
    ob_start();
  }
}

// check if config files had errors
if ($err_str = $RCMAIL->config->get_error()) {
  raise_error(array(
    'code' => 601,
    'type' => 'php',
    'message' => $err_str), false, true);
}

// check DB connections and exit on failure
if ($err_str = $DB->is_error()) {
  raise_error(array(
    'code' => 603,
    'type' => 'db',
    'message' => $err_str), FALSE, TRUE);
}

// error steps
if ($RCMAIL->action=='error' && !empty($_GET['_code'])) {
  raise_error(array('code' => hexdec($_GET['_code'])), FALSE, TRUE);
}

// check client X-header to verify request origin
/*if ($OUTPUT->ajax_call) {
  if (!$RCMAIL->config->get('devel_mode') && !rc_request_header('X-RoundCube-Referer')) {
    header('HTTP/1.1 404 Not Found');
    die("Invalid Request");
  }
}*/


// handle keep-alive signal
if ($RCMAIL->action == 'keep-alive') {
  $OUTPUT->reset();
  $OUTPUT->send();
}
// save preference value
/*
else if ($RCMAIL->action == 'save-pref') {
  $RCMAIL->user->save_prefs(array(get_input_value('_name', RCUBE_INPUT_POST) => get_input_value('_value', RCUBE_INPUT_POST)));
  $OUTPUT->reset();
  $OUTPUT->send();
}
*/

// map task/action to a certain include file
$action_map = array(
  'mail' => array(
    'preview' => 'show.inc',
    'print'   => 'show.inc',
    'moveto'  => 'move_del.inc',
    'delete'  => 'move_del.inc',
    'send'    => 'sendmail.inc',
    'expunge' => 'folders.inc',
    'purge'   => 'folders.inc',
    'remove-attachment'  => 'attachments.inc',
    'display-attachment' => 'attachments.inc',
    'upload' => 'attachments.inc',
  ),
  
  'addressbook' => array(
    'add' => 'edit.inc',
  ),
  
  'settings' => array(
    'folders'       => 'manage_folders.inc',
    'create-folder' => 'manage_folders.inc',
    'rename-folder' => 'manage_folders.inc',
    'delete-folder' => 'manage_folders.inc',
    'subscribe'     => 'manage_folders.inc',
    'unsubscribe'   => 'manage_folders.inc',
    'add-identity'  => 'edit_identity.inc',
  )
);

// include task specific functions
include_once 'program/steps/'.$RCMAIL->task.'/func.inc';

// allow 5 "redirects" to another action
$redirects = 0; $incstep = null;
while ($redirects < 5) {
  $stepfile = !empty($action_map[$RCMAIL->task][$RCMAIL->action]) ?
    $action_map[$RCMAIL->task][$RCMAIL->action] : strtr($RCMAIL->action, '-', '_') . '.inc';
    
  // try to include the step file
  if (is_file(($incfile = 'program/steps/'.$RCMAIL->task.'/'.$stepfile))) {
    include($incfile);
    $redirects++;
  }
  else {
    break;
  }
}


// parse main template (default)
$OUTPUT->send($RCMAIL->task);


// if we arrive here, something went wrong
raise_error(array(
  'code' => 404,
  'type' => 'php',
  'line' => __LINE__,
  'file' => __FILE__,
  'message' => "Invalid request"), true, true);
                      
?>
