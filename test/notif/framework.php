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
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$opts = getopt('v', ['env:', 'logdir:', 'testcase:']);

$kernel = new Schoolbox\Application\Daemon\Kernel('Notification Test Framework', [
    'verbose' => isset($opts['v']) ? $opts['v'] : [ false, false, false ],
    'errors'  => E_ALL,
    'flags'   => $opts
]);

$kernel->run(function($di, $loop, $log, $opts) {
    /**
     * @var Di\Container $di
     * @var LoopInterface $loop
     * @var LoggerInterface $log
     * @var array $opts
     */

    $framework = new Framework($di, $loop, $log, $opts['flags']);

    $framework->ensureDirectories();
    $framework->freezeHell();

    try {
        // find out the name of the test case, depending on whether or not it was supplied as a flag value
        if (empty($opts['flags']['testcase'])) {
            $testClass = $framework->promptForTest();
        } else {
            $testClass = $opts['flags']['testcase'];
        }

        // construct the test case
        $testCase = $framework->buildTestCase($testClass);

        // punch it.
        $framework->launch($testCase);

    } catch (UnderflowException $e) {
        $log->critical($e->getMessage(), [ $e ]);
        return;

    } catch (OutOfBoundsException $e) {
        $log->error($e->getMessage(), [ $e ]);
        return;

    } catch (InvalidArgumentException $e) {
        $log->error($e->getMessage(), [ $e ]);
        return;
    }

});
