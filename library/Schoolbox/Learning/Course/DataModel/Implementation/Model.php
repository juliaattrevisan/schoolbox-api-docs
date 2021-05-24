<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Implementation;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Course\DataModel\AbstractCourse;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;

/**
 * An Course Implementation is an import of a Course Specification onto a Folder.
 *
 * This object will contain one or more Unit Implementations (whichever of the
 * Course Specification's Unit Specifications were selected as part of the import process).
 *
 * (Historically, this did not exist as a separate concept. Unit Implementations were
 *  related directly to the Folder the Course Specification was imported onto, and
 *  linked to a component.)
 */
class Model extends AbstractCourse
{
    /**
     * The Course Specification model that this model belongs to.
     *
     * @var CourseSpecification
     */
    private $courseSpecification;

    /**
     * The unit spec models for this model.
     *
     * @var Unit\Model[]|GenericCollection
     */
    private $unitImplementations;


    /**
     * Constructor
     *
     * @throws \UnexpectedValueException
     */
    public function __construct()
    {
        $this->unitImplementations = new GenericCollection(UnitImplementation::class);
    }

    /**
     * @return CourseSpecification
     */
    public function getSpecification()
    {
        return $this->courseSpecification;
    }

    /**
     * @param CourseSpecification $courseSpecification
     * @return static
     */
    public function setSpecification(CourseSpecification $courseSpecification)
    {
        $this->courseSpecification = $courseSpecification;
        return $this;
    }


    /**
     * @return Unit\Model[]|GenericCollection
     */
    public function getUnits(): GenericCollection
    {
        return $this->unitImplementations;
    }


    /**
     * @param Unit\Model[]|GenericCollection $unitImplementations
     * @return static
     * @throws \DomainException
     */
    public function setUnits(GenericCollection $unitImplementations): self
    {
        $this->unitImplementations->replaceWith($unitImplementations);
        return $this;
    }
}
