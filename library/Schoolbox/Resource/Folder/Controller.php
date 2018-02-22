<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Resource\Folder;

use Alaress\Color\ColorFactory;
use Alaress\Web\Controller\Base as BaseController;
use ArrayAccess;
use Homepage;
use InvalidArgumentException;
use OutOfBoundsException;
use RangeException;
use DomainException;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Curriculum;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Exception as ResourceException;
use Schoolbox\Resource\Exception;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplate;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\Resource\LinkMapper;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use UnexpectedValueException;
use Schoolbox\Resource\Folder\Code\Mapper as ClassCodeMapper;
use Timetable_ColourMapper;
use Schoolbox\Collection;

class Controller extends BaseController
{

    /** @var Mapper */
    protected $folderMapper;

    /** @var HomepageConfigMapper */
    protected $homepageConfigMapper;

    /** @var HomepageTemplateMapper */
    protected $homepageTemplateMapper;

    /** @var RoleAccessMapper */
    protected $roleAccessMapper;

    /** @var UserAccessMapper */
    protected $userAccessMapper;

    /** @var RoleMapper */
    protected $roleMapper;

    /** @var ErrorMapper */
    protected $errorMapper;

    /** @var Access */
    protected $access;

    /** @var ClassCodeMapper */
    protected $classCodeMapper;

    /** @var Curriculum\Node\Mapper */
    protected $curriculumNodeMapper;

    /** @var GroupMapper */
    protected $groupMapper;

    /** @var FileMapper */
    protected $fileMapper;


    /**
     * Folder\Controller constructor.
     * TODO DI
     */
    public function __construct()
    {
        $this->setFolderMapper(Mapper::getInstance());
        $this->setHomepageConfigMapper(HomepageConfigMapper::getInstance());
        $this->setHomepageTemplateMapper(HomepageTemplateMapper::getInstance());
        $this->setRoleAccessMapper(RoleAccessMapper::getInstance());
        $this->setUserAccessMapper(UserAccessMapper::getInstance());
        $this->setRoleMapper(RoleMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
        $this->setAccess(Access::getInstance());
        $this->setClassCodeMapper(ClassCodeMapper::getInstance());
        $this->setCurriculumNodeMapper(Curriculum\Node\Mapper::getInstance());
        $this->setGroupMapper(GroupMapper::getInstance());
        $this->setFileMapper(FileMapper::getInstance());
    }

    /**
     * Display update folder screen
     *
     * @api GET /resource/folder/modify/:folderId
     * @param $id
     * @throws SchoolboxException
     */
    public function getFolderModify($id)
    {
        $access = $this->getAccess();
        $userAccess = $access->getFolderAccess($this->getUser(), $id);
        $folderMapper = $this->getFolderMapper();
        $homepageConfigMapper = $this->getHomepageConfigMapper();
        $homepageTemplateMapper = $this->getHomepageTemplateMapper();
        $roleAccessMapper = $this->getRoleAccessMapper();
        $roleMapper = $this->getRoleMapper();
        $errorMapper = $this->getErrorMapper();
        if ($userAccess < UserAccess::ACCESS_WRITE) {
            throw $errorMapper->generateException(ErrorMapper::ERROR_PERM_CATEGORY_WRITE);
        }

        try {
            $folder = $folderMapper->findById($id);
            $parent = $folderMapper->findById($folder->getParent()->getId());
        } catch (\Exception $e) {
            throw $errorMapper->generateException(ErrorMapper::ERROR_CATEGORY_DELETED, $e);
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
            $homepageConfigMapper->findOneByColumn('cid', $folder->getId());
            $templateId = HomepageTemplateMapper::TEMPLATE_KEEP_EXISTING;
        } catch (\Exception $e) {
            $templateId = HomepageTemplateMapper::TEMPLATE_NONE;
        }

        $permissions = $roleAccessMapper->findByColumn('cid', $folder->getId());

        $rolePermissions = [];

        foreach ($permissions as $permission) {
            $rolePermissions[$permission->getRole()->getId()] = $permission->getType();
        }

        $homepageTemplates = $homepageTemplateMapper->findAll(['order' => ['name' => 'asc']]);
        $homepageTemplates->prepend($homepageTemplateMapper->getKeepExistingHomepageTemplate());

        $config = new ConfigLoader();

        $zone = new Zone('coverImage');
        $zone
            ->setMulti(false)
            ->setAttachPrompt('Attach a cover image')
            ->setAttachmentsLabel('')
            ->setMimeFilter('image/*')
        ;

        if ($folder->hasCoverImage()) {
            $zone->attachFiles([ $folder->getCoverImage()->getHash() ]);
        }

        $this->getResponse()->content->set(
            array(
                'template' => '@folder/modify.twig',
                'data'     => array(
                    'user'              => $this->getUser(),
                    'breadcrumbs'       => $breadcrumbs,
                    'folder'            => $folder,
                    'parent'            => $parent,
                    'templates'         => $homepageTemplates,
                    'templateId'        => $templateId,
                    'roles'             => $roleMapper->findAll(),
                    'rolePermissions'   => $rolePermissions,
                    'database'          => ucfirst($config->get('external_type')),
                    'curriculumTagging' => $config->get('curriculum_tagging_enabled'),
                    'userAccess'        => $userAccess,
                    'deny'              => UserAccess::ACCESS_DENY,
                    'read'              => UserAccess::ACCESS_READ,
                    'write'             => UserAccess::ACCESS_WRITE,
                    'moderate'          => UserAccess::ACCESS_FULL,
                    'coverImageZone'    => $zone->getTwigContext(),
                ),
            )
        );
    }

    /**
     * Display create folder screen
     *
     * @param $parentId
     * @throws SchoolboxException
     */
    public function getFolderCreate($parentId)
    {
        $access = $this->getAccess();
        $folderMapper = $this->getFolderMapper();
        $homepageTemplateMapper = $this->getHomepageTemplateMapper();
        $roleMapper = $this->getRoleMapper();
        $errorMapper = $this->getErrorMapper();

        if ($access->getFolderAccess($this->getUser(), $parentId) < UserAccess::ACCESS_WRITE) {
            throw $errorMapper->generateException(ErrorMapper::ERROR_PERM_CATEGORY_WRITE);
        }

        try {
            $parent = $folderMapper->findById($parentId);
        } catch (\Exception $e) {
            throw $errorMapper->generateException(ErrorMapper::ERROR_CATEGORY_DELETED, $e);
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

        // Default to parent's permissions
        $permissions = $this->getRoleAccessMapper()->findByColumn('cid', $parent->getId());
        $rolePermissions = [];
        foreach ($permissions as $permission) {
            $rolePermissions[$permission->getRole()->getId()] = $permission->getType();
        }

        // Folders that are not in the root note should inherit by default to reduce the user interface

        if ($parentId != Mapper::FOLDER_ROOT)
            $folder->enableInherit();

        $zone = new Zone('coverImage');
        $zone
            ->setMulti(false)
            ->setAttachPrompt('Attach a cover image')
            ->setAttachmentsLabel('')
            ->setMimeFilter('image/*')
        ;

        $this->getResponse()->content->set(
            array(
                'template' => '@folder/modify.twig',
                'data'     => array(
                    'user'              => $this->getUser(),
                    'breadcrumbs'       => $breadcrumbs,
                    'folder'            => $folder,
                    'parent'            => $parent,
                    'templates'         => $homepageTemplateMapper->findAll(['order' => ['name' => 'asc']]),
                    'roles'             => $roleMapper->findAll(),
                    'rolePermissions'   => $rolePermissions,
                    'database'          => ucfirst($config->get('external_type')),
                    'curriculumTagging' => $config->get('curriculum_tagging_enabled'),
                    'deny'              => UserAccess::ACCESS_DENY,
                    'read'              => UserAccess::ACCESS_READ,
                    'write'             => UserAccess::ACCESS_WRITE,
                    'moderate'          => UserAccess::ACCESS_FULL,
                    'coverImageZone'    => $zone->getTwigContext(),
                ),
            )
        );
    }

    /**
     * Delete folder AJAX
     *
     * @throws SchoolboxException
     */
    public function postDeleteFolder()
    {
        $folderMapper = $this->getFolderMapper();
        $errorMapper = $this->getErrorMapper();
        $access = $this->getAccess();

        // Find the folder to be deleted

        try {
            $folder = $folderMapper->findById($this->getRequest()->post["id"]);
        } catch (\Exception $e) {
            throw $errorMapper->generateException(ErrorMapper::ERROR_CATEGORY_DELETED, $e);
        }

        // Make sure they have access to delete the folder

        if ($access->getFolderAccess($this->getUser(), $folder->getId()) < WRITE_ACCESS) {
            throw $errorMapper->generateException(ErrorMapper::ERROR_PERM_RESOURCE_DELETE);
        }

        $this->recursiveDelete($this->getRequest()->post["id"], $this->getUser());

        $this->getResponse()->content->set([
            'format' => 'json',
            'data' => json_encode(['result' => 'success'])
        ]);
    }

    /**
     * Move folder with an AJAX response
     *
     * @throws SchoolboxException
     */
    public function postMoveFolder()
    {
        $access = $this->getAccess();
        $errorMapper = $this->getErrorMapper();
        $folderMapper = $this->getFolderMapper();

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
                throw $errorMapper->generateException(ErrorMapper::ERROR_CATEGORY_DELETED, $e);
            }

            if ($access->getFolderAccess($this->getUser(), $source->getId()) < WRITE_ACCESS)
                throw $errorMapper->generateException(ErrorMapper::ERROR_PERM_RESOURCE_DELETE);

            if ($access->getFolderAccess($this->getUser(), $destination->getId()) < WRITE_ACCESS)
                throw $errorMapper->generateException(ErrorMapper::ERROR_PERM_RESOURCE_DELETE);

            // Move the folder

            $folderMapper->moveFolder($source->getId(), $destination->getId(), $this->getUser());

            // Yippee, all done.

            $this->getResponse()->content->set([
                'format' => 'json',
                'data' => json_encode(['result' => 'success'])
            ]);
        }
        else
        {
            // Doesn't make sense, source and destination folders the same

            throw $errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }
    }

    /**
     * Update a folders details
     *
     * @api POST /resource/folder/update
     * @level 156
     * @var int|null $_POST['categoryID'] If absent, this is an INSERT, if present, an UPDATE
     * @var string   $_POST['name']
     * @var int      $_POST['parent']
     * @var bool     $_POST['inherit']    Whether to inherit permissions
     * @var int      $_POST['template']
     * @var int[]    $_POST['group']      Maps role to permission level
     * @var bool     $_POST['recursive']  If true && user is SU, applies $data['inherit'] to entire subtree
     * @var string   $_POST['code']       Comma-separated codes
     * @throws SchoolboxException
     */
    public function postUpdateFolder()
    {
        $errorMapper = $this->getErrorMapper();

        try {
            $user        = $this->getUser();
            $model       = $this->persistFolder($this->getRequest()->post, $user);
            $newLocation = $this->createGroup($this->getRequest()->post, $model, $user);

            $this->getResponse()->redirect->afterPost($newLocation);

        } catch (RangeException $e) {
            // not enough info submitted to the form
            throw $errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING, $e);

        } catch (OutOfBoundsException $e) {
            // you lack permission to do the things
            throw $errorMapper->generateException(ErrorMapper::ERROR_PERM_CATEGORY_WRITE, $e);

        } catch (UnexpectedValueException $e) {
            // you lack permission to do the other things
            throw $errorMapper->generateException(ErrorMapper::ERROR_PERM_CATEGORY_CREATE, $e);
        }
    }

    /**
     * This is public for now because /cms/categoryForm.php needs to reference it
     *
     * @param  ArrayAccess|array $postData        the array structure we'll use to construct this folder
     * @var int|null $postData['categoryID']      If absent, this is an INSERT, if present, an UPDATE
     * @var string   $postData['name']
     * @var int      $postData['parent']
     * @var bool     $postData['inherit']         Whether to inherit permissions
     * @var int      $postData['template']
     * @var int[]    $postData['curriculumCodes']
     * @var int[]    $postData['group']           Maps role to permission level
     * @var bool     $postData['recursive']       If true && $user is SU, applies $postData['inherit'] to entire subtree
     * @var string   $postData['code']            Comma-separated codes
     * @param  User              $user            the effecting user
     * @throws OutOfBoundsException               if you lack permission to perform functions
     * @throws RangeException                     if the database fails to save your folder
     * @return Model                              representing the freshly saved folder
     */
    public function persistFolder($postData, User $user)
    {
        $folderMapper     = $this->getFolderMapper();
        $userAccessMapper = $this->getUserAccessMapper();
        $roleAccessMapper = $this->getRoleAccessMapper();
        $access           = $this->getAccess();

        $model = $folderMapper->createModel()
            ->setName($postData['name'])
            ->setParent(
                $folderMapper->createModel()
                    ->setId($postData['parent'])
            );

        // Make sure the user has permission to perform the action

        // Is this an UPDATE ?
        if (is_numeric($postData['categoryID'])) {
            // check this user has write access
            if ($access->getFolderAccess($user, $postData['categoryID']) < UserAccess::ACCESS_WRITE) {
                throw new OutOfBoundsException("I find your lack of access disturbing.");
            }
            $model->setId($postData['categoryID']);
        }
        // Or is action INSERT ?
        elseif ($access->getFolderAccess($user, $postData['parent']) < UserAccess::ACCESS_WRITE) {
            // check this user has access to the parent folder
            throw new OutOfBoundsException("I find your lack of access disturbing.");
        }

        // Is user SU or creating new Folder ?
        if($user->isSuperuser() || !$model->getId()){
            $existingFolder = null;
        } else {
            try {
                $existingFolder = $folderMapper->findById($model->getId());
            } catch (MapperException\Deleted $e) {
                $existingFolder = $e->getDeletedObject();
            } catch (MapperException $e) {
                $existingFolder = null;
            }
        }

        // Either user is SU, or insertion of new Folder, or Folder did not have Class Codes
        if($user->isSuperuser() || !$existingFolder || !$existingFolder->getCodes()->count()){
            if (1 == $postData["inherit"]) {
                $model->enableInherit();
            } else {
                $model->disableInherit();
            }
        // Else user is not a SU, folder exists, and it has Class Codes
        } else {
            // Ignore form data, and use existing values.
            if ($existingFolder->getInherit()) {
                $model->enableInherit();
            } else {
                $model->disableInherit();
            }
        }

        if ($model->getInherit()) {

            // If inheriting permissions we need to remove any user access permissions...
            $userAccessMapper->deleteByColumnList([
                "sid"     => $postData["categoryID"],
                "service" => UserAccess::SERVICE_HOMEPAGE,
            ]);

            // ...and role permissions
            $roleAccessMapper->deleteByColumn("cid", $postData["categoryID"]);
        }

        // apply Class Codes
        if($user->isSuperuser()){
            $classCodes = $model->getInherit() ? '' : $postData['code'];

            $classCodeArray = explode(',', $classCodes);

            foreach ($classCodeArray as $code) {
                if (strlen(trim($code)) > 20) {
                    throw $this->errorMapper->generateException(ErrorMapper::ERROR_CODE_TOO_LONG);
                }
            }

            $this->setClassCodesOnFolder($model, $classCodes, $user);
        } elseif($existingFolder) {
            $model->setCodes($existingFolder->getCodes());
        }

        // apply Curriculum codes
        $curriculumCodes = (is_array($postData['curriculumCodes']) ? $postData['curriculumCodes'] : []);
        $this->setCurriculumCodesOnFolder($model, $curriculumCodes);

        // apply candy
        try {
            $model->setColour((new ColorFactory())->build($postData['colour']));
        } catch (InvalidArgumentException $e) {
            $model->setColour(null);
        }

        try {
            $model->setCoverImage((new CollectorFactory())->build($postData['attachment'] ?: [])->getFirst());

        } catch (ResourceException $e) {
            // no attachment, nothing to do
        }

        $affectedIds = $folderMapper->save($model);

        if ($affectedIds === false) {
            // something went wrong
            throw new RangeException("Failed to save folder.");
        }

        // only allow recursive permissions to supers
        $recursive = $user->isSuperuser() && $postData['recursive'];

        if (!$model->getInherit()) {
            // If rolepermission collection is defined, reapply permissions (possibly recursively)
            $roleAccessMapper->savePermissions($model, $postData['group'], $recursive);
        }

        if ($recursive) {
            // Recurse inheritance
            $this->recurseInheritance($model);
        }

        // HomepageConfig definition
        $this->saveHomepageConfig($model, $postData['template']);

        return $model;
    }

    /**
     * Put the given Class code lists onto the given folder
     *
     * @param Model $model
     * @param string       $classCodeString
     * @param User         $user
     * @throws MapperException
     */
    protected function setClassCodesOnFolder(Model $model, $classCodeString, User $user)
    {
        // Only superusers get to change class codes
        if(!$user->isSuperuser()){
            throw new DomainException("Only SuperUsers may set Class Codes");
        }

        $classCodeMapper = $this->getClassCodeMapper();
        $codeCollection  = $classCodeMapper->createCollection();

        foreach (explode(',', $classCodeString) as $code) {
            $trimmedCode = trim($code);
            if (strlen($trimmedCode)) {
                $codeModel = $classCodeMapper->createModel();
                $codeModel->setCode($trimmedCode);
                $codeCollection->add($codeModel);
            }
        }
        $model->setCodes($codeCollection);

        // Delete category codes here, allowing them to be recreated when the category gets saved.
        // (I'm not confident that deleting cat codes in the postSave method
        //  regardless of whether there are any won't break somewhere else)
        if ($model->getId() && count($codeCollection) === 0) {
            $classCodeMapper->deleteByColumn('folder_id', $model->getId());
        }
    }

    /**
     * Put the given Curriculum code lists onto the given folder
     *
     * @param Model $model
     * @param array $curriculumCodes
     * @throws MapperException
     */
    protected function setCurriculumCodesOnFolder(Model $model, $curriculumCodes)
    {
        $curriculumNodeMapper = $this->getCurriculumNodeMapper();

        if (isset($curriculumCodes) && count($curriculumCodes)) {
            $model->setCurriculumNodes(
                $curriculumNodeMapper->findByColumn('id', $curriculumCodes)
            );
        }
    }


    /**
     * Save the given template for the given folder
     *
     * @param Model $model
     * @param int          $template
     * @throws RangeException if the specified template is not acceptable
     */
    protected function saveHomepageConfig(Model $model, $template)
    {
        $confMapper = $this->getHomepageConfigMapper();
        $tplMapper  = $this->getHomepageTemplateMapper();

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
     * @param Model $model
     */
    protected function recurseInheritance(Model $model)
    {
        $folderMapper = $this->getFolderMapper();

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
        $roleAccessMapper = $this->getRoleAccessMapper();
        $userAccessMapper = $this->getUserAccessMapper();
        $groupMapper      = $this->getGroupMapper();

        // is 'Create Group' set true?
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
                        ->setCategory($model->getId())
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
        }

        return $this->getSession()->getHistory(2);
    }

    /**
     * @param int  $id
     * @param User $user
     * @throws SchoolboxException
     */
    public function recursiveDelete($id, User $user)
    {
        $errorMapper = $this->getErrorMapper();
        $folderMapper = $this->getFolderMapper();

        // If I'm not a superuser, I can't delete a non-empty folder
        if (!$user->isSuperuser()) {
            if ($folderMapper->countByColumn('parent', $id) > 0) {
                throw $errorMapper->generateException(ErrorMapper::ERROR_PERM_CATEGORY_DELETE_SUBFOLDERS);
            }
        }

        try {
            $folder = $folderMapper->findById($id);
        } catch (\Exception $e ) {
            throw $errorMapper->generateException(ErrorMapper::ERROR_CATEGORY_DELETED, $e);
        }

        $folderMapper->recursiveDelete($folder);

        $this->getSession()->set('resourcesID', $folder->getParent()->getId());

        $this->getResponse()->content->set([
            'format' => 'json',
            'data' => json_encode(['result' => 'success'])
        ]);
    }

    /**
     * Reset folder content
     *
     * @throws SchoolboxException
     */
    public function postFolderReset()
    {
        $access = $this->getAccess();
        $errorMapper = $this->getErrorMapper();
        $folderMapper = $this->getFolderMapper();
        $fileMapper = $this->getFileMapper();

        try
        {
            $folder = $folderMapper->findById($this->getRequest()->post["id"]);

            if ($access->getFolderAccess($this->getUser(), $folder->getId()) < WRITE_ACCESS) {
                throw $errorMapper->generateException(ErrorMapper::ERROR_PERM_CATEGORY_WRITE, null);
            }
        }
        catch (\Exception $e)
        {
            throw $errorMapper->generateException(ErrorMapper::ERROR_CATEGORY_DELETED, $e);
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

    /**
     * Displays a list of groups for the current logged in user
     *
     * @api GET /resource/mygroups
     */
    public function getGroups() {

        /* Get Timetable colours to use as random colours for null set folders */
        $colourList = Timetable_ColourMapper::getInstance()->findAll();
        $colourIndex = 0;
        $folderList = new Collection();
        $userAccess  = Array();
        foreach($this->getFolderMapper()->findForUser($this->getUser(), false)->sortBy('name', 'asc') as $folder) {
            if (!$folder->getColour()) {
                /* Apply colour from list if no colour exists */
                $folder->setColour($colourList[$colourIndex]->getHexColour());
                $colourIndex = ($colourIndex + 1) % $colourList->count();
            }
            $accessType = Access::getInstance()->getFolderAccess($this->getUser(), $folder->getId());
            switch ($accessType) {
                case UserAccess::ACCESS_READ:
                    $userAccess[$folder->getId()] = Array('read' => true);
                    break;
                case UserAccess::ACCESS_WRITE:
                    $userAccess[$folder->getId()] = Array('write' => true);
                    break;
                case UserAccess::ACCESS_FULL:
                    $userAccess[$folder->getId()] = Array('admin' => true);
                    break;
                default:
                    $userAccess[$folder->getId()] = Array();
                    break;
            }
            $folderList->add($folder);
        }
        $this->getResponse()->content->set($this->buildHtmlOutput('@folder/groups.twig', [
            'groupList'           => $folderList,
            'userAccess'          => $userAccess
        ]));
    }

    /**
     * @return Mapper
     */
    protected function getFolderMapper()
    {
        return $this->folderMapper;
    }


    /**
     * @param Mapper $folderMapper
     * @return static
     */
    public function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
        return $this;
    }


    /**
     * @return HomepageConfigMapper
     */
    protected function getHomepageConfigMapper()
    {
        return $this->homepageConfigMapper;
    }


    /**
     * @param HomepageConfigMapper $homepageConfigMapper
     * @return static
     */
    public function setHomepageConfigMapper($homepageConfigMapper)
    {
        $this->homepageConfigMapper = $homepageConfigMapper;
        return $this;
    }


    /**
     * @return HomepageTemplateMapper
     */
    protected function getHomepageTemplateMapper()
    {
        return $this->homepageTemplateMapper;
    }


    /**
     * @param HomepageTemplateMapper $homepageTemplateMapper
     * @return static
     */
    public function setHomepageTemplateMapper($homepageTemplateMapper)
    {
        $this->homepageTemplateMapper = $homepageTemplateMapper;
        return $this;
    }


    /**
     * @return RoleAccessMapper
     */
    protected function getRoleAccessMapper()
    {
        return $this->roleAccessMapper;
    }


    /**
     * @param RoleAccessMapper $roleAccessMapper
     * @return static
     */
    public function setRoleAccessMapper($roleAccessMapper)
    {
        $this->roleAccessMapper = $roleAccessMapper;
        return $this;
    }


    /**
     * @return UserAccessMapper
     */
    public function getUserAccessMapper()
    {
        return $this->userAccessMapper;
    }


    /**
     * @param UserAccessMapper $userAccessMapper
     * @return static
     */
    public function setUserAccessMapper($userAccessMapper)
    {
        $this->userAccessMapper = $userAccessMapper;
        return $this;
    }


    /**
     * @return RoleMapper
     */
    protected function getRoleMapper()
    {
        return $this->roleMapper;
    }


    /**
     * @param RoleMapper $roleMapper
     * @return static
     */
    public function setRoleMapper($roleMapper)
    {
        $this->roleMapper = $roleMapper;
        return $this;
    }


    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->errorMapper;
    }


    /**
     * @param ErrorMapper $errorMapper
     * @return static
     */
    public function setErrorMapper($errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }


    /**
     * @return Access
     */
    protected function getAccess()
    {
        return $this->access;
    }


    /**
     * @param Access $access
     * @return static
     */
    public function setAccess($access)
    {
        $this->access = $access;
        return $this;
    }


    /**
     * @return ClassCodeMapper
     */
    protected function getClassCodeMapper()
    {
        return $this->classCodeMapper;
    }


    /**
     * @param ClassCodeMapper $classCodeMapper
     * @return static
     */
    public function setClassCodeMapper($classCodeMapper)
    {
        $this->classCodeMapper = $classCodeMapper;
        return $this;
    }


    /**
     * @return Curriculum\Node\Mapper
     */
    protected function getCurriculumNodeMapper()
    {
        return $this->curriculumNodeMapper;
    }


    /**
     * @param Curriculum\Node\Mapper $curriculumNodeMapper
     * @return static
     */
    public function setCurriculumNodeMapper($curriculumNodeMapper)
    {
        $this->curriculumNodeMapper = $curriculumNodeMapper;
        return $this;
    }


    /**
     * @return GroupMapper
     */
    protected function getGroupMapper()
    {
        return $this->groupMapper;
    }


    /**
     * @param GroupMapper $groupMapper
     * @return static
     */
    public function setGroupMapper($groupMapper)
    {
        $this->groupMapper = $groupMapper;
        return $this;
    }


    /**
     * @return FileMapper
     */
    protected function getFileMapper()
    {
        return $this->fileMapper;
    }


    /**
     * @param FileMapper $fileMapper
     * @return static
     */
    public function setFileMapper($fileMapper)
    {
        $this->fileMapper = $fileMapper;
        return $this;
    }

}
