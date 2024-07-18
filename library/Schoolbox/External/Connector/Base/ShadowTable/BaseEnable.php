<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Base\ShadowTable;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\Mapper;
use Schoolbox\Mapper\ShadowTable;

/**
 * Enable writing to shadow tables (instead of to the base tables) for the given
 * mappers, and copy the content of the base tables into the shadow tables.
 */
class BaseEnable implements IStrategy
{
    /**
     * @var array<Mapper>
     */
    private array $mappers;

    /**
     * Constructor
     *
     * @param LoggerInterface $log
     * @param bool            $perform
     * @param Mapper          ...$mappers
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        Mapper ...$mappers,
    ) {
        $this->mappers = $mappers;
    }

    /**
     * @inheritdoc
     */
    public function import(): void
    {
        if (!$this->perform) {
            return;
        }

        $this->log->info('Configuring mappers to write to shadow tables');
        foreach ($this->mappers as $mapper) {
            $this->log->info(sprintf('%s (%s => %s)', get_class($mapper), $mapper->getBaseTableName(), $mapper->getShadowTableName()));
        }

        $shadowTable = new ShadowTable(...$this->mappers);

        $this->log->info('Creating shadow tables');
        $shadowTable->createShadowTables();

        $this->log->info('Copying existing data from base to shadow tables');
        $shadowTable->copyBaseToShadow();
        $shadowTable->enable();
    }
}
