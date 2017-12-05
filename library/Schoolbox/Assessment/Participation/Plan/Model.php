<?php
/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/**
 * Created by PhpStorm.
 * User: natalie.bakhtadze
 * Date: 27/11/17
 * Time: 3:11 PM
 */

namespace Schoolbox\Assessment\Participation\Plan;

use Schoolbox\ISoftDeletable;
use Alaress_Date_Format;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Learning\Assessment\DataModel as Assessment;

/**
 * Class Model
 *
 * @package Schoolbox\Assessment\Participation\Plan
 */
class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;

    /**
     * @var Alaress_Date_Format
     */
    protected $publishDate;

    /**
     * @var Alaress_Date_Format
     */
    protected $openDate;

    /**
     * @var Alaress_Date_Format
     */
    protected $dueDate;

    /**
     * @var Alaress_Date_Format
     */
    protected $closeDate;

    /**
     * @var Assessment\Model
     *
     */
    protected $assessment;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var boolean
     */
    protected $isDynamic;

    /**
     * @return Alaress_Date_Format
     */
    public function getCreatedAt(): Alaress_Date_Format
    {
        return $this->createdAt;
    }

    /**
     * @param Alaress_Date_Format $createdAt
     */
    public function setCreatedAt(Alaress_Date_Format $createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getPublishDate(): Alaress_Date_Format
    {
        return $this->publishDate;
    }

    /**
     * @param Alaress_Date_Format $publishDate
     */
    public function setPublishDate(Alaress_Date_Format $publishDate)
    {
        $this->publishDate = $publishDate;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getOpenDate(): Alaress_Date_Format
    {
        return $this->openDate;
    }

    /**
     * @param Alaress_Date_Format $openDate
     */
    public function setOpenDate(Alaress_Date_Format $openDate)
    {
        $this->openDate = $openDate;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getDueDate(): Alaress_Date_Format
    {
        return $this->dueDate;
    }

    /**
     * @param Alaress_Date_Format $dueDate
     */
    public function setDueDate(Alaress_Date_Format $dueDate)
    {
        $this->dueDate = $dueDate;
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
     */
    public function setCloseDate(Alaress_Date_Format $closeDate)
    {
        $this->closeDate = $closeDate;
    }

    /**
     * @return Assessment\Model
     */
    public function getAssessment(): Assessment\Model
    {
        return $this->assessment;
    }

    /**
     * @param Assessment\Model $assessment
     */
    public function setAssessment(Assessment\Model $assessment)
    {
        $this->assessment = $assessment;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return boolean
     */
    public function getIsDynamic(): boolean
    {
        return $this->isDynamic;
    }

    /**
     * @param boolean $isDynamic
     */
    public function setIsDynamic($isDynamic)
    {
        $this->is_dynamic = $isDynamic;
    }

}