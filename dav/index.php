<?php
/**
 * created by jetbrains phpstorm.
 * user: jleckie
 * date: 3/21/12
 * time: 9:56 pm
 */

use Alaress\Application\Kernel;
use Schoolbox\WebDav\FileServer\Folder as WebDavFolder;

/** @var Kernel $kernel */
$kernel = require_once __DIR__ . '/../library/Bootstrap.php';

/** @var WebDavFolder $publicdir */
$publicdir = $kernel->get('webdav:server');

$server = new Sabre\DAV\Server($publicdir);
$plugin = new Sabre\DAV\Browser\Plugin();
$server->addPlugin($plugin);

$lockBackend = new Sabre\DAV\Locks\Backend\File(sys_get_temp_dir() . '/webdavlocks.dat');
$lockPlugin = new Sabre\DAV\Locks\Plugin($lockBackend);
$server->addPlugin($lockPlugin);

$config = \Schoolbox\Config\ConfigMapper::getInstance();
$authBackendFactory = new DAV_Auth_Factory($config);
$authPlugin = new Sabre\DAV\Auth\Plugin($authBackendFactory->build());
$server->addPlugin($authPlugin);

//$server->addPlugin(new DAV_ExceptionPlugin());

$server->setBaseUri('/');
$server->exec();
