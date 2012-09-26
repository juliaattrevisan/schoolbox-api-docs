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

    $resource = Storage_ResourceMapper::getInstance()->getByHash($_GET['hash']);

    if ($resource != false) {

        if ($_GET['save']) {
            $attach = "attachment;";
        } else {
            $attach = "";
        }

        //send headers
        if ($resource->mime) {
            header("Content-Type: {$resource->mime}");
        } else {
            header("Content-Type: application/octet-stream");
        }

        header("Pragma: ");
        header("Cache-control: ");
        header("Content-Disposition: $attach filename=\"" . $resource->filename . "\"");
        header("Content-Length: $resource->filesize");
        readfile($resource->getFileLocation());
    }

}