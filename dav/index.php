<?php
/**
 * created by jetbrains phpstorm.
 * user: jleckie
 * date: 3/21/12
 * time: 9:56 pm
 */

require_once dirname(__FILE__) . '/../include/Bootstrap.php';

include dirname(__FILE__) . '/../include/Session.php';

$publicdir = new DAV_Folder('');

$server = new Sabre\DAV\Server($publicdir);
$plugin = new Sabre\DAV\Browser\Plugin();
$server->addPlugin($plugin);

$lockBackend = new Sabre\DAV\Locks\Backend\File(sys_get_temp_dir() . '/webdavlocks.dat');
$lockPlugin = new Sabre\DAV\Locks\Plugin($lockBackend);
$server->addPlugin($lockPlugin);

$config = new \Schoolbox\Config\ConfigMapper();
$authBackendFactory = new DAV_Auth_Factory($config);
$authPlugin = new Sabre\DAV\Auth\Plugin($authBackendFactory->build());
$server->addPlugin($authPlugin);

//$server->addPlugin(new DAV_ExceptionPlugin());

$server->setBaseUri('/');
$server->exec();
