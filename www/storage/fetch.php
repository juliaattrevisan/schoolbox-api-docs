<?php
/**
 * Returns objects from the storage engine as plain files
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2012 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */


require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\BasicRequest');
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

try {
    if (isset($_GET['hash']) && $_GET['hash']) {
        $page = new SitePage;

        /* @var $resource Schoolbox\Resource\Storage\Model */
        $resource = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($_GET['hash']);

        if (!$resource->getMimetype()->getInline() || $_GET['save']) {
            $attach = "attachment;";
        } else {
            $attach = "";
        }

        //send headers
        if ($resource->getMime()) {
            header("Content-Type: {$resource->getMime()}");
        } else {
            header("Content-Type: application/octet-stream");
        }

        $basename = $resource->getBasename();

        header("Pragma: ");
        header("Cache-control: private");
        header("Content-Ranges: none");
        header("Content-Disposition: $attach filename=\"$basename\"");
        header("Content-Length: ".$resource->getSize());
        fpassthru($resource->readStream());
        exit;
    }
}
catch (Exception $ex) {}
//
// File not found error
error(41);
