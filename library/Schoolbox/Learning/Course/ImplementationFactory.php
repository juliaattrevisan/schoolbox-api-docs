<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course;

use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Learning\Course\Implementation as CourseImplementation;
use Schoolbox\Learning\Course\Specification as CourseSpecification;
use Schoolbox\Learning\Course\Unit\ImplementationFactory as UnitImplementationFactory;

/**
 * A factory for Course Implementation objects.
 *
 * @package Schoolbox\Learning\Course\Implementation
 */
class ImplementationFactory
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
     * @param Folder              $destination
     * @return CourseImplementation
     */
    public function build(CourseSpecification $courseSpecification, Folder $destination)
    {
        $implementation = new CourseImplementation();
        foreach ($courseSpecification->getUnits() as $unitSpecification) {
            $implementation->getUnits()->add(
                $this->unitImplementationFactory->build($unitSpecification)->setCourse($implementation)
            );
        }
        return $implementation
            ->setFolder($destination)
            ->setSpecification($courseSpecification);
    }
}
