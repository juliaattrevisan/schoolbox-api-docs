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
use Schoolbox\GenericCollection;
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
            case Filters\Assessment\AssessmentType\Definition::class:
                return $this->buildFactory(Filters\Assessment\AssessmentType\Factory::class);
            case Filters\Assessment\Folder\TeacherInvolved\Definition::class:
                return $this->buildFactory(Filters\Assessment\Folder\TeacherInvolved\Factory::class, [
                    'folderMapper' => $this->getMapperFactory()->get(Folder\Mapper::class),
                    'access'       => $this->getMapperFactory()->get(Access::class),
                ]);
            case Filters\Assessment\Folder\StudentInteractions\Definition::class:
                return $this->buildFactory(Filters\Assessment\Folder\StudentInteractions\Factory::class, [
                    'folderMapper' => $this->getMapperFactory()->get(Folder\Mapper::class),
                ]);
            case Filters\Assessment\Term\Due\Definition::class:
                return $this->buildFactory(Filters\Assessment\Term\Due\Factory::class, [
                    'termMapper' => $this->getMapperFactory()->get(Term\Mapper::class),
                ]);
            case Filters\Assessment\Term\Close\Definition::class:
                return $this->buildFactory(Filters\Assessment\Term\Close\Factory::class, [
                    'termMapper' => $this->getMapperFactory()->get(Term\Mapper::class),
                ]);
            case Filters\Assessment\TemporalState\Definition::class:
                return $this->buildFactory(Filters\Assessment\TemporalState\Factory::class);
            case Filters\Assessment\WorkType\Definition::class:
                return $this->buildFactory(Filters\Assessment\WorkType\Factory::class, [
                    'workTypeMapper' => $this->getMapperFactory()->get(WorkType\Mapper::class),
                ]);
            case Filters\Assessment\WeightingType\Definition::class:
                return $this->buildFactory(Filters\Assessment\WeightingType\Factory::class);
            case Filters\Assessment\NullableContext\UnitImportSpec\Definition::class:
                return $this->buildFactory(Filters\Assessment\NullableContext\UnitImportSpec\Factory::class, [
                    'folderMapper'     => $this->getMapperFactory()->get(Folder\Mapper::class),
                    'courseImplMapper' => $this->getMapperFactory()->get(CourseImpl\Mapper::class),
                    'unitSpecMapper'   => $this->getMapperFactory()->get(UnitSpec\Mapper::class),
                ]);
            case Filters\Assessment\NullableContext\Project\Definition::class:
                return $this->buildFactory(Filters\Assessment\NullableContext\Project\Factory::class, [
                    'folderMapper'   => $this->getMapperFactory()->get(Folder\Mapper::class),
                    'unitImplMapper' => $this->getMapperFactory()->get(UnitImpl\Mapper::class),
                    'projectMapper'  => $this->getMapperFactory()->get(Project\Mapper::class),
                ]);
            case Filters\Assessment\NullableContext\UnitSpec\Definition::class:
                return $this->buildFactory(Filters\Assessment\NullableContext\UnitSpec\Factory::class, [
                    'unitSpecMapper' => $this->getMapperFactory()->get(UnitSpec\Mapper::class),
                ]);
            case Filters\Assessment\NullableContext\UnitImpl\Definition::class:
                return $this->buildFactory(Filters\Assessment\NullableContext\UnitImpl\Factory::class, [
                    'unitImplMapper' => $this->getMapperFactory()->get(UnitImpl\Mapper::class),
                ]);
        }
        throw new \InvalidArgumentException('No filter factory registered for class: ' . $filterDefinitionClass);
    }


    /**
     * An assessment-specific function to ensure that if the admin disables the teacher-folder filter
     * then we fall back to the viewee's involved classes and groups.
     *
     * Also users that the user is an instructor of any filtered-to teacher-folder.
     *
     * @param null[]|Collection[] $filters
     * @param User                $user
     * @param User                $viewee
     * @return null[]|Collection[]
     */
    public function checkTeacherFolders(array $filters, User $user, User $viewee)
    {
        /** @var Folder\Mapper $folderMapper */
        $folderMapper    = $this->getMapperFactory()->get(Folder\Mapper::class);
        $access          = Access::getInstance();

        // No teacher folder ?
        if (!array_key_exists(Filters\Assessment\Folder\TeacherInvolved\Definition::class, $filters)){

            // Insert all involved folders for the specified viewee
            $filters[Filters\Assessment\Folder\TeacherInvolved\Definition::class] = new Collection();
            $involvedFolders = $folderMapper->findForUser($viewee, true, UserAccess::ACCESS_WRITE);
            foreach($involvedFolders as $involvedFolder){
                $folderId = $involvedFolder->getId();
                $filters[Filters\Assessment\Folder\TeacherInvolved\Definition::class][$folderId] = $folderId;
            }
        }

        foreach($filters[Filters\Assessment\Folder\TeacherInvolved\Definition::class] as $folderId){
            $folder = $folderMapper->createModel($folderId);
            if(!$access->isInstructor($user, $folder)){
                $filters[Filters\Assessment\Folder\TeacherInvolved\Definition::class]->offsetUnset($folderId);
            }
        }

        return $filters;
    }


    /**
     * @inheritdoc
     */
    protected function buildTransformer(GenericCollection $keyConverters, Collection $selectionsPerFilter)
    {
        return new Transformer($keyConverters, $selectionsPerFilter);
    }
}
