#!/usr/bin/php
<?php
/**
 *
 * Search for messages that match the given pattern(s) and blast from existence.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\Config\ConfigMapper;

if (posix_getuid() !== 0) {
    die("Make me a sandwich!\n");
}

$opts = getopt('r');

$stdin = fopen('php://stdin', 'r');


$regexMode = isset($opts['r']);

$type = $regexMode ? 'pattern (regex' : '(string';

echo "\nSurgical Removal of Messages\n\n",
     " ** WARNING **\n",
     "This script will destroy messages that match the given parameters.\n",
     "There is no 'safe mode'!\n\n",
     "All arguments are optional, but they cannot all be blank.\n";

if (!$regexMode) {
    echo "(If you need regex, use the -r flag)\n";
}

echo "\n";

$printf = "%-28s: ";

$params = array(
    'body'  => array("Bodytext $type)",             function ($v) { return $v; }),
    'href'  => array("Href $type)",                 function ($v) { return $v; }),
    'start' => array("No earlier than (strtotime)", function ($v) { return strtotime($v); }),
    'end'   => array("No later than (strtotime)",   function ($v) { return strtotime($v); })
);

$args = array();

foreach ($params as $key => $prompt) {
    printf($printf, $prompt[0]);
    // remove all unprintable characters that may have been injected
    $input = trim(preg_replace('/[[:cntrl:]]+/', '', fgets($stdin)));
    if ($input) {
        $args[$key] = $prompt[1]($input);
    }
}

echo "\n";

if (!count($args)) {
    die("No search parameters specified!\n");
}

echo "Starting search & destroy...\n";

// ok, setup done, ready to do this
require_once __DIR__ . '/../../include/Bootstrap.php';

checkRedis($args, $regexMode);
checkAmqp($args, $regexMode);



/**
 * @param array $args
 * @param bool $regexMode
 */
function checkRedis($args, $regexMode)
{
    $redis = \Alaress\Redis::getClientInstance();

    // get a list of all available redis messages o.O;
    $redisList = $redis->executeCommand($redis->createCommand('keys', array('msg:*:m:*')));

    echo "Checking redis data: ";
    $acks = 0;

    foreach ($redisList as $key) {
        $compressed = $redis->executeCommand($redis->createCommand('get', array($key)));
        $uncompress = gzuncompress($compressed);

        $msg = json_decode($uncompress, true);

        if (checkData($args, $regexMode, $msg)) {
            $redis->executeCommand($redis->createCommand('del', array($key)));
            ++$acks;
        }
    }

    echo "$acks messages matched and removed\n";
}


/**
 * @param array $args
 * @param bool  $regexMode
 */
function checkAmqp($args, $regexMode)
{
    // stop the socket daemon
    `service schoolbox-socketd stop`;

    $conf = ConfigMapper::getInstance();

    // get a list of all available amqp queues
    $ctrl = new \Alaress\Async\AMQP\Bunny\BunnyControl([
        'host'     => $conf->get('amqp_host'),
        'port'     => $conf->get('amqp_admin_port'),
        'username' => $conf->get('amqp_user'),
        'password' => $conf->get('amqp_pass'),
        'vhost'    => $conf->get('amqp_vhost')
    ]);
    $amqp = \Schoolbox\Notif\MQ\Connector::getInstance();

    $queueList = $ctrl->listQueues(array('name'));

    foreach ($queueList as $queue) {
        $queueName = $queue['name'];

        // only operate on unread queues
        if (!strpos($queueName, 'unread')) {
            continue;
        }

        printf("%-32s| ", $queueName);

        $nacks = array();
        $acks  = array();

        // iterate messages
        while ($m = $amqp->readFromQueue($queueName)) {

            $data = json_decode($m->body, true);
            $msg  = json_decode($data['message'], true);

            if (checkData($args, $regexMode, $msg)) {
                $acks[] = $m;
            } else {
                $nacks[] = $m;
            }
        }

        printf("%4d ACK | %4d NACK\n", count($acks), count($nacks));

        $amqp->acceptMessages($acks);
        $amqp->rejectMessages($nacks);
    }
}


/**
 * @param array $args
 * @param bool  $regexMode
 * @param array $msg
 * @return bool
 */
function checkData($args, $regexMode, $msg)
{
    $success = false;

    if (isset($args['body'])) {
        $success = $success || checkStr($regexMode, $args['body'], $msg['title']['text']);
    }

    if (isset($args['href'])) {
        $success = $success || checkStr($regexMode, $args['href'], $msg['href']);
    }

    if (isset($args['start'])) {
        $success = $success && checkTime(true, $args['start'], $msg['time']);
    }

    if (isset($args['end'])) {
        $success = $success && checkTime(false, $args['start'], $msg['time']);
    }

    return $success;
}


/**
 * @param bool   $regexMode
 * @param string $test
 * @param string $text
 * @return bool
 */
function checkStr($regexMode, $test, $text)
{
    if ($regexMode) {
        return !!preg_match("`$test`", $text);
    } else {
        return strpos($text, $test) !== false;
    }
}


/**
 * @param bool $before
 * @param int  $test
 * @param int  $time
 * @return bool
 */
function checkTime($before, $test, $time)
{
    if ($before) {
        return $test <= $time;
    } else {
        return $test >= $time;
    }
}
