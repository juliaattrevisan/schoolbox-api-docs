#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Monolog\Logger;
use Predis\Collection\Iterator\Keyspace;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Renames session keys if possible to avoid logging out users
 */
$app = new class extends CliTool\App {

    /** @inheritDoc */
    public function run(): void
    {
        $this->checkWeCanRenameSessionKeys();
        $this->renameSessionKeys();
    }


    /**
     * Checks we can safely rename session keys, exits otherwise
     */
    private function checkWeCanRenameSessionKeys(): void
    {
        $package   = $this->getPackage();
        $instances = parse_ini_file("/etc/$package/instances.ini", true);

        if (!count($instances)) {
            $this->getLogger()->warning("No instances found for package: $package");
            exit(CliTool\Kernel::EXIT_STATUS_OK);
        }
        if (count($instances) > 1) {
            $this->getLogger()->warning("Multiple instances found for package: $package, cannot re-key sessions");
            exit(CliTool\Kernel::EXIT_STATUS_OK);
        }
        if ($this->isSharedOnPrem()) {
            $this->getLogger()->warning("This is a shared onprem STG/PRD box, cannot re-key sessions");
            exit(CliTool\Kernel::EXIT_STATUS_OK);
        }

        // This will occur on installation of a second instance to a package that already contains exactly one instance
        if (INSTANCE_ID !== array_keys($instances)[0]) {
            $this->getLogger()->warning(sprintf("Sole pre-existing instance (%s) != this instance (%s)", array_keys($instances)[0], INSTANCE_ID));
            exit(CliTool\Kernel::EXIT_STATUS_OK);
        }

        $this->getLogger()->notice("1 instances found for package: $package, and not a shared box, will re-key sessions");
    }


    /**
     * Renames session keys to include the instance id
     */
    private function renameSessionKeys(): void
    {
        /** @var Predis\ClientInterface $redis */
        $redis = $this->getDi()->get('redis');

        $oldKeys = new Keyspace($redis, "PHPREDIS_SESSION:*");
        foreach ($oldKeys as $oldKey) {
            if (strpos($oldKey, "PHPREDIS_SESSION:" . INSTANCE_ID) !== false) {
                $this->getLogger()->debug("Session key already has instance id: '$oldKey'");
                continue;
            }

            $newKey = str_replace("PHPREDIS_SESSION", "PHPREDIS_SESSION:" . INSTANCE_ID, $oldKey);
            $this->getLogger()->info("Renaming session key from '$oldKey' to '$newKey'");
            $redis->rename($oldKey, $newKey);
        }
    }

    /**
     * Returns which package this instance belongs to. One of [schoolbox,schoolboxdev]
     *
     * @return string
     */
    private function getPackage(): string
    {
        $package = basename(dirname(__DIR__, 2));
        return $package === 'vagrant' ? 'schoolbox' : $package;
    }


    /**
     * Returns if this is a shared schoolbox/schoolboxdev box
     *
     * @return bool
     */
    private function isSharedOnPrem(): bool
    {
        return file_exists('/etc/schoolbox/instances.ini') && file_exists('/etc/schoolboxdev/instances.ini');
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Logger::DEBUG]))->runApp();
