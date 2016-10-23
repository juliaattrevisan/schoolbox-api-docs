<?php
use Schoolbox\File\MimetypeMapper;

/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if (empty($this)) {
    require_once __DIR__ . '/../../library/Bootstrap.php';
    SitePage::localRedir('/mime');
}

/** @var SitePage $this */
$this->setAjax();

$filenames = array();

try {

    $size = empty($_GET['size']) ? 128 : (int)$_GET['size'];

    $root = __DIR__ . "/../images/mime";
    $base = "$root/";

    // special case -_-
    if ($this->arg == array('folder')) {
        $filenames[] = __DIR__ . "/../images/icons/128x128/folder.png";
    } else {
        $filenames[] = "$root/128x128/mime.png";
        $filenames[] = "$base/mime.png";
    }

    if(count($this->arg) != 2) {
        throw new Schoolbox\Mapper\Exception("No mime specified");
    }

    $mimetype = strtolower(implode('/', $this->arg));

    $mtm = MimetypeMapper::getInstance();

    /** @var \Schoolbox\File\Mimetype $mime */
    $mime = $mtm->findOneByColumnList(array(
        'mime' => $mimetype,
        'enable' => 'true'
    ));

    $icon = $mime->getIconFile();

    $filenames[] = "$base/$icon";


} catch(Schoolbox\Mapper\Exception $e) {
    // do nothing
}

foreach(array_reverse($filenames) as $filename) {
    if(!file_exists($filename)) {
        continue;
    }

    $fp = fopen($filename, 'rb');

    $maxAge = 2419200; // 28 days

    // send the right headers
    header('Content-Type: image/svg');
    header('Content-Length: ' . filesize($filename));
    header('Pragma: public');
    header("Cache-Control: public, max-age=$maxAge");
    header('Expires: ' . date('r', $_SERVER['REQUEST_TIME'] + $maxAge));

    // dump the picture and stop the script
    fpassthru($fp);
    exit;
}
error(41);
