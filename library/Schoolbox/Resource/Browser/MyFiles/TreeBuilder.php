<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser\MyFiles;


use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Resource;
use Schoolbox\Resource\Browser\AbstractTreeBuilder;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\IFolder;
use Schoolbox\User;

class TreeBuilder extends AbstractTreeBuilder
{

    /** @var User */
    protected $user;


    /**
     * @param Folder\ILocator $locator
     * @param User            $user
     */
    public function __construct(Folder\ILocator $locator, User $user)
    {
        parent::__construct($locator);
        $this->user = $user;
    }


    /**
     * @param IFolder $folder
     * @throws Resource\Exception
     * @return array
     */
    protected function buildFolderNode(IFolder $folder)
    {
        return $this->generateNodeArray($folder, 'folder', null, [
            'data-permission' => 'full'
        ]);
    }


    /**
     * @param int $nodeId
     * @return Collection|IFolder[]
     */
    protected function getNodeFolders($nodeId)
    {
        /** @var Mapper $locator */
        $locator = $this->folderLocator;

        // TODO cache this
        return $locator->findByColumnList([
            'owner'  => $this->user->getId(),
            'parent' => $nodeId,
        ]);
    }


    /**
     * @param int $nodeId
     * @return string
     */
    protected function getFolderPermission($nodeId)
    {
        return 'full';
    }

}