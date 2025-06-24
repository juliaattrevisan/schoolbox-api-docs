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
     * @param bool $perform
     * @param string $version
     * @param Mapper ...$mappers
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private string $version,
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

        /* Compare the version of schoolbox detected at the start of the import process
         * and see if it's different to what it is right now, prior to shadow table restoration.
         * If they were different, simply abort with a log message.
         */
        $currentVersion = $this->getVersion();

        if ($currentVersion !== $this->version) {
            $this->log->warning(sprintf(
                'Instance version changed while import was running; it was %s, but is now %s. shadow table schema might be different. Abandoning import without applying changes.',
                $this->version,
                $currentVersion
            ));

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


    /**
     * Fetch the freshest version number from config,
     * bypassing any mapper-related caching mechanisms
     */
    protected function getVersion(): string
    {
        /* Make a massive assumption that we have at least one mapper dependency-injected
         * I'm not sure how this would work without such mappers and so far, every subclass
         * instance of BaseDisable always has at least one mapper hardcoded...so
         */
        return trim((string)$this->mappers[0]->getDatabase()->executeQuery("
            SELECT value FROM config WHERE name = 'site_version' LIMIT 1
        ")->fetchColumn());
    }
}
