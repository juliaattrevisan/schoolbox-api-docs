<?php
use Schoolbox\File\Remote\GDriveFetcher;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Browser\NodeFactory;
use Schoolbox\Resource\Storage;

/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if (empty($this) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    require_once __DIR__ . '/../../../../library/Bootstrap.php';
    // nope. go away
    SitePage::localRedir('/resource/external/google');
}

/** @var SitePage $this */

/** @var IConfigLoader $conf */
$conf    = $this->getDi()->get('config');
if (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
} else {
    /* Handle nginx/php-fpm case instead which doesn't have apache specific functions
     */
    $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
}
$proxy   = new Proxy\Server($conf);
$grive   = new GDriveFetcher(sys_get_temp_dir(), $proxy->curlInit(), $headers['Authorization']);
$mapper  = Storage\Mapper::getInstance();
$files   = array();

foreach ($_POST['docs'] as $doc) {
    $meta = $grive->fetchMetaData($doc['id']);
    $filename = $grive->deriveFileName($meta);
    $dest = $grive->fetchToFile($doc['id']);

    try {
        // this will return an existing record if the file previously existed
        $storageFile      = $mapper->accept($filename, $dest, $_SESSION['id']);
        $meta['hash']     = NodeFactory::STORE . ':' . $storageFile->getHash();
        $meta['filesize'] = $storageFile->getSize();

        $files[] = $meta;
    }
    catch (Schoolbox\File\Exception $e) {
        $files[] = array(
            'title' => $meta['title'],
            'size'  => 0,
            'type'  => 'unknown/binary',
            'err'   => $e->getMessage()
        );
    }

}

header('Content-Type: application/json');
die(json_encode($files));

