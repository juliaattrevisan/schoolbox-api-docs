#!/usr/bin/php
<?php

/**
 * Search for messages that match the given pattern(s) and blast from existence.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Async\Notification\Notification;
use Alaress\Async\Notification\NotificationBundle;
use Schoolbox\Application\CliTool;
use Schoolbox\Realtime\Notification\Decorator;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App{

    /** @var string|null $instanceId */
    private ?string $instanceId;

    /** @inheritDoc */
    public function runAsRoot(): ?bool
    {
        return null;
    }

    /** @inheritDoc */
    public function getShortOpts(): array
    {
        return ['h','r'];
    }

    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return ['help', 'title:', 'href:', 'start:', 'end:', 'instance-id:'];
    }

    /** @inheritDoc */
    public function run(): void
    {

        if ($this->getOpt('h') || $this->getOpt('help')) {
            $this->printHelp();
            exit(CliTool\Kernel::EXIT_STATUS_OK);
        }

        $this->instanceId = $this->getOpt('instance-id:');
        if (empty($this->instanceId)) {
            $this->instanceId = INSTANCE_ID;
        }


        $regexMode = $this->getOpt('r');
        $args = [];

        if ($this->getOpt('title:')) {
            $args['title'] = $this->getOpt('title:');
        }

        if ($this->getOpt('href:')) {
            $args['href'] = $this->getOpt('href:');
        }

        if ($this->getOpt('start:')) {
            $args['start'] = strtotime($this->getOpt('start:'));
        }

        if ($this->getOpt('end:')) {
            $args['end'] = strtotime($this->getOpt('end:'));
        }

        if (!count($args)) {
            $this->getLogger()->error("Insufficient search parameters specified! Try -h for more information and help.");
            exit(1);
        }

        $this->checkRedis($args, $regexMode);
    }


    /**
     * @param array $args
     * @param bool $regexMode
     */
    private function checkRedis($args, $regexMode): void
    {
        $redis = \Alaress\Redis::getClientInstance();
        $decorator = new Decorator();

        if (!$redis) {
            $this->getLogger()->critical("Redis instance not available");
            exit(1);
        }

        // get a list of all available redis messages o.O;
        $redisList = $redis->executeCommand($redis->createCommand('keys', array('msg:' . $this->instanceId . ':m:*')));

        $this->getLogger()->info("Checking redis data...");
        $acks = 0;

        foreach ($redisList as $key) {
            $compressed = $redis->executeCommand($redis->createCommand('get', array($key)));
            $uncompress = gzuncompress($compressed);

            $msg = unserialize($uncompress);
            $msgArray = $msg->jsonSerialize();
            $notification = new Notification([ $msg ]);
            $msgArray['title']['plain'] = $decorator->renderTitleAsPlaintext($notification);

            if ($this->checkData($args, $regexMode, $msgArray)) {
                $this->getLogger()->info(sprintf(
                    'Found %s %s: %s (%s)',
                    $msgArray['namespace'],
                    $key,
                    $msgArray['title']['plain'],
                    $msgArray['href']
                ));

                if ($this->perform()) {
                    $redis->executeCommand($redis->createCommand('del', [$key]));
                }
                ++$acks;
            }
        }

        if ($acks == 0) {
            $this->getLogger()->info("No matching notifications found!");
        } elseif ($this->perform()) {
            $this->getLogger()->info("$acks messages matched and removed");
        } else {
            $this->getLogger()->info("$acks messages matched, no action taken run with -p to delete");
        }
    }

    /**
     * @param array $args
     * @param bool  $regexMode
     * @param array $msg
     * @return bool
     */
    private function checkData(array $args, bool $regexMode, array $msg): bool
    {
        $success = true;

        if (isset($args['title'])) {
            $success = $this->checkStr($regexMode, $args['title'], $msg['title']['plain']);
        }

        if (isset($args['href'])) {
            $success = $success && $this->checkStr($regexMode, $args['href'], $msg['href']);
        }

        if (isset($args['start'])) {
            $success = $success && $this->checkTime(true, $args['start'], $msg['time']);
        }

        if (isset($args['end'])) {
            $success = $success && $this->checkTime(false, $args['end'], $msg['time']);
        }

        return $success;
    }


    /**
     * @param bool   $regexMode
     * @param string $test
     * @param string $text
     * @return bool
     */
    private function checkStr(bool $regexMode, string $test, string $text): bool
    {
        if ($regexMode) {
            return (bool)preg_match("`$test`", $text);
        }
        return strpos($text, $test) !== false;
    }


    /**
     * @param bool $before
     * @param int  $test
     * @param int  $time
     * @return bool
     */
    private function checkTime(bool $before, int $test, int $time): bool
    {
        if ($before) {
            return $test <= $time;
        }
        return $test >= $time;
    }

    /**
     * Print a help message of available options
     */
    private function printHelp(): void
    {
        echo "
This script is used to remove notifications from the system PERMANENTLY. It will search the entire notification message
list and delete any records it matches. To avoid unnecessary damage specify as many parameters as possible to limit
the search space. Please ensure you do a dry run before you run the script as this is unrecoverable.

Usage: scalpel.php [--title='posted by'] [--href='/news/483'] [--start='2021-02-02 10:00am'] [--end='2021-02-02 14:00'] [-p]

Options:
    -h, --help
        Print help message and exit.
    --instance-id
        If set, match notifications from this instance.
        If not set, falls back to:
        * the INSTANCE_ID environment variable
        * the first instance defined for this system, if INSTANCE_ID is not set
    --title
        If set, delete notifications that contain this in the title text of the notification
    --href
        If set, delete notifications that have this URL
    --start
        Only notifications delivered after this date will be matched
    --end
        Only notifications delivered before this time will be matched
    -r
        If present, title and href will be treated as preg expression strings and matches done as regexps
    -p, --perform
        Without this flag, tool will not delete messages
";
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
