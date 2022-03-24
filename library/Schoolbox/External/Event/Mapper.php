<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event;

use Schoolbox\GenericCollection;

/**
 * @method Model findById($id)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method Model findOneByColumnList($columnList, array $options = [])
 * @method GenericCollection findByColumn($columnName, $columnValue, array $options = [])
 * @method GenericCollection findByColumnList($columnList, array $options = [])
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model createModel($idValue = null)
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('sis_event');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('detail', self::TYPE_STRING);
        $this->uses('location', self::TYPE_STRING);
        $this->uses('day', self::TYPE_STRING);
        $this->uses('period', self::TYPE_STRING);
        $this->uses('start_at', self::TYPE_DATETIME);
        $this->uses('finish_at', self::TYPE_DATETIME);
        $this->uses('type', self::TYPE_STRING);
        $this->uses('classification', self::TYPE_STRING);
        $this->uses('guid', self::TYPE_STRING);
        $this->uses('source', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->joins('code', \Schoolbox\Resource\Folder\Code\Mapper::class, 'code', null, null, 'code', 'folderCode');
    }


    /**
     * @inheritDoc
     *
     * @return string
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString()
    {
        $x = "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('folderCode', 'fc')},
                {$this->getJoinColumnString('folderCode.folder', 'f')}
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('folderCode', '', 'fc')}
            LEFT JOIN {$this->getJoinSql('folderCode.folder', 'fc', 'f')}
        ";

        return $x;
    }


    /**
     * @inheritDoc
     *
     * @param \PDOStatement $statement
     * @return \Schoolbox\GenericCollection
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    protected function hydrateSelect(\PDOStatement $statement)
    {
        $stack = $this->createCollection();

        foreach ($statement as $row) {
            new \Alaress_Probe($row);
            $event = $this->hydrate($row);
//
            /* If a folder code x folder join can be found, shove it in
             */
            if ($row['f.id']) {
                /** @var \Schoolbox\Resource\Folder\Model $folder */
                $folder = $this->hydrateJoin($row, 'folderCode.folder', 'f');

                /** @var \Schoolbox\Resource\Folder\Code\Model $folderCode */
                $folderCode = $this->hydrateJoin($row, 'folderCode', 'fc');

                $folder->setCodes($this->getJoinMapper('folderCode')->createCollection([$folderCode]));


            }

            new \Alaress_Probe($row, $folderCode, $folder);
        }

        return $stack;
    }
}
