#!/usr/bin/php
<?php
/**
 *
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Aura\Di;
use Predis\ClientInterface;
use Predis\Collection\Iterator\Keyspace;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon\App;

$fields = array('porcelain', 'mem', 'truemem', 'pid');

foreach($fields as $k => $name) {
    if(!isset($opts[$name])) {
        unset($fields[$k]);
    }
}

require_once __DIR__ . '/../../conf/core/require.php';

$k = new \Schoolbox\Application\Daemon\Kernel('TTL Reset', [
    'verbose' => [true, true],
    'errors'  => E_ALL,
    'fields'  => array_values($fields),
]);

try {
    $k->run(new class extends App {

        private const P_INTERVAL = 'PT%dS';
        private const P_TIME     = '%dd %hh %im %ss';
        private const P_OUTPUT   = '%s TTL %s';
        private const PERSISTED  = 'NOT SET';
        private const NOT_EXIST  = 'DOES NOT EXIST';

        /**
         * @param Di\Container  $di
         * @param LoopInterface $loop
         * @param Logger        $log
         * @param array         $opts
         * @return void
         */
        public function run(Di\Container $di, LoopInterface $loop, Logger $log, array $opts)
        {
            /** @var ClientInterface $redis */
            $redis = $di->get('redis');

            // non-blocking search of the keyspace for all messages
            $search = new Keyspace($redis, 'msg:' . INSTANCE_ID . ':m:*');

            $log->debug('Initiating search');

            foreach ($search as $key) {
                // extract the existing TTL
                $ttl = $redis->ttl($key);

                switch ($ttl) {
                    case -2: // key doesn't even exist, lolwut
                        $level = Monolog\Logger::ERROR;
                        $exp   = self::NOT_EXIST;
                        break;

                    case -1: // key exists, but not TTL is set
                        $level = Monolog\Logger::WARNING;
                        $exp   = self::PERSISTED;
                        break;

                    default: // key has a TTL
                        $level = Monolog\Logger::INFO;
                        $exp = (new DateIntervalEnhanced(sprintf(self::P_INTERVAL, $ttl)))->recalculate()->format(self::P_TIME);
                        break;
                }

                $log->log($level, sprintf(self::P_OUTPUT, $key, $exp));

                if ($exp === self::PERSISTED) {
                    // this key has no TTL, extract the data so we can reverse-engineer what it should be
                    $data = unserialize(gzuncompress($redis->get($key)))->jsonSerialize();

                    // add 2 weeks to the date in the msg payload
                    $expireTime = Alaress_Date_Format::newFromTimestamp($data['time'])->addDays(14);

                    // reset the expiry
                    $redis->expireat($key, $expireTime->getUnixTimeStamp());

                    $log->notice(sprintf('%s NEW EXPIRY: %s', $key, $expireTime->getDatabase()), [ 'char' => '+' ]);
                }

            }

            $log->debug('Search complete');
        }

    });
} catch (\Throwable $e) {
    throw $e;
}


/**
 * Class DateIntervalEnhanced
 * @courtesy-of http://php.net/manual/en/dateinterval.format.php#113204
 */
class DateIntervalEnhanced extends DateInterval {

    public function recalculate()
    {
        $from = new DateTime;
        $to = clone $from;
        $to = $to->add($this);
        $diff = $from->diff($to);
        foreach ($diff as $k => $v) $this->$k = $v;
        return $this;
    }

}
