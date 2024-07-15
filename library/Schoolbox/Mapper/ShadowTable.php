<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Mapper;

use Schoolbox\Mapper;

class ShadowTable
{
    /**
     * Suffix for temporary table names: used only when copying the shadow table
     * to the base table name.
     */
    private const TEMP_SUFFIX = '__temp';

    /**
     * The mappers to apply shadow tables to.
     *
     * @var array<Mapper>
     */
    private array $mappers;

    /**
     * Constructor
     *
     * @param Mapper $mapper1    The first mapper to use shadow tables on (there must be at least one).
     * @param Mapper ...$mappers Any other mappers to use shadow tables on.
     */
    public function __construct(Mapper $mapper1, Mapper ...$mappers)
    {
        array_unshift($mappers, $mapper1);
        $this->mappers = $mappers;
    }

    /**
     * Enable shadow tables on all mappers in this object.
     *
     * @return void
     */
    public function enable(): void
    {
        foreach ($this->mappers as $mapper) {
            $mapper->enableShadowTable();
        }
    }

    /**
     * Disable shadow tables on all mappers in this object.
     *
     * @return void
     */
    public function disable(): void
    {
        foreach ($this->mappers as $mapper) {
            $mapper->disableShadowTable();
        }
    }

    /**
     * Create the shadow tables for all mappers in this object.
     *
     * @return void
     */
    public function createShadowTables(): void
    {
        foreach ($this->mappers as $mapper) {
            $database = $mapper->getDatabase();
            $database->execute(
                sprintf(
                    'CREATE OR REPLACE TABLE %s LIKE %s',
                    $mapper->getShadowTableName(),
                    $mapper->getBaseTableName(),
                )
            );
        }
    }

    /**
     * Swap shadow with base table for all mappers in this object.
     *
     * This renames the tables in place using
     * `RENAME TABLE base1 to base1_tmp, shadow1 to base1, base1_tmp to shadow1`
     * for all tables in this object, which should be a fast operation.
     *
     * @return void
     */
    public function swapShadowWithBase(): void
    {
        $renamePart = '%s TO %s';
        $queryParts = [];
        $database   = null;
        foreach ($this->mappers as $mapper) {
            $database ??= $mapper->getDatabase();
            $queryParts[] = sprintf($renamePart, $mapper->getBaseTableName(), $mapper->getBaseTableName() . self::TEMP_SUFFIX);
            $queryParts[] = sprintf($renamePart, $mapper->getShadowTableName(), $mapper->getBaseTableName());
            $queryParts[] = sprintf($renamePart, $mapper->getBaseTableName() . self::TEMP_SUFFIX, $mapper->getShadowTableName());
        }
        $database->execute('RENAME TABLE ' . implode(', ', $queryParts) . ' TO ' . implode(', ', $queryParts));
    }
}
