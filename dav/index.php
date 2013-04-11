<?php
/**
 * created by jetbrains phpstorm.
 * user: jleckie
 * date: 3/21/12
 * time: 9:56 pm
 */

require_once dirname(__FILE__) . '/../include/Bootstrap.php';

include dirname(__FILE__) . '/../include/Session.php';


/**
 * Dumb exception plugin which puts a message in temp when an exception gets
 * thrown.
 *
 * Use this when trying to debug issues with non-web clients.
 */
class ExceptionPlugin extends Sabre\DAV\ServerPlugin {
	protected $server;
	public function initialize(Sabre\DAV\Server $server) {
		$this->server = $server;
		$this->server->subscribeEvent('exception',array($this,'onException'),10);
	}
	public function onException(Exception $exception) {
		$temp_file = tempnam(sys_get_temp_dir(), 'exc');
		file_put_contents($temp_file, $exception->getMessage());
		return true;
	}
}


$publicdir = new DAV_Folder('');

$server = new Sabre\DAV\Server($publicdir);
$plugin = new Sabre\DAV\Browser\Plugin();
$server->addPlugin($plugin);

$lockBackend = new Sabre\DAV\Locks\Backend\File(sys_get_temp_dir() . '/webdavlocks.dat');
$lockPlugin = new Sabre\DAV\Locks\Plugin($lockBackend);
$server->addPlugin($lockPlugin);

$authBackend = new DAV_Digest();
$authPlugin = new Sabre\DAV\Auth\Plugin($authBackend, Cfg::get('site_location'));
$server->addPlugin($authPlugin);

//$server->addPlugin(new ExceptionPlugin());

$server->setBaseUri('/');
$server->exec();
