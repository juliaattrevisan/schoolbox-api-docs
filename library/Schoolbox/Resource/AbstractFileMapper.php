<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource;

use Alaress\Database\Predicate\Builder;
use PDO;
use PDOStatement;
use Schoolbox;
use Schoolbox\Collection;
use Schoolbox\Resource;
use Schoolbox\Resource\File\IFile;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\UserMapper;

/**
 * Class AbstractFileMapper
 *
 * Provides common mapping functionality for AbstractFiles
 *
 * @package Schoolbox\Resource
 * @method AbstractFile hydrate(array $row, $alias = '', $callback = null)
 */
abstract class AbstractFileMapper extends Schoolbox\Mapper
{

    /**
     * @var Resource\Storage\Mapper
     */
    protected $storageMapper;

    /**
     * @param int $idValue
     * @throws \Schoolbox\Mapper\Exception
     * @return AbstractFile
     */
    public function createModel($idValue = null)
    {
        /** @var AbstractFile $model */
        $model = parent::createModel($idValue);
        $model->setStorage($this->getStorageMapper()->createModel());
        return $model;
    }

    /**
     * @return void
     */
    public function setup()
    {
        // TODO this should be injectable
        $this->setStorageMapper(Resource\Storage\Mapper::getInstance());
    }


    /**
     * @param  Resource\Storage\Mapper $storageMapper
     * @return $this
     */
    public function setStorageMapper($storageMapper)
    {
        $this->storageMapper = $storageMapper;
        return $this;
    }


    /**
     * @return Resource\Storage\Mapper
     */
    public function getStorageMapper()
    {
        return $this->storageMapper;
    }


    /**
     * @param Storage $storage
     * @return IFile
     */
    public function wrap(Storage $storage)
    {
        return $this->createModel()->setStorage($storage);
    }


    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('realname', self::TYPE_STRING, null, 'filename');
        $this->uses('description', self::TYPE_STRING, null, 'title');
        $this->uses('submitted', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP, 'createdAt');
        $this->joins('owner', UserMapper::class, 'id');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->joins('hash', Resource\Storage\Mapper::class, 'hash', null, null, 'storage');
    }


    /**
     * @return string
     */
    protected function getSelectString()
    {
        $hashJoin = $this->getJoinMapper('hash');

        $table = $this->getTable();

        // much join.
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('hash', 'h')},
                   {$hashJoin->getJoinColumnString('allowedFileExtension', 'm')},
                   {$this->getJoinColumnString('owner', 'u')},
                   {$this->getJoinColumnString($this->getFolderFieldname(), 'c')}
            FROM `$table`
            LEFT JOIN {$this->getJoinSql('hash', $table, 'h')}
            LEFT JOIN {$hashJoin->getJoinSql('allowedFileExtension', 'h', 'm', true)}
            LEFT JOIN {$this->getJoinSql('owner', $table, 'u', true)}
            {$this->getFolderJoin()} {$this->getJoinSql($this->getFolderFieldname(), $table, 'c', true)}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $c = $this->createCollection();

        while ($r = $statement->fetch(PDO::FETCH_ASSOC)) {
            // can't set storage if it doesn't exist
            if (!$r['h.hash']) {
                continue;
            }

            $file = $this->hydrate($r);
            $mimetype = $this->getJoinMapper('hash')->hydrateJoin($r, 'allowedFileExtension', 'm');

            /** @var Storage $store */
            $store = $this->hydrateJoin($r, 'hash', 'h')
                          ->setMimetype($mimetype);

//            // ensure the path is set
//            $store->setPath($this->getStorageMapper()->getManager()->calcPath($store));

            $file->setStorage($store);
            $file->setOwner($this->hydrateJoin($r, 'owner', 'u'));
            if ($this instanceof Resource\File\Mapper) {
                $file->setModifiedBy($this->hydrateJoin($r, 'modified_by', 'um'));
            }
            if ($file->getFolder() && (int)$file->getFolder()->getId() === 0) {
                // root folder
                $folder = $this->getJoinMapper($this->getFolderFieldname())->findById(0);
            } else {
                $folder = $this->hydrateJoin($r, $this->getFolderFieldname(), 'c');
            }

            $file->setFolder($folder);

            $c->add($file);
        }

        return $c;
    }


    /**
     * @param Builder $predicate
     * @param array   $columnList
     * @param array   $options
     * @return array
     */
    protected function buildWherePart(Builder $predicate, $columnList, $options)
    {
        // if the mime is specified, add a LIKE predicate to the query
        if (isset($options['mime'])) {
            $mime = str_replace('*', '%', $options['mime']);
            $predicate->like('m.mime', $mime);
        }

        return parent::buildWherePart($predicate, $columnList, $options);
    }

    /**
     * Return the files contained within the folder with the given ID
     *
     * @param int $nodeId
     * @return Collection|IFile[]
     */
    public function findByFolderId($nodeId)
    {
        // Sort by title
        return $this->findByColumn(
            $this->getFolderFieldname(),
            $nodeId,
            ['order' => ['description' => 'asc',],]
        );
    }


    /**
     * @return string
     */
    abstract protected function getFolderFieldname();


    /**
     * Is the relationship to the file's folder optional (LEFT) or mandatory (INNER)?
     *
     * @return string
     */
    protected function getFolderJoin()
    {
        // Defaults to mandatory
        return 'INNER JOIN';
    }
}
