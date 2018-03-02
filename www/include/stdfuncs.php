<?php
/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
*	A collection of standard common functions				*
*   $Id: stdfuncs.php,v 1.5 2004/04/27 05:16:48 jleckie Exp $
*                                                           *
************************************************************/

/**
 * Function for handling error conditions
 * @deprecated 17.5
 * @see Schoolbox\Error\Mapper::generateException()
 *
 * @param $ec
 * @param $message
 * @param $code
 */
function error($ec, $message="", $code = 400) {
    /** @var Schoolbox\Application\WebRequest\Kernel $kernel */
    global $kernel;
    /** @var SitePage $page */
    global $page;
    if (isset($page) && $page instanceof SitePage) {
        $page->setDi($kernel->getDependencyInjector());
        $page->error($ec, $message, $code);
    } elseif (isset($this) && $this instanceof SitePage) {
        $this->setDi($kernel->getDependencyInjector());
        $this->error($ec, $message, $code);
    } else {
        $page = $kernel->getDependencyInjector()->get('layoutEngine');
        $page->error($ec, $message, $code);
    }
}

/**
 * Uses superglobals to ascertain whether or not a file was attempted upload, but failed
 * @deprecated 17.5
 * 
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
 * @deprecated 17.5
 *
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
        http_response_code($code);
    }
    else {
        http_response_code(400);
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
    $regexp = <<<REGEXP
%^
(?P<dirname>.*?)
(?P<basename>[^/\\\\]*?)
[\\\\/]*
$%imx
REGEXP;

    preg_match($regexp, $filepath, $m);

    $map = [
        PATHINFO_DIRNAME   => 'dirname',
        PATHINFO_BASENAME  => 'basename',
        PATHINFO_EXTENSION => 'extension',
        PATHINFO_FILENAME  => 'filename'
    ];

    $ret = [];

    if (isset($m['dirname']) && $m['dirname']) {
        // Trim trailing /
        $ret[$map[PATHINFO_DIRNAME]] = preg_replace('%[\\\\/]$%', '', $m['dirname']);
    }
    if (isset($m['basename']) && $m['basename']) {
        $ret[$map[PATHINFO_BASENAME]] = $m['basename'];
        $parts = explode('.', $m['basename']);
        if (count($parts) === 1) {
            $filename = $m['basename'];
        } else {
            $ret[$map[PATHINFO_EXTENSION]] = array_pop($parts);
            $filename                      = implode('.', $parts);
        }
        if (strlen($filename)) {
            $ret[$map[PATHINFO_FILENAME]] = $filename;
        }

    }

    if ($options && isset($map[$options])) {
        return array_key_exists($map[$options], $ret) ? $ret[$map[$options]] : '';
    }

    return $ret;
}

/**
 * Convenience wrapper for consistent temp file naming
 *
 * @param string $prefix
 * @param string $dir
 * @return string
 */
function sb_tempnam($prefix = '', $dir = '')
{
    return tempnam($dir ?: sys_get_temp_dir(), 'sb-' . INSTANCE_ID . ($prefix ? "-$prefix" : ''));
}

/**
 * Get the number of file descriptors this process is currently holding open
 *
 * @return int
 */
function open_fd_count()
{
    $pid = getmypid();
    return (int)`lsof -b -ap $pid 2>/dev/null | wc -l`;
}
