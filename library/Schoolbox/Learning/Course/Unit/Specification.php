<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Unit;

use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\Folder\Model as Folder;

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
 *
 * TODO unit tests
 *
 * @package Schoolbox\Learning\Course\Unit
 */
class Specification implements AssessmentContext, ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * The Folder which this object belongs to.
     *
     * TODO review if this relation is required - this object can't exist without Folders
     *
     * @var Folder
     */
    protected $folder;

    /**
     * The Assessable objects which are a part of this object.
     *
     * @var Collection|IAssessable[]
     */
    protected $assessables;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->createdAt = new \Alaress_Date_NullDate();
        $this->updatedAt = new \Alaress_Date_NullDate();
        $this->deletedAt = new \Alaress_Date_NullDate();
        $this->assessables = new Collection();
    }

    /* Getters/Setters */

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
    public function setFolder($folder)
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * @return Collection|IAssessable[]
     */
    public function getAssessables()
    {
        return $this->assessables;
    }

    /**
     * @param Collection|IAssessable[] $assessables
     * @return static
     */
    public function setAssessables($assessables)
    {
        $this->assessables = $assessables;
        return $this;
    }

    /**
     * Deep clone object properties.
     */
    public function __clone()
    {
        if (is_object($this->getCreatedAt())) {
            $this->setCreatedAt(clone $this->getCreatedAt());
        }
        if (is_object($this->getUpdatedAt())) {
            $this->setUpdatedAt(clone $this->getUpdatedAt());
        }
        if (is_object($this->getDeletedAt())) {
            $this->setDeletedAt(clone $this->getDeletedAt());
        }
        if (is_object($this->getAssessables())) {
            $this->setAssessables(clone $this->getAssessables());
        }
    }
}
