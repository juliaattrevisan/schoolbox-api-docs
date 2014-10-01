<?php
/**
 * Mapper for the folders in My Files.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox;
use Schoolbox\Mapper;
use Schoolbox\Resource\FolderLocatorInterface;

class MyFolderMapper extends Mapper implements FolderLocatorInterface {

    protected function mapping () {
        $this->hasTable('myFolder');
        $this->hasModel('\\Schoolbox\\MyFolder');

        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ));
        $this->hasMap(array(
            'column'     => 'parent',
            'property'   => 'parent',
            'type'       => self::TYPE_MODEL,
            'join'       => 'parent',
            'join_def'   => array(
                'mapper'    => '\\Schoolbox\\MyFolderMapper',
                'other_key' => 'id',
            ),
        ));
        $this->hasMap(array(
            'column'     => 'owner',
            'property'   => 'owner',
            'type'       => self::TYPE_MODEL,
            'join'       => 'owner',
            'join_def'   => array(
                'mapper'    => '\\Schoolbox\\UserMapper',
                'other_key' => 'id',
            ),
        ));
        $this->hasMap(array(
            'column'     => 'name',
            'property'   => 'name',
            'type'       => self::TYPE_STRING,
        ));
    }


    /**
     * Return the folders contained within the folder with the given ID
     *
     * @param int $nodeId
     * @return FolderInterface[]
     */
    public function findByParentId($nodeId)
    {
        return $this->findByColumnList(array(
            'parent' => $nodeId,
            'owner'  => $_SESSION['id'] // TODO: FIX. DO NOT MERGE THIS
        ));
    }
}
