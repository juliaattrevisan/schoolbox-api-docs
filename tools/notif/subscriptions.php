#!/usr/bin/php
<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Routing\ByUserRouter;
use Alaress\Routing\Dictionary\SubscriptionDictionary;
use Alaress\Routing\IRouter;
use Alaress\Routing\Router;
use Aura\Di;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon\App;
use Schoolbox\Realtime\Routing\Cache\Redis\ReadCache;
use Schoolbox\Realtime\Routing\Subscription\Test\NestTestResolver;
use Schoolbox\Application\Daemon;

require __DIR__ . '/../../conf/core/require.php';

$kernel = new Daemon\Kernel(
    'Subscription Reader',
    [
        Daemon\Kernel::OPTION_ERRORS        => E_ALL,
        Daemon\Kernel::OPTION_APP_LONG_OPTS => ['user:', 'pattern:'],
    ]
);

const HALP = <<<EOF
Schoolbox Subscription Reader

Usage: 
  ./subscriptions.php [-v[v[v]]][--instance=INSTANCE_ID][--user=USER_ID][--pattern=PATTERN]

Options:
  -h, --help                    Display this help message
  -v[v[v]]]                     Various levels of verbosity
  --user=USER_ID                The ID of a user: that user's subscriptions will be returned 
  --pattern=PATTERN             A subscription pattern to search: all users subscribed will be returned

Example patterns to search by (quotes are not required, btw):
  superuser                     All superusers
  roletype.staff                All staff
  role.ROLE_ID                  All users in role ROLE_ID
  service.1.PAGE_ID.1           Users with read access (therefore all users) subscribed to homepage PAGE_ID
  service.1.PAGE_ID.2           Users with write access subscribed to homepage PAGE_ID
  service.1.PAGE_ID.1.news      Users who would receive a news article posted to homepage PAGE_ID


EOF;


try {
    $kernel->run(new class extends App
    {
        /** @var LoggerInterface */
        private $log;

        /** @var ClientInterface */
        private $redis;

        /**
         * @inheritdoc
         */
        public function run(Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts, string $flags): void
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
                    echo HALP;
                    return;
                }

                $log->notice($count . ' results returned');
            } catch (InvalidArgumentException|OverflowException $e) {
                $log->alert($e->getMessage(), [ 'exception'=>$e ]);
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

            if (empty($router->get($userId))) {
                $this->log->notice("Subscription does not exist for user {$userId}");
                return 0;
            }

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
