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
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImplementation;

/**
 * The Assessment FilterFactoryFactory is responsible for creating assessment-filter factories.
 */
class FactoryFactory extends Filter\AbstractFactoryFactory
{

    protected const ENTITY = Assessment\Mapper::class;


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
    public function buildFilterFactoryForFilter($filterDefinitionClass): IFactory
    {
        if (!class_exists($filterDefinitionClass)) {
            throw new \InvalidArgumentException('No filter definition found for class: ' . $filterDefinitionClass);
        }

        // Registry of filters definitions, and their extra dependencies
        switch ($filterDefinitionClass) {
            case AssessmentType\Definition::class:
                return $this->buildFactory(AssessmentType\Factory::class);
            case Filters\Assessment\Folder\TeacherInvolved\Definition::class:
                return new Filters\Assessment\Folder\TeacherInvolved\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->getFolderMapper(),
                    $this->getAccess()
                );
            case Filters\Assessment\Folder\CourseImports\Definition::class:
                return new Filters\Assessment\Folder\CourseImports\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->getFolderMapper(),
                    $this->getAccess(),
                    $this->getFilterMapper(),
                    $this->getUnitImplMapper()
                );
            case Filters\Assessment\Folder\StudentInteractions\Definition::class:
                return $this->buildFactory(Filters\Assessment\Folder\StudentInteractions\Factory::class, [
                    'folderMapper' => $this->getMapperFactory()->get(Folder\Mapper::class),
                    'access'       => $this->getAccess(),
                ]);
            case Filters\Assessment\Term\Due\Definition::class:
                return $this->buildFactory(Filters\Assessment\Term\Due\Factory::class, [
                    'termMapper' => $this->getMapperFactory()->get(Term\Mapper::class),
                ]);
            case Filters\Assessment\Term\Close\Definition::class:
                return $this->buildFactory(Filters\Assessment\Term\Close\Factory::class, [
                    'termMapper' => $this->getMapperFactory()->get(Term\Mapper::class),
                ]);
            case TemporalState\Definition::class:
                return $this->buildFactory(TemporalState\Factory::class);
            case Filters\Assessment\WorkType\Definition::class:
                return $this->buildFactory(Filters\Assessment\WorkType\Factory::class, [
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
     * @throws \InvalidArgumentException    If unable to find specified Mapper class
     */
    public function checkTeacherFolders(array $filters, User $viewee): array
    {
        /** @var Folder\Mapper $folderMapper */
        $folderMapper = $this->getMapperFactory()->get(Folder\Mapper::class);

        // No teacher-folder filter ? (Either not defined or set to NULL)
        if (!isset($filters[Filters\Assessment\Folder\TeacherInvolved\Definition::class])) {

            // Insert all involved folders for the specified viewee
            $filters[Filters\Assessment\Folder\TeacherInvolved\Definition::class] = new Collection();
            $involvedFolders                                                      = $folderMapper->findForUser($viewee, true, UserAccess::ACCESS_WRITE);
            foreach ($involvedFolders as $involvedFolder) {
                $folderId                                                                        = $involvedFolder->getId();
                $filters[Filters\Assessment\Folder\TeacherInvolved\Definition::class][$folderId] = $folderId;
            }
        }

        return $filters;
    }


    /**
     * @return Folder\Mapper
     * @throws \InvalidArgumentException
     */
    protected function getFolderMapper(): Folder\Mapper
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getMapperFactory()->get(Folder\Mapper::class);
    }


    /**
     * @return Access
     * @throws \InvalidArgumentException
     */
    protected function getAccess(): Access
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getMapperFactory()->get(Access::class);
    }


    /**
     * @return Filter\Mapper
     * @throws \InvalidArgumentException
     */
    protected function getFilterMapper(): Filter\Mapper
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getMapperFactory()->get(Filter\Mapper::class);
    }


    /**
     * @return UnitImplementation\Mapper
     * @throws \InvalidArgumentException
     */
    protected function getUnitImplMapper(): UnitImplementation\Mapper
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getMapperFactory()->get(UnitImplementation\Mapper::class);
    }
}
