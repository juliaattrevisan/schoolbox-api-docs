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

/**
 * This function exists because pathinfo() breaks non-latin things
 *
 * @see http://php.net/manual/en/function.pathinfo.php#107461
 *
 * @param string $filepath
 * @param int    $options
 * @return array
 */
function mb_pathinfo($filepath, $options = null) {
    preg_match('%^(.*?)[\\\\/]*(([^/\\\\]*?)(\.([^\.\\\\/]+?)|))[\\\\/\.]*$%im', $filepath, $m);

    $map = [
        PATHINFO_DIRNAME   => 'dirname',
        PATHINFO_BASENAME  => 'basename',
        PATHINFO_EXTENSION => 'extension',
        PATHINFO_FILENAME  => 'filename'
    ];

    $ret = [];

    if (isset($m[1]) && $m[1]) {
        $ret[$map[PATHINFO_DIRNAME]] = $m[1];
    }
    if (isset($m[2]) && $m[2]) {
        $ret[$map[PATHINFO_BASENAME]] = $m[2];
    }
    if (isset($m[5])) {
        $ret[$map[PATHINFO_EXTENSION]] = $m[5];
    }
    if (isset($m[3]) && $m[3]) {
        $ret[$map[PATHINFO_FILENAME]] = $m[3];
    }

    if ($options && isset($map[$options])) {
        return array_key_exists($map[$options], $ret) ? $ret[$map[$options]] : '';
    }

    return $ret;
}
