<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Storage;

use Alaress_Byte_Format;
use PDO;
use PDOStatement;
use Schoolbox;
use Schoolbox\Collection;
use Schoolbox\File\MimetypeMapper;

/**
 * Class Mapper
 *
 * @package Schoolbox\Resource\Storage
 *
 * @method Model hydrate()
 * @method Model createModel()
 */
class Mapper extends Schoolbox\Mapper
{

    /** @var Manager */
    protected $mgr;


    /**
     * @return void
     */
    public function setup()
    {
        // TODO this does not belong here, but where does it go
        $factory = new Schoolbox\Resource\FilesystemFactory();
        $this->mgr = new Manager($factory->build($this->config_loader->get('storage_dir')), new Alaress_Byte_Format());
    }


    /**
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('storage');
        $this->hasPrototype(new Model($this->mgr->getFilesystem(Manager::FS_STORAGE)));

        $this->uses('hash', self::TYPE_STRING, self::COLUMN_IDENTIFIER);
        $this->uses('filesize', self::TYPE_INTEGER, null, 'size');

        $this->joins('mime', MimetypeMapper::class, 'mime', null, null, 'mimetype');

        $this->registerCallback(self::CALLBACK_PRESAVE,    [ $this->mgr, 'safetyCheck' ]);
        $this->registerCallback(self::CALLBACK_POSTSAVE,   [ $this, self::CALLBACK_POSTSAVE ]);
        $this->registerCallback(self::CALLBACK_POSTDELETE, [ $this->mgr, 'delete' ]);
    }


    /**
     * @param Model $storage
     * @param Model $previous
     * @return bool
     */
    protected function postSave(Model $storage, Model $previous = null)
    {
        if ($previous) {
            // not the first time we've seen this file? no copying to take place
            return true;
        }

        return $this->mgr->save($storage);
    }


    /**
     * @param $hash
     * @return object
     */
    public function findByHash($hash)
    {
        return $this->findOneByColumn('hash', $hash);
    }


    /**
     * Always join onto Mime
     *
     * @return string
     */
    protected function getSelectString()
    {
        $table = $this->database->quoteIdentifier($this->table);

        return "
          SELECT {$this->getColumnString()},
                 {$this->getJoinColumnString('mime')}
          FROM $table
          LEFT JOIN {$this->getJoinSql('mime')}
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
            $storage = $this->hydrate($r);
            $storage->setMimetype($this->hydrateJoin($r, 'mime'));

            $c->add($storage);
        }

        return $c;
    }


    /**
     * @param Model $object
     * @return bool
     * @throws Schoolbox\Mapper\Exception
     */
    public function delete($object)
    {
        // protect storage objects from accidental deletion by forcing a second, undocument parameter that must be TRUE
        $args = func_get_args();

        if (count($args) < 2 || $args[1] !== true) {
            throw new Schoolbox\Mapper\Exception("Attempt to delete Storage file denied");
        }

        return parent::delete($object);
    }

}
