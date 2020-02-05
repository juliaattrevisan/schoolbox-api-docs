<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Folder\CourseImports;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment\Folder\KeyConverter;
use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\Filter\Filters\Assessment\Folder as FolderFilter;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImplementation;
use Schoolbox\Resource\Folder;

/**
 * A factory for building CourseImport 'Folder' filter objects
 */
class Factory extends FolderFilter\Factory
{

    /** @var CourseImplementation\Mapper */
    private $courseImplMapper;


    /**
     * @param Filter\Page\Option\Mapper          $optionMapper
     * @param Filter\Page\Option\Defaults\Mapper $defaultMapper
     * @param Filter\Selection\Mapper            $selectionMapper
     * @param Folder\Mapper                      $folderMapper
     * @param Access                             $access
     * @param CourseImplementation\Mapper        $courseImplMapper
     */
    public function __construct(
        Filter\Page\Option\Mapper $optionMapper,
        Filter\Page\Option\Defaults\Mapper $defaultMapper,
        Filter\Selection\Mapper $selectionMapper,
        Folder\Mapper $folderMapper,
        Access $access,
        CourseImplementation\Mapper $courseImplMapper
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper, $folderMapper, $access);
        $this->courseImplMapper = $courseImplMapper;
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
     * * It inserts any folders specified in GET args if user is staff or instructor
     * * Inserts any folders where course has been imported to
     *
     * @inheritDoc
     * @return Filter\IComponent|FolderFilter\Component
     * @throws \InvalidArgumentException If $viewee is null
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
                if ($this->isStaffOrInstructor($user, $extraFolder)) {
                    $folders[$extraFolder->getId()] = $extraFolder;
                }
            }
        }

        // Course-markbook is only interested in units specs that were previously imported to folders,
        // not the current courseSpec's unitSpecs
        if (Filter\Page\Model::PAGE_COURSE_MARK_BOOK === $pageFilter->getPage()) {

            // Remove any folders that were added via override, only populate with relevant folders
            // But don't modify the folder overrides, they will continue to affect selection of available folder options
            // This ensures a user can't inject a random folder and unit-spec and see assessments that were not imported from the courseSpec
            $folders = $this->getFolderMapper()->createCollection();

            // Find all folders where the course has been imported
            $courseSpecId     = $this->findCourseSpecModel($routeArgs)->getId();
            $courseImpls      = $this->courseImplMapper->findAllByCourseSpecificationId($courseSpecId);
            $availableFolders = $this->getFolderMapper()->createCollection();
            foreach ($courseImpls as $courseImpl) {
                $folder = $courseImpl->getFolder();
                if ($this->isStaffOrInstructor($user, $folder)) {
                    $availableFolders[$folder->getId()] = $folder;
                }
            }

            // If configured to display groups, we need to re-hydrate to check whether folder is a group
            if ($availableFolders->count() && $pageFilter->getOptions()[Definition::KEY_GROUPS]->getAvailable()) {
                $availableFolders = $this->getFolderMapper()->findByColumn('id', $availableFolders->getPropertyValues('id'));
            }

            // Ensure the admin has configured this sort of folder to be available.
            foreach ($availableFolders as $folder) {
                if ($this->isAvailable($folder->getFolder(), $pageFilter->getOptions())) {
                    $folders[$folder->getId()] = $folder;
                }
            }
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
     * Returns whether the specified user has the staff role type, or is an instructor of the specified folder
     *
     * @param User         $user
     * @param Folder\Model $folder
     * @return bool
     */
    private function isStaffOrInstructor(User $user, Folder\Model $folder): bool
    {
        return $user->getRole()->isStaff() || $this->getAccess()->getFolderAccess($user, $folder->getId()) >= UserAccess::ACCESS_WRITE;
    }


    /**
     * Returns whether the admin has configured this sort of folder to be available in the filter options
     *
     * @param Folder\Model                                 $folder (Must have group relationship hydrated if displaying groups)
     * @param GenericCollection|Filter\Page\Option\Model[] $options
     * @return bool
     */
    private function isAvailable(Folder\Model $folder, GenericCollection $options): bool
    {
        if ($options[Definition::KEY_CLASSES]->getAvailable() && ($folder->isClass() || $folder->wasClass())) {
            return true;
        }
        if ($folder->isGroup() && $options[Definition::KEY_GROUPS]->getAvailable()) {
            return true;
        }
        return false;
    }
}
