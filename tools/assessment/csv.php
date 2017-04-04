<?php
/**
 * Export assessment data for Accellerus import
 *
 * @author    Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Aura\Di;
use Schoolbox\Learning\Assessment\Exporter\Exporter;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Assessment CSV');

$kernel->run(function(Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts) {
    $exporter = new Exporter($di->get('databaseInternal'));
    $exporter->generate(STDOUT);
});
