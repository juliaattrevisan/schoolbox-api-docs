#!/usr/bin/php
<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Alaress\Routing\ByUserRouter;
use Alaress\Routing\Dictionary\SubscriptionDictionary;
use Alaress\Routing\IRouter;
use Alaress\Routing\Router;
use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon\App;
use Schoolbox\Realtime\Routing\Cache\Redis\ReadCache;
use Schoolbox\Realtime\Routing\Subscription\Resolver;

$fields = ['user:', 'pattern:'];

$opts = require __DIR__ . '/../../conf/core/require-cli.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Subscription Reader', [
    'verbose' => [ false, false, false ],
    'errors'  => E_ALL,
    'fields'  => array_keys($opts),
    'pattern' => $opts['pattern'] ?? null,
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
            if ($opts['user'] && $opts['pattern']) {
                $log->alert('Specify ONLY one of --user=:id or --pattern=:string');
                return;
            }

            if ($opts['user']) {
                $log->info("Listing subscriptions for user {$opts['user']}", [ 'char' => '+' ]);

                $search = $opts['user'];
                $router = new ByUserRouter();
                $find   = function (IRouter $router, string $search) {
                    $output = [];

                    foreach ($router->get($search) as $route) {
                        $output[] = preg_replace('`(^.*notify\.|\.#$)`', '', $route);
                    }

                    return $output;
                };

            } elseif ($opts['pattern']) {
                $log->info("Listing users subscribed to `{$opts['pattern']}`", [ 'char' => '+' ]);

                $search = "INFO.notify.{$opts['pattern']}.anything";
                $router = new Router(new SubscriptionDictionary('.'));
                $find   = function (IRouter $router, string $search) {
                    return (new Resolver($router))->getSubscribedUserIds($search);
                };
            } else {
                $log->alert('Specify one of --user=:id or --pattern=:string');
                return;
            }

            $cache = new ReadCache($di->get('redis'), $log);

            $cache->readIntoRouter(INSTANCE_ID, $router);

            $output = $find($router, $search);

            // sigh for double iteration
            sort($output);

            foreach ($output as $route) {
                printf("%s\n", $route);
            }

            $log->debug(count($output) . ' results returned');
        }
    });

} catch (Exception $e) {
    throw $e;
}
