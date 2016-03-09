<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Resource\Folder;

use Alaress\Web\Controller\Base as BaseController;

use Schoolbox\Breadcrumbs;
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
use Schoolbox\RoleMapper;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\LinkMapper;
use Schoolbox\Error\Mapper as ErrorMapper;

class Controller extends BaseController
{
    /**
     * Display update folder screen
     *
     * @param Int $id
     * @throws \Exception
     */
    public function getFolderModify($id)
    {
        $folder = \Schoolbox\Resource\Folder\Mapper::getInstance()->findById($id);
        $parent = \Schoolbox\Resource\Folder\Mapper::getInstance()->findById($folder->getParent()->getId());

        $breadcrumbs = [
            [
                'text' => $parent->getName(),
                'href' => '/homepage/' . $parent->getId(),
            ],
            [
                'text' => 'Modify Folder',
                'href' => '',
            ]
        ];

        try {
            $homepageConfig = \Schoolbox\Resource\HomepageConfigMapper::getInstance()->findOneByColumn('cid', $folder->getId());
            $templateId = $homepageConfig->getTemplate()->getId();
        } catch (\Exception $e) {
            $templateId = -1;
        }

        $permissions = RoleAccessMapper::getInstance()->findByColumn('cid', $folder->getId());

        $rolePermissions = [];

        foreach ($permissions as $permission) {
            $rolePermissions[$permission->getRole()->getId()] = $permission->getType();
        }

        $config = new \Schoolbox\Config\Loader();

        $this->getResponse()->content->set(
            array(
                'template' => '@folder/modify.twig',
                'data'     => array(
                    'user'              => $this->getUser(),
                    'breadcrumbs'       => $breadcrumbs,
                    'folder'            => $folder,
                    'parent'            => $parent,
                    'templates'         => HomepageTemplateMapper::getInstance()->findAll(),
                    'templateId'        => $templateId,
                    'roles'             => RoleMapper::getInstance()->findAll(),
                    'rolePermissions'   => $rolePermissions,
                    'database'          => ucfirst($config->get('external_type')),
                    'curriculumTagging' => $config->get('curriculum_tagging_enabled')
                ),
            )
        );
    }

    /**
     * Display create folder screen
     *
     * @param Int $parentId
     * @throws \Exception
     */
    public function getFolderCreate($parentId)
    {
        $parent = \Schoolbox\Resource\Folder\Mapper::getInstance()->findById($parentId);

        $breadcrumbs = [
            [
                'text' => $parent->getName(),
                'href' => '/homepage/' . $parent->getId(),
            ],
            [
                'text' => 'Create Folder',
                'href' => '',
            ]
        ];

        $config = new \Schoolbox\Config\Loader();

        $folder = new Model();
        $folder->enableInherit();

        $this->getResponse()->content->set(
            array(
                'template' => '@folder/modify.twig',
                'data'     => array(
                    'user'              => $this->getUser(),
                    'breadcrumbs'       => $breadcrumbs,
                    'folder'            => $folder,
                    'parent'            => $parent,
                    'templates'         => HomepageTemplateMapper::getInstance()->findAll(),
                    'roles'             => RoleMapper::getInstance()->findAll(),
                    'database'          => ucfirst($config->get('external_type')),
                    'curriculumTagging' => $config->get('curriculum_tagging_enabled')
                ),
            )
        );
    }

    /**
     * Delete folder AJAX
     */
    public function postDeleteFolder()
    {
        $folder = FolderMapper::getInstance()->findById($this->getRequest()->post["id"]);

        $_SESSION['resourcesID'] = $folder->getParent()->getId();

        FolderMapper::getInstance()->delete($folder);

        $this->getResponse()->content->set([
            'format' => 'json',
            'data' => json_encode(['result' => 'success'])
        ]);
    }

    /**
     * Move folder with an AJAX response
     *
     * @throws \Exception
     */
    public function postMoveFolder()
    {
        FolderMapper::getInstance()->moveFolder($this->getRequest()->post["source"], $this->getRequest()->post["destination"]);

        $this->getResponse()->content->set([
            'format' => 'json',
            'data' => json_encode(['result' => 'success'])
        ]);
    }

    /**
     * Update a folders details
     *
     * @throws \Exception
     */
    public function postUpdateFolder()
    {
        $folderMapper = FolderMapper::getInstance();

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

                throw new \Exception(ErrorMapper::getInstance()->getMessageById(6));
            }

            $model->setId($this->getRequest()->post['categoryID']);
        }
        elseif (Access::getInstance()->getFolderAccess($this->getUser(), [$this->getRequest()->post['parent']]) < WRITE_ACCESS)
        {
            // No write access to parent

            throw new \Exception(ErrorMapper::getInstance()->getMessageById(6));
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

            throw new \Exception(ErrorMapper::getInstance()->getMessageById(2));
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

        $folderMapper->recurseInheritance($model);

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
                throw new \Exception(ErrorMapper::getInstance()->getMessageById(20));
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
     * @throws \Exception
     */
    public function postDeleteFolderAll()
    {
        if (!$this->getUser()->isSuperuser()) {
            throw new \Exception(ErrorMapper::getInstance()->getMessageById(12));
        }

        $folderMapper = FolderMapper::getInstance();

        $folder = $folderMapper->findById($this->getRequest()->post["id"]);
        $folderMapper->recursiveDelete($folder);

        $_SESSION['resourcesID'] = $folder->getParent()->getId();

        $this->getResponse()->content->set([
            'format' => 'json',
            'data' => json_encode(['result' => 'success'])
        ]);
    }

    /**
     * Reset folder content
     *
     * @throws \Exception
     */
    public function postFolderReset()
    {
        $folderMapper = FolderMapper::getInstance();

        try
        {
            $folder = $folderMapper->findById($this->getRequest()->post["id"]);

            if (Access::getInstance()->getFolderAccess($this->getUser(), [$folder->getId()]) < WRITE_ACCESS) {
                throw new \Exception(ErrorMapper::getInstance()->getMessageById(6));
            }
        }
        catch (\Exception $e)
        {
            throw new \Exception(ErrorMapper::getInstance()->getMessageById(13));
        }

        // Remove component content

        $homepage = new \Homepage($folder->getId());
        $homepage->emptyHomepage();

        // Delete all files

        $fileMapper = FileMapper::getInstance();
        $fileList = $fileMapper->findForFolders([ $folder->getId() ]);

        foreach ($fileList as $file) {
            $fileMapper->delete($file);
        }

        // Delete all links

        $linkMapper = new LinkMapper();
        $allLinks = $linkMapper->findAllInCategory($folder, array());

        foreach ($allLinks as $currentLink) {
            $linkMapper->delete($currentLink);
        }

        $this->getResponse()->content->set([
            'format' => 'json',
            'data' => json_encode(['result' => 'success'])
        ]);
    }
}
