#!/usr/bin/php
<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Monolog\Level;
use Monolog\Logger;
use Schoolbox\Config\Loader as ConfigLoader;

$helptext = <<<EOF
Usage: $argv[0] [--icon=<icon>] [--href=<href>] [--severity=<level>] <topic> <bodytext>
Send a message through the notifier
The topic should be a valid AMQP topic

  -i, --icon=<icon>         the icon provided in the corner of the message; absolute,
                            or relative to the installation base
  -h, --href=<href>         the href to which the message points; absolute, or relative
                            to the installation base
  -s, --severity=<level>    the severity at which to post this message; by default,
                            messages are sent at INFO(100)

EOF;



if ($argc == 2 && $argv[1] == '--help') {
    die($helptext);
}

// parse arguments for correctness
if ($argc < 3) {
    die($helptext);
}

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Schoolbox\Application\Daemon\Kernel(__FILE__);

$levels = array_combine(Level::NAMES, Level::VALUES);

$map = array(
    'i' => 'icon',
    'h' => 'href',
    's' => 'severity'
);

$values = array();

$opts = getopt("i:h:s:", array(
    'icon::',
    'href::',
    'severity::',
));

foreach ($map as $short => $long) {
    $values[$long] = isset($opts[$short]) ? $opts[$short] : (isset($opts[$long]) ? $opts[$long] : '#');
}

// if severity valid, cast to int, otherwise 100.
if (isset($levels[strtoupper($values['severity'])])) {
    $values['severity'] = $levels[strtoupper($values['severity'])];
} else {
    $values['severity'] = 100;
}

$body = array_pop($argv);
$topic = array_pop($argv);

$builder = new \Alaress\Async\Notification\Instance\Builder();

$instance = $builder
    ->setTitle($body)
    ->setIcon($values['icon'])
    ->setHref($values['href'])
    ->setTimestamp(time())
    ->setNamespace('debug')
    ->setSeverity($values['severity'])
    ->addTopic($topic)
    ->build();

//$factory = new \Schoolbox\Notif\Message\Factory();
//$factory
//  ->setTimestamp(time())
//  ->setIcon($values['icon'])
//  ->setHref($values['href'])
//  ->setMsgBody($body);
//
//$msg = $factory->build();
//
//$msg->getInstance()
//  ->setSeverity($values['severity'])
//  ->setTopics((array)$topic);

$n = \Schoolbox\Notif\Notifactory::create($topic, ConfigLoader::getInstance());

$n->log($values['severity'], "$instance");

echo "Message '$body' sent via '$topic' at severity {$values['severity']}\n";
