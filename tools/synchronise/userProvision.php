#!/usr/bin/php
<?php

/**
 * User Provisioning System command line entry point
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Lumberjack\CompositeLoggerInterface;
use Schoolbox\Sync\Provisioner\Provisioner;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App {
    private const OPT_LONG_PERFORM_UPDATE = 'perform-update';
    private const OPT_LONG_INSTANCE = 'instance';
    private const OPT_LONG_HELP = 'help';
    private const OPT_SHORT_ROLE = 'r:';

    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }

    /** @inheritDoc */
    public function getShortOpts(): array
    {
        return [
            self::OPT_SHORT_ROLE,
        ];
    }

    /**
     * Ensure tool being run as www-data user
     * @inheritDoc
     */
    public function getRequiredProcessUser(): string
    {
        return 'www-data';
    }

    /**
     * @return void
     */
    public function run(): void
    {
        $perform = $this->getOpt(self::OPT_LONG_PERFORM_UPDATE);
        $instanceId = $this->getOpt(self::OPT_LONG_INSTANCE) ?? INSTANCE_ID;
        $roleType = in_array($this->getOpt(self::OPT_SHORT_ROLE), ['staff', 'student', 'parent'])? $this->getOpt(self::OPT_SHORT_ROLE) : null;

        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo  <<<HELP
Usage: userProvision.php [--perform-update] [--instance=instanceId] [-r roleType]
Options:
  --perform-update   Perform the update
  --instance         Instance ID to use (default: INSTANCE_ID)
  -r, --role         Role type (staff, student, parent)

HELP;
            exit(0);
        }

        // AFAICT this is unused
        // $di->values['ups.redisKey'] = $instanceId . '.provision.user.' . getmypid();

        /** @var CompositeLoggerInterface $logger */
        $logger = $this->getDi()->get('ups.logger');

        // append the CLI logger to the main logger
        $logger->pushComposite($this->getLogger());

        /** @var Provisioner $ups */
        $ups = $this->getDi()->get('ups.provisioner');

        // Note that trying to overwrite the logger (and thus destroy the old one)
        // will cause an exit as soon as stdout is written
        // (because stdout is closed when the old logger is destroyed)
        $ups->run($perform, $roleType);


        if ($perform) {
            // we need to manually refresh the routing cache now
            /** @var \Schoolbox\Realtime\Routing\Cache\Redis\WriteCache $cache */
            $cache = $this->getDi()->get('subscriptionCache');
            $cache->setLog($this->getLogger());
            $cache->cacheAllUsers($instanceId);
        }
    }
};

$kernel = new CliTool\Kernel($app, [
    CliTool\Kernel::OPTION_ERRORS => E_ALL
]);


// Signal handling unique to the provisioner
// (ticks required for pcntl_signal functions to work)
declare(ticks=1);
$cleanup = function ($signo) use ($kernel) {
    /** @var Provisioner $ups */
    $ups = $kernel->getDependencyInjector()->get('ups.provisioner');
    $ups->onInterrupt($signo);
    exit;
};
pcntl_signal(SIGINT, $cleanup);
pcntl_signal(SIGTERM, $cleanup);
pcntl_signal(SIGQUIT, $cleanup);
$kernel->registerExceptionCallback($cleanup);

$kernel->runApp();
