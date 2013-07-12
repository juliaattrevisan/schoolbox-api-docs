<?php
/**
 * Returns objects from the storage engine as plain files
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2012 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */


require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

if (isset($_GET['hash']) && $_GET['hash']) {
    $page = new SitePage;

    /* @var $resource Schoolbox\File */
    $resource = Schoolbox\FileMapper::getInstance()->findOneByColumn('hash', $_GET['hash']);

    if ($resource != false) {

        if ($_GET['save']) {
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

        header("Pragma: ");
        header("Cache-control: ");
        header("Content-Disposition: $attach filename=\"" . $resource->getFilename() . "\"");
        header("Content-Length: ".$resource->getFilesize());
        readfile($resource->getFileLocation());
    }

}