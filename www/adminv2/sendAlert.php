<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

// require SitePage context
if (empty($this)) {
    require_once __DIR__ . '/../../include/Bootstrap.php';
    SitePage::localRedir('/adminv2/home');
    exit;
}

/** @var SitePage $this */

// require POST method
if (empty($_POST)) {
    SitePage::localRedir('/adminv2/home');
    exit;
}

/** @var Schoolbox\User $user */
$user = $_SESSION['user'];

// require SuperUser access
if (!$user->isSuperuser()) {
    SitePage::localRedir('/adminv2/home');
    exit;
}

$userId = (int)$_POST['user'];
$body   = Scrub::text($_POST['body']);

$bob = new \Alaress\Async\Notification\Instance\Builder();
$bob->setTitle($body)
    ->setSeverity(\Monolog\Logger::INFO)
    ->setNamespace('ALERT')
    ->setHref('#')
    ->setIcon('/images/icon.php?icon=bolt&size=64')
    ->addTopic("notify.user.$userId")
    ->putMeta('ttl', 30)
    ->putMeta('title', 'Message from ' . $user->formatName());

$n = new \Schoolbox\Notif\Notif("instant.user.$userId.alert");

$n->log(\Monolog\Logger::INFO, json_encode($bob->build()->jsonSerialize()));

$this->backToTheFuture(1);
