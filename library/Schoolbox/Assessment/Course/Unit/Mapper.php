<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Unit;

use Component_Homepage_CourseBuilderController;
use PDO;
use PDOStatement;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper\PredicateBuilder;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder;

/**
 * A Unit is a specific kind of folder; specifically, one that has a Homepage Course Builder instance attached to it.
 *
 * Class Mapper
 *
 * @package Schoolbox\Assessment\Course\Unit
 * @method Model findById($id)
 */
class Mapper extends Folder\Mapper
{

    /**
     * @inheritdoc
     *
     * @throws \Schoolbox\Mapper\Exception
     */
    protected function mapping()
    {
        parent::mapping();
        $this->hasModel(Model::class);

        // define the join for unit tasks (it's a reverse join, but it works)
        $this->joins('id', TaskMapper::getInstance(), 'category_id', null, self::COLUMN_JOIN_MULTI, 'tasks', 'task');
    }


    /**
     * By default, folders have to be filtered to determine their unity
     *
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinMapper('task')->getColumnString('t')}
        ";
    }


    /**
     * @return string
     */
    protected function getFromString()
    {
        $c = "`{$this->getTable()}`";

        return "
            FROM $c
            LEFT JOIN category course ON $c.parent = course.id
            RIGHT JOIN component_instance ci ON ci.identifier = $c.id AND ci.module = :module
            LEFT JOIN component_definition cd ON ci.component_definition_id = cd.id
            LEFT JOIN {$this->getJoinSql('task', '', 't')}
        ";
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
        // append the custom predicates from the select string
        $predicate->equals('cd.class', Component_Homepage_CourseBuilderController::class);

        if (empty($options['delete']) || $options['delete'] == false) {
            $predicate->isNull('course.deleted_at');
        }

        // add custom param from component_instance join
        $predicate->putParam('module', 'homepage');

        return parent::buildWherePart($predicate, $columnList, $options);
    }


    /**
     * Helper method for hydrating a result from a query created by getFindQuery().
     *
     * @param  PDOStatement $statement
     * @throws MapperException if statement returned no category
     * @return Collection|Folder\Model[]
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var Model[] $cache */
        $cache = [];

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {

            $unitId = $row['id'];

            if (empty($cache[$unitId])) {
                $cache[$unitId] = $this->hydrate($row);
                $cache[$unitId]->setTasks($this->createCollection());
            }

            $tasks = $cache[$unitId]->getTasks();

            $tasks->add($this->hydrateJoin($row, 'task', 't'));
        }

        return $this->createCollection(array_values($cache));
    }


    /**
     * Convenience method for findByColumn('parent')
     *
     * @param int   $courseId
     * @param array $options
     * @return \Schoolbox\Collection
     */
    public function findByCourseId($courseId, $options = [])
    {
        return $this->findByColumn('parent', $courseId, $options);
    }

    /**
     * Convenience method for countByColumn('parent')
     *
     * @param int   $courseId
     * @param array $options
     * @return int
     */
    public function countByCourseId($courseId, array $options = [])
    {
        return $this->countByColumn('parent', $courseId, $options);
    }

}
