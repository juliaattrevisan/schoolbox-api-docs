<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel;

use Alaress_Date_Format;
use Alaress_Date_NullDate;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * Class Model
 */
class Model
{
    use Id;

    // TODO add created/updated/deleted?

    /**
     * A descriptive title of the assessment.
     *
     * @var string
     */
    protected $title;

    /**
     * A longer description of the assessment.
     *
     * @var string
     */
    protected $description;

    /**
     * The date/time at which this assessment become visible to its participants.
     *
     * @var Alaress_Date_Format
     */
    protected $publishDate;

    /**
     * The date/time at which participants able to create responses to this assessment.
     *
     * @var Alaress_Date_Format
     */
    protected $openDate;

    /**
     * The date/time after which a participant's response will be marked as late.
     *
     * @var Alaress_Date_Format
     */
    protected $dueDate;

    /**
     * The date/time at which participants are no longer able to create responses to this assessment.
     *
     * TODO add close date to database and imports
     *
     * @var Alaress_Date_Format
     */
    protected $closeDate;

    /**
     * The comparative amount that this assessment affects a student's grade by.
     *
     * @var int
     */
    protected $weight = 0;

    /**
     * The sort order of this assessment within its container
     * (a class page, course specification or implementation or project).
     *
     * Assessments are sorted by sequence in ascending order.
     *
     * @var int
     */
    protected $sequence = 0;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->publishDate = new Alaress_Date_NullDate();
        $this->openDate    = new Alaress_Date_NullDate();
        $this->dueDate     = new Alaress_Date_NullDate();
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
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return static
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getPublishDate()
    {
        return $this->publishDate;
    }

    /**
     * @param Alaress_Date_Format $publishDate
     * @return static
     */
    public function setPublishDate(Alaress_Date_Format $publishDate = null)
    {
        $this->publishDate = $publishDate;
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
    public function setOpenDate(Alaress_Date_Format $openDate = null)
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
    public function setDueDate(Alaress_Date_Format $dueDate = null)
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getCloseDate(): Alaress_Date_Format
    {
        return $this->closeDate;
    }

    /**
     * @param Alaress_Date_Format $closeDate
     * @return static
     */
    public function setCloseDate($closeDate)
    {
        $this->closeDate = $closeDate;
        return $this;
    }

    /**
     * @return int
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * @param int $weight
     * @return static
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;
        return $this;
    }

    /**
     * @return int
     */
    public function getSequence()
    {
        return $this->sequence;
    }

    /**
     * @param int $sequence
     * @return static
     */
    public function setSequence($sequence)
    {
        $this->sequence = $sequence;
        return $this;
    }
}
