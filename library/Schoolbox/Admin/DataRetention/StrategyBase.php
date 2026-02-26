<?php

/**
 * Strategy Base, retain soft deleted data for a certain time
 *
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception;
use Psr\Log\LoggerInterface;
use Schoolbox\Admin\DataRetention\IStrategy;
use Schoolbox\IConfigLoader;
use Symfony\Component\Lock\LockInterface;

class StrategyBase implements IStrategy
{
    public const CHUNK_LOOPS = 3;

    protected const CHUNK_SIZE = 1000; // how many things to delete at once
    /**
     * @inheritDoc
     */
    public function __construct(
        protected ?LoggerInterface $log,
        protected bool $perform,
        protected IConnector $database,
        protected LockInterface $lock,
        protected IConfigLoader $config,
        protected int $chunkLoops = self::CHUNK_LOOPS
    ) {
    }

    /**
     * @inheritDoc
     */
    public function process(): void
    {
        throw new Exception('Not implemented.');
    }

    /**
     * @inheritDoc
     */
    public function getRetentionLabel(): string
    {
        throw new Exception('Not implemented.');
    }
}
