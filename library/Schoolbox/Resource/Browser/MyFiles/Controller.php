<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser\MyFiles;


use Schoolbox\Resource\Browser\NodeFactory;
use Schoolbox\Resource\Folder\IFolder as FolderInterface;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Browser\AbstractController;

class Controller extends AbstractController
{

    /**
     * @param int $folderId
     * @return FolderInterface
     */
    protected function getFolder($folderId)
    {
        try {
            return $this->folderMapper->findById($folderId);

        } catch (Mapper\Exception\Deleted $e) {
            // Deleted: load the parent (recursively)
            return $this->getFolder($e->getDeletedObject()->getParent()->getId());

        } catch (Mapper\Exception $e) {
            return $this->folderMapper->createModel()->setId(0);

        }
    }


    /**
     * @param FolderInterface $folder
     * @return array
     */
    protected function getBreadcrumbs(FolderInterface $folder)
    {
        try {
            $node = $this->nodeFactory->build(NodeFactory::MYFILE, $folder->getId());
            $tree = $node->render();

            return $tree['breadcrumbs'];

        } catch (\InvalidArgumentException $e) {
            return [];
        }
    }


    /**
     * @param array $folderIds
     * @return int
     */
    protected function getAccess(array $folderIds)
    {
        // you'll always have access to the folders
        return UserAccess::ACCESS_FULL;
    }

} 