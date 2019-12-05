#!/usr/bin/php
<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Html\ToText as Html2Text;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Realtime\Notification\Decorator;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\UserMapper;

if (count($argv) < 4) {
    die("Usage: {$argv[0]} <NotifierClass> <ObjectID> <MethodName>\n");
}

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\Daemon\Kernel(__FILE__);

list (, $classname, $id, $method) = $argv;

// use reflection to attempt a safe use of a user-defined class

if (!class_exists($classname)) {
    die("Class $classname does not exist\n");
}

$rc = new ReflectionClass($classname);

if (!$rc->isSubclassOf(AbstractModuleNotifier::class)) {
    die("Class $classname is not a Notifier");
}

if (!$rc->hasMethod($method)) {
    die ("Method $classname::$method does not exist\n");
}

$rm = $rc->getMethod($method);

if (!$rm->isPublic()) {
    die ("Method $classname::$method is not accessible\n");
}

/** @var AbstractModuleNotifier $notifier */
$notifier = new $classname(ConfigLoader::getInstance());

try {
    $object = $notifier->getMapper()->findById($id);
    $user   = UserMapper::getInstance()->findOneByColumn('username', 'alaress');
} catch (Schoolbox\Mapper\Exception $e) {
    die("Could not instantiate object");
}

printf("---\nInvoking %s::%s(%s #%d, User #%d)\n---\n", $classname, $method, $notifier->getMapper()->getModel(), $object->getId(), $user->getId());

/** @var Envelope $envelope */
$envelope = $notifier->$method($object, $user);

printf("Message count: %d\n", $envelope->count());

foreach ($envelope->getAll() as $instance) {
    $dec = new Decorator();

    $dec->appendInstance($instance);

    $pageArray = $dec->renderAsSitePageArray();
    $pageArray = reset($pageArray);

    $html2text = new Html2Text($pageArray['title']);

    printf(" ** [%s] '%s'\n", $pageArray['href'], str_replace("\n","",$html2text->getText()));
}

