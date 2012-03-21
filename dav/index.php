<?php
/**
 * created by jetbrains phpstorm.
 * user: jleckie
 * date: 3/21/12
 * time: 9:56 pm
 */

require_once dirname(__FILE__) . '/../include/Bootstrap.php';

include dirname(__FILE__) . '/../include/Sabre/autoload.php';
include dirname(__FILE__) . '/../include/Session.php';
include dirname(__FILE__) . '/../library/DAV_Folder.php';
include dirname(__FILE__) . '/../library/DAV_File.php';

$session = new Session();
$session->create('alaress');

$publicdir = new DAV_Folder('');

$server = new Sabre_DAV_Server($publicdir);
$plugin = new Sabre_DAV_Browser_Plugin();
$server->addPlugin($plugin);

$lockBackend = new Sabre_DAV_Locks_Backend_File(sys_get_temp_dir().'/webdavlocks.dat');
$lockPlugin = new Sabre_DAV_Locks_Plugin($lockBackend);
$server->addPlugin($lockPlugin);
$server->setBaseUri('/');
$server->exec();