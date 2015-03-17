<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\File;

use PDO;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Resource;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 *
 * @package Schoolbox\Resource\File
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model findById($id)
 * @method Model createModel()
 */
class Mapper extends \Schoolbox\Mapper implements ILocator, IFileMapper
{
    /**
     * @var Resource\Storage\Mapper
     */
    protected $storageMapper;


    /**
     * @return void
     */
    public function setup() {
        // TODO this should be injectable
        $this->setStorageMapper(Resource\Storage\Mapper::getInstance());
    }

    /**
     * @param  Resource\Storage\Mapper $storageMapper
     * @return $this
     */
    public function setStorageMapper($storageMapper) {
        $this->storageMapper = $storageMapper;
        return $this;
    }

    /**
     * @return Resource\Storage\Mapper
     */
    public function getStorageMapper() {
        return $this->storageMapper;
    }


    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $model = new Model();
        $model->setStorage($this->getStorageMapper()->createModel());

        $this->hasTable('file');
        $this->hasPrototype($model);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('realname', self::TYPE_STRING, null, 'filename');
        $this->uses('description', self::TYPE_STRING, null, 'title');
        $this->uses('comment', self::TYPE_STRING, null, 'description'); // TODO rename that field!
        $this->uses('hidden', self::TYPE_BOOLEAN);
        $this->uses('submitted', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP, 'createdAt');
        $this->uses('modified', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP, 'modifiedAt');

        $this->joins('owner', UserMapper::class, 'id');
        $this->joins('category', Resource\Folder\Mapper::class, 'id', null, null, 'folder');

        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->joins('hash', Resource\Storage\Mapper::class, 'hash', null, null, 'storage');
    }


    /**
     * @return string
     */
    protected function getSelectString()
    {
        // much join.
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('hash', 'h')},
                   {$this->getJoinMapper('hash')->getJoinColumnString('allowedFileExtension', 'm')},
                   {$this->getJoinColumnString('owner', 'u')},
                   {$this->getJoinColumnString('category', 'c')}
            FROM `{$this->getTable()}`
            LEFT JOIN {$this->getJoinSql('hash', 'file', 'h')}
            LEFT JOIN {$this->getJoinMapper('hash')->getJoinSql('allowedFileExtension', 'h', 'm', true)}
            LEFT JOIN {$this->getJoinSql('owner', 'file', 'u')}
            LEFT JOIN {$this->getJoinSql('category', 'file', 'c')}
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

            $file = $this->hydrate($r);

            // just in case we wanted this to be simple
            $file->setStorage(
                $this->hydrateJoin($r, 'hash', 'h')
                     ->setMimetype(
                         $this->getJoinMapper('hash')
                              ->hydrateJoin($r, 'allowedFileExtension', 'm')
                     )
            );

            $file->setOwner($this->hydrateJoin($r, 'owner', 'u'));
            $file->setFolder($this->hydrateJoin($r, 'category', 'c'));

            $c->add($file);
        }

        return $c;
    }


    /**
     * @param array $columnList
     * @param array $options
     * @return array
     */
    protected function buildWherePart($columnList, $options)
    {
        $parts = parent::buildWherePart($columnList, $options);

        if (isset($options['mime'])) {
            $mime = str_replace('*', '%', $options['mime']);
            $parts['where'][] = sprintf("%s LIKE %s",
                $this->database->quoteIdentifier('mime_type'),
                $this->database->quote($mime)
            );
        }

        return $parts;
    }

    /**
     * Return the files contained within the folder with the given ID
     *
     * @param int $nodeId
     * @return Collection|Model[]
     */
    public function findByFolderId($nodeId)
    {
        return $this->findByColumn('category', $nodeId);
    }


    /**
     * @param Storage $storage
     * @return IFile
     */
    public function wrap(Storage $storage)
    {
        return $this->createModel()->setStorage($storage);
    }

}