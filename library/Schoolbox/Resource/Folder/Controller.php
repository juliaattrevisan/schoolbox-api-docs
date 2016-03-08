<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Resource\Folder;

use Alaress\Web\Controller\Base as BaseController;

use Schoolbox\Collection;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Code\Mapper as FolderCodeMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplate;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\Resource\Folder\Code\Model as FolderCode;
use Schoolbox\Curriculum\Node\Mapper as CurriculumNodeMapper;

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

    /**
     * Move folder with an AJAX response
     *
     * @throws \Exception
     */
    public function postMoveFolder()
    {
        FolderMapper::getInstance()->moveFolder($this->getRequest()->post["source"], $this->getRequest()->post["destination"]);
        die(json_encode(['result' => 'success']));
    }

    /**
     * Apply inherited permissions down the tree
     *
     * @throws \Exception
     */
    public function recurseInheritance(\Schoolbox\Resource\Folder\Model $model)
    {
        $folders = FolderMapper::getInstance()->findAllChildren($model->getId(), true);
        foreach ($folders as $folder)

        {
            $folder->setInherit($model->getInherit());
            FolderMapper::getInstance()->save($folder);
            $this->recurseInheritance($folder);
        }
    }

    /**
     * Update a folders details
     *
     * @throws \Exception
     */
    public function postUpdateFolder()
    {
        $folderMapper = FolderMapper::getInstance();
        $folderMapper->setSessionData($_SESSION);

        $model = $folderMapper->createModel()
            ->setName($this->getRequest()->post['name'])
            ->setParent
            (
                $folderMapper->createModel()
                    ->setId($this->getRequest()->post['parent'])
            );

        if ($this->getRequest()->post["inherit"] == 1) {
            $model->enableInherit();
        } else {
            $model->disableInherit();
        }

        // Make sure the user has permission to perform the action

        if (is_numeric($this->getRequest()->post['categoryID']))
        {
            if (Access::getInstance()->getFolderAccess($this->getUser(), [$this->getRequest()->post['categoryID']]) < WRITE_ACCESS)
            {
                // No write access to the folder

                error(6);
            }

            $model->setId($this->getRequest()->post['categoryID']);
        }
        elseif (Access::getInstance()->getFolderAccess($this->getUser(), [$this->getRequest()->post['parent']]) < WRITE_ACCESS)
        {
            // No write access to parent

            error(6);
        }

        // Delete category codes here, allowing them to be recreated when the category gets saved.

        // (I'm not confident that deleting cat codes in the postSave method regardless of whether there are any won't
        // break somewhere else)

        if ($this->getUser()->isSuperuser() && $model->getId()) {
            FolderCodeMapper::getInstance()->deleteByColumn('categoryID', $model->getId());
        }

        if ($this->getRequest()->post['inherit'] == 1)
        {
            // If inheriting permissions we need to remove any existing class codes...

            FolderCodeMapper::getInstance()->deleteByColumn('categoryID', $this->getRequest()->post['categoryID']);

            // ...and user access permissions...

            UserAccessMapper::getInstance()->deleteByColumn("sid", $this->getRequest()->post["categoryID"]);

            // ...and role permissions

            RoleAccessMapper::getInstance()->deleteByColumn("cid", $this->getRequest()->post["categoryID"]);
        }
        else
        {
            // Only superusers get to change class codes

            $codeCollection = new Collection();

            if ($this->getUser()->isSuperuser()) {
                foreach (explode(',', $this->getRequest()->post['code']) as $code) {
                    $trimmedCode = trim($code);
                    if (strlen($trimmedCode)) {
                        $codeModel = new FolderCode();
                        $codeModel->setCode($trimmedCode);
                        $codeCollection->add($codeModel);
                    }
                }

                $model->setCodes($codeCollection);
            }

            // Only privileged users (teachers?) get to change curriculum codes?

            if (isset($this->getRequest()->post['curriculumCodes']))
            {
                $model->setCurriculumNodes(
                    CurriculumNodeMapper::getInstance()
                        ->findByColumn('id', $this->getRequest()->post['curriculumCodes'])
                );
            }
            elseif ($model->getId())
            {
                $existingModel = $folderMapper->findById($model->getId());
                $model->setCurriculumNodes($existingModel->getCurriculumNodes());
            }

            foreach (explode(',', $this->getRequest()->post['code']) as $code)
            {
                $trimmedCode = trim($code);

                if (strlen($trimmedCode))
                {
                    $codeModel = new FolderCode();
                    $codeModel->setCode($trimmedCode);
                    $codeCollection->add($codeModel);
                }
            }

            $model->setCodes($codeCollection);
        }

        $affectedIds = $folderMapper->save($model);

        if ($affectedIds === false)
        {
            // Something went wrong saving

            error(2);
        }

        $model->setId($affectedIds[0]);

        if ($this->getRequest()->post["inherit"] == 0)
        {
            // If role permission collection is defined, reapply permissions (possibly recursively)

            RoleAccessMapper::getInstance()->savePermissions(
                $model,
                $this->getRequest()->post['group'],
                $this->getRequest()->post['recursive']
            );
        }

        // Recurse inheritance

        $this->recurseInheritance($model);

        // HomepageConfig definition

        $homepageConfig = HomepageConfigMapper::getInstance()
            ->createModel()
            ->setCategory($model);

        if ($this->getRequest()->post['template'] == HomepageTemplate::HOMEPAGE_REMOVE)
        {
            // If homepage is to be removed

            HomepageConfigMapper::getInstance()->emptyHomepage($homepageConfig);
            HomepageConfigMapper::getInstance()->delete($homepageConfig);
        }
        elseif ($this->getRequest()->post['template'] > 0)
        {
            // If template defined

            $homepageConfig->setTemplate(HomepageTemplateMapper::getInstance()->findById($this->getRequest()->post['template']));

            // Save homepage config

            HomepageConfigMapper::getInstance()->save($homepageConfig);
            HomepageConfigMapper::getInstance()->resetToTemplate($homepageConfig);
        }

        // Ram it back into its parent object where it belongs

        $model->setHomepage($homepageConfig);

        // No changes in "KEEP_EXISTING" case

        $groupHandler = new \Group();

        if ($this->getRequest()->post['groupcheck'])
        {
            // Prevent students with write access from being able to create folders

            if ($groupHandler->allowOwner($_SESSION['id']))
            {
                // Setup group and forward to group management page

                $groupHandler->createGroup($model->getId());
                $groupHandler->addUser($model->getId(), $_SESSION['id'], 3);

                $this->getResponse()->redirect->afterPost('/group/modify/'.$model->getId());
            }
            else
            {
                error(20);
            }
        }
        else
        {
            // Redirect to / if the homepage was removed from a category, otherwise go back to the category

            if ($this->getRequest()->post['template'] == \Homepage::REMOVE_HOMEPAGE_TEMPLATE_ID)
            {
                $this->getResponse()->redirect->afterPost('/resources/' . $model->getId());
            }
            else
            {
                // If a category code exists, just go back to where you came from

                if (strlen(trim($this->getRequest()->post['code'])))
                {
                    $this->getResponse()->redirect->afterPost(
                        $this->getSession()->getHistory(2)
                    );
                }
                else
                {
                    // Otherwise if you deleted the category code, redirect to categoryId link instead

                    $this->getResponse()->redirect->afterPost('/homepage/' . $model->getId());
                }
            }
        }
    }

    /**
     * Delete folder and all children (superusers only)
     *
     */
    public function getDeleteFolderAll()
    {
        if (!$this->getUser()->isSuperuser())
            error(12);

        try
        {
            $folderMapper = FolderMapper::getInstance();

            $folder = $folderMapper->findById($_GET['deleteAll']);
            $folderMapper->recursiveDelete($folder);
            $_SESSION['resourcesID'] = $folder->getParent()->getId();
        }
        catch (\Exception $e)
        {
            error(2);
        }

        $this->getResponse()->redirect->afterPost(
            $this->getSession()->getHistory(2)
        );
    }
}
