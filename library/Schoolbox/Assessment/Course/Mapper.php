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
     * Hook method which occurs after mapping()
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
            $data['lineage'][$currentCourse->getId()] = $this->getAncestry()->findParentsCheaply($currentCourse, $this->getFacultyNode());
        }

        return $data;
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
     * @throws \Schoolbox\Mapper\Exception
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
        $taskCountQuery = "
            SELECT
                unit.id,
                COUNT(task.id) AS taskCount
            FROM {$this->getTable()} course
            LEFT JOIN {$this->getTable()} unit ON unit.parent = course.id AND unit.deleted_at IS NULL
            LEFT JOIN task ON task.category_id = unit.id AND task.deleted_at IS NULL
            WHERE course.deleted_at IS NULL AND course.id = ?
            GROUP BY unit.id
        ";

        $taskCountStatement = $this->database->prepare($taskCountQuery);
        $taskCountStatement->execute([$course->getId()]);
        $taskCounts = [];

        foreach ($taskCountStatement as $row) {
            $taskCounts[$row['id']] = $row['taskCount'];
        }

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
     * @param Int $id
     */
    public function deleteUnit($id)
    {
        // Grab handles to the mappers as we'll reuse them

        $errorMapper = ErrorMapper::getInstance();
        $folderMapper = FolderMapper::getInstance();
        $unitInstanceMapper = UnitInstanceMapper::getInstance();

        // Locate the unit we want to delete

        try {
            $unit = $unitInstanceMapper->findById($id);
        } catch (\Exception $e) {
            $errorMapper->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
        }

        if (Access::getInstance()->getFolderAccess($_SESSION["user"], [$unit->getUnitCategory()->getId()]) >= WRITE_ACCESS)
        {
            try {
                $folderMapper->deleteFolder($unit->getUnitCategory()->getId());
            } catch (\Exception $e) {
                $errorMapper->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
            }

            $unitInstanceMapper->delete($unit);
        }
        else
        {
            $errorMapper->generateException(ErrorMapper::ERROR_PERM_CATEGORY_WRITE);
        }
    }

}
