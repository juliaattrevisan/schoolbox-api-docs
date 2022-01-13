<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Hierarchy\Cli;

use Alaress\Lock\InstanceLockFactory;
use Predis\ClientInterface;
use Schoolbox\Application\CliTool;
use Schoolbox\Clipboard\Hierarchy\Factory;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;

/**
 * Encapsulated cli tool
 */
class HierarchyTool extends CliTool\App
{

    /**
     * Lock name
     */
    public const CLIPBOARD_HIERARCHY_LOCK_NAME = 'clipboard_hierarchy';


    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * --instance=...
     */
    private const OPT_LONG_INSTANCE = 'instance:';


    /**
     * Lock ttl (seconds)
     *
     * Reserves this amount for every iteration,
     * refreshing at the start of every iteration
     */
    private const LOCK_TTL = 60;


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Import Clipboard Category/Subcategory/Teams into schoolbox Divisions/Teams equivalent.

Usage: php hierarchy.php


HELP;
            exit;
        }

        /* Check that this isn't currently running
         */

        /** @var ClientInterface $redis */
        $redis      = $this->getDi()->get('redis');
        $instanceId = $this->getOpt(self::OPT_LONG_INSTANCE) ?? INSTANCE_ID;

        $lockFactory = new InstanceLockFactory(new LockFactory(new RedisStore($redis)), $instanceId);
        $lock        = $lockFactory->createLock(self::CLIPBOARD_HIERARCHY_LOCK_NAME, self::LOCK_TTL);

        try {
            $acquired = $lock->acquire();
        } catch (LockConflictedException | LockAcquiringException $e) {
            $acquired = false;
        }

        if ($acquired) {
            $this->getLogger()->info('Clipboard to Schoolbox Fixtures Hierarchy synchronisation.');

            /* Used to shank a database instance
             */
            $db = \Schoolbox\UserMapper::getInstance()->getDatabase();

            try {
                $composites = (new Factory(
                    $this->getLogger(),
                    (string)$this->getConfig()->get('clipboard_endpoint_host'),
                    (string)$this->getConfig()->get('clipboard_endpoint_token'),
                ))->produce();
            } catch (\DomainException $e) {
                $this->getLogger()->warning($e->getMessage());

                return;
            }

            /* Wrap in transaction so changes are atomic
             */
            $db->beginTransaction();

            foreach ($composites as $composite) {
                $composite->import();

                $this->getLogger()->debug(sprintf(
                    'Refreshing lock for another %s seconds',
                    self::LOCK_TTL
                ));

                $lock->refresh();
            }

            /* Only commit changes in Perform mode
             */
            if ($this->perform()) {
                $db->commit();
            } else {
                $db->rollBack();

                $this->getLogger()->info('Planning finished.');
            }

            /* Releasing control
             */
            $lock->release();
        } else {
            $this->getLogger()->warning("Cannot acquire lock. Likely previous execution still in progress. Exiting");
            exit;
        }
    }


    /**
     * FIXME Decide whether this script must be run as root
     *  By default, this returns false (see CliTool\App) but if you're using methods that require
     *  access to special folders (such as when you're creating files) you need to run this as root.
     *  Otherwise it'll fail with a generic error that might mislead you
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return parent::runAsRoot();
    }
}
