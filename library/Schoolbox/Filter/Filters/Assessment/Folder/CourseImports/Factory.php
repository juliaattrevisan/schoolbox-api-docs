<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Folder\CourseImports;

use function count;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment\Folder\KeyConverter;
use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\Filter\Filters\Assessment\Folder as FolderFilter;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImplementation;
use Schoolbox\Resource\Folder;
use Schoolbox\Mapper;

/**
 * A factory for building CourseImport 'Folder' filter objects
 */
class Factory extends FolderFilter\Factory
{

    /** @var Filter\Mapper */
    private $filterMapper;

    /** @var UnitImplementation\Mapper */
    private $unitImplMapper;


    /**
     * @param Filter\Page\Option\Mapper          $optionMapper
     * @param Filter\Page\Option\Defaults\Mapper $defaultMapper
     * @param Filter\Selection\Mapper            $selectionMapper
     * @param Folder\Mapper                      $folderMapper
     * @param Access                             $access
     * @param Filter\Mapper                      $filterMapper
     * @param UnitImplementation\Mapper          $unitImplMapper
     */
    public function __construct(
        Filter\Page\Option\Mapper $optionMapper,
        Filter\Page\Option\Defaults\Mapper $defaultMapper,
        Filter\Selection\Mapper $selectionMapper,
        Folder\Mapper $folderMapper,
        Access $access,
        Filter\Mapper $filterMapper,
        UnitImplementation\Mapper $unitImplMapper
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper, $folderMapper, $access);
        $this->filterMapper   = $filterMapper;
        $this->unitImplMapper = $unitImplMapper;
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Definition|Filter\IDefinition
     */
    public function buildDefinition(Filter\Model $filter): Filter\IDefinition
    {
        return new Definition(
            $filter,
            $this->getOptionMapper(),
            $this->getDefaultsMapper()
        );
    }


    /**
     * The 'Course Import Folder' component has the following custom functionality:
     * * It inserts any folders specified in GET args if user has READ+ perm to them
     * * Inserts any folders where GET-injected UnitImportSpecs reside
     *
     * @inheritDoc
     * @return Filter\IComponent|FolderFilter\Component
     * @throws \InvalidArgumentException If $viewee is null
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function buildComponent(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        ?GenericCollection $selections,
        ?User $viewee,
        array $routeArgs,
        GenericCollection $overrides
    ): Filter\IComponent {
        $folders = $this->getFolderMapper()->createCollection();

        // Did caller specify they wish to insert some custom folders in the list?
        $folderOverrides = $overrides[$pageFilter->getFilter()->getId()];
        if ($folderOverrides && $folderOverrides->count()) {
            $folderIds    = $folderOverrides->getPropertyValues('key');
            $extraFolders = $this->getFolderMapper()->findByColumn($this->getFolderMapper()->getIdentifierColumn(), $folderIds);

            foreach ($extraFolders as $extraFolder) {
                // Ensure user is Staff or has WRITE+ perm to the requested folder (this will also be enforced via key-converter)
                if ($user->getRole()->isStaff() || $this->getAccess()->getFolderAccess($user, $extraFolder->getId()) >= UserAccess::ACCESS_WRITE) {
                    $folders[$extraFolder->getId()] = $extraFolder;
                }
            }
        }

        // Course Markbook enables insertion of unit-import-specs and their host folders
        // TODO inspect new $routeArgs to more-reliably determine CourseSpec if on PAGE_COURSE_MARK_BOOK
        if (Filter\Page\Model::PAGE_COURSE_MARK_BOOK === $pageFilter->getPage()) {
            if (null === $folderOverrides) {
                $folderOverrides = new GenericCollection(Filter\Selection\Model::class);
            }
            $this->insertUnitImportSpecFolders($definition, $user, $pageFilter, $overrides, $folders, $folderOverrides);
        }

        return new Component(
            $definition,
            $user,
            $pageFilter,
            $selections,
            $folderOverrides,
            $this->getSelectionMapper(),
            $folders->sortBy('name', 'ASC', null, true)
        );
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        $folderIds = (new Collection($this->getFolderMapper()->findAllIds(['deleted' => true])))
            ->indexByCallback(function ($folderId) {
                return $folderId;
            });
        return new KeyConverter($filter, $folderIds, $this->getAccess());
    }


    /**
     * Inspects the query overrides for any UnitImportSpecs. If found, inserts any corresponding folders into the
     * folder options, and mutates the overrides to ensure they are selected.
     *
     * Ignores admin-configured group/class folder settings.
     *
     * @param Filter\IDefinition                                             $definition
     * @param User                                                           $user
     * @param Filter\Page\Model                                              $pageFilter
     * @param GenericCollection|GenericCollection|Filter\Selection\Model[][] $overrides
     * @param GenericCollection|Folder\Model[]                               $folders
     * @param GenericCollection|Filter\Selection\Model[]                     $folderOverrides
     * @throws \Schoolbox\Mapper\Exception     If unable to create a Selection model
     */
    private function insertUnitImportSpecFolders(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        GenericCollection $overrides,
        GenericCollection $folders,
        GenericCollection $folderOverrides
    ): void {
        $definitionClass = Filter\Filters\Assessment\NullableContext\UnitImportSpec\Definition::class;
        /** @var Filter\Model $unitSpecFilter */
        $unitSpecFilter    = $this->filterMapper->findByClass($definitionClass)->first();
        $unitSpecOverrides = $overrides[$unitSpecFilter->getId()];

        if (!$unitSpecOverrides || count($unitSpecOverrides) === 0) {
            // If no UnitSpecs requested via query args, then no folders require insertion and we are done.
            return;
        }

        // Find any unit impls imported from these specs
        $unitImpls = $this->unitImplMapper->findAllByUnitSpecificationId($unitSpecOverrides->getPropertyValues('key'));

        foreach ($unitImpls as $unitImpl) {
            $folderId = $unitImpl->getFolder()->getId();
            // Ensure user is STAFF or has WRITE perm to the requested folder (this will also be enforced via key-converter)
            if (!$user->getRole()->isStaff() && $this->getAccess()->getFolderAccess($user, $folderId) < UserAccess::ACCESS_WRITE) {
                continue;
            }

            // Ensure the admin has configured this sort of folder to be available.
            if (!$this->isAvailable($unitImpl->getFolder(), $pageFilter->getOptions())) {
                continue;
            }
            // Insert folder into available options
            $folders[$folderId] = $unitImpl->getFolder();

            // Tweak overrides to ensure folder is selected
            if (!$folderOverrides->keyExists($folderId)) {
                $folderOverrides[$folderId] = $this->getSelectionMapper()->createModel()
                                                   ->setFilter($definition->getFilter())
                                                   ->setUser($user)
                                                   ->setPage($pageFilter->getPage())
                                                   ->setKey((string)$folderId)
                                                   ->setSelected(false);
            }

        }
    }


    /**
     * Returns whether the admin has configured this sort of folder to be available in the filter options
     *
     * @param Folder\Model                                 $folder
     * @param GenericCollection|Filter\Page\Option\Model[] $options
     * @return bool
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     */
    private function isAvailable(Folder\Model $folder, GenericCollection $options): bool
    {
        if ($options[Definition::KEY_CLASSES]->getAvailable() && ($folder->isClass() || $folder->wasClass())) {
            return true;
        }
        // Re-hydrate group
        $folder = $this->getFolderMapper()->findById($folder->getId());
        if ($folder->isGroup() && $options[Definition::KEY_GROUPS]->getAvailable()) {
            return true;
        }
        return false;
    }
}
