#!/usr/bin/php
<?php
/**
 * Perform Schoolbox User/Synergetic synchronisations
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon\App;

echo "[".date('Y-m-d H:i:s')."] Running sync script\n";

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\Daemon\Kernel('External DB Sync', '', ['perform-update', 'diagnostic', 'site-location:']);

$app = new class extends App{
    /**
     * @inheritdoc
     */
    public function run(Di\Container $di, LoopInterface $loop, Logger $log, array $options, array $flags): void
    {
        if (!Cfg::get('external_sync_data')) {
            die("Data synchronisation is not enabled.\n");
        }

        $siteLocation = $options['site-location'] ?? Cfg::get('site_location');

        // check for Saint Something as best we can in a simple fashion
        if(preg_match('/^st([^aeiouyr])/', $siteLocation, $matches) > 0) {
            // uppercase the 3rd character (eg stmichaels to stMichaels)
            $siteLocation[2] = strtoupper($siteLocation[2]);
        }

        $sync = new Synchronise_Facade(ucfirst($siteLocation), isset($options['v']), isset($options['perform-update']));

        $strategies = explode(' ', Cfg::get('sync_patterns'));

        $sync->setStrategies($strategies);

        if (isset($options['diagnostic'])) {
            $sync->diagnose();
        } else {
            $sync->execute();
            \Alaress\Redis::getClientInstance()->publish('routed.'.INSTANCE_ID.'.reload', '');
        }
    }
};

$kernel->run($app);
