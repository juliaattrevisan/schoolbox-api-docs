<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\NullableContext\UnitImportSpec;

use Schoolbox\Mapper\DefinitionException;
use function count;
use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Page;
use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\Resource\Folder;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Filter\Filters\Assessment\NullableContext;

/**
 * A factory for building 'UnitSpec' filter objects
 */
class Factory extends NullableContext\Factory
{

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var  CourseImpl\Mapper */
    private $courseImplMapper;

    /** @var UnitSpec\Mapper */
    private $unitSpecMapper;


    /**
     * @param Filter\Page\Option\Mapper          $optionMapper
     * @param Filter\Page\Option\Defaults\Mapper $defaultMapper
     * @param Filter\Selection\Mapper            $selectionMapper
     * @param Folder\Mapper                      $folderMapper
     * @param CourseImpl\Mapper                  $courseImplMapper
     * @param UnitSpec\Mapper                    $unitSpecMapper
     */
    public function __construct(
        Filter\Page\Option\Mapper $optionMapper,
        Filter\Page\Option\Defaults\Mapper $defaultMapper,
        Filter\Selection\Mapper $selectionMapper,
        Folder\Mapper $folderMapper,
        CourseImpl\Mapper $courseImplMapper,
        UnitSpec\Mapper $unitSpecMapper
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
        $this->folderMapper     = $folderMapper;
        $this->courseImplMapper = $courseImplMapper;
        $this->unitSpecMapper   = $unitSpecMapper;
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
     * @inheritDoc
     * @throws InvalidArgumentException If $viewee is NULL or neither $selections or $overrides are supplied
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
        if (null === $viewee) {
            throw new InvalidArgumentException('Viewee cannot be null for UnitImportSpec filter');
        }

        $unitSpecOverrides          = $overrides[$definition->getFilter()->getId()];
        $unitSpecsImportedToFolders = $this->unitSpecMapper->createCollection();
        $unitSpecsImportedToClasses = $this->unitSpecMapper->createCollection();
        $unitSpecsImportedToGroups  = $this->unitSpecMapper->createCollection();

        // Course-markbook lists UnitImportSpecs that belonged to the specified CourseSpec at time of import
        if (Page\Model::PAGE_COURSE_MARK_BOOK === $pageFilter->getPage()) {
            $courseSpecId = $this->findCourseSpecModel($routeArgs)->getId();
            $courseImpls  = $this->courseImplMapper->findAllByCourseSpecificationId($courseSpecId, [], true);
            foreach ($courseImpls as $courseImpl) {
                foreach ($courseImpl->getUnits() as $unitImpl) {
                    $unitSpec = $unitImpl->getSpecification();
                    $unitSpecsImportedToFolders->setOffset($unitSpec->getId(), $unitSpec);
                    if ($courseImpl->getFolder()->isClass() || $courseImpl->getFolder()->wasClass()) {
                        $unitSpecsImportedToClasses->setOffset($unitSpec->getId(), $unitSpec);
                    }
                    if ($courseImpl->getFolder()->isGroup()) {
                        $unitSpecsImportedToGroups->setOffset($unitSpec->getId(), $unitSpec);
                    }
                }
            }

            // Course Markbook permits insertion of UnitImportSpecs
            if ($unitSpecOverrides) {
                $unitSpecs = $this->unitSpecMapper->findByColumn($this->unitSpecMapper->getIdentifierColumn(), $unitSpecOverrides->keys());
                foreach ($unitSpecs as $unitSpec) {
                    $unitSpecsImportedToFolders->setOffset($unitSpec->getId(), $unitSpec);
                }
            }
        } else {
            // Get all unitSpecs imported to relevant folders
            $folders = $this->folderMapper->createCollection();

            if ($this->isInvolvedFolderPage($pageFilter->getPage())) {
                $folders = $folders->mergeUnique($this->fetchRelatedFolders($pageFilter, $viewee), 'id');
            }
            // Are we in the context of a filtered page that is for a single folder?
            if ($this->isSingleFolderPage($pageFilter->getPage())) {
                $folder                    = $this->findFolderModel($routeArgs);
                $folders[$folder->getId()] = $folder;
            }

            /* If we inserted any extra folder/s, we need to also insert any units imported to that folder,
             * otherwise any assessments inside units imported to the extra folder will never be visible.
             */
            $extraFolderIds = $this->parseFolderIds($overrides);
            if (count($extraFolderIds)) {
                $extraFolders = $this->folderMapper->findByColumn('id', $extraFolderIds);
                $folders      = $folders->mergeUnique($extraFolders, 'id');
            }

            // Get all courses imported to these folders
            $courseImpls = $this->courseImplMapper->findByFolderId($folders->getPropertyValues('id'));

            // We need folder codes hydrated to determine if course was imported to class or group
            $this->loadFolderCodes($courseImpls);

            foreach ($courseImpls as $courseImpl) {
                foreach ($courseImpl->getUnits() as $unitImpl) {
                    $unitSpec = $unitImpl->getSpecification();
                    $unitSpecsImportedToFolders->setOffset($unitSpec->getId(), $unitSpec);
                }
            }

            // Divide folders into Classes and Groups (not mutually exclusive!)

            // Was course imported to a class? ?
            /** @var CourseImpl\Model[] $courseImplsOnClasses */
            $courseImplsOnClasses = $courseImpls->filter(static function (CourseImpl\Model $courseImpl) {
                return $courseImpl->getFolder()->isClass() || $courseImpl->getFolder()->wasClass();
            })->indexBy('id');

            // Was course imported to a group? ?
            /** @var CourseImpl\Model[] $courseImplsOnGroups */
            $courseImplsOnGroups = $courseImpls->filter(static function (CourseImpl\Model $courseImpl) {
                return $courseImpl->getFolder()->isGroup();
            })->indexBy('id');

            // Get all unitSpecs imported to classes
            foreach ($courseImplsOnClasses as $courseImpl) {
                foreach ($courseImpl->getUnits() as $unitImpl) {
                    $unitSpec = $unitImpl->getSpecification();
                    $unitSpecsImportedToClasses->setOffset($unitSpec->getId(), $unitSpec);
                }
            }

            // Get all unitSpecs imported to groups
            foreach ($courseImplsOnGroups as $courseImpl) {
                foreach ($courseImpl->getUnits() as $unitImpl) {
                    $unitSpec = $unitImpl->getSpecification();
                    $unitSpecsImportedToGroups->setOffset($unitSpec->getId(), $unitSpec);
                }
            }
        }

        return new Component(
            $definition,
            $user,
            $pageFilter,
            $selections,
            $unitSpecOverrides,
            $this->getSelectionMapper(),
            $unitSpecsImportedToFolders,
            $unitSpecsImportedToClasses,
            $unitSpecsImportedToGroups
        );
    }

    /**
     * Hydrates folder codes onto Course Implementation folders
     *
     * @param GenericCollection $courseImplementations
     * @throws DefinitionException
     */
    private function loadFolderCodes(GenericCollection $courseImplementations): void
    {
        /** @var Folder\Code\Mapper $folderCodeMapper */
        $folderCodeMapper = $this->folderMapper->getJoinMapper('codes');

        /** @var Collection|Folder\Model[] $indexedFolders */
        $indexedFolders = $courseImplementations->map(static function (CourseImpl\Model $courseImpl) {
            return $courseImpl->getFolder();
        })->indexByCallback(static function (Folder\Model $folder) {
            return $folder->getId();
        });
        $codesPerFolderId = $folderCodeMapper->findForCategories($indexedFolders->keys())
                                             ->groupByCallback(static function (Folder\Code\Model $code) {
                                                 return $code->getFolder()->getId();
                                             });
        foreach($indexedFolders as $folderId => $folder){
            $folder->setCodes($codesPerFolderId[$folderId] ?? $folderCodeMapper->createCollection());
        }
    }


    /**
     * Returns the folders that the specified user teachers or is an instructor of, depending
     * on whether groups/classes are configured by the admin to be available.
     *
     * @param Filter\Page\Model $pageFilter
     * @param User              $viewee
     * @return Collection|Folder\Model[]
     */
    private function fetchRelatedFolders(Filter\Page\Model $pageFilter, User $viewee)
    {
        // Should this role type see classes for this page?
        $options     = $pageFilter->getOptions();
        $noneOption  = $options[Definition::KEY_NONE];
        $classOption = $options[Definition::KEY_IMPORTED_TO_CLASS];
        $groupOption = $options[Definition::KEY_IMPORTED_TO_GROUP];

        if ($pageFilter->getUserConfigurable()) {
            $getClasses = $classOption->getAvailable();
            $getGroups  = $groupOption->getAvailable();
        } else {
            $roleType   = $viewee->getRole()->getType();
            $getNone    = $noneOption->getDefaults()[$roleType]->getSelected();
            $getClasses = $classOption->getDefaults()[$roleType]->getSelected();
            $getGroups  = $groupOption->getDefaults()[$roleType]->getSelected();

            // Are all available options unchecked ?
            if (!$getNone && !$getClasses && !$getGroups) {
                // For consistency, this must logically equate to the filter not reducing the full set.
                $getClasses = $getGroups = true;
            }
        }

        // Note: '4' is a hack to mean 'INVALID', to force no group folders to be matched.
        $groupAccessLevel = $getGroups ? UserAccess::ACCESS_WRITE : UserAccess::ACCESS_FULL + 1;

        return $this->folderMapper->findForUser($viewee, $getClasses, $groupAccessLevel, false);
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|NullableContext\KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        $unitSpecIds = (new Collection($this->unitSpecMapper->findAllIds(['deleted' => true])))
            ->indexByCallback(function ($unitSpecId) {
                return $unitSpecId;
            });
        return new NullableContext\KeyConverter($filter, $unitSpecIds);
    }
}
