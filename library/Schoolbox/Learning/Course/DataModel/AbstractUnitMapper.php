<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;

/**
 * Common behaviour for UnitSpec + UnitImpl mappers
 */
abstract class AbstractUnitMapper extends BaseMapper
{
    /**
     * Finds all units for this course
     *
     * @param int|array $courseId
     * @param array     $options
     * @return AbstractUnit[]|GenericCollection
     */
    abstract function findAllByCourseId($courseId, $options = []);
}