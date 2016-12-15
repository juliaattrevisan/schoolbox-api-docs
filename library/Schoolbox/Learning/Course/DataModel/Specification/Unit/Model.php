<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Specification\Unit;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Course\DataModel\AbstractUnit;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
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
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

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
     */
    public function getCourse()
    {
        return $this->courseSpecification;
    }

    /**
     * @param CourseSpecification $courseSpecification
     * @return static
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
}
