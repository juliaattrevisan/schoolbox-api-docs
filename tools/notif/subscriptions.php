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
use Predis\ClientInterface;
use Psr\Log\LoggerInterface as Logger;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon\App;
use Schoolbox\Realtime\Routing\Cache\Redis\ReadCache;
use Schoolbox\Realtime\Routing\Subscription\Test\NestTestResolver;

$fields = ['user:', 'pattern:'];

$opts = require __DIR__ . '/../../conf/core/require-cli.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Subscription Reader', [
    'verbose' => array_fill(0, max(2, count($opts['v'] ?? [])), false),
    'errors'  => E_ALL,
    'fields'  => array_keys($opts),
    'pattern' => $opts['pattern'] ?? null,
    'user'    => $opts['user'] ?? null
]);


try {
    $kernel->run(new class extends App
    {
        /** @var LoggerInterface */
        private $log;

        /** @var ClientInterface */
        private $redis;

        /**
         * The main() of this "app"
         *
         * @param Di\Container  $di
         * @param LoopInterface $loop
         * @param Logger        $log
         * @param array         $opts
         * @return void
         * @throws Di\Exception\ServiceNotFound
         */
        public function run(Di\Container $di, LoopInterface $loop, Logger $log, array $opts)
        {
            $this->log   = $log;
            $this->redis = $di->get('redis');

            if ($opts['user'] && $opts['pattern']) {
                $log->alert('Specify ONLY one of --user=:id or --pattern=:string');
                return;
            }

            try {
                if ($opts['user']) {
                    $count = $this->findByUser($opts['user']);
                } elseif ($opts['pattern']) {
                    $count = $this->findByPattern($opts['pattern']);
                } else {
                    $log->alert('Specify one of --user=:id or --pattern=:string');
                    return;
                }

                $log->notice($count . ' results returned');
            } catch (InvalidArgumentException|OverflowException $e) {
                $log->alert($e->getMessage(), [ $e ]);
            }
        }


        /**
         * @param int $userId
         * @throws InvalidArgumentException
         * @return int
         */
        private function findByUser($userId)
        {
            $router = $this->loadRouter(new ByUserRouter());

            $this->log->notice("Listing subscriptions for user {$userId}", [ 'char' => '+' ]);

            $output = [];

            foreach ($router->get($userId) as $route) {
                $output[] = NestTestResolver::trim($route);
            }

            sort($output);

            foreach ($output as $route) {
                printf("%s\n", $route);
            }

            return count($output);
        }


        /**
         * @param string $pattern
         * @throws OverflowException
         * @return int
         */
        private function findByPattern($pattern)
        {
            $resolver = new NestTestResolver($this->loadRouter(new Router(new SubscriptionDictionary('.'))));

            $this->log->notice("Listing users subscribed to `{$pattern}`", [ 'char' => '+' ]);

            $output = $resolver->getSubscribedUserIds(sprintf(NestTestResolver::PATTERN_CHANNEL, $pattern));

            ksort($output);

            foreach ($output as $userId => $route) {
                printf("%6d : %s\n", $userId, $route);
            }

            return count($output);
        }


        /**
         * @param IRouter $router
         * @return IRouter for convenience
         */
        private function loadRouter(IRouter $router)
        {
            (new ReadCache($this->redis, $this->log))->readIntoRouter(INSTANCE_ID, $router);

            return $router;
        }
    });
} catch (Exception $e) {
    throw $e;
}
