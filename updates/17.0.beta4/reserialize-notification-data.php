<?php
/**
 * This script reads out all existing message payloads in Redis, json-decoding and then serializing
 * before inserting back in
 *
 * The data expectation changed from v16.5 to v17, so the payloads need to be migrated to the new format.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Alaress\Async\Notification\Instance\Instance;
use Aura\Di;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;

$flags = implode(' ', array_slice($argv, 1));
$opts = require __DIR__ . '/../../conf/core/require-cli.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Notification Data Reserlializer', [
    'verbose' => [ false, false ,false ], // always full volume
    'errors'  => E_ALL,
    'fields'  => array_keys($opts),
    'flags'   => $flags,
]);

const NO_TTL_SET = -1;
const TWO_WEEKS  = 1209600;

$kernel->run(function (Di\Container $di, LoopInterface $loop, Logger $log, array $opts) {

    /** @var ClientInterface $redis */
    $redis = $di->get('redis');

    $instanceId  = INSTANCE_ID;
    $payloadKeys = $redis->keys("msg:$instanceId:m:*");

    $log->info(sprintf('Found %d payload%s to reserialize', count($payloadKeys), count($payloadKeys) === 1 ? '' : 's'));

    foreach ($payloadKeys as $key) {
        // retrieve
        $payload = $redis->get($key);
        $expiry  = $redis->ttl($key);

        $log->info("Fetching '$key' (TTL is $expiry)");

        // inflate
        $uncompressed = gzuncompress($payload);

        try {
            // convert from json string to array (this might explode with ErrorException, or just return null)
            if (json_decode($uncompressed, true) === null) {
                throw new InvalidArgumentException('Failed to JSON decode');
            }

        } catch (Exception $e) {
            $log->error(sprintf('JSON decode failed, assuming payload is already converted (head looks like: "%s...")', substr($uncompressed, 0, 64)));
            continue;
        }

        $log->notice("Reserializing '$key'");

        // convert to Instance object
        $instance = Instance::jsonUnserialize($uncompressed);

        // serialize
        $serial = serialize($instance);

        // compress
        $compressed = gzcompress($serial, 9);

        // replace the payload and hope for the best
        $redis->set($key, $compressed, 'EX', $expiry === NO_TTL_SET ? TWO_WEEKS : $expiry);
    }

});