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

namespace Schoolbox\Assessment\Participant\Plan;

use Schoolbox\ISoftDeletable;
use Alaress_Date_Format;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Learning\Assessment\DataModel as Assessment;

class Model implements ISoftDeletable
{

    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;

    /**
     * @var Assessment\Model
     *
     */
    private $assessment;

    /**
     * @var boolean
     */
    private $isDynamic;

    /**
     * @var string
     */
    private $description;

    /**
     * @var null|Alaress_Date_Format
     */
    private $publishDate;

    /**
     * @var null|Alaress_Date_Format
     */
    private $openDate;

    /**
     * @var null|Alaress_Date_Format
     */
    private $dueDate;

    /**
     * @var null|Alaress_Date_Format
     */
    private $closeDate;


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
     * @return boolean
     */
    public function getIsDynamic(): boolean
    {
        return $this->isDynamic;
    }


    /**
     * @param boolean $isDynamic
     */
    public function setIsDynamic(boolean $isDynamic)
    {
        $this->isDynamic = $isDynamic;
    }


    /**
     * @return null|string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }


    /**
     * @param null|string $description
     */
    public function setDescription(?string $description)
    {
        $this->description = $description;
    }


    /**
     * @return null|Alaress_Date_Format
     */
    public function getPublishDate(): ?Alaress_Date_Format
    {
        return $this->publishDate;
    }


    /**
     * @param null|Alaress_Date_Format $publishDate
     */
    public function setPublishDate(?Alaress_Date_Format $publishDate)
    {
        $this->publishDate = $publishDate;
    }


    /**
     * @return null|Alaress_Date_Format
     */
    public function getOpenDate(): ?Alaress_Date_Format
    {
        return $this->openDate;
    }


    /**
     * @param null|Alaress_Date_Format $openDate
     */
    public function setOpenDate(?Alaress_Date_Format $openDate)
    {
        $this->openDate = $openDate;
    }


    /**
     * @return null|Alaress_Date_Format
     */
    public function getDueDate(): ?Alaress_Date_Format
    {
        return $this->dueDate;
    }


    /**
     * @param null|Alaress_Date_Format $dueDate
     */
    public function setDueDate(?Alaress_Date_Format $dueDate)
    {
        $this->dueDate = $dueDate;
    }


    /**
     * @return null|Alaress_Date_Format
     */
    public function getCloseDate(): ?Alaress_Date_Format
    {
        return $this->closeDate;
    }


    /**
     * @param null|Alaress_Date_Format $closeDate
     */
    public function setCloseDate(?Alaress_Date_Format $closeDate)
    {
        $this->closeDate = $closeDate;
    }
}
