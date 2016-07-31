<?php
/**
 * READ JIRA TICKET SA-477 <https://schoolbox.atlassian.net/browse/SA-477> BEFORE ATTEMPTING TO DO ANYTHING WITH THIS SCRIPT
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */


use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;


require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Rubric Course Realignment', array(
    // force full verbosity
    'verbose' => [ false, false, false ],
    'errors'  => E_ALL,
));

$kernel->run(function($di, $loop, $log, $opts) {
    /**
     * @var Di\Container    $di
     * @var LoopInterface   $loop
     * @var LoggerInterface $log
     * @var array           $opts
     */

    if ($di->get('config')->get('site_location') !== 'seda') {
        $log->info("Rubric course alignment not relevant to this instance: terminating.");
        // exit zero because this isn't an error
        exit;
    }

    // report the database name
    $log->notice("Operating on database '" . SQL_db . "'");

    $log->alert("Initialising rubric course realignment");

});
