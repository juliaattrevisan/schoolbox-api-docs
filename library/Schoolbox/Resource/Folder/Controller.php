<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Resource\Folder;

use Alaress\Web\Controller\Base as BaseController;
use ArrayAccess;
use Homepage;
use OutOfBoundsException;
use RangeException;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Curriculum;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplate;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\Resource\LinkMapper;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use UnexpectedValueException;

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
        $folderMapper = Folder\Mapper::getInstance();
        $homepageConfigMapper = HomepageConfigMapper::getInstance();
        $homepageTemplateMapper = HomepageTemplateMapper::getInstance();
        $roleAccessMapper = RoleAccessMapper::getInstance();
        $roleMapper = RoleMapper::getInstance();
        $errorMapper = ErrorMapper::getInstance();
        
        try {
            $folder = $folderMapper->findById($id);
            $parent = $folderMapper->findById($folder->getParent()->getId());
        } catch (\Exception $e) {
            $errorMapper->generateException(ErrorMapper::ERROR_CATEGORY_DELETED, $e);
        }

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
            $homepageConfig = $homepageConfigMapper->findOneByColumn('cid', $folder->getId());
            $templateId = $homepageConfig->getTemplate()->getId();
        } catch (\Exception $e) {
            $templateId = HomepageTemplateMapper::TEMPLATE_NONE;
        }

        $permissions = $roleAccessMapper->findByColumn('cid', $folder->getId());

        $rolePermissions = [];

        foreach ($permissions as $permission) {
            $rolePermissions[$permission->getRole()->getId()] = $permission->getType();
        }

        $config = new ConfigLoader();

        $this->getResponse()->content->set(
            array(
                'template' => '@folder/modify.twig',
                'data'     => array(
                    'user'              => $this->getUser(),
                    'breadcrumbs'       => $breadcrumbs,
                    'folder'            => $folder,
                    'parent'            => $parent,
                    'templates'         => $homepageTemplateMapper->findAll(),
                    'templateId'        => $templateId,
                    'roles'             => $roleMapper->findAll(),
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
        $folderMapper = FolderMapper::getInstance();
        $homepageTemplateMapper = HomepageTemplateMapper::getInstance();
        $roleMapper = RoleMapper::getInstance();
        $errorMapper = ErrorMapper::getInstance();

        try {
            $parent = $folderMapper->findById($parentId);
        } catch (\Exception $e) {
            $errorMapper->generateException(ErrorMapper::ERROR_CATEGORY_DELETED, $e);
        }

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

        $config = new ConfigLoader();

        $folder = new Model();

        // Folders that are not in the root note should inherit by default to reduce the user interface

        if ($parentId != FolderMapper::FOLDER_ROOT)
            $folder->enableInherit();

        $this->getResponse()->content->set(
            array(
                'template' => '@folder/modify.twig',
                'data'     => array(
                    'user'              => $this->getUser(),
                    'breadcrumbs'       => $breadcrumbs,
                    'folder'            => $folder,
                    'parent'            => $parent,
                    'templates'         => $homepageTemplateMapper->findAll(),
                    'roles'             => $roleMapper->findAll(),
                    'database'          => ucfirst($config->get('external_type')),
                    'curriculumTagging' => $config->get('curriculum_tagging_enabled')
                ),
            )
        );
    }


    /**
     * Delete folder AJAX
     *
     */
    public function postDeleteFolder()
    {
        $folderMapper = FolderMapper::getInstance();
        $errorMapper = ErrorMapper::getInstance();
        $access = Access::getInstance();

        // Find the folder to be deleted

        try {
            $folder = $folderMapper->findById($this->getRequest()->post["id"]);
        } catch (\Exception $e) {
            $errorMapper->generateException(ErrorMapper::ERROR_CATEGORY_DELETED, $e);
        }

        // Make sure they have access to delete the forlder

        if ($access->getFolderAccess($this->getUser(), [$folder->getId()]) < WRITE_ACCESS) {
            $errorMapper->generateException(ErrorMapper::ERROR_PERM_RESOURCE_DELETE);
        }

        $_SESSION['resourcesID'] = $folder->getParent()->getId();

        $folderMapper->delete($folder);

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
        $errorMapper = ErrorMapper::getInstance();
        $folderMapper = FolderMapper::getInstance();

        if ($this->getRequest()->post["source"] != $this->getRequest()->post["destination"])
        {
            // Check the folders exist

            try
            {
                $source = $folderMapper->findById($this->getRequest()->post["source"]);
                $destination = $folderMapper->findById($this->getRequest()->post["destination"]);
            }
            catch (\Exception $e)
            {
                // One of the folders didn't exist

                $errorMapper->generateException(ErrorMapper::ERROR_CATEGORY_DELETED, $e);
            }

            // Move the folder

            $folderMapper->moveFolder($source->getId(), $destination->getId());

            // Yippee, all done.

            $this->getResponse()->content->set([
                'format' => 'json',
                'data' => json_encode(['result' => 'success'])
            ]);
        }
        else
        {
            // Doesn't make sense, source and destination folders the same

            $errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }
    }


    /**
     * Update a folders details
     *
     * @throws \Exception
     */
    public function postUpdateFolder()
    {
        $errorMapper = ErrorMapper::getInstance();

        try {
            $user        = $this->getUser();
            $model       = $this->persistFolder($this->getRequest()->post, $user);
            $newLocation = $this->createGroup($this->getRequest()->post, $model, $user);

            $this->getResponse()->redirect->afterPost($newLocation);

        } catch (RangeException $e) {
            // not enough info submitted to the form
            $errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING, $e);

        } catch (OutOfBoundsException $e) {
            // you lack permission to do the things
            $errorMapper->generateException(ErrorMapper::ERROR_PERM_CATEGORY_WRITE, $e);

        } catch (UnexpectedValueException $e) {
            // you lack permission to do the other things
            $errorMapper->generateException(ErrorMapper::ERROR_PERM_CATEGORY_CREATE, $e);
        }
    }





    /**
     * This is public for now because /cms/categoryForm.php needs to reference it
     *
     * @param  ArrayAccess|array $postData  the array structure we'll use to construct this folder
     * @param  User              $user      the effecting user
     * @throws OutOfBoundsException         if you lack permission to perform functions
     * @throws RangeException               if the database fails to save your folder
     * @return Folder\Model                 representing the freshly saved folder
     */
    public function persistFolder($postData, User $user)
    {
        $folderMapper     = Mapper::getInstance();
        $codeMapper       = Code\Mapper::getInstance();
        $userAccessMapper = UserAccessMapper::getInstance();
        $roleAccessMapper = RoleAccessMapper::getInstance();
        $access           = Access::getInstance();

        $model = $folderMapper->createModel()
                      ->setName($postData['name'])
                      ->setParent(
                          $folderMapper->createModel()
                               ->setId($postData['parent'])
                      );

        if ($postData["inherit"] == 1) {
            $model->enableInherit();
        } else {
            $model->disableInherit();
        }

        // Make sure the user has permission to perform the action
        if (is_numeric($postData['categoryID'])) {
            // check this user has write access
            if ($access->getFolderAccess($user, [ $postData['categoryID'] ]) < UserAccess::ACCESS_WRITE) {
                throw new OutOfBoundsException("I find your lack of access disturbing.");

            }
            $model->setId($postData['categoryID']);

        } elseif ($access->getFolderAccess($user, [ $postData['parent'] ]) < UserAccess::ACCESS_WRITE) {
            // check this user has access to the parent folder
            throw new OutOfBoundsException("I find your lack of access disturbing.");
        }

        if ($postData['inherit'] == 1) {

            // If inheriting permissions we need to remove any existing class codes...
            $codeMapper->deleteByColumn('categoryID', $postData['categoryID']);

            // ...and user access permissions...
            $userAccessMapper->deleteByColumn("sid", $postData["categoryID"]);

            // ...and role permissions
            $roleAccessMapper->deleteByColumn("cid", $postData["categoryID"]);

        } else {
            // apply codes
            $this->setCodesOnFolder($model, $postData['code'], $postData['curriculumCodes'], $user);
        }

        $affectedIds = $folderMapper->save($model);

        if ($affectedIds === false) {
            // something went wrong
            throw new RangeException("Failed to save folder.");
        }

        if ($postData["inherit"] == 0) {
            // only allow recursive permissions to supers
            $recursive = $user->isSuperuser() && $postData['recursive'];

            // If rolepermission collection is defined, reapply permissions (possibly recursively)
            $roleAccessMapper->savePermissions($model, $postData['group'], $recursive);
        }

        // Recurse inheritance
        $this->recurseInheritance($model);

        // HomepageConfig definition
        $this->saveHomepageConfig($model, $postData['template']);

        return $model;
    }


    /**
     * Put the given code lists onto the given folder
     *
     * @param Folder\Model $model
     * @param string       $classCodeString
     * @param array        $curriculumCodes
     * @param User         $user
     * @throws MapperException
     */
    protected function setCodesOnFolder(Folder\Model $model, $classCodeString, array $curriculumCodes, User $user)
    {
        $codeMapper           = Code\Mapper::getInstance();
        $curriculumNodeMapper = Curriculum\Node\Mapper::getInstance();
        $folderMapper         = Mapper::getInstance();

        $codeCollection       = $codeMapper->createCollection();

        // Only superusers get to change class codes
        if ($user->isSuperuser()) {
            foreach (explode(',', $classCodeString) as $code) {
                $trimmedCode = trim($code);
                if (strlen($trimmedCode)) {
                    $codeModel = $codeMapper->createModel();
                    $codeModel->setCode($trimmedCode);
                    $codeCollection->add($codeModel);
                }
            }
            $model->setCodes($codeCollection);
        }

        // Delete category codes here, allowing them to be recreated when the category gets saved.
        // (I'm not confident that deleting cat codes in the postSave method
        //  regardless of whether there are any won't break somewhere else)
        if ($user->isSuperuser() && $model->getId() && count($codeCollection) === 0) {
            $codeMapper->deleteByColumn('categoryID', $model->getId());
        }

        // Only privileged users (teachers?) get to change curriculum codes?
        if (isset($curriculumCodes) && count($curriculumCodes)) {
            $model->setCurriculumNodes(
                $curriculumNodeMapper->findByColumn('id', $curriculumCodes)
            );

        } elseif ($model->getId()) {
            $existingModel = $folderMapper->findById($model->getId());
            $model->setCurriculumNodes($existingModel->getCurriculumNodes());

        }

    }


    /**
     * Save the given template for the given folder
     *
     * @param Folder\Model $model
     * @param int          $template
     * @throws RangeException if the specified template is not acceptable
     */
    protected function saveHomepageConfig(Folder\Model $model, $template)
    {
        $confMapper = HomepageConfigMapper::getInstance();
        $tplMapper  = HomepageTemplateMapper::getInstance();

        $homepageConfig = $confMapper->createModel()->setCategory($model);

        // if homepage is to be removed
        try {
            if ($template == HomepageTemplate::HOMEPAGE_REMOVE) {
                $confMapper->emptyHomepage($homepageConfig);
                $confMapper->delete($homepageConfig);

            } elseif ($template == HomepageTemplate::HOMEPAGE_KEEP_EXISTING) {
                // we're done. get out.
                return;

            } elseif ($template > 0) {
                // if template defined
                $homepageConfig->setTemplate($tplMapper->findById($template));

                // Save homepage config
                $confMapper->save($homepageConfig);
                $confMapper->resetToTemplate($homepageConfig);

            } else {
                throw new RangeException("Invalid template ID specified");
            }
        } catch (MapperException $e) {
            throw new RangeException("Invalid template ID specified", 0, $e);
        }

        // Ram it back into its parent object where it belongs
        $model->setHomepage($homepageConfig);
    }


    /**
     * Recursively apply inheritance. Why? Not sure. Cut-Paste from /cms/categoryForm.php
     *
     * @param Folder\Model $model
     */
    protected function recurseInheritance(Folder\Model $model)
    {
        $folderMapper = Mapper::getInstance();

        $folders = $folderMapper->findAllChildren($model->getId(), true);
        foreach ($folders as $folder) {
            $folder->setInherit($model->getInherit());
            $folderMapper->save($folder);
            $this->recurseInheritance($folder);
        }
    }


    /**
     * Helper function to persist a group for a folder
     *
     * @param ArrayAccess $postData the array structure we'll use to construct this group
     * @param Model       $model    the Folder model we're working with
     * @param User        $user     the effecting user
     * @throws \Exception
     * @return string
     */
    protected function createGroup($postData, Model $model, User $user)
    {
        // No changes in "KEEP_EXISTING" case
        $roleAccessMapper = RoleAccessMapper::getInstance();
        $userAccessMapper = UserAccessMapper::getInstance();
        $groupMapper      = GroupMapper::getInstance();

        if ($postData['groupcheck']) {

            // Prevent students with write access from being able to create folders
            if ($roleAccessMapper->roleMayOwnFolders($user->getRole())) {
                // Setup group and forward to group management page

                try {
                    // confirm the group exists
                    $groupMapper->findOneByColumn('cid', $model->getId());

                } catch (MapperException $e) {
                    // doesn't exist
                    $group = $groupMapper->createModel()
                        ->setCategory($model)
                        ->setType(Group::PRIVATE_GROUP)
                        ->setForceFollow(false);

                    // create the group
                    $groupMapper->save($group);
                }

                // assign group ownership to the effecting user
                $uA = $userAccessMapper->createModel()
                    ->setService(UserAccess::SERVICE_HOMEPAGE)
                    ->setAccessLevel(UserAccess::ACCESS_FULL)
                    ->setServiceIdentifier($model->getId())
                    ->setUser($user);

                $userAccessMapper->save($uA);

                // TODO replace with routeLookup once it exists
                return '/group/modify/'.$model->getId();

            } else {
                throw new UnexpectedValueException("I find your lack of access disturbing.");
            }

        } elseif ($postData['template'] == Homepage::REMOVE_HOMEPAGE_TEMPLATE_ID) {
            // Redirect to / if the homepage was removed from a category, otherwise go back to the category
            // TODO replace with routeLookup once it exists
            return '/resources/' . $model->getId();

        } elseif (strlen(trim($postData['code']))) {
            // If a category code exists, just go back to where you came from
            return $this->getSession()->getHistory(2);

        }

        // Otherwise if you deleted the category code, redirect to categoryId link instead
        // TODO replace with routeLookup once it exists
        return '/homepage/' . $model->getId();
    }


    /**
     * Delete folder and all children (superusers only)
     *
     * @throws \Exception
     */
    public function postDeleteFolderAll()
    {
        $errorMapper = ErrorMapper::getInstance();
        $folderMapper = FolderMapper::getInstance();

        if (!$this->getUser()->isSuperuser()) {
            $errorMapper->generateException(ErrorMapper::ERROR_PERM_CATEGORY_DELETE_SUBFOLDERS);
        }

        try {
            $folder = $folderMapper->findById($this->getRequest()->post["id"]);
        } catch (\Exception $e ) {
            $errorMapper->generateException(ErrorMapper::ERROR_CATEGORY_DELETED, $e);
        }
        
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
        $access = Access::getInstance();
        $errorMapper = ErrorMapper::getInstance();
        $folderMapper = FolderMapper::getInstance();
        $fileMapper = FileMapper::getInstance();

        try
        {
            $folder = $folderMapper->findById($this->getRequest()->post["id"]);

            if ($access->getFolderAccess($this->getUser(), [$folder->getId()]) < WRITE_ACCESS) {
                $errorMapper->generateException(ErrorMapper::ERROR_PERM_CATEGORY_WRITE, null);
            }
        }
        catch (\Exception $e)
        {
            $errorMapper->generateException(ErrorMapper::ERROR_CATEGORY_DELETED, $e);
        }

        // Remove component content

        $homepage = new Homepage($folder->getId());
        $homepage->emptyHomepage();

        // Delete all files

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
