<?php
/**
 * This script performs a mass migration of the given $patterns from brokenFS to OTFS
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Resource;
use Schoolbox\Resource\Storage;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');
$kernel = new Schoolbox\Application\Daemon\Kernel('Returns Migration', [
    'verbose' => [ 'v', 'v', 'v' ]
]);

$kernel->run(function ($di, $loop, $log, $opts) {
    /**
     * @var Di\Container    $di
     * @var LoopInterface   $loop
     * @var LoggerInterface $log
     * @var array           $opts
     */

    $migrator = new Storage\Migrator($log);

    $migrator->migrate(new SubmissionBox\ReturnedWork\Migration());

});



