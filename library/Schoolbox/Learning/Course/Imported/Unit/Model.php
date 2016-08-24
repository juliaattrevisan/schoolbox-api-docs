<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Imported\Unit;

use Schoolbox\Collection;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Course\Imported\Model as ImportedCourseModel;
use Schoolbox\Learning\Course\Template\Unit\Model as TemplateUnitModel;

/**
 * An Imported Unit is a single import of a Unit onto a Folder. It belongs to an
 * Imported Course.
 *
 * On creation, this object makes a copy of the Assessables in the Template Unit it was
 * created from, and adds them to itself - a teacher may then add, modify or remove individual
 * Assessable objects inside this object.
 *
 * (Historically, this was known as a Unit Instance. It did not belong to an
 *  Imported Course - instead, it belonged directly to the Folder it was imported
 *  onto, and was also linked to a component on that Folder.)
 *
 * TODO merge existing Unit Instance class (\Schoolbox\Assessment\Course\UnitInstance) into this
 *
 * @package Schoolbox\Learning\Course\Imported\Unit
 */
class Model implements AssessmentContext
{
    /**
     * Unique identifier.
     *
     * @var int
     */
    protected $id;

    /**
     * The Imported Course which this object belongs to.
     *
     * TODO review if this relation is required - this object can't exist without its Imported Course
     *
     * @var ImportedCourseModel
     */
    protected $course;

    /**
     * The Template Unit which this object was created from.
     *
     * @var TemplateUnitModel
     */
    protected $template;

    /**
     * The Assessable objects which are a part of this object.
     *
     * TODO add the Assessable interface to this definition, once it exists
     *
     * @var Collection
     */
    protected $assessables;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->template = new TemplateUnitModel();
        $this->course   = new ImportedCourseModel();
    }

    /* Getters/Setters */

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return TemplateUnitModel
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @param TemplateUnitModel $template
     * @return static
     */
    public function setTemplate($template)
    {
        $this->template = $template;
        return $this;
    }

    /**
     * @return ImportedCourseModel
     */
    public function getCourse()
    {
        return $this->course;
    }

    /**
     * @param ImportedCourseModel $course
     * @return static
     */
    public function setCourse($course)
    {
        $this->course = $course;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getAssessables()
    {
        return $this->assessables;
    }

    /**
     * @param Collection $assessables
     * @return static
     */
    public function setAssessables($assessables)
    {
        $this->assessables = $assessables;
        return $this;
    }

    /**
     * This object's assessment context is its Imported Course's assessment context.
     */
    public function getFolder()
    {
        return $this->getCourse()->getFolder();
    }
}
