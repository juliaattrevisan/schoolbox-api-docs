<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Resource\Folder;

use Alaress\Web\Controller\Base as BaseController;

use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder;

class Controller extends BaseController
{
    /**
     * Delete folder and do a history redirect
     *
     * @param Int $id
     * @param Int $level
     * @throws \Exception
     */
    public function getDeleteFolderHistory($id, $level)
    {
        $folder = FolderMapper::getInstance()->findById($id);

        $_SESSION['resourcesID'] = $folder->getParent()->getId();

        FolderMapper::getInstance()->deleteFolder($id);

        $this->getResponse()->redirect->afterPost(
            $this->getSession()->getHistory($level)
        );
    }

    /**
     * Delete folder with an AJAX response
     *
     * @param Int $id
     * @throws \Exception
     */
    public function getDeleteFolderAjax($id)
    {
        $folder = FolderMapper::getInstance()->findById($id);

        $_SESSION['resourcesID'] = $folder->getParent()->getId();

        FolderMapper::getInstance()->deleteFolder($id);

        die(json_encode(['result' => 'success']));
    }
}
