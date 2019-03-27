#!/usr/bin/php
<?php
/**
 * Test data generator for comms comments.
 *
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon;
use Schoolbox\Discussion\Test\DataGenerator;


require_once __DIR__ . '/../../../conf/core/require.php';

$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Comment Test Data Generator',
    [
        Daemon\Kernel::OPTION_LEVEL         => Monolog\Logger::DEBUG,
        Daemon\Kernel::OPTION_ERRORS        => E_ALL,
        Daemon\Kernel::OPTION_APP_LONG_OPTS => ['seed:'],
    ]
);


// "Are you sure?"
echo "This script will soft-delete any news article comments which exist.\n";
$answer = '';
while (!in_array($answer, ['y', 'n'])) {
    $answer = strtolower(readline("Do you still wish to run it? [y/n] "));
}

if ($answer === 'n') {
    exit;
}


/** @noinspection PhpUnhandledExceptionInspection */
$kernel->run(new class extends Daemon\App {
    /**
     * Generate the test data.
     *
     * @param Di\Container  $di
     * @param LoopInterface $loop
     * @param Logger        $log
     * @param array         $opts
     * @param string        $flags
     */
    public function run(Di\Container $di, LoopInterface $loop, Logger $log, array $opts, string $flags): void
    {
        $dataGenerator = new DataGenerator(
            $log,
            Faker\Factory::create(),
            Schoolbox\UserMapper::getInstance(),
            Schoolbox\Comms\News\Mapper::getInstance(),
            Schoolbox\Discussion\Thread\Mapper::getInstance(),
            Schoolbox\Discussion\Post\Mapper::getInstance()
        );

        $dataGenerator->generateTestData(
            filter_var($opts['seed'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE)
        );
    }
});
