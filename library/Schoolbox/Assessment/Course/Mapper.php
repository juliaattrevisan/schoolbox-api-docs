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

        $this->hasModel(Model::class);

        // reverse join onto self for unit definition
        $this->joins('id', Unit\Mapper::class, 'parent', null, self::COLUMN_JOIN_MULTI, 'units', 'unit');

        $this->ancestry = new Ancestry(FolderMapper::getInstance());
    }


    /**
     * Hook method which occurs after successful construction
     */
    protected function postSetup()
    {
        $this->setFacultyNode(FolderMapper::getInstance()->findCheaplyById($this->determineFacultyNode()));
    }

    /**
     * Determine the faculty node id
     * All courses live beneath this node in the resource browser
     *
     * Faculty Root Node can be obtained Cheaply
     * but we have to strip out the /homepage/ or /resource/ part
     *
     * @return string
     */
    public function determineFacultyNode()
    {
        return preg_replace('/[^0-9]/', '', $this->getConfigLoader()->get('course_homepage'));
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
    protected function hydrateSelect(PDOStatement $statement)
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
     * It is permissible that a folder be converted to a course (ie. have units put inside it)
     * if and only if the following three conditions are met:
     *  1) it is in the Faculty area (that is, it is a descendant of facultyRoot)
     *  2) it does not contain any courses anywhere beneath it
     *  3) it is not a unit
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

        if ($this->inFacultyArea($folder)) {
            try {
                Unit\Mapper::getInstance()->findById($folder->getId());
            } catch (MapperException $e) {
                // this is the desired case here: not a unit
                return !$this->containsCourses($folder);
            }
        }

        return false;
    }

    /**
     * Is $folder a valid location for a course to be put into? It is if it's either:
     * * it's the faculty root
     * * it's a child of the faculty root
     *
     * @param Folder $folder
     * @return bool
     */
    public function isValidCourseLocation(Folder $folder)
    {
        return
            $this->getFacultyNode()->getId() == $folder->getId()
            || $this->inFacultyArea($folder);
    }

    /**
     * @param Folder $folder
     * @return bool
     */
    public function inFacultyArea(Folder $folder)
    {
        $facultyNode = $this->getFacultyNode();

        if(0 === $facultyNode->getId()){
            return true;
        }

        return $this->getAncestry()->hasParent($folder, $facultyNode);
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
