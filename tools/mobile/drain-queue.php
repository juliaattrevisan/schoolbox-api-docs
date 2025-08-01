#!/usr/bin/env php
<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

/**
 * Drains the mobile daemon's accumulated message queue.
 *
 * Until now, the mobile daemon's queue's messages were stored without a TTL
 * (therefore, indefinitely), even though they have no use once they have been
 * processed by the daemon.
 *
 * This script will find these leftover messages, and set a TTL on them.
 */

use Alaress\Async\Notification\Instance\Instance;
use Predis\ClientInterface;
use Predis\Collection\Iterator\Keyspace;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

require __DIR__ . '/../../conf/core/require.php';

$kernel = new CliTool\Kernel(
    new class extends CliTool\App
    {
        /**
         * The pattern to match upon when iterating Redis' keyspace.
         *
         * `%s` will be replaced with the current instance ID, or `*` if running
         * in multi-instance mode.
         */
        private const KEYSPACE_PATTERN = 'msg:%s:s:*';

        /**
         * @inheritdoc
         */
        public function run(): void
        {
            $logger = $this->getLogger();

            /** @var ClientInterface $redis */
            $redis    = $this->getDi()->get('redis.storage');
            $pattern  = sprintf(self::KEYSPACE_PATTERN, INSTANCE_ID);
            $logger->notice(sprintf('Scanning Redis storage instance keyspace: "%s"', $pattern));

            $keyspace = new Keyspace($redis, $pattern, 10_000);
            foreach ($keyspace as $key) {
                // First, attempt to decode the key:
                // if it's a message instance, set its TTL.
                $value             = $redis->get($key);
                $uncompressedValue = gzuncompress($value);
                if ($uncompressedValue === false) {
                    $logger->info(sprintf('Could not decompress key "%s": assuming it is uncompressed', $key));
                    $uncompressedValue = $value;
                }

                // Decode the value:
                // only care about Instance objects
                $decodedValue = unserialize($uncompressedValue, ['allowed_classes' => [Instance::class]]);
                if (!$decodedValue instanceof Instance) {
                    $logger->debug(sprintf('Key "%s" does not contain a "%s" instance: skipping', $key, Instance::class));
                } else {
                    $logger->debug(sprintf('Key "%s" contains a "%s" instance', $key, Instance::class));
                    $currentKeyTtl = $redis->ttl($key);
                    if ($currentKeyTtl !== -1) {
                        $logger->debug('.. key has a TTL: skipping');
                    } else {
                        $logger->debug('.. key has no TTL: setting TTL');
                        $messageTime   = $decodedValue->getTime();
                        $messageTtl    = $decodedValue->getTTL();
                        $newExpiryTime = $messageTime + $messageTtl;
                        $currentTime   = time();
                        $newKeyTtl     = $currentTime - $newExpiryTime;
                        $logger->debug(sprintf('.. message time:    %s', date(DATE_ATOM, $messageTime)));
                        $logger->debug(sprintf('.. message TTL:     %d', $messageTtl));
                        $logger->debug(sprintf('.. new expiry time: %s (message time + TTL)', date(DATE_ATOM, $newExpiryTime)));
                        $logger->debug(sprintf('.. current time:    %s', date(DATE_ATOM, $currentTime)));
                        $logger->debug(sprintf('.. new key TTL:     %d (time - new expiry time)', $newKeyTtl));
                        if ($newKeyTtl < 0) {
                            $logger->info(sprintf('Key "%s" TTL < 0: deleting immediately', $key));
                            if ($this->perform()) {
                                $redis->del($key);
                            }
                        } else {
                            $logger->info(sprintf('Key "%s" TTL >= 0: setting TTL', $key));
                            if ($this->perform()) {
                                $redis->expire($key, $newExpiryTime);
                            }
                        }
                    }
                }
            }
        }

        public function runAsRoot(): ?bool
        {
            return null;
        }
    },
    [CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::INFO],
    true,
);
$kernel->runApp();
