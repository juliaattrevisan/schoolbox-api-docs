<?php

use Schoolbox\File\ExtensionMapper;
use Schoolbox\File\Remote\GDriveFetcher;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\Resource\Browser\NodeFactory;
use Schoolbox\Resource\Storage;

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

if (empty($this) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    require_once __DIR__ . '/../../../../conf/core/require.php';
    $kernel = new \Schoolbox\Application\WebRequest\Kernel();
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
$grive   = new GDriveFetcher(
    ExtensionMapper::getInstance(),
    sys_get_temp_dir(),
    $proxy->curlInit(),
    $headers['Authorization'],
    (bool)$conf->get('googledrive_export_pdf')
);
$mapper  = Storage\Mapper::getInstance();
$files   = array();

foreach ($_POST['docs'] as $doc) {
    try {
        $meta = $grive->fetchMetaData($doc['id']);

        $filename = $grive->deriveFileName($meta);

        $dest = $grive->fetchToFile($doc['id']);
    } catch (DomainException $e) {

        /* - Some mysterious error - skip this file, leave a bootprint in sentry
         *
         * - Someone tried to access a restricted file, leave a bootprint in sentry
         *   because we can't inform the user via the ui atm
         */
        trigger_error('Could not upload google drive file: ' . $e->getMessage(), E_USER_WARNING);

        continue;
    } catch (Mapper\Exception $e) {

        /* noop: unknown file extension: silently disregard for now
         * because deriveFileName() couldn't figure out the extension
         */
        continue;
    }

    try {
        // this will return an existing record if the file previously existed
        $storageFile      = $mapper->accept($filename, $dest);
        $meta['hash']     = NodeFactory::STORE . ':' . $storageFile->getHash();
        $meta['filesize'] = $storageFile->getSize();

        /* Get correct mimetype for compatibility with attachzone rendering
         */
        $meta['mimeType'] = $grive->determineMimeIcon($meta);

        $files[] = $meta;
    }
    catch (Schoolbox\File\Exception $e) {

        /* TODO: Find out how to handle this "successful error"
         * TODO: UI doesn't understand this payload
         */
//        $files[] = array(
//            'title' => $meta['title'],
//            'size'  => 0,
//            'type'  => 'unknown/binary',
//            'err'   => $e->getMessage()
//        );
    }

}

header('Content-Type: application/json');
die(json_encode($files));

