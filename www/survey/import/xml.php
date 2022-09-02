<?php

/**
 * Survey import. Uses handlers to import their question types from Moodle format.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Storage\FilesystemProxy;

SitePage::extendExecutionTime();

/** @var SitePage $this */
if (!is_numeric($this->arg[0])) {
    error(ErrorMapper::ERROR_SUBJECT_DELETED);
}

/** @var FilesystemProxy $filesystemProxy */
$filesystemProxy = $this->getDi()->get('filesystem.storageProxy');

$collector = (new CollectorFactory())->build($_POST['importXml'] ?? []);
try {
    $file = $collector->getFirst();
    $data = $filesystemProxy->read($file);
} catch (\Schoolbox\Resource\Exception $e) {
    error(ErrorMapper::ERROR_IMPORT_FILE_NOT_FOUND);
}

$import = new Survey_Import_Xml($this->arg[0], new Survey_Main(), new Survey_Section(), new Survey_Factory(), $data, $this);
$import->import();

$route = "/survey/modify/{$import->getAssessId()}" . Redirect::queryString("/survey/summary/{$import->getAssessId()}");

SitePage::localRedir($route);
