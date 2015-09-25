<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\My\Folder;


use Schoolbox\Resource\AbstractFolderMapper;
use Schoolbox\Resource\Folder\IFolder;
use Schoolbox\Resource\Folder\ILocator;
use Schoolbox\UserMapper;

/**
 * Class Mapper
 *
 * MyFolder Mapper. Maps myFolder table to portfolio folder objects.
 *
 * @package Schoolbox\Resource\My\Folder
 */
class Mapper extends AbstractFolderMapper implements ILocator
{

    /**
     * {@inheritdoc}
     */
    public function findById($id)
    {
        // special case: 0 == root, but not in db
        if ($id == 0) {
            return $this->createModel()
                        ->setId(0)
                        ->setName('Root')
                        ->setParent($this->createModel()->setId('#'));
        }

        return parent::findById($id);
    }

    /**
     * @return void
     */
    protected function mapping()
    {
        parent::mapping();

        $this->hasTable('myFolder');

        $this->hasPrototype(new Model());

        $this->joins('owner', UserMapper::class, 'id');
    }


    /**
     * Return the folders contained within the folder with the given ID
     *
     * @param int $nodeId
     * @return IFolder[]
     */
    public function findByParentId($nodeId)
    {
        return $this->findByColumnList([
            'parent' => $nodeId,
            'owner'  => $_SESSION['id'] // TODO: FIX. DO NOT MERGE THIS
        ]);
    }

} 