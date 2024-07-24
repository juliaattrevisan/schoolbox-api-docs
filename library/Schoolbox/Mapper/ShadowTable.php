<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Mapper;

use Schoolbox\Mapper;

/**
 * Management class for shadow tables.
 *
 * This class provides convenience methods for performing the following with a
 * collection of mappers:
 * * enabling/disabling usage of shadow tables
 * * creating shadow tables
 * * copying data from base to shadow
 * * swapping shadow and base tables, so that the shadow becomes the new base
 */
class ShadowTable
{
    /**
     * Suffix for temporary table names: used only when swapping the shadow
     * table with the base table.
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
            $mapper->getDatabase()->execute(
                sprintf(
                    'CREATE OR REPLACE TABLE %s LIKE %s',
                    $mapper->getShadowTableName(),
                    $mapper->getBaseTableName(),
                )
            );
        }
    }

    /**
     * Copy data from base to shadow tables.
     *
     * @return void
     */
    public function copyBaseToShadow(): void
    {
        foreach ($this->mappers as $mapper) {
            $mapper->getDatabase()->execute(
                sprintf(
                    'INSERT INTO %s SELECT * FROM %s',
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
        $database->execute('RENAME TABLE ' . implode(', ', $queryParts));
    }
}
