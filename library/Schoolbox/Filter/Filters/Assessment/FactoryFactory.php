<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\IFactory;
use Schoolbox\Assessment\Term;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Assessment\DataModel\Project;
use Schoolbox\Filter\Filters;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\User;
use Schoolbox\Filter\Filters\Assessment\Folder as FolderFilter;
use Schoolbox\Filter\Filters\Assessment\Term as TermFilter;
use Schoolbox\Filter\Filters\Assessment\WorkType as WorkTypeFilter;


/**
 * The Assessment FilterFactoryFactory is responsible for creating assessment-filter factories.
 */
class FactoryFactory extends Filter\AbstractFactoryFactory
{

    const ENTITY = Assessment\Mapper::class;


    /**
     * A registry of assessment-filter factories, along with their construction dependencies.
     *
     * Registered by filter definition class name.
     *
     * @param string $filterDefinitionClass
     * @return IFactory
     * @throws \InvalidArgumentException if no filter definition class not found, or filter factory not registered
     * @throws \ReflectionException If the specified class does not exist
     */
    public function buildFilterFactoryForFilter($filterDefinitionClass)
    {
        if (!class_exists($filterDefinitionClass)) {
            throw new \InvalidArgumentException('No filter definition found for class: ' . $filterDefinitionClass);
        }

        // Registry of filters definitions, and their extra dependencies
        switch ($filterDefinitionClass) {
            case AssessmentType\Definition::class:
                return $this->buildFactory(AssessmentType\Factory::class);
            case FolderFilter\TeacherInvolved\Definition::class:
                return $this->buildFactory(FolderFilter\TeacherInvolved\Factory::class, [
                    'folderMapper' => $this->getMapperFactory()->get(Folder\Mapper::class),
                    'access'       => $this->getMapperFactory()->get(Access::class),
                ]);
            case FolderFilter\StudentInteractions\Definition::class:
                return $this->buildFactory(FolderFilter\StudentInteractions\Factory::class, [
                    'folderMapper' => $this->getMapperFactory()->get(Folder\Mapper::class),
                ]);
            case TermFilter\Due\Definition::class:
                return $this->buildFactory(TermFilter\Due\Factory::class, [
                    'termMapper' => $this->getMapperFactory()->get(Term\Mapper::class),
                ]);
            case TermFilter\Close\Definition::class:
                return $this->buildFactory(TermFilter\Close\Factory::class, [
                    'termMapper' => $this->getMapperFactory()->get(Term\Mapper::class),
                ]);
            case TemporalState\Definition::class:
                return $this->buildFactory(TemporalState\Factory::class);
            case WorkTypeFilter\Definition::class:
                return $this->buildFactory(WorkTypeFilter\Factory::class, [
                    'workTypeMapper' => $this->getMapperFactory()->get(WorkType\Mapper::class),
                ]);
            case WeightingType\Definition::class:
                return $this->buildFactory(WeightingType\Factory::class);
            case NullableContext\UnitImportSpec\Definition::class:
                return $this->buildFactory(NullableContext\UnitImportSpec\Factory::class, [
                    'folderMapper'     => $this->getMapperFactory()->get(Folder\Mapper::class),
                    'courseImplMapper' => $this->getMapperFactory()->get(CourseImpl\Mapper::class),
                    'unitSpecMapper'   => $this->getMapperFactory()->get(UnitSpec\Mapper::class),
                ]);
            case NullableContext\Project\Definition::class:
                return $this->buildFactory(NullableContext\Project\Factory::class, [
                    'folderMapper'   => $this->getMapperFactory()->get(Folder\Mapper::class),
                    'unitImplMapper' => $this->getMapperFactory()->get(UnitImpl\Mapper::class),
                    'projectMapper'  => $this->getMapperFactory()->get(Project\Mapper::class),
                ]);
            case NullableContext\UnitSpec\Definition::class:
                return $this->buildFactory(NullableContext\UnitSpec\Factory::class, [
                    'unitSpecMapper' => $this->getMapperFactory()->get(UnitSpec\Mapper::class),
                ]);
            case NullableContext\UnitImpl\Definition::class:
                return $this->buildFactory(NullableContext\UnitImpl\Factory::class, [
                    'unitImplMapper' => $this->getMapperFactory()->get(UnitImpl\Mapper::class),
                ]);
            case YearDue\Definition::class:
                return $this->buildFactory(YearDue\Factory::class);
        }
        throw new \InvalidArgumentException('No filter factory registered for class: ' . $filterDefinitionClass);
    }


    /**
     * An assessment-specific function to ensure that if the admin disables the teacher-folder filter
     * then we fall back to the viewee's involved classes and groups.
     *
     * @param null[]|Collection[] $filters
     * @param User                $viewee
     * @return null[]|Collection[]
     */
    public function checkTeacherFolders(array $filters, User $viewee)
    {
        /** @var Folder\Mapper $folderMapper */
        $folderMapper = $this->getMapperFactory()->get(Folder\Mapper::class);

        // No teacher-folder filter ? (Either not defined or set to NULL)
        if (!isset($filters[FolderFilter\TeacherInvolved\Definition::class])) {

            // Insert all involved folders for the specified viewee
            $filters[FolderFilter\TeacherInvolved\Definition::class] = new Collection();
            $involvedFolders                                         = $folderMapper->findForUser($viewee, true, UserAccess::ACCESS_WRITE);
            foreach ($involvedFolders as $involvedFolder) {
                $folderId                                                           = $involvedFolder->getId();
                $filters[FolderFilter\TeacherInvolved\Definition::class][$folderId] = $folderId;
            }
        }

        return $filters;
    }
}
