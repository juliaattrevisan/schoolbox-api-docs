<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\NullableContext\UnitImportSpec;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractFilterFactory;
use Schoolbox\Filter\Filters\Assessment\NullableContext\UnitImportSpec\Definition;
use Schoolbox\Filter\IFilterDefinition;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\Filter\Selection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\Resource\Folder;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;


/**
 * A factory for building 'UnitSpec' filter objects
 */
class Factory extends AbstractFilterFactory
{

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var  CourseImpl\Mapper */
    private $courseImplMapper;

    /** @var UnitSpec\Mapper */
    private $unitSpecMapper;


    /**
     * @param Filter\Mapper     $filterMapper
     * @param Option\Mapper     $optionMapper
     * @param Defaults\Mapper   $defaultMapper
     * @param Selection\Mapper  $selectionMapper
     * @param Folder\Mapper     $folderMapper
     * @param CourseImpl\Mapper $courseImplMapper
     * @param UnitSpec\Mapper   $unitSpecMapper
     */
    public function __construct(
        Filter\Mapper $filterMapper,
        Option\Mapper $optionMapper,
        Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper,
        Folder\Mapper $folderMapper,
        CourseImpl\Mapper $courseImplMapper,
        UnitSpec\Mapper $unitSpecMapper
    ) {
        parent::__construct($filterMapper, $optionMapper, $defaultMapper, $selectionMapper);
        $this->folderMapper     = $folderMapper;
        $this->courseImplMapper = $courseImplMapper;
        $this->unitSpecMapper   = $unitSpecMapper;
    }


    /**
     * @inheritdoc
     */
    protected function buildExtraComponentArgs(User $user, $page, IFilterDefinition $definition, User $viewee = null)
    {
        // Should this role type see classes for this page?
        /** @var Option\Model[][] $options */
        $options     = $definition->getOptions($page);
        $classOption = $options[Definition::KEY_IMPORTED_TO_CLASS][$page];
        $groupOption = $options[Definition::KEY_IMPORTED_TO_GROUP][$page];

        $getSubjects = $classOption->getAvailable();

        $groupAccessLevel = $groupOption->getAvailable()
            ? UserAccess::ACCESS_WRITE
            : -1; // TODO - fix this dirty hack which is being used to prevent retrieval of group folders!

        $folders = $this->folderMapper->findForUser($viewee, $getSubjects, $groupAccessLevel, true);


        // Get all courses imported to these folders
        $courseImpls = $this->courseImplMapper->findByFolderId($folders->getColumnValues('id'));

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

        return [
            'unitSpecsImportedToClasses' => $unitSpecsImportedToClasses,
            'unitSpecsImportedToGroups'  => $unitSpecsImportedToGroups,
        ];
    }


    /**
     * @inheritdoc
     */
    protected function buildKeyConverterArgs(Filter\Model $filter)
    {
        $unitSpecIds = (new Collection($this->unitSpecMapper->findAllIds(['deleted' => true])))->indexByCallback(function ($unitSpecId) {
            return $unitSpecId;
        });

        return array_merge(parent::buildKeyConverterArgs($filter), [
            'unitSpecIds' => $unitSpecIds,
        ]);
    }

}
