<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\NullableContext\UnitImportSpec;

use Schoolbox\Collection;
use Schoolbox\Filter;
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
     * @param Filter\IDefinition     $definition
     * @param User                   $user
     * @param Filter\Page\Model      $pageFilter
     * @param null|GenericCollection|Filter\Selection\Model[] $selections Indexed selection.key
     * @param null|User                                       $viewee
     * @param null|GenericCollection|Filter\Selection\Model[] $overrides  Indexed selection.key
     * @return Filter\IComponent
     * @throws \InvalidArgumentException If $viewee is NULL or neither $selections or $overrides are supplied
     * @throws \UnexpectedValueException
     */
    public function buildComponent(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        ?GenericCollection $selections,
        ?User $viewee,
        ?GenericCollection $overrides
    ): Filter\IComponent {
        if (null === $viewee) {
            throw new \InvalidArgumentException('Viewee cannot be null for UnitImportSpec filter');
        }

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

        $folders = $this->folderMapper->findForUser($viewee, $getClasses, $groupAccessLevel, false);

        /* If we inserted an extra folder/s, we need to also insert any units imported to that folder,
         * otherwise any assessments inside units imported to the extra folder will never be visible.
         */
        $extraFolderIds = $overrides ? $this->parseFolderIds($overrides) : [];
        if (count($extraFolderIds)) {
            $extraFolders = $this->folderMapper->findByColumn('id', $extraFolderIds);
            $folders      = $folders->mergeUnique($extraFolders, 'id');
        }

        // Get all courses imported to these folders
        $courseImpls = $this->courseImplMapper->findByFolderId($folders->getColumnValues('id'));

        // Get all unitSpecs imported to relevant folders
        $unitSpecsImportedToFolders = $this->unitSpecMapper->createCollection();
        foreach ($courseImpls as $courseImpl) {
            foreach ($courseImpl->getUnits() as $unitImpl) {
                $unitSpec = $unitImpl->getSpecification();
                $unitSpecsImportedToFolders->setOffset($unitSpec->getId(), $unitSpec);
            }
        }

        // Divide folders into Classes and Groups (not mutually exclusive!)

        // Was course imported to a class? ?
        /** @var CourseImpl\Model[] $courseImplsOnClasses */
        $courseImplsOnClasses = $courseImpls->filter(function (CourseImpl\Model $courseImpl) {
            return $courseImpl->getFolder()->getCodes()->count();
        })->indexBy('id');

        // Was course imported to a group? ?
        /** @var CourseImpl\Model[] $courseImplsOnGroups */
        $courseImplsOnGroups = $courseImpls->filter(function (CourseImpl\Model $courseImpl) {
            return $courseImpl->getFolder()->getGroup();
        })->indexBy('id');

        // Get all unitSpecs imported to classes
        $unitSpecsImportedToClasses = $this->unitSpecMapper->createCollection();
        foreach ($courseImplsOnClasses as $courseImpl) {
            foreach ($courseImpl->getUnits() as $unitImpl) {
                $unitSpec = $unitImpl->getSpecification();
                $unitSpecsImportedToClasses->setOffset($unitSpec->getId(), $unitSpec);
            }
        }

        // Get all unitSpecs imported to groups
        $unitSpecsImportedToGroups = $this->unitSpecMapper->createCollection();
        foreach ($courseImplsOnGroups as $courseImpl) {
            foreach ($courseImpl->getUnits() as $unitImpl) {
                $unitSpec = $unitImpl->getSpecification();
                $unitSpecsImportedToGroups->setOffset($unitSpec->getId(), $unitSpec);
            }
        }
        return new Component(
            $definition,
            $user,
            $pageFilter,
            $selections,
            $overrides,
            $this->getSelectionMapper(),
            $unitSpecsImportedToFolders,
            $unitSpecsImportedToClasses,
            $unitSpecsImportedToGroups
        );
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        $unitSpecIds = (new Collection($this->unitSpecMapper->findAllIds(['deleted' => true])))
            ->indexByCallback(function ($unitSpecId) {
                return $unitSpecId;
            });
        return new KeyConverter($filter, $unitSpecIds);
    }
}
