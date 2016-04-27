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

use Component_Homepage_CourseBuilderController;
use PDO;
use PDOStatement;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Collection;
use Schoolbox\Mapper\PredicateBuilder;
use Schoolbox\Resource\Folder\Ancestry;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Error\Mapper as ErrorMapper;

/**
 * Class Mapper
 *
 * @package Schoolbox\Assessment\Course
 * @method Model findById($id)
 */
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
    protected $ancestry;

    /**
     * @inheritdoc
     *
     * @throws \Schoolbox\Mapper\Exception
     */
    protected function mapping()
    {
        parent::mapping();

        $this->hasPrototype(new Model());

        // reverse join onto self for unit definition
        $this->joins('id', Unit\Mapper::class, 'parent', null, self::COLUMN_JOIN_MULTI, 'units', 'unit');

        $this->ancestry = new Ancestry(FolderMapper::getInstance());
    }


    /**
     * Hook method which occurs after successful construction
     */
    protected function postSetup()
    {

        /* TODO: Replace /homepage/29 in config/course_homepage with just int
         * Faculty Root Node can be obtained Cheaply
         * but we have to strip out the /homepage/ part
         */
        $facultyNodeId = preg_replace('/[^0-9]/', '', $this->getConfigLoader()->get('course_homepage'));

        $this->setFacultyNode(FolderMapper::getInstance()->findCheaplyById($facultyNodeId));
    }


    /**
     * Return the first part of the string required to select from this mapper
     *
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('unit', 'unit')}
        ";
    }


    /**
     * It seems we're going down this road...
     *
     * @return string
     */
    protected function getFromString()
    {
        return "
            FROM `{$this->getTable()}`
            LEFT JOIN {$this->getJoinSql('unit', '', 'unit', true)}
            INNER JOIN component_instance ci ON ci.identifier = unit.id AND ci.module = :module
            LEFT JOIN component_definition cd ON ci.component_definition_id = cd.id
        ";
    }


    /**
     * @param  PDOStatement $statement
     * @return Collection|Folder[]
     */
    protected function hydrateSelect($statement)
    {
        /** @var Model[] $cache */
        $cache = [];

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {

            $courseId = $row['id'];

            if (empty($cache[$courseId])) {
                $cache[$courseId] = $this->hydrate($row);
                $cache[$courseId]->setUnits($this->createCollection());
            }

            $cache[$courseId]->getUnits()->add($this->hydrateJoin($row, 'unit', 'unit'));
        }

        return $this->createCollection(array_values($cache));
    }


    /**
     * Builds a where part and params for this query out of a column list
     *
     * @param PredicateBuilder $predicate
     * @param  array           $columnList
     * @param  array           $options
     *
     * @return array where:  The where parts of the query
     *               params: the parameters
     * @throws \UnderflowException
     */
    protected function buildWherePart(PredicateBuilder $predicate, $columnList, $options)
    {
        $predicate->equals('cd.class', Component_Homepage_CourseBuilderController::class);

        if ($options['deleted'] == false) {
            $predicate->isNull('unit.deleted_at');
        }

        // add custom param from component_instance join
        $predicate->putParam('module', 'homepage');

        return parent::buildWherePart($predicate, $columnList, $options);
    }


    /**
     * List the courses. Convenience method for findByColumn('name', LIKE($name));
     *
     * @param string $searchParam
     * @param array  $options
     * @return Collection|Folder[]
     */
    public function findCourseListByName($searchParam, $options = [])
    {
        return $this->findByColumn('name', "%{$this->database->quoteForLike($searchParam)}%", $options);
    }


    /**
     * Get a list of Units and Tasks for a particular Course
     *
     * We collect homepage configs and Group access information here
     * because sometimes we need to check whether a Course or Unit is
     * linked to a homepage. If there is a homepage for the unit, we
     * also need to check access perms before providing a link to users
     *
     * @deprecated idk where this needs to go, but it needs to not be here
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
     * Find the number of tasks for each subfolder (unit) of the given course, indexed by ID. Convenience method.
     *
     * @param Folder $course
     * @return array
     */
    protected function findUnitTaskCount(Folder $course)
    {
        return TaskMapper::getInstance()->countForEachUnit(Unit\Mapper::getInstance()->findByCourseId($course->getId()));
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
     * @param Folder $course
     * @return bool
     */
    public function verifyCourse(Folder $course)
    {
        // a course is defined as a folder that contains units
        return Unit\Mapper::getInstance()->findByCourseId($course->getId())->count() > 0;
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


    /**
     * It is permissible that a folder be converted to a course (ie. have units put inside it)
     * if and only if the following two conditions are met:
     *  1) it is in the Faculty area (that is, it is a descendant of facultyRoot)
     *  2) it does not contain any courses anywhere beneath it
     *
     * @param Folder $folder
     * @return bool
     */
    public function mayBeConvertedToCourse(Folder $folder)
    {
        // it's a Course... it's allowed to be a course
        if ($folder instanceof Model) {
            return true;
        }

        return $this->inFacultyArea($folder) && !$this->containsCourses($folder);
    }

    /**
     * @param Folder $folder
     * @return bool
     */
    protected function inFacultyArea(Folder $folder)
    {
        return $this->getAncestry()->hasParent($folder, $this->getFacultyNode());
    }

    /**
     * Iteratively checks whether or not the given folder contains courses
     *
     * @param Folder $folder
     * @return bool
     */
    protected function containsCourses(Folder $folder)
    {
        foreach ($this->findAll() as $course) {
            $ancestorIds = $this->getAncestry()->findParents($course)->getColumnValues('id');

            if (in_array($folder->getId(), $ancestorIds)) {
                return true;
            }
        }

        return false;
    }


    /**
     * @return Ancestry
     */
    public function getAncestry()
    {
        return $this->ancestry;
    }
}
