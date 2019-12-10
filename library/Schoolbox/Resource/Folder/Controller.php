<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Folder;

use Alaress\Color\Color;
use Alaress\Color\ColorFactory;
use Alaress\Web\Controller\Base as BaseController;
use ArrayAccess;
use Aura\Router;
use Homepage;
use InvalidArgumentException;
use OutOfBoundsException;
use PDOException;
use RangeException;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Curriculum;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\RoleAccess;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Exception as ResourceException;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Resource\HomepageConfig;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplate;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\Resource\LinkMapper;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\YearLevel;
use UnexpectedValueException;
use Schoolbox\Resource\Folder;
use Timetable_ColourMapper;
use Schoolbox\Collection;
use function in_array;
use function strnatcasecmp;

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

    /** @var Curriculum\Node\Mapper */
    protected $curriculumNodeMapper;

    /** @var GroupMapper */
    protected $groupMapper;

    /** @var FileMapper */
    protected $fileMapper;

    /** @var YearLevel\Mapper */
    private $yearLevelMapper;


    /**
     * @param Mapper                 $folderMapper
     * @param HomepageConfigMapper   $homepageConfigMapper
     * @param HomepageTemplateMapper $homepageTemplateMapper
     * @param RoleAccessMapper       $roleAccessMapper
     * @param UserAccessMapper       $userAccessMapper
     * @param RoleMapper             $roleMapper
     * @param ErrorMapper            $errorMapper
     * @param Access                 $access
     * @param Curriculum\Node\Mapper $curriculumNodeMapper
     * @param GroupMapper            $groupMapper
     * @param FileMapper             $fileMapper
     * @param YearLevel\Mapper       $yearLevelMapper
     */
    public function __construct(
        Mapper $folderMapper,
        HomepageConfigMapper $homepageConfigMapper,
        HomepageTemplateMapper $homepageTemplateMapper,
        RoleAccessMapper $roleAccessMapper,
        UserAccessMapper $userAccessMapper,
        RoleMapper $roleMapper,
        ErrorMapper $errorMapper,
        Access $access,
        Curriculum\Node\Mapper $curriculumNodeMapper,
        GroupMapper $groupMapper,
        FileMapper $fileMapper,
        YearLevel\Mapper $yearLevelMapper
    ) {
        $this->folderMapper           = $folderMapper;
        $this->homepageConfigMapper   = $homepageConfigMapper;
        $this->homepageTemplateMapper = $homepageTemplateMapper;
        $this->roleAccessMapper       = $roleAccessMapper;
        $this->userAccessMapper       = $userAccessMapper;
        $this->roleMapper             = $roleMapper;
        $this->errorMapper            = $errorMapper;
        $this->access                 = $access;
        $this->curriculumNodeMapper   = $curriculumNodeMapper;
        $this->groupMapper            = $groupMapper;
        $this->fileMapper             = $fileMapper;
        $this->yearLevelMapper        = $yearLevelMapper;
    }


    /** @noinspection PhpUnused */
    /**
     * Display create folder page
     *
     * @api GET /resource/folder/{parentId}/create
     *
     * @param Model $parent
     * @throws UnexpectedValueException
     * @throws Router\Exception\RouteNotFound
     */
    public function getFolderCreate(Model $parent): void
    {
        $breadcrumbs = [
            [
                'text' => $parent->getName(),
                'href' => '/homepage/' . $parent->getId(),
            ],
            [
                'text' => 'Create Folder',
                'href' => '',
            ],
        ];

        $formAction = $this->getRouter()->generate(
            'folder.postCreateFolder',
            ['parent' => $parent, 'parentId' => $parent->getId()]
        );

        $folder = (new Folder\Model())
            ->setParent($parent)
            ->setCodes(new GenericCollection(Code\Model::class))
            ->setYearLevels(new GenericCollection(YearLevel\Model::class))
            ->setCurriculumNodes(new GenericCollection(Curriculum\Node\Model::class));
        $this->displayFolderModifyForm($breadcrumbs, $formAction, $folder);
    }


    /** @noinspection PhpUnused */
    /**
     * Display update folder screen
     *
     * @api GET /resource/folder/{id}/modify
     * @param Model $folder
     * @throws UnexpectedValueException
     * @throws Router\Exception\RouteNotFound
     * @throws MapperException
     */
    public function getFolderModify(Model $folder): void
    {
        // Lightly hydrate parent for breadcrumb label
        $folder->setParent($this->folderMapper->findCheaplyById($folder->getParent()->getId()));
        $breadcrumbs = [
            [
                'text' => $folder->getParent()->getName(),
                'href' => '/homepage/' . $folder->getParent()->getId(),
            ],
            [
                'text' => $folder->getName(),
                'href' => '/homepage/' . $folder->getId(),
            ],
            [
                'text' => 'Modify',
                'href' => '#',
            ],
        ];

        $formAction = $this->getRouter()->generate(
            'folder.postUpdateFolder',
            ['folder' => $folder, 'id' => $folder->getId()]
        );

        $this->displayFolderModifyForm($breadcrumbs, $formAction, $folder);
    }


    /**
     * Displays the folder modify form.
     *
     * @param array  $breadcrumbs
     * @param string $formAction
     * @param Model  $folder
     * @throws UnexpectedValueException
     */
    private function displayFolderModifyForm(array $breadcrumbs, string $formAction, Model $folder): void
    {
        // Homepage template
        $homepageTemplates = $this->homepageTemplateMapper->findAll(['order' => ['name' => 'asc']]);
        if (!$folder->getId()) {
            $templateId = HomepageTemplate::HOMEPAGE_NONE;
        } else {
            try {
                $this->homepageConfigMapper->findOneByColumn('cid', $folder->getId());
                $templateId = HomepageTemplate::HOMEPAGE_KEEP_EXISTING;
                $homepageTemplates->prepend($this->homepageTemplateMapper->getKeepExistingHomepageTemplate());
            } catch (\Exception $e) {
                $templateId = HomepageTemplate::HOMEPAGE_NONE;
            }
        }

        // Cover Image
        $zone = new Zone('coverImage');
        $zone
            ->setMulti(false)
            ->setAttachPrompt('Attach a cover image')
            ->setAttachmentsLabel('')
            ->setMimeFilter('image/*');
        if ($folder->hasCoverImage()) {
            $zone->attachFiles([$folder->getCoverImage()]);
        }

        // Year Levels
        $toSelectizeOption = static function (YearLevel\Model $yearLevel) {
            return [
                'id'   => $yearLevel->getId(),
                'name' => $yearLevel->getName(),
            ];
        };
        $yearLevelOptions  = $this->yearLevelMapper->findAll()->map($toSelectizeOption)->getAll();
        $yearLevelValues   = $folder->getYearLevels()->map($toSelectizeOption)->reindex()->getAll();

        // Role permissions (CREATE defaults to parent folder perms)
        $permissionFolder = $folder->getId() ?? $folder->getParent()->getId();
        $rolePermissions  = [];
        foreach ($this->roleAccessMapper->findByColumn('cid', $permissionFolder) as $permission) {
            $rolePermissions[$permission->getRole()->getId()] = $permission->getType();
        }

        // New folders that are not in the root note inherit by default to reduce the user interface
        if (!$folder->getId() && $folder->getParent()->getId() !== Model::ROOT_ID) {
            $folder->enableInherit();
        }

        $this->getResponse()->content->set(
            [
                'template' => '@folder/modify.twig',
                'data'     => [
                    'user'               => $this->getUser(),
                    'breadcrumbs'        => $breadcrumbs,
                    'formAction'         => $formAction,
                    'folder'             => $folder,
                    'parent'             => $folder->getParent(),
                    'templates'          => $homepageTemplates,
                    'templateId'         => $templateId,
                    'roles'              => $this->roleMapper->findAll(),
                    'rolePermissions'    => $rolePermissions,
                    'database'           => ucfirst($this->getConfigLoader()->get('external_type')),
                    'curriculumTagging'  => $this->getConfigLoader()->get('curriculum_tagging_enabled'),
                    'userAccess'         => $this->access->getFolderAccess($this->getUser(), $permissionFolder),
                    'deny'               => UserAccess::ACCESS_DENY,
                    'read'               => UserAccess::ACCESS_READ,
                    'write'              => UserAccess::ACCESS_WRITE,
                    'moderate'           => UserAccess::ACCESS_FULL,
                    'coverImageZone'     => $zone->getTwigContext(),
                    'editYearLevelUsage' => $this->getUser()->isSuperuser(),
                    'yearLevelOptions'   => $yearLevelOptions,
                    'yearLevelValues'    => $yearLevelValues,
                ],
            ]
        );
    }

    /** @noinspection PhpUnused */
    /**
     * Delete folder
     *
     * @api DELETE /resource/folder/{id}
     * @param Model $folder
     * @throws SchoolboxException
     */
    public function deleteFolder(Folder\Model $folder): void
    {
        $this->recursiveDelete($folder->getId(), $this->getUser());

        $this->getResponse()->content->set([
            'format' => 'json',
            'data' => json_encode(['result' => 'success'])
        ]);
    }


    /** @noinspection PhpUnused */
    /**
     * Move folder with an AJAX response
     *
     * @api PUT /resource/folder/{id}/move/{parentId}
     * @param Model $folder
     * @param Model $newParent
     * @throws MapperException
     * @throws SchoolboxException If move would cause folder ancestry cycle
     */
    public function putMoveFolder(Model $folder, Model $newParent): void
    {
        if($folder->getId() === $newParent->getId()){
            // Doesn't make sense, source and destination folders the same
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        $this->folderMapper->moveFolder($folder->getId(), $newParent->getId(), $this->getUser());

        $this->getResponse()->content->set([
            'format' => 'json',
            'data' => json_encode(['result' => 'success'])
        ]);
    }


    /** @noinspection PhpUnused */
    /**
     * Creates a new folder in the specified parent folder
     *
     * @api   POST /resource/folder/{parentId}/create
     * @since 19.1
     * @param Model $parent
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws SchoolboxException
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws DefinitionException
     */
    public function postCreateFolder(Model $parent): void
    {
        $newFolder = (new Model())
            ->setParent($parent)
            ->setCodes(new GenericCollection(Code\Model::class))
            ->setYearLevels(new GenericCollection(YearLevel\Model::class))
            ->setCurriculumNodes(new GenericCollection(Curriculum\Node\Model::class));
        $this->postUpdateFolder($newFolder);
    }

    /**
     * Update a folder's details
     *
     * @api   POST /resource/folder/{folderId}/update
     * @since 156
     * @param Model $folder
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws SchoolboxException
     * @throws PDOException
     * @throws DefinitionException
     */
    public function postUpdateFolder(Model $folder): void
    {
        $errorMapper = $this->errorMapper;
        $post        = $this->getRequest()->post->getArrayCopy();

        /** No whitespace in the codes or year levels please **/
        $classCodes = array_filter(array_map('trim', explode(',', $post['code'])));
        $yearLevels = array_filter(array_map('trim', explode(',', $post['yearLevelIds'])));

        try {
            $model = $this->persistFolder(
                $folder,
                $post['name'],
                (bool)($post['inherit'] ?? false),
                (int)$post['template'],
                $post['colour'] ?: null,
                $post['attachment'][0] ?? null,
                isset($post['curriculumCodes']) ? array_filter($post['curriculumCodes']) : null,
                $post['group'],
                (bool)($post['recursive-permissions'] ?? false),
                $classCodes,
                $yearLevels,
                (bool)($post['recursive-year-levels'] ?? false),
                $this->getUser()
            );
            $newLocation = $this->createGroup($this->getRequest()->post, $model, $this->getUser());

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
     * Creates a new, or updates an existing, folder.
     *
     * @param Model       $folder             The folder to persist
     * @param string      $name
     * @param bool        $inherit
     * @param int         $templateId
     * @param string|null $colour
     * @param string|null $coverImage
     * @param array|null  $curriculumCodeIds
     * @param string[]    $rolePermissions    Maps role ids to permission levels
     * @param bool        $recursePermissions If true && $user is SU, applies 'inherit' to entire subtree
     * @param array       $classCodes
     * @param array       $yearLevelIds       Comma-separated YearLevel ids
     * @param bool        $recurseYearLevels  If true
     * @param User        $user               the effecting user
     * @return Model                          Representing the freshly saved folder
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws RangeException if the database fails to save your folder
     * @throws SchoolboxException
     * @throws UnexpectedValueException
     * @throws PDOException
     * @throws DefinitionException
     */
    public function persistFolder(
        Model $folder,
        string $name,
        bool $inherit,
        int $templateId,
        ?string $colour,
        ?string $coverImage,
        ?array $curriculumCodeIds,
        array $rolePermissions,
        bool $recursePermissions,
        array $classCodes,
        array $yearLevelIds,
        bool $recurseYearLevels,
        User $user
    ): Model {
        $folder->setName($name);
        $homepageTemplate = $this->buildHomepageTemplate($folder, $templateId);

        // Either user is SU, or insertion of new Folder, or Folder did not have Class Codes
        if ($inherit && ($user->isSuperuser() || !$folder->getId() || !$folder->getCodes()->count())) {
            $folder->enableInherit();
        } else {
            $folder->disableInherit();
        }

        // Cover image
        if ($coverImage) {
            try {
                $folder->setCoverImage((new CollectorFactory())->build($coverImage ? [$coverImage] : [])->getFirst());
            } catch (ResourceException $e) {
                // noop - retain current attachment
                trigger_error("Unable to build folder cover image", E_USER_WARNING);
            }
        } else {
            $folder->setCoverImage(null);
        }

        // Colour
        if ($colour) {
            try {
                $folder->setColour((new ColorFactory())->build($colour));
            } catch (InvalidArgumentException $e) {
                // noop - retain current colour
                trigger_error("Unable to build folder colour", E_USER_WARNING);
            }
        } else {
            $folder->setColour(null);
        }

        // Validate class codes (prior to saving anything)
        if ($user->isSuperuser() && !$folder->getInherit()) {
            foreach ($classCodes as $code) {
                if (strlen(trim($code)) > Code\Model::CODE_LENGTH_MAX) {
                    throw $this->errorMapper->generateException(ErrorMapper::ERROR_CODE_TOO_LONG);
                }
            }
        }

        // Only superusers get to change class codes and year levels
        if ($user->isSuperuser()) {
            $this->updateFolderClassCodes($folder, $classCodes);
            $this->updateFolderYearLevels($folder, $yearLevelIds);
        }

        // update Curriculum codes
        if (isset($curriculumCodeIds)) {
            $this->updateFolderCurriculumNodes($folder, $curriculumCodeIds);
        }

        // update role permission
        $rolePermsToSave = $this->updateRolePermissions($folder, $rolePermissions);

        // Save folder (also persists class-codes + year-level joins)
        $this->folderMapper->save($folder);

        $this->updateHomepageTemplate($folder, $homepageTemplate);

        // Update role perms
        if ($rolePermsToSave->count()) {
            $this->roleAccessMapper->save($rolePermsToSave);
        }

        // only allow recursive permissions to supers
        if (($recursePermissions || $recurseYearLevels) && $user->isSuperuser()) {
            foreach ($this->folderMapper->findAllChildren($folder->getId(), true) as $descendant) {
                $saveFolderRequired = false;
                if ($recursePermissions) {
                    if ($inherit) {
                        // Mapper post-save will handle the group/role permission + folder-code clean up for us
                        $descendant->enableInherit();
                        $saveFolderRequired = true;
                    } else {
                        if ($descendant->getInherit()) {
                            $descendant->setInherit(false);
                            $saveFolderRequired = true;
                        }
                        $rolePermsToSave = $this->updateRolePermissions($descendant, $rolePermissions);
                        if ($rolePermsToSave->count()) {
                            $this->roleAccessMapper->save($rolePermsToSave);
                        }
                    }
                }
                if ($recurseYearLevels) {
                    $this->updateFolderYearLevels($descendant, $yearLevelIds);
                    $saveFolderRequired = true;
                }
                if ($saveFolderRequired) {
                    $this->folderMapper->save($descendant);
                }
            }
        }

        return $folder;
    }

    /**
     * Efficiently builds the set of role perms to persist
     *
     * @param Model $folder
     * @param string[] $rolePermissions Maps role ids to permission levels
     * @return GenericCollection|RoleAccess[]
     * @throws MapperException
     * @throws DefinitionException
     */
    private function updateRolePermissions(Model $folder, array $rolePermissions): GenericCollection
    {
        $rolePermsToSave = $this->roleAccessMapper->createCollection();
        if (!$folder->getInherit()) {
            /** @var GenericCollection|RoleAccess[] $existingRolePerms */
            $existingRolePerms = $folder->getId()
                ? $this->roleAccessMapper
                    ->findAllForFolder($folder)
                    ->indexByCallback(
                        static function (RoleAccess $roleAccess) {
                            return $roleAccess->getRole()->getId();
                        }
                    )
                : $this->roleAccessMapper->createCollection();

            foreach ($rolePermissions as $roleId => $permissionLevel) {
                $existingPerm = $existingRolePerms[$roleId];
                if ($existingPerm) {
                    // Only modify if not already at this value
                    if ($existingPerm->getType() !== (int)$permissionLevel) {
                        $existingPerm->setType($permissionLevel);
                        $rolePermsToSave[$roleId] = $existingPerm;
                    }
                } else {
                    // Create
                    $rolePermsToSave[$roleId] = (new RoleAccess())
                        ->setCategory($folder)
                        ->setRole(RoleMapper::getInstance()->createModel($roleId))
                        ->setType($permissionLevel);
                }
            }
        }
        return $rolePermsToSave;
    }

    /**
     * Updates a folder with the new codes
     *
     * @param Model    $folder
     * @param string[] $newClassCodes
     * @throws UnexpectedValueException
     */
    private function updateFolderClassCodes(Model $folder, array $newClassCodes): void
    {
        // Clear codes
        $folder->setCodes(new GenericCollection(Folder\Code\Model::class));

        // Remove codes if folder inherits perms, or if code no longer in use
        if ($folder->getInherit()) {
            // If folder is inheriting permissions, ignore class codes
            return;
        }

        foreach ($newClassCodes as $newCode) {
            $folder->getCodes()[$newCode] = (new Folder\Code\Model())
                ->setFolder($folder)
                ->setCode($newCode);
        }
    }

    /**
     * Efficiently update a folder's year levels
     *
     * @param Model    $folder
     * @param string[] $yearLevelIds
     * @throws MapperException
     */
    private function updateFolderYearLevels(Model $folder, array $yearLevelIds): void
    {
        // Build yearLevels to remove. Remove if not in new set
        foreach ($folder->getYearLevels() as $idx => $yearLevel) {
            if (!in_array($yearLevel->getId(), $yearLevelIds, false)) {
                $folder->getYearLevels()->offsetUnset($idx);
            }
        }

        // Build yearLevels to add. Add if not already on folder
        foreach ($yearLevelIds as $yearLevelId) {
            if (!in_array($yearLevelId, $folder->getYearLevels()->keys(), false)) {
                $folder->getYearLevels()[$yearLevelId] = $this->yearLevelMapper->findById($yearLevelId);
            }
        }
    }

    /**
     * Put the given Curriculum code lists onto the given folder
     *
     * @param Model    $folder
     * @param string[] $nodeIds
     * @throws MapperException
     */
    private function updateFolderCurriculumNodes(Model $folder, array $nodeIds): void
    {
        // Ensure nodes are indexed by node.id
        $folder
            ->getCurriculumNodes()
            ->indexByCallback(
                static function (Curriculum\Node\Model $node) {
                    return $node->getId();
                }
            );

        // Remove existing node if not in new set
        foreach ($folder->getCurriculumNodes() as $idx => $node) {
            if (!in_array($node->getId(), $nodeIds, false)) {
                $folder->getCurriculumNodes()->offsetUnset($idx);
            }
        }

        // Add new node if not already on folder
        foreach ($nodeIds as $nodeId) {
            if (!in_array($nodeId, $folder->getCurriculumNodes()->keys(), false)) {
                $folder->getCurriculumNodes()[$nodeId] = $this->curriculumNodeMapper->findById($nodeId);
            }
        }
    }

    /**
     * Validates and returns new homepage template
     *
     * @param Model $model
     * @param int   $templateId
     * @return HomepageTemplate
     * @throws MapperException
     * @throws RangeException
     */
    private function buildHomepageTemplate(Model $model, int $templateId): HomepageTemplate
    {
        if ($templateId === HomepageTemplate::HOMEPAGE_KEEP_EXISTING) {
            return $this->homepageTemplateMapper->getKeepExistingHomepageTemplate();
        }
        if ($templateId === HomepageTemplate::HOMEPAGE_NONE) {
            return $this->homepageTemplateMapper->getNoHomepageTemplate();
        }

        // Avoid a DB call if user selected current template
        $oldHomepage = $model->getHomepage();
        if ($oldHomepage && $oldHomepage->getTemplate()->getId() === $templateId) {
            return $oldHomepage->getTemplate();
        }

        // Check new template exists and return it.
        try {
            return $this->homepageTemplateMapper->findById($templateId);
        } catch (MapperException $e) {
            throw new RangeException("Invalid template ID specified", 0, $e);
        }
    }

    /**
     * Save the given template for the given folder
     *
     * @param Model            $folder
     * @param HomepageTemplate $homepageTemplate
     * @throws MapperException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function updateHomepageTemplate(Model $folder, HomepageTemplate $homepageTemplate): void
    {
        if ($homepageTemplate->getId() === HomepageTemplate::HOMEPAGE_KEEP_EXISTING) {
            return;
        }
        if ($homepageTemplate->getId() === HomepageTemplate::HOMEPAGE_NONE) {
            if ($folder->getHomepage()) {
                $this->homepageConfigMapper->emptyHomepage($folder->getHomepage());
                $this->homepageConfigMapper->delete($folder->getHomepage());
            }
            $folder->setHomepage(null);
            return;
        }

        $homepageConfig = (new HomepageConfig())
            ->setCategory($folder)
            ->setTemplate($homepageTemplate);
        $folder->setHomepage($homepageConfig);

        $this->homepageConfigMapper->save($homepageConfig);
        $this->homepageConfigMapper->resetToTemplate($homepageConfig);
    }

    /**
     * Helper function to persist a group for a folder
     *
     * @param ArrayAccess $postData the array structure we'll use to construct this group
     * @param Model       $model    the Folder model we're working with
     * @param User        $user
     * @return string
     * @throws MapperException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    protected function createGroup($postData, Model $model, User $user)
    {
        // No changes in "KEEP_EXISTING" case

        // is 'Create Group' set true?
        if ($postData['groupcheck']) {

            // Prevent students with write access from being able to create folders
            if ($this->roleAccessMapper->roleMayOwnFolders($user->getRole())) {
                // Setup group and forward to group management page

                try {
                    // confirm the group exists
                    $this->groupMapper->findOneByColumn('cid', $model->getId());

                } catch (MapperException $e) {
                    // doesn't exist
                    $group = (new Group())
                        ->setFolder($model)
                        ->setType(Group::PRIVATE_GROUP)
                        ->setForceFollow(false);

                    // create the group
                    $this->groupMapper->save($group);
                }

                // assign group ownership to the effecting user
                $uA = (new UserAccess())
                    ->setService(UserAccess::SERVICE_HOMEPAGE)
                    ->setAccessLevel(UserAccess::ACCESS_FULL)
                    ->setServiceIdentifier($model->getId())
                    ->setUser($user);

                $this->userAccessMapper->save($uA);

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
        $errorMapper  = $this->errorMapper;
        $folderMapper = $this->folderMapper;

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


    /** @noinspection PhpUnused */
    /**
     * Reset folder content
     *
     * @api POST /resource/folder/{id}/reset
     * @param Model $folder
     * @throws MapperException
     */
    public function postFolderReset(Model $folder): void
    {
        // Remove component content
        $homepage = new Homepage($folder->getId());
        $homepage->emptyHomepage();

        // Soft Delete Assessments
        $this->folderMapper->softDeleteAssessments($folder);

        // Delete all files
        $fileList = $this->fileMapper->findForFolders([ $folder->getId() ]);

        foreach ($fileList as $file) {
            $this->fileMapper->delete($file);
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
    public function getGroups(): void
    {
        /* Get Timetable colours to use as random colours for null set folders */
        $colourList = Timetable_ColourMapper::getInstance()->findAll();
        $colourIndex = 0;
        $folderList = new Collection();
        $userAccess  = [];
        $colourFactory = new ColorFactory();
        $colourCache = new GenericCollection(Color::class);

        /** @var \Schoolbox\Resource\Folder\Model $folder */
        foreach ($this->folderMapper->findForUser($this->getUser(), false, UserAccess::ACCESS_READ, false, true)
                     ->sortBy('name', 'asc') as $folder) {
            if (!$folder->getColour()) {
                $hexColour = $colourList[$colourIndex]->getHexColour();
                if(!$colourCache->offsetExists($hexColour)){
                    $colourCache->setOffset($hexColour, $colourFactory->build($hexColour));
                }
                /* Apply colour from list if no colour exists */
                $folder->setColour($colourCache->getOffset($hexColour));
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
            'groupList'           => $folderList->sortByCallback(function ($a, $b) {
                return strnatcasecmp($a->getName(), $b->getName());
            }),
            'userAccess'          => $userAccess
        ]));
    }
}
