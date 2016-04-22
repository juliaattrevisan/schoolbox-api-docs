<?php
/**
 * A Mapper Composite (a pseudo mapper) like the RubricMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Assessment\Course;

use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Collection;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Ancestry;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Error\Mapper as ErrorMapper;

class Mapper extends FolderMapper
{
    /**
     * A Folder instance that represents the root node
     * for Faculties
     *
     * @var Folder
     */
    private $facultyNode = null;

    /**
     * A cache of ancestral Folder instances, to save
     * on re-querying the same Folder from the db over
     * and over again
     *
     * @var Ancestry
     */
    private $ancestry = null;

    /**
     * Hook method which occurs after successful construction
     */
    protected function postSetup()
    {
        $this->setAncestry(new Ancestry());

        /* TODO: Replace /homepage/29 in config/course_homepage with just int
         * Faculty Root Node can be obtained Cheaply
         * but we have to strip out the /homepage/ part
         */
        $facultyNodeId = preg_replace('/[^0-9]/', '', $this->getConfigLoader()->get('course_homepage'));

        $this->setFacultyNode($this->findCheaplyById($facultyNodeId));
    }

    /**
     * Delegation method
     *
     * Clear out all the objects in the Ancestry
     */
    public function resetAncestry()
    {
        $this->getAncestry()->reset();
    }

    /**
     * Similar to findById() but restricts finds to Course Folders only
     * Courses must have Units and Tasks to be matched successfully
     *
     * @param $id
     * @return Folder
     * @throws MapperException
     */
    public function findCourseById($id)
    {
        $query = "
            SELECT
                course.*,
                COUNT(task.id) AS taskCount
            FROM category course
            LEFT JOIN category unit ON unit.parent = course.id AND unit.deleted_at IS NULL
            INNER JOIN `task` ON task.category_id = unit.id AND task.deleted_at IS NULL
            WHERE course.deleted_at IS NULL
            AND course.id = :id
            GROUP BY course.id
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'id' => $id
        ]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            return $this->hydrate($row);
        }

        throw new MapperException('Course not found');
    }

    /**
     * List the courses. Returns a combined array of Folder instances
     * and information about any found Units and their task counts
     *
     * @param $searchParam
     * @return array
     */
    public function findCourseListByName($searchParam)
    {
        $query = "
            SELECT
                course.id,
                COUNT(task.id) AS taskCount
            FROM category course
            LEFT JOIN category unit ON unit.parent = course.id AND unit.deleted_at IS NULL
            INNER JOIN `task` ON task.category_id = unit.id AND task.deleted_at IS NULL
            WHERE course.deleted_at IS NULL
            AND course.name LIKE :searchParam
            GROUP BY course.id
        ";

        /* Find all the Courses with names like $searchParam,
         * if that was provided (otherwise returns all)
         */
        $statement = $this->database->prepare($query);
        $statement->execute([
            'searchParam' => "%{$this->database->quoteForLike($searchParam)}%"
        ]);

        /* Accumulate a list of ids
         */
        $allFolderIds = [];

        foreach ($statement as $row) {
            $allFolderIds[] = $row['id'];
        }

        /* Manufacture a list of Courses so we can get their
         * Unit information and Task Counts
         */

        /** @var Collection|Folder[] $courses */
        $allCourses = $this->findByColumn('id', $allFolderIds);

        return $allCourses;
    }

    /**
     * List the courses. Returns a combined array of Folder instances
     * and information about any found Units and their task counts
     *
     * @param $searchParam
     * @return array
     */
    public function findCourseList($searchParam)
    {
        $allCourses = $this->findCourseListByName($searchParam);

        $data = [
            'courses' => $allCourses
        ];

        foreach ($allCourses as $currentCourse) {
            $allUnits = $this->findTaskCountForAllUnits($currentCourse);

            /* Only display units which have tasks in them
             */
            foreach ($allUnits as $currentUnit) {
                if ($currentUnit->getTaskCount()) {
                    $data['units'][$currentCourse->getId()][] = $currentUnit;
                }
            }

            /* Also store the path to this Course
             */
            $data['lineage'][$currentCourse->getId()] = $this->getLineage($currentCourse);
        }

        return $data;
    }


    /**
     * @param Folder $course
     * @return Collection
     */
    public function getLineage(Folder $course)
    {
        return $this->getAncestry()->findParentsCheaply($course, $this->getFacultyNode());
    }

    /**
     * Get a list of Units and Tasks for a particular Course
     *
     * We collect homepage configs and Group access information here
     * because sometimes we need to check whether a Course or Unit is
     * linked to a homepage. If there is a homepage for the unit, we
     * also need to check access perms before providing a link to users
     *
     * @param Folder $course
     * @return Collection|Folder[]
     * @throws MapperException
     */
    public function findTaskCountForAllUnits(Folder $course)
    {
        $code_mapper = $this->getJoinMapper('codes');
        $homepageConfig_mapper = $this->getJoinMapper('homepages');
        $group_mapper = $this->getJoinMapper('groups');

        // Please forgive me, I added a subquery for getting if a category
        // contains tasks
        $query = "
            SELECT
                {$this->getColumnString('c')},
                {$code_mapper->getColumnString('cc')},
                {$homepageConfig_mapper->getColumnString('hp')},
                {$group_mapper->getColumnString('ug')}
            FROM {$this->getTable()} c
            LEFT JOIN {$this->getJoinSql('codes', 'c', 'cc')}
            LEFT JOIN {$this->getJoinSql('homepages', 'c', 'hp')}
            LEFT JOIN {$this->getJoinSql('groups', 'c', 'ug')}
            WHERE c.deleted_at IS NULL AND c.parent=?
            ORDER BY c.name
        ";

        $statement = $this->database->prepare($query);
        $statement->execute(array($course->getId()));

        /* Get task count as separate result set
         *
         * This augments a Unit instance with the number of tasks
         * contained within it. That can be used to determine
         * whether a Unit is reachable or not via various ui
         */
        $taskCounts = $this->findUnitTaskCount($course);

        $collection = new Collection();
        $category = null;

        foreach ($statement as $row) {
            $existingCat = $collection->filterBy('id', $row['c.id']);

            if ($existingCat->count()) {
                //Folder already exists in collection
                $code = $code_mapper->hydrate($row, 'cc');

                if ($code->getCategory()->getId()) {
                    $existingCat->first()->addCode($code);
                }
            }
            else {
                $category = $this->hydrate($row, 'c');

                if (isset($taskCounts[$row['c.id']])) {
                    $category->setTaskCount($taskCounts[$row['c.id']]);
                }

                /* We collect homepage configs and Group access information here
                 * because sometimes we need to check whether a Course or Unit is
                 * linked to a homepage. If there is a homepage for the unit, we
                 * also need to check access perms
                 */
                $homepageConfig = $homepageConfig_mapper->hydrate($row, 'hp');
                $category->setHomepage($homepageConfig);

                $group = $group_mapper->hydrate($row, 'ug');
                $category->setGroup($group);

                $code = $code_mapper->hydrate($row, 'cc');

                if ($code->getCategory()->getId()) {
                    $category->addCode($code);
                }

                $collection->add($category);
            }
        }

        return $collection;
    }


    /**
     * Find the number of tasks for each subfolder (unit) of the given course, indexed by ID
     *
     * @param Folder $course
     * @return array
     */
    protected function findUnitTaskCount(Folder $course)
    {
        $unitIds = $this->findUnitsInCourse($course)->getColumnValues('id');

        $stmt = $this->getDatabase()->prepare("
            SELECT COUNT(*)
            FROM {$this->getTable()} unit
            LEFT JOIN task ON task.category_id = unit.id AND task.deleted_at IS NULL
            WHERE unit.id = ?
            GROUP BY unit.id;
        ");

        // preset everything to zero: we still want the keys populated even if the query somehow returns nothing
        $taskCounts = array_combine($unitIds, array_fill(0, count($unitIds), 0));

        foreach ($unitIds as $unitId) {
            // iterate and find the task count for each unit
            $stmt->execute([ $unitId ]);
            $taskCounts[$unitId] = intval($stmt->fetchColumn());
        }

        return $taskCounts;
    }


    /**
     * Find the total number of tasks for the given course
     *
     * @param Folder $course
     * @return int
     */
    public function findCourseTaskCount(Folder $course)
    {
        return array_sum($this->findUnitTaskCount($course));
    }


    /**
     * Returns whether or not the given folder is, in fact, a course
     *
     * @param Folder $folder
     * @return bool
     */
    public function verifyCourse(Folder $folder)
    {
        // a course is defined as a folder that contains units
        return $this->findUnitsInCourse($folder)->count() > 0;
    }


    /**
     * @param Folder $course
     * @param bool   $hydrate  whether or not to hydrate the models found
     * @return Collection|Folder[]
     */
    public function findUnitsInCourse(Folder $course, $hydrate = false)
    {
        // the definition of a unit is that is has a course builder: that's it
        $sql = "
            SELECT {$this->getColumnString('c')}
            FROM {$this->getTable()} c
            LEFT JOIN category p ON c.parent = p.id
            LEFT JOIN component_instance ci ON ci.identifier = c.id AND ci.module = :module
            LEFT JOIN component_definition cd ON ci.component_definition_id = cd.id
            WHERE cd.class = :class
            AND c.parent = :parent
            AND c.deleted_at IS NULL
            AND p.deleted_at IS NULL
        ";

        $args = [
            'module' => 'homepage',
            'class'  => \Component_Homepage_CourseBuilderController::class,
            'parent' => $course->getId()
        ];

        $stmt = $this->getDatabase()->query($sql, $args);

        $units = $this->createCollection();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            if ($hydrate) {
                // complete and total hydration. Beware that this is EXPENSIVE!
                $folder = $this->findById($row['c.id']);

            } else {
                $folder = $this->hydrate($row);
            }

            $units->add($folder);
        }

        return $units;
    }

    /* Getters/Setters
     */

    /**
     * Get faculty node
     *
     * @return Folder
     */
    public function getFacultyNode()
    {
        return $this->facultyNode;
    }

    /**
     * Set faculty node
     *
     * @param Folder $facultyNode
     */
    private function setFacultyNode($facultyNode)
    {
        $this->facultyNode = $facultyNode;
    }

    /**
     * Get ancestry
     *
     * @return Ancestry
     */
    private function getAncestry()
    {
        return $this->ancestry;
    }

    /**
     * Set ancestry
     *
     * @param Ancestry $ancestry
     */
    private function setAncestry($ancestry)
    {
        $this->ancestry = $ancestry;
    }

    /**
     * Delete unit from course
     *
     * @param $id
     * @throws SchoolboxException
     */
    public function deleteUnit($id)
    {
        // Grab handles to the mappers as we'll reuse them

        $errorMapper = ErrorMapper::getInstance();
        $folderMapper = FolderMapper::getInstance();
        $unitInstanceMapper = UnitInstanceMapper::getInstance();
        $access = Access::getInstance();

        // Locate the unit we want to delete

        try {
            $unit = $unitInstanceMapper->findById($id);
        } catch (\Exception $e) {
            throw $errorMapper->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
        }

        if ($access->getFolderAccess($this->getSessionUser(), [$unit->getUnitCategory()->getId()]) >= WRITE_ACCESS)
        {
            try {
                $folderMapper->deleteFolder($unit->getUnitCategory()->getId());
            } catch (\Exception $e) {
                throw $errorMapper->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
            }

            $unitInstanceMapper->delete($unit);
        }
        else
        {
            throw $errorMapper->generateException(ErrorMapper::ERROR_PERM_CATEGORY_WRITE);
        }
    }

}
