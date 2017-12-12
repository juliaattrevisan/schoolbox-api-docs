#!/usr/bin/php
<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Alaress\Routing\ByUserRouter;
use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon\App;
use Schoolbox\Realtime\Routing\Cache\Redis\ReadCache;

$fields = ['user:'];

$opts = require __DIR__ . '/../../conf/core/require-cli.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Subscription Reader', [
    'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'errors'  => E_ALL,
    'fields'  => array_keys($opts),
    'user'    => $opts['user'] ?? null
]);


try {

    $kernel->run(new class extends App
    {

        /**
         * @param Di\Container  $di
         * @param LoopInterface $loop
         * @param Logger        $log
         * @param array         $opts
         * @return void
         * @throws Di\Exception\ServiceNotFound
         */
        public function run(Di\Container $di, LoopInterface $loop, Logger $log, array $opts)
        {
            if (!$opts['user']) {
                $log->alert('Specify a user with --user=:id');
                return;
            }

            $router = new ByUserRouter();

            $cache = new ReadCache($di->get('redis'), $log);

            $cache->readIntoRouter(INSTANCE_ID, $router);

            $output = [];

            foreach ($router->get($opts['user']) as $route) {
                $output[] = preg_replace('`(^.*notify\.|\.#$)`', '', $route);
            }

            // sigh for double iteration
            sort($output);

            foreach ($output as $route) {
                printf("%s\n", $route);

            }
        }
    });

} catch (Exception $e) {
    throw $e;
}
