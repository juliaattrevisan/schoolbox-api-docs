<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Config\Loader;
use Schoolbox\External\Connector\Kamar\Base\Endpoint\Receiver;
use Schoolbox\External\Connector\Kamar\Base\Endpoint\Setup;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

header('Content-type: application/xml');

/* Consume data from stream...
 */
$data = file_get_contents('php://input');

/* ...and place the results in this special folder
 * which will be created if it doesn't already exist
 */
$cacheDir = (new Setup(null, Loader::getInstance()->get('stream-dir')))->getCacheDir();

/* Testing independently of a Kamar push
 *
 * You can test Receiver's processing by loading from a local xml
 * $data = file_get_contents('/vagrant/data/cache/stream/temp.xml');
 *
 * Or you can do it via the cli:
 * clear;curl --data-binary "@/vagrant/tools/synchronise/temp.xml" -k https://vagrant.dev.schoolbox.cloud/api/kamar.php
 */
(new Receiver($cacheDir))->receive((string)$data);

exit();
