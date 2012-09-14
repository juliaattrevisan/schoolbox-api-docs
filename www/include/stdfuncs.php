<?php
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
*	A collection of standard common functions				*
*   $Id: stdfuncs.php,v 1.5 2004/04/27 05:16:48 jleckie Exp $
*                                                           *
************************************************************/

//Function for handling error conditions
function error($ec, $message="") {
	global $page;
	if (is_object($page))
		$page->error($ec, $message);
	elseif (is_object($this))
		$this->error($ec, $message);
	else {
		require_once dirname(__FILE__) . '/../../include/SitePage.php';
		$page = new SitePage;
		$page->error($ec, $message);
	}
}

/**
 * Handler function that will be called whenver a script terminates, but will only do something if the termination
 * was triggered by a fatal error
 */
function shutdown_safely() {

    $error = error_get_last();

    // it's almost a guarantee that some kind of minor error will have occurred at some point
    if($error !== NULL) {

        // use the document_root to get a Schoolbox-relative file in which the error occurred
        $cause = substr($error['file'], strlen($_SERVER['DOCUMENT_ROOT']) - 4);

        // if it was a fatal error that triggered our sudden but inevitable demise...
        if($error['type'] == E_ERROR) {

            // decide what to tell the user based on where this error came from
            switch($cause) {

                // certain queries in the reports can return too much data to fit under the memory limit
                case '/library/Report/Login.php':
                case '/library/Report/Activity.php':
                    $info = "The report you tried to run has returned too much data. Please press 'Back' and refine your report filter parameters.";
                    break;

                // if it's not somewhere we expect to find an error, just be generic about it
                default:
                    $info = "If the problem persists, please contact your System Administrator including the following information:";
                    break;
            }

            // explain that something went cataclysmically wrong.
            echo '<div style="margin:40px auto 0 auto;max-width:700px">'.
                 "<h2>Sorry, Schoolbox has encountered a fatal error</h2><p><b>$info</b></p>" .
                 '<ul style="list-style:none;padding:0;margin:0;color:#444;font-size:0.7em;">' .
                 '<li>Datetime: '.date('Y-m-d H:i:s').'</li>' .
                 '<li>URL: http'.(getenv("SERVER_PORT") == 443 ? 's://' : '://').$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'</li>' .
                 "<li>{$error['message']} in {$error['file']} on line {$error['line']}.</li>" .
                 '</ul></div>';
        }
    }
}

/**
 * Uses superglobals to ascertain whether or not a file was attempted upload, but failed
 * @return bool
 */
function upload_failed() {
    return $_SERVER['REQUEST_METHOD'] == 'POST'
        && $_SERVER['CONTENT_LENGTH'] > 0
        && empty($_POST)
        && empty($_FILES);
}