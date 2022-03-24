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
     * @var \Schoolbox\Resource\Folder\Mapper
     */
    private \Schoolbox\Resource\Folder\Mapper $folderMapper;


    /**
     * @var \Schoolbox\Resource\Folder\Code\Mapper
     */
    private \Schoolbox\Resource\Folder\Code\Mapper $folderCodeMapper;


    /**
     * Special usages of mappers due to an exotic join
     */
    public function __construct()
    {
        parent::__construct();

        $this->folderMapper = \Schoolbox\Resource\Folder\Mapper::getInstance();
        $this->folderCodeMapper = \Schoolbox\Resource\Folder\Code\Mapper::getInstance();
    }


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
        $this->uses('code', self::TYPE_STRING);
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
    }


    /**
     * @inheritDoc
     *
     * @return string
     */
    protected function getSelectString()
    {
        /* I can't join to a folder via a code (code doesn't have an identifier property)
         */
        $folderColumnString = $this->folderMapper->getColumnString('f');
        $folderCodeColumnString = $this->folderCodeMapper->getColumnString('fc');

        return "
            SELECT
                {$this->getColumnString()},
                {$folderColumnString}, 
                {$folderCodeColumnString}
            FROM {$this->getTable()}
            LEFT JOIN folder_code fc ON {$this->getTable()}.code = fc.code
            LEFT JOIN folder f ON fc.folder_id = f.id 
        ";
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
            $event = $this->hydrate($row);

            /* If a folder code x folder join can be found, shove it in
             */
            if ($row['f.id']) {
                /** @var \Schoolbox\Resource\Folder\Model|null $folder */
                $folder = $this->folderMapper->hydrate($row, 'f');

                /** @var \Schoolbox\Resource\Folder\Code\Model|null $folderCode */
                $folderCode = $this->folderCodeMapper->hydrate($row, 'fc');

                $folder->setCodes($this->folderCodeMapper->createCollection([$folderCode]));

                $event->setFolder($folder);
            }

            $stack->add($event);
        }

        return $stack;
    }
}
