#!/usr/bin/php
<?php
/**
 * This script searches for all docs with status of 'error' and error_message of 'Conversion failed',
 * and puts them back into the queue for re-uploading.
 *
 * This *must* be deployed in a later release that the code-fix in SA-6354 to guarantee that this script
 * does not execute while we have old-format documents still asynchronously processing in Kami.
 *
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\Jitter\Jitter;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon\App;
use Schoolbox\Learning\InlineGrader\Kami;
use Alaress\Random;
use Schoolbox\MapperFactory;

$opts = require __DIR__ . '/../../conf/core/require-cli.php';

$kernel = new Schoolbox\Application\Daemon\Kernel("'Re-queue kami conversion errors'", [
    'verbose' => $opts['v'] ?? [1, 2, 3],
    'errors'  => E_ALL,
]);


$reQueue = new class() extends App
{

    /**
     * Quasi-constructor and execution entry-point
     *
     * @param Di\Container    $di
     * @param LoopInterface   $loop
     * @param LoggerInterface $log
     * @param array           $opts
     * @return void
     * @throws \InvalidArgumentException
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function run(Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts): void
    {
        /**
         * @var MapperFactory  $mapperFactory
         * @var Kami\Connector $kamiConnector
         * @var Kami\Mapper    $kamiMapper
         */
        $mapperFactory = $di->get('mapperFactory');
        $kamiConnector = $di->get('kamiConnector');
        $kamiMapper    = $mapperFactory->get(Kami\Mapper::class);
        $jitter        = new Jitter(new Random\FloatGenerator());

        try {
            // Ensure we are as up-to-date with Kami as possible
            $statusChecker = new Kami\StatusUpdater($kamiConnector, $kamiMapper, $jitter, $log);
            $statusChecker->checkProcessingDocumentsStatus(false, Kami\StatusUpdater::DEFAULT_MAX_SKIPS);
            $log->info('');

            // Re-queue any docs that are in 'error' because 'Conversion failed'
            $requeueErrors = new Kami\ReQueueErrors($kamiConnector, $kamiMapper, $jitter, $log);
            $requeueErrors->deleteAndReQueue(false, Kami\ReQueueErrors::DEFAULT_MAX_SKIPS, 'Conversion failed');
        } catch (Throwable $e) {
            $log->error($e->getMessage(), ['exception'=>$e]);
        }
    }
};

try {
    $kernel->run($reQueue);
    exit(0);
} catch (Throwable $e) {
    exit(2);
}
