<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types = 1);

namespace Schoolbox\Filter\Filters\Assessment;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\IFactory;
use Schoolbox\Assessment\Term;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\MapperFactory;
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
use Schoolbox\Mapper;
use Schoolbox\YearLevel;
use InvalidArgumentException;

/**
 * The Assessment FilterFactoryFactory is responsible for creating assessment-filter factories.
 */
class FactoryFactory extends Filter\AbstractFactoryFactory
{

    protected const ENTITY = Assessment\Mapper::class;

    /** @var RosettaStone */
    private $rosettaStone;


    /**
     * @param MapperFactory $mapperFactory
     * @param RosettaStone  $rosettaStone
     */
    public function __construct(MapperFactory $mapperFactory, RosettaStone $rosettaStone)
    {
        parent::__construct($mapperFactory);
        $this->rosettaStone = $rosettaStone;
    }


    /**
     * @inheritDoc
     */
    public function buildFilterFactoryForFilter(string $filterDefinitionClass): IFactory
    {
        if (!class_exists($filterDefinitionClass)) {
            throw new InvalidArgumentException('No filter definition found for class: ' . $filterDefinitionClass);
        }

        // Registry of filters definitions, and their extra dependencies
        switch ($filterDefinitionClass) {
            case AssessmentType\Definition::class:
                return new AssessmentType\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper()
                );
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
                    $this->getCourseImplMapper()
                );
            case Filters\Assessment\Folder\StudentInteractions\Definition::class:
                return new Filters\Assessment\Folder\StudentInteractions\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->getFolderMapper(),
                    $this->getAccess()
                );
            case Filters\Assessment\Term\Due\Definition::class:
                return new Filters\Assessment\Term\Due\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->getTermMapper()
                );
            case Filters\Assessment\Term\Close\Definition::class:
                return new Filters\Assessment\Term\Close\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->getTermMapper()
                );
            case TemporalState\Definition::class:
                return new TemporalState\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper()
                );
            case Filters\Assessment\WorkType\Definition::class:
                return new Filters\Assessment\WorkType\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->getWorkTypeMapper()
                );
            case WeightingType\Definition::class:
                return new WeightingType\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper()
                );
            case NullableContext\UnitImportSpec\Definition::class:
                return new NullableContext\UnitImportSpec\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->getFolderMapper(),
                    $this->getCourseImplMapper(),
                    $this->getUnitSpecMapper()
                );
            case NullableContext\Project\Definition::class:
                return new NullableContext\Project\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->getFolderMapper(),
                    $this->getUnitImplMapper(),
                    $this->getProjectMapper()
                );
            case NullableContext\UnitSpec\Definition::class:
                return new NullableContext\UnitSpec\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->getUnitSpecMapper()
                );
            case NullableContext\UnitImpl\Definition::class:
                return new NullableContext\UnitImpl\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->getUnitImplMapper()
                );
            case YearDue\Definition::class:
                return new YearDue\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper()
                );
            case RubricUsage\Definition::class:
                return new RubricUsage\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->getFolderMapper(),
                    $this->rosettaStone
                );
            case MarkPerCap\Definition::class:
                return new MarkPerCap\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->rosettaStone
                );
            case FolderYearLevel\Definition::class:
                return new FolderYearLevel\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->getYearLevelMapper()
                );
        }
        throw new InvalidArgumentException('No filter factory registered for class: ' . $filterDefinitionClass);
    }


    /**
     * An assessment-specific function to ensure that if the admin disables the teacher-folder filter
     * then we fall back to the viewee's involved classes and groups.
     *
     * @param null[]|Collection[] $filters
     * @param User                $viewee
     * @return null[]|Collection[]
     * @throws InvalidArgumentException    If unable to find specified Mapper class
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
     * @throws InvalidArgumentException
     */
    private function getFolderMapper(): Folder\Mapper
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getMapperFactory()->get(Folder\Mapper::class);
    }


    /**
     * @return Access
     * @throws InvalidArgumentException
     */
    private function getAccess(): Access
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getMapperFactory()->get(Access::class);
    }


    /**
     * @return UnitImpl\Mapper
     * @throws InvalidArgumentException
     */
    private function getUnitImplMapper(): UnitImpl\Mapper
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getMapperFactory()->get(UnitImpl\Mapper::class);
    }


    /**
     * @return Term\Mapper|Mapper
     * @throws InvalidArgumentException
     */
    private function getTermMapper(): Term\Mapper
    {
        return $this->getMapperFactory()->get(Term\Mapper::class);
    }


    /**
     * @return WorkType\Mapper|Mapper
     * @throws InvalidArgumentException
     */
    private function getWorkTypeMapper(): WorkType\Mapper
    {
        return $this->getMapperFactory()->get(WorkType\Mapper::class);
    }


    /**
     * @return CourseImpl\Mapper|Mapper
     * @throws InvalidArgumentException
     */
    private function getCourseImplMapper(): CourseImpl\Mapper
    {
        return $this->getMapperFactory()->get(CourseImpl\Mapper::class);
    }


    /**
     * @return UnitSpec\Mapper|Mapper
     * @throws InvalidArgumentException
     */
    private function getUnitSpecMapper(): UnitSpec\Mapper
    {
        return $this->getMapperFactory()->get(UnitSpec\Mapper::class);
    }


    /**
     * @return Project\Mapper|Mapper
     * @throws InvalidArgumentException
     */
    private function getProjectMapper(): Project\Mapper
    {
        return $this->getMapperFactory()->get(Project\Mapper::class);
    }

    /**
     * @return YearLevel\Mapper|Mapper
     * @throws InvalidArgumentException
     */
    private function getYearLevelMapper(): YearLevel\Mapper
    {
        return $this->getMapperFactory()->get(YearLevel\Mapper::class);
    }
}
