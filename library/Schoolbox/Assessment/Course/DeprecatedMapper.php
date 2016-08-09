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
use Config\Modules\Course;
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
 * @deprecated This pseudo-mapper will be replaced with a mapper onto an actual
 *             course data source.
 *
 * @package Schoolbox\Assessment\Course
 * @method DeprecatedModel findById($id)
 */
class DeprecatedMapper extends FolderMapper
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

        $this->hasModel(DeprecatedModel::class);

        // reverse join onto self for unit definition
        $this->joins('id', Unit\DeprecatedMapper::class, 'parent', null, self::COLUMN_JOIN_MULTI, 'units', 'unit');

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
        return parent::getSelectString().",
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
        return parent::getFromString()."
            LEFT JOIN {$this->getJoinSql('unit', '', 'unit', true)}
            INNER JOIN component_instance ci ON ci.identifier = unit.id AND ci.module = :module
            LEFT JOIN component_definition cd ON ci.component_definition_id = cd.id
        ";
    }


    /**
     * @param  PDOStatement $statement
     * @return Collection|Folder[]
     */
    protected function hydrateSelect(PDOStatement $statement) {
        $courses = parent::hydrateSelect($statement);
        $statement->execute();
        $units = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $courseId = $row[$this->getIdentifierColumn()];
            // Hydrate units (avoiding duplicates) and cache
            $unitId = $row['unit.'.$this->getIdentifierColumn()];
            if(!isset($units[$courseId][$unitId])){
                $unit = $this->hydrateJoin($row, 'unit', 'unit');
                $units[$courseId][$unit->getId()] = $unit;
            }
        }
        // set n:1 and n:n objects
        foreach ($courses as $course) {
            $course->setUnits($this->createCollection());
            if(!empty($units[$course->getId()])) {
                foreach ($units[$course->getId()] as $unit) {
                    $course->getUnits()->add($unit);
                }
            }
        }
        return $courses;
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
        return Unit\DeprecatedMapper::getInstance()->findByCourseId($course->getId())->count() > 0;
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
        if ($folder instanceof DeprecatedModel) {
            return true;
        }

        if ($this->inFacultyArea($folder)) {
            try {
                Unit\DeprecatedMapper::getInstance()->findById($folder->getId());
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

        if (0 === $facultyNode->getId()) {
            return true;
        }

        try {
            return $this->getAncestry()->hasParent($folder, $facultyNode);
        } catch (MapperException $e) {
            // a parent in the ancestry has been deleted - so no, this isn't in any area, because it technically doesn't exist
            return false;
        }
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
            try {
                $ancestorIds = $this->getAncestry()->findParentsCheaply($course)->getColumnValues('id');
                if (in_array($folder->getId(), $ancestorIds)) {
                    return true;
                }
            } catch (MapperException $e) {
                // an ancestor has been deleted, so ignore this course
                continue;
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


    /**
     * Converts a DeprecatedModel for a course into a new model.
     *
     * @param DeprecatedModel $deprecatedModel
     * @return Model
     */
    public function convertDeprecatedModel(DeprecatedModel $deprecatedModel)
    {
        $model = new Model();
        return $model
            ->setFolder(FolderMapper::getInstance()->findById($deprecatedModel->getId()));
    }
}
