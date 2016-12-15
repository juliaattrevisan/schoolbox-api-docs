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
 * Common behaviour for CourseSpec + CourseImpl mappers
 */
abstract class AbstractCourseMapper extends BaseMapper
{
    /**
     * @param mixed $identifierValue
     * @return AbstractCourse
     */
    public function findById($identifierValue)
    {
        /** @var AbstractCourse $course */
        $course = parent::findById($identifierValue);
        return $this->loadUnits($course);
    }

    /**
     * Loads all units specifications into a course specification.
     *
     * @param AbstractCourse $course
     * @return AbstractCourse
     */
    protected function loadUnits(AbstractCourse $course)
    {
        $collection = $this->createCollection();
        $collection->add($course);
        $this->loadUnitsForCollection($collection);
        return $collection->first();
    }

    /**
     * Loads all units into all the courses in a collection.
     *
     * @param GenericCollection|AbstractCourse[] $courses
     * @return GenericCollection|AbstractCourse[]
     */
    protected function loadUnitsForCollection(GenericCollection $courses)
    {
        $unitMapper = $this->getUnitMapper();

        // Load all the unit impls for all the supplied course impls
        $ids = [];
        foreach ($courses as $course) {
            $ids[] = $course->getId();
        }
        $units = $unitMapper->findAllByCourseId($ids, ['order' => $this->getUnitOrderByClause()])
            ->groupByCallback(function ($v) {
                /** @var AbstractUnit $v */
                return $v->getCourse()->getId();
            });

        // Match unitImpls to their courseImpl
        foreach ($courses as $course) {
            if (isset($units[$course->getId()])) {
                $course->setUnits($units[$course->getId()]);
            }
        }
        return $courses;
    }

    /**
     * @return AbstractUnitMapper
     */
    abstract protected function getUnitMapper();

    /**
     * Get an ORDER BY clause for sorting units
     * @return string[]
     */
    abstract protected function getUnitOrderByClause();
}