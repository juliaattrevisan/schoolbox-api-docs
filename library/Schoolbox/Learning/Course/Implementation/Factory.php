<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Implementation;

use Schoolbox\Learning\Course\Implementation\Unit\Factory as UnitImplementationFactory;
use Schoolbox\Learning\Course\Implementation\Model as CourseImplementation;
use Schoolbox\Learning\Course\Specification as CourseSpecification;

/**
 * A factory for Course Implementation objects.
 *
 * @package Schoolbox\Learning\Course\Implementation
 */
class Factory
{
    /**
     * Internal object - used to build Unit Implementation objects for the Course Implementation.
     *
     * @var UnitImplementationFactory
     */
    protected $unitImplementationFactory;

    /**
     * Constructor
     *
     * @param UnitImplementationFactory $unitImplementationFactory
     */
    public function __construct(UnitImplementationFactory $unitImplementationFactory)
    {
        $this->unitImplementationFactory = $unitImplementationFactory;
    }

    /**
     * Builds a Unit Implementation out of the given Unit Specification.
     *
     * @param CourseSpecification $courseSpecification
     * @return CourseImplementation
     */
    public function buildFromSpecification(CourseSpecification $courseSpecification)
    {
        $courseImplementation = new CourseImplementation();
        foreach ($courseSpecification->getUnits() as $unitSpecification) {
            $courseImplementation->getUnits()->add(
                $this->unitImplementationFactory->buildFromSpecification($unitSpecification)
            );
        }
        return $courseImplementation;
    }
}
