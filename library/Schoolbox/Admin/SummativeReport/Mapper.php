<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SummativeReport;

use Schoolbox\File\MimetypeMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;

/**
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection findByColumn($columnName, $columnValue, $options = [])
 * @method GenericCollection createCollection($contents = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('summative_report');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('introduction', self::TYPE_STRING);
        $this->joins('logo_hash', \Schoolbox\Resource\Storage\Mapper::class, 'hash', null, null, 'logo', 'logo');
        $this->uses('options', self::TYPE_STRING);
        $this->joins('updated_by', UserMapper::class, 'id', null, null, 'updatedBy', 'updatedBy');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * Return the first part of the string required to select from this mapper
     *
     * @return string
     */
    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('updatedBy', 'u')},
                {$this->getJoinColumnString('logo', 'l')},
                {$this->getJoinColumnString('logo.allowedFileExtension', 'lfe')}
        ";
    }


    /**
     * The Next Pieces
     *
     * @return string
     * @throws DefinitionException
     */
    protected function getFromString(): string
    {
        return "
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('updatedBy', null, 'u', true)}
            LEFT JOIN {$this->getJoinSql('logo', null, 'l')}
            LEFT JOIN {$this->getJoinSql('logo.allowedFileExtension', 'l', 'lfe', true)}
        ";
    }


    /**
     * @inheritdoc
     *
     * @throws \Schoolbox\Mapper\Exception
     * @throws DefinitionException
     * @throws \UnexpectedValueException
     */
    protected function hydrateSelect(\PDOStatement $statement): GenericCollection
    {
        $idColumn = $this->getIdentifierColumn();

        /** @var \Schoolbox\Resource\Storage\Mapper $logoMapper */
        $logoMapper = $this->getJoinMapper('logo');

        /** @var MimetypeMapper $mimeTypeMapper */
        $mimeTypeMapper = $this->getJoinMapper('logo.allowedFileExtension');

        /** @var UserMapper $updatedByMapper */
        $updatedByMapper = $this->getJoinMapper('updatedBy');

        $collection = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /* If the report was put into this stack already, just reuse it
             * Otherwise hydrate and store
             */
            if ($collection->offsetExists($row[$idColumn])) {
                $report = $collection->offsetGet($row[$idColumn]);
            } else {
                $report = $this->hydrate($row);
                $collection->setOffset($row[$idColumn], $report);

                $report->setUpdatedBy($updatedByMapper->hydrate($row, 'u'));

                /* If there's a logo, bolt that on
                  */
                $logo = $logoMapper->hydrate($row, 'l');

                if ($logo) {
                    $report->setLogo($logo->setMimetype($mimeTypeMapper->hydrate($row, 'lfe')));
                }
            }
        }

        return $collection;
    }
}
