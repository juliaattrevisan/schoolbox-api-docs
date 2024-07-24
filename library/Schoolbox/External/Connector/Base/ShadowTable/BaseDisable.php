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

/**
 * Disable writing to shadow tables (instead of to the base tables) for the
 * given mappers, and update SUDS data by swapping the shadow and base tables in
 * situ.
 */
class BaseDisable implements IStrategy
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

        $this->log->info('Configuring mappers to write to base tables');
        foreach ($this->mappers as $mapper) {
            $this->log->info(sprintf('%s (%s => %s)', get_class($mapper), $mapper->getShadowTableName(), $mapper->getBaseTableName()));
        }

        $shadowTable = new Mapper\ShadowTable(...$this->mappers);

        $this->log->info('Swapping shadow tables with base tables');
        $shadowTable->swapShadowWithBase();
        $shadowTable->disable();
    }
}
