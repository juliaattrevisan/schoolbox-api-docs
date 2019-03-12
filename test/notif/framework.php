<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Alaress\Async\Test\Framework;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

// require root
if (posix_getuid() !== 0) {
    die("Make me a sandwich.\n");
}

// do not allow this to be cached
unset($_SERVER['REQUEST_TIME']);

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Notification Test Framework',
    '',
    ['env:', 'logdir:', 'testcase:'],
    Monolog\Logger::DEBUG,
    E_ALL
);

$kernel->run(function(Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts) {

    $framework = new Framework($di, $loop, $log, $opts['env'] ?? null, $opts['logdir'] ?? null);

    $framework->ensureDirectories();
    $framework->freezeHell();

    try {
        // find out the name of the test case, depending on whether or not it was supplied as a flag value
        if (empty($opts['testcase'])) {
            $testClass = $framework->promptForTest();
        } else {
            $testClass = $opts['testcase'];
        }

        // construct the test case
        $testCase = $framework->buildTestCase($testClass);

        // punch it.
        $framework->launch($testCase);

    } catch (UnderflowException $e) {
        $log->critical($e->getMessage(), [ 'exception'=>$e ]);
        return;

    } catch (OutOfBoundsException $e) {
        $log->error($e->getMessage(), [ 'exception'=>$e ]);
        return;

    } catch (InvalidArgumentException $e) {
        $log->error($e->getMessage(), [ 'exception'=>$e ]);
        return;
    }

});
