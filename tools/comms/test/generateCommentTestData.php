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
use Schoolbox\Application\Daemon\App;
use Schoolbox\Discussion\Test\DataGenerator;


require_once __DIR__ . '/../../../conf/core/require.php';

$opts   = getopt('', ['seed:']);
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Comment Test Data Generator',
    [
        'seed' => $opts['seed'] ?? null,
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
$kernel->run(new class extends App {
    /**
     * Generate the test data. Sorry, no logging yet
     *
     * @param Di\Container  $di
     * @param LoopInterface $loop
     * @param Logger        $log
     * @param array         $opts
     */
    public function run(Di\Container $di, LoopInterface $loop, Logger $log, array $opts): void
    {
        $dataGenerator = new DataGenerator(
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
