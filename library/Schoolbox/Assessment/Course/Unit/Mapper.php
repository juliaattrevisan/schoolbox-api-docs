<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Unit;

use Component_Homepage_CourseBuilderController;
use Schoolbox\Mapper\PredicateBuilder;
use Schoolbox\Resource\Folder;

/**
 * A Unit is a specific kind of folder; specifically, one that has a Homepage Course Builder instance attached to it.
 *
 * Class Mapper
 *
 * @package Schoolbox\Assessment\Course\Unit
 */
class Mapper extends Folder\Mapper
{

    /**
     * By default, folders have to be filtered to determine their unity
     *
     * @return string
     */
    protected function getSelectString()
    {
        return 'SELECT ' . $this->getColumnString();
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

        if ($options['delete'] == false) {
            $predicate->isNull('course.deleted_at');
        }

        // add custom param from component_instance join
        $predicate->putParam('module', 'homepage');

        return parent::buildWherePart($predicate, $columnList, $options);
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
}
