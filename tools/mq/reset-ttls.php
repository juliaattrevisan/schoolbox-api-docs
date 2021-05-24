#!/usr/bin/php
<?php

/**
 *
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Predis\ClientInterface;
use Predis\Collection\Iterator\Keyspace;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App{

    private const P_INTERVAL = 'PT%dS';
    private const P_TIME     = '%dd %hh %im %ss';
    private const P_OUTPUT   = '%s TTL %s';
    private const PERSISTED  = 'NOT SET';
    private const NOT_EXIST  = 'DOES NOT EXIST';

    /** @inheritDoc */
    public function run(): void
    {
        $this->getLogger()->info('Starting reset-ttls');

        /** @var ClientInterface $redis */
        $redis = $this->getDi()->get('redis');

        // non-blocking search of the keyspace for all messages
        $search = new Keyspace($redis, 'msg:' . INSTANCE_ID . ':m:*');

        $this->getLogger()->debug('Initiating search');

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
                    /**
                     * Class extending DateInterval
                     * @courtesy-of http://php.net/manual/en/dateinterval.format.php#113204
                     */
                    $exp = (new class (sprintf(self::P_INTERVAL, $ttl)) extends DateInterval{

                        public function recalculate()
                        {
                            $from = new DateTime();
                            $to = clone $from;
                            $to = $to->add($this);
                            $diff = $from->diff($to);
                            foreach ($diff as $k => $v) {
                                $this->$k = $v;
                            }
                            return $this;
                        }
                    })->recalculate()->format(self::P_TIME);
                    break;
            }

            $this->getLogger()->log($level, sprintf(self::P_OUTPUT, $key, $exp));

            if ($exp === self::PERSISTED) {
                if (empty($redis->get($key))) {
                    // empty message key. ignoring.
                    continue;
                }
                // this key has no TTL, extract the data so we can reverse-engineer what it should be
                $data = unserialize(gzuncompress($redis->get($key)))->jsonSerialize();

                // add 2 weeks to the date in the msg payload
                $expireTime = Alaress_Date_Format::newFromTimestamp($data['time'])->addDays(14);

                // reset the expiry
                $redis->expireat($key, $expireTime->getUnixTimeStamp());

                $this->getLogger()->notice(sprintf('%s NEW EXPIRY: %s', $key, $expireTime->getDatabase()), ['char' => '+']);
            }
        }

        $this->getLogger()->debug('Search complete');
        $this->getLogger()->info('Finishing reset-ttls');
    }
};

$options = [
    CliTool\Kernel::OPTION_LEVEL  => Monolog\Logger::INFO,
    CliTool\Kernel::OPTION_ERRORS => E_ALL,
];
$kernel  = new CliTool\Kernel($app, $options);
$kernel->runApp();
