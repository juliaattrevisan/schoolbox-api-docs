#!/usr/bin/php
<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(__DIR__ . '/../../library/Bootstrap.php');

if($argc != 3) {
	die("Usage: {$argv[0]} <notifierClassname> <method>\n");
}

$class = $argv[1];
$method = $argv[2];

if(!class_exists($class)) {
	die("! Class '$class' doesn't exist\n");
}

$n = new $class(\Schoolbox\Config\ConfigMapper::getInstance());

if(!method_exists($n, $method)) {
	die("! Method '$method' doesn't exist in '$class'\n");
}

$callback = array($n, $method);

if(!is_callable($callback)) {
	die("! Cannot call '$class::$method' from this context\n");
}

/** @var \Schoolbox\Notif\Message\Messenger $msgr */
$msgr = $n->$method(
	\Schoolbox\Blog\CommentMapper::getInstance()->findAll()->first(),
	\Schoolbox\UserMapper::getInstance()->findById('151')
);

echo $msgr->isEmpty() ? "No messages returned\n" : "Messages returned\n";
