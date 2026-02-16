<?php

/**
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention;

use Alaress\Database\Connector\IConnector;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockInterface;

/**
 * Interface IStrategy
 *
 * Every data retention class must provide this method
 *
 * @package Schoolbox\Admin\DataRetention
 */
interface IStrategy
{
    /**
     * @param null|LoggerInterface  $log // log to STDERR
     * @param bool                  $perform // should we actually perform the deletion?
     * @param IConnector            $database // database connector, do we can craft manual queries rather than use mappers
     * @param LockInterface         $lock // lock to prevent multiple instances of this running at once, should be refreshed by long running processes
     * @param string                $retentionPeriod // configured by admin with planned options 1 Day, 30 Days, 365 Days, <null> (null is do NOT hard delete this)
     * @return void
     */
    public function __construct(?LoggerInterface $log, bool $perform, IConnector $database, LockInterface $lock, string $retentionPeriod = '');

    /**
     * find and remove all soft deleted data
     */
    public function process(): void;

    /**
     * a human readable label for this strategy, used in the admin interface
     * @return string
     */
    public function getRetentionLabel(): string;
}
