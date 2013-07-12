<?php

/**
 * Storage Engine Object Mapper
 *
 * Provides access to objects within the storage engine in Schoolbox
 *
 * @deprecated Use Schoolbox\FileMapper instead
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2012 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

class Storage_ResourceMapper extends Mapper {

    protected $table = 'storage';

    protected function mapping() {
        $this->hasMap(
            array(
                'column' => 'storage_id',
                'property' => 'id',
                'type' => Mapper::TYPE_INTEGER,
                'identifier' => true

            )
        );

        $this->hasMap(
            array(
                'column' => 'filename',
                'property' => 'filename',
                'type' => Mapper::TYPE_STRING,
            )
        );

        $this->hasMap(
            array(
                'column' => 'hash',
                'property' => 'hash',
                'type' => Mapper::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column' => 'filesize',
                'property' => 'filesize',
                'type' => Mapper::TYPE_INTEGER,
            )
        );
        $this->hasMap(
            array(
                'column' => 'mime',
                'property' => 'mime',
                'type' => Mapper::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column' => 'uploaded_by',
                'property' => 'uploaded_by',
                'type' => Mapper::TYPE_INTEGER,
            )
        );
        $this->hasMap(
            array(
                'column' => 'uploaded_on',
                'property' => 'uploaded_on',
                'type' => Mapper::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column' => 'last_accessed',
                'property' => 'last_accessed',
                'type' => Mapper::TYPE_STRING,
            )
        );


        parent::mapping();
    }

    /**
     * Will create a new storage object based on a file location and filename
     *
     * @deprecated Use Schoolbox\FileMapper to create a new object instead
     *
     * @param $filename
     * @param $location
     * @param $user_id
     * @return Storage_Resource
     */
    public function create($filename, $location, $user_id) {

        $allowedFileHandler = new AllowedFile();
        /* Check allowed file */

        if ($allowedFileHandler->isAllowed($filename) == false) {


            $resource = new Storage_Resource;

            $resource->filename = $filename;
            $resource->setHash($location);
            $resource->filesize = filesize($location);
            $resource->mime = $allowedFileHandler->getMime($allowedFileHandler->getExtension($filename));
            $resource->uploaded_by = $user_id;

            $now = new Alaress_Date_Format(mktime(), 'U');
            $resource->uploaded_on = $now->getDatabase();
            $resource->last_accessed = $now->getDatabase();

            $this->save($resource);

            copy($location, $resource->getFileLocation());
            unlink($location);

            return $resource;
        }

        return false;
    }

    /**
     * @deprecated Use Schoolbox\FileMapper->findOneByColumn('hash') instead
     *
     * @param $hash
     *
     * @return bool|Model
     */
    public function getByHash($hash) {
        $statement = $this->database->prepare(
            "
            SELECT {$this->getColumnString()}
            FROM `{$this->table}`
            WHERE `hash` = :hash
        "
        );

        $statement->bindParam(':hash', $hash);
        $statement->execute();
        $row = $statement->fetch();
        if ($row != false) {
            return $this->hydrate($row);
        } else {
            return false;
        }
    }


    /**
     * @deprecated Use Schoolbox\FileMapper to delete a file instead
     *
     * @param Storage_Resource $object
     *
     * @return void
     */
    public function delete(Storage_Resource $object) {
        unlink($object->getFileLocation());
        parent::delete($object);
    }


}