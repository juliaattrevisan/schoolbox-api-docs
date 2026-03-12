#!/usr/bin/env php
<?php

/**
 * Search for messages that match the given pattern(s) and blast from existence.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Notification;
use Alaress\Redis;
use Schoolbox\Application\CliTool;
use Schoolbox\Realtime\Notification\Decorator;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\BaseApp
{
    private ?string $instanceId;

    /** @inheritDoc */
    public function runAsRoot(): ?bool
    {
        return null;
    }

    /** @inheritdoc */
    protected function getName(): string
    {
        return 'MQ Scalpel';
    }

    /** @inheritdoc */
    protected function getDescription(): string
    {
        return <<<DESC
            Searches for Redis notification messages matching the given pattern(s) and permanently deletes them.
            Specify as many parameters as possible to limit the search space.
            Always do a dry run before performing deletions, as this operation is unrecoverable.
            DESC;
    }

    /** @inheritdoc */
    protected function setOptions(): CliTool\BaseApp
    {
        $this->optionCollection->add('r', 'Use regex matching for --title and --href filters')->defaultValue(false);
        $this->optionCollection->add('instance-id:', 'Match notifications from this instance; defaults to INSTANCE_ID');
        $this->optionCollection->add('title:', 'Delete notifications containing this in the title text');
        $this->optionCollection->add('topic:', 'Delete notifications containing this in their topic');
        $this->optionCollection->add('action:', 'Delete notifications with this action (e.g. send, retract-single, retract-all)');
        $this->optionCollection->add('href:', 'Delete notifications with this URL');
        $this->optionCollection->add('start:', 'Only match notifications delivered after this date');
        $this->optionCollection->add('end:', 'Only match notifications delivered before this date');
        $this->optionCollection->add('output-hash', 'Also output each matched message\'s hash to stdout (pipeable to read-msgdata.php)');
        return $this;
    }

    /** @inheritdoc */
    protected function hasPerformMode(): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function run(): void
    {
        $this->instanceId = $this->getOpt('instance-id:');
        if (empty($this->instanceId)) {
            $this->instanceId = INSTANCE_ID;
        }


        $regexMode = $this->getOpt('r');
        $args = [];

        if ($this->getOpt('title:')) {
            $args['title'] = $this->getOpt('title:');
        }

        if ($this->getOpt('topic:')) {
            $args['topic'] = $this->getOpt('topic:');
        }

        if ($this->getOpt('action:')) {
            $args['action'] = $this->getOpt('action:');
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
        $outputHash = $this->getOpt('output-hash');
        $redis = Redis::getStorageClientInstance();
        $decorator = new Decorator();

        if (!$redis) {
            $this->getLogger()->critical("Redis instance not available");
            exit(1);
        }

        // get a list of all available redis messages o.O;
        $redisList = $redis->keys('msg:' . $this->instanceId . ':m:*');

        $this->getLogger()->info("Checking redis data...");
        $acks = 0;

        foreach ($redisList as $key) {
            $compressed = $redis->get($key);
            $uncompress = gzuncompress($compressed);
            $msg        = unserialize($uncompress, ['allowed_classes' => Instance::ALLOWED_UNSERIALIZE_CLASSES]);
            try {
                $notification = new Notification([ $msg ]);
            } catch (Error $e) {
                $this->getLogger()->warning(
                    sprintf(
                        'Could not decode %s: use cleanup-poison-msgs.php to clean up (message: %s)',
                        $key,
                        $e->getMessage(),
                    ),
                );
                continue;
            }

            $msgArray = $msg->jsonSerialize();
            $msgArray['title']['plain'] = $decorator->renderTitleAsPlaintext($notification);

            if ($this->checkData($args, $regexMode, $msgArray)) {
                if ($outputHash) {
                    $keyParts = explode(':', $key);
                    printf("%s\n", implode(':', array_slice($keyParts, -2)));
                }
                $this->getLogger()->info(sprintf(
                    'Found %s %s: %s (%s)',
                    $msgArray['namespace'],
                    $key,
                    $msgArray['title']['plain'],
                    $msgArray['href']
                ));

                if ($this->perform()) {
                    $redis->del($key);
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

        if (isset($args['topic'])) {
            $topicSuccess = false;
            foreach ($msg['topics'] as $topic) {
                if ($this->checkStr($regexMode, $args['topic'], $topic)) {
                    $topicSuccess = true;
                    break;
                }
            }
            $success = $topicSuccess;
        }

        if (isset($args['action'])) {
            $success = $this->checkStr($regexMode, $args['action'], $msg['action']);
        }

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
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
