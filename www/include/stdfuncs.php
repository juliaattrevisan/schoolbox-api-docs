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
function error($ec, $message="", $code = 400) {
	global $page;
	if (is_object($page))
		$page->error($ec, $message, $code);
	elseif (is_object($this))
		$this->error($ec, $message, $code);
	else {
		require_once dirname(__FILE__) . '/../../include/Bootstrap.php';
		$page = new SitePage;
		$page->error($ec, $message, $code);
	}
}

/**
 * Handler function that will be called whenver a script terminates, but will only do something if the termination
 * was triggered by a fatal error
 */
function shutdown_safely(Exception $e = null) {

    if ($e instanceof Exception) {
        $error = array(
            'file'    => $e->getFile(),
            'message' => "<pre>$e</pre>",
            'line'    => $e->getLine(),
        );
    }
    else {
        $error = error_get_last();
    }

    // it's almost a guarantee that some kind of minor error will have occurred at some point
    if ($error !== NULL) {

        // use the document_root to get a Schoolbox-relative file in which the error occurred
        $cause = substr($error['file'], strlen($_SERVER['DOCUMENT_ROOT']) - 4);

        // if it was a fatal error or exception that triggered our sudden but inevitable demise...
        if (
            (intval($error['type']) & (E_ERROR|E_CORE_ERROR|E_USER_ERROR|E_COMPILE_ERROR))
            || $e instanceof Exception
        ) {
            // RedisException extends RuntimeException: occurs when Redis
            // session management isn't working for whatever reason.
            $code    = 400;
            $message = 'Schoolbox has encountered a fatal error.';
            $info    = "If the problem persists, please contact your System Administrator including the following information:";
            if ($e instanceof RuntimeException) {
                $code    = 500;
                $message = 'Schoolbox is currently down due to an internal service being inactive.';
            }
            elseif($e instanceof PDOException) {
	            $code = 500;
	            $message = 'Schoolbox is currently experiencing internal problems with its database. This issue has been logged.';
            }
            else {
                // decide what to tell the user based on where this error came from
                switch($cause) {
                    // certain queries in the reports can return too much data to fit under the memory limit
                    case '/library/Report/Login.php':
                    case '/library/Report/Activity.php':
                        $info = "The report you tried to run has returned too much data. Please press 'Back' and refine your report filter parameters.";
                        break;
                    // if it's not somewhere we expect to find an error, just be generic about it
                    default:
                        break;
                }
            }

            // Report to New Relic if not a RuntimeException
            if (extension_loaded('newrelic')) {
                if ($e instanceof Exception && !($e instanceof RuntimeException)) {
                    newrelic_notice_error(null, $e);
                }
            }

            // explain that something went cataclysmically wrong.
            $output =
                 "<p><b>$message $info</b></p>" .
                 '<ul>' .
                 '<li>Datetime: '.date('Y-m-d H:i:s').'</li>' .
                 '<li>URL: http'.(getenv("SERVER_PORT") == 443 ? 's://' : '://').$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'</li>' .
                 "<li>{$error['message']} in {$error['file']} on line {$error['line']}.</li>" .
                 '</ul>';

            // Use Sitepage to output an error message in the school's design.
            $page = new SitePage(false);
            $page->error(null, $output, $code);
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


/**
 * An HTTP-sensitive way to send a plaintext failure response.
 * @param $code - the HTTP code to send
 * @param $message - if numeric, will be looked up in the error table on the DB, otherwise displayed as-is
 */
function httpError($code, $message) {
    if(is_numeric($message)) {
        $msg = Database::getInstance()->query("SELECT message FROM error WHERE id = $message")->fetchColumn();
    }
    else {
        $msg = $message;
    }
    $codes = array(
        '400' => 'Bad Request',
        '401' => 'Unauthorized',
        '403' => 'Forbidden',
        '405' => 'Method Not Allowed',
    );
    if(isset($codes[$code])) {
        header('HTTP/1.1 '.$code.' '.$codes[$code]);
    }
    else {
        header('HTTP/1.1 400 Bad Request');
    }
    die($msg);
}


/**
 * @param $camel
 * @param string $delim
 * @return string
 */
function camel2score($camel, $delim = '_') {
    $xpl = preg_split('/([[:upper:]][[:lower:]]+)/', $camel, null, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
    return strtolower(implode($delim, $xpl));
}


/**
 * @param $score
 * @param string $delim
 * @return string
 */
function score2camel($score, $delim = '_') {
    return lcfirst(score2pascal($score, $delim));
}


/**
 * @param $score
 * @param string $delim
 * @return string
 */
function score2pascal($score, $delim = '_') {
    $xpl = explode($delim, $score);
    foreach($xpl as &$v) {
        $v = ucfirst($v);
    }
    return implode('', $xpl);
}
