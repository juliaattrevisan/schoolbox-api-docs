<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Specification\Unit;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Course\DataModel\AbstractUnit;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;

/**
 * A Unit Specification is a collection of Assessable objects. It exists on a Folder.
 *
 * It is intended to cover a single area of learning within a subject, and may be imported
 * onto another Folder as part of the action of importing a Course onto that folder.
 * This action creates an Unit Implementation for each Unit Specification that was chosen to be imported
 * as part of that process.
 *
 * (Historically, this was simply known as a Unit, and defined as a Folder which contained a
 *  Course Builder component. This class puts this concept into a concrete class.)
 */
class Model extends AbstractUnit
{
    /**
     * @var CourseSpecification
     */
    private $courseSpecification;

    /**
     * @var Folder
     */
    private $folder;

    /**
     * @var UnitImplementation[]|GenericCollection
     */
    private $unitImplementations;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->unitImplementations = new GenericCollection(UnitImplementation::class);
    }

    /**
     * Returns title of folder, for AssessmentContext Interface
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getFolder()->getTitle();
    }

    /* Getters / Setters
     */

    /**
     * @return CourseSpecification
     * @deprecated To get from unitSpec to courseSpec, please use canonical relationship: unitSpec.folder.parent.courseSpec
     * @see https://schoolbox.atlassian.net/browse/SA-9187
     */
    public function getCourse()
    {
        return $this->courseSpecification;
    }

    /**
     * @param CourseSpecification $courseSpecification
     * @return static
     * @deprecated To get from unitSpec to courseSpec, please use canonical relationship: unitSpec.folder.parent.courseSpec
     * @see https://schoolbox.atlassian.net/browse/SA-9187
     */
    public function setCourse(CourseSpecification $courseSpecification)
    {
        $this->courseSpecification = $courseSpecification;
        return $this;
    }

    /**
     * @return Folder
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * @param Folder $folder
     * @return static
     */
    public function setFolder(Folder $folder)
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return Collection|UnitImplementation[]
     */
    public function getUnitImplementations()
    {
        return $this->unitImplementations;
    }

    /**
     * @param Collection|UnitImplementation[] $unitImplementations
     * @return static
     */
    public function setUnitImplementations($unitImplementations)
    {
        $this->unitImplementations = $unitImplementations;
        return $this;
    }

    /**
     * This is a specification context
     *
     * @return true
     */
    public function isSpecificationContext()
    {
        return true;
    }

    /**
     * This is a specification context
     *
     * @return false
     */
    public function isImplementationContext()
    {
        return false;
    }
}
