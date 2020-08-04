<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Implementation\Unit;

use Alaress_Date_Format;
use Schoolbox\Learning\Course\DataModel\AbstractUnit;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * An Unit Implementation is a single import of a Unit Specification onto a Folder. It belongs to an
 * Course Implementation.
 *
 * On creation, this object makes a copy of the Assessables in the Unit Specification it was
 * created from, and adds them to itself - a teacher may then add, modify or remove individual
 * Assessable objects inside this object.
 *
 * (Historically, this was known as a Unit Instance. It did not belong to a
 *  Course Implementation - instead, it belonged directly to the Folder it was imported
 *  onto, and was also linked to a component on that Folder.)
 */
class Model extends AbstractUnit
{
    /**
     * @var CourseImplementation
     */
    private $courseImplementation;

    /**
     * @var UnitSpecification
     */
    private $unitSpecification;

    /**
     * This will be equal to the value of the unitSpec's folder.title at time of import create/modify
     * @var string
     */
    private $title;

    /**
     * @var Alaress_Date_Format
     */
    private $openDate;

    /**
     * @var Alaress_Date_Format
     */
    private $dueDate;


    /**
     * This unit is open if the current date is after the open date, and before
     * the due date.
     *
     * @param Alaress_Date_Format $now Optionally override to enable testing
     * @return bool
     */
    public function isOpen(Alaress_Date_Format $now = null)
    {
        if (!$now instanceof Alaress_Date_Format) {
            $now = Alaress_Date_Format::now();
        }
        return !($this->isBeforeOpenDate($now) || $this->isAfterDueDate($now));
    }

    /**
     * Is this unit open for submissions?
     *
     * @param Alaress_Date_Format $now Optionally override to enable testing
     * @return bool
     */
    public function isBeforeOpenDate(Alaress_Date_Format $now = null)
    {
        if (!$now instanceof Alaress_Date_Format) {
            $now = Alaress_Date_Format::now();
        }
        return $now->compareDateOnly($this->getOpenDate()) < 0;
    }

    /**
     * Is this unit open for submissions?
     *
     * @param Alaress_Date_Format $now Optionally override to enable testing
     * @return bool
     */
    public function isAfterDueDate(Alaress_Date_Format $now = null)
    {
        if (!$now instanceof Alaress_Date_Format) {
            $now = Alaress_Date_Format::now();
        }
        return $now->compareDateOnly($this->getDueDate()) > 0;
    }

    /**
     * Retrieves a time relative to whenever the course unit opens.
     *
     * It opens on 12am of the open date.
     *
     * @return Alaress_Date_Format
     */
    public function getRelativeOpenDate()
    {
        $date = clone $this->getOpenDate()->getDateTimeObject();
        $date->modify('00:00:00');
        return new Alaress_Date_Format($date);
    }

    /**
     * Retrieves a time relative to whenever the course unit closes.
     *
     * It closes on the last second of the due date.
     *
     * @return Alaress_Date_Format
     */
    public function getRelativeDueDate()
    {
        $date = clone $this->getDueDate()->getDateTimeObject();
        $date->modify('23:59:59');
        return new Alaress_Date_Format($date);
    }

    /* Getters / Setters
     */

    /**
     * @return Folder
     */
    public function getFolder()
    {
        return $this->getCourse()->getFolder();
    }

    /**
     * @param Folder $folder
     * @return static
     */
    public function setFolder(Folder $folder)
    {
        $this->getCourse()->setFolder($folder);
        return $this;
    }

    /**
     * @return CourseImplementation
     */
    public function getCourse()
    {
        return $this->courseImplementation;
    }

    /**
     * @param CourseImplementation $courseImplementation
     * @return static
     */
    public function setCourse($courseImplementation)
    {
        $this->courseImplementation = $courseImplementation;
        return $this;
    }

    /**
     * @return UnitSpecification
     */
    public function getSpecification()
    {
        return $this->unitSpecification;
    }

    /**
     * @param UnitSpecification $unitSpecification
     * @return static
     */
    public function setSpecification($unitSpecification)
    {
        $this->unitSpecification = $unitSpecification;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return static
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getOpenDate()
    {
        return $this->openDate;
    }

    /**
     * @param Alaress_Date_Format $openDate
     * @return static
     */
    public function setOpenDate($openDate)
    {
        $this->openDate = $openDate;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getDueDate()
    {
        return $this->dueDate;
    }

    /**
     * @param Alaress_Date_Format $dueDate
     * @return static
     */
    public function setDueDate($dueDate)
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    /**
     * This is an implementation context
     *
     * @return false
     */
    public function isSpecificationContext()
    {
        return false;
    }

    /**
     * This is an implementation context
     *
     * @return true
     */
    public function isImplementationContext()
    {
        return true;
    }
}
