<?php

/**
 * This is just enough of a mapper to get the survey component rendering. This is in no way complete, and is only being
 * used to read data out of the table. This should be blown to smithereeens(sp?) when Quiz/Surveys are sucked out of
 * the refactoring airlock.
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Survey;

use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\DataModel\Model as Assessment;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Resource\Folder\Model as Folder;

class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use DeletedAtDate;

    /** @var string */
    protected $availability;

    /** @var string */
    protected $title;

    /** @var Folder $folder */
    protected $folder;

    /** @var Assessment $assessment */
    protected $assessment;

    /** @var Collection $sections */
    protected $sections;

    /** @var Collection $attempts */
    protected $attempts;

    /** @var int $maxAttempts */
    protected $maxAttempts;

    /** @var bool */
    protected $anonymousResult;

    /** @var \Alaress_Date_Format */
    protected $finish;

    /**
     * @param string $availability
     * @return self
     */
    public function setAvailability($availability)
    {
        $this->availability = $availability;
        return $this;
    }

    /**
     * @return string
     */
    public function getAvailability()
    {
        return $this->availability;
    }

    /**
     * @param string $title
     * @return self
     */
    public function setTitle($title)
    {
        $this->title = $title;
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
     * @param int $maxAttempts
     * @return self
     */
    public function setMaxAttempts($maxAttempts)
    {
        $this->maxAttempts = $maxAttempts;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxAttempts()
    {
        return $this->maxAttempts;
    }

    /**
     * @param Folder $folder
     * @return self
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;
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
     * @param Assessment $assessment
     * @return self
     */
    public function setAssessment($assessment)
    {
        $this->assessment = $assessment;
        return $this;
    }

    /**
     * @return Assessment
     */
    public function getAssessment()
    {
        return $this->assessment;
    }

    /**
     * @param Collection $sections
     * @return self
     */
    public function setSections($sections)
    {
        $this->sections = $sections;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getSections()
    {
        return $this->sections;
    }

    /**
     * @param Collection $attempts
     * @return self
     */
    public function setAttempts($attempts)
    {
        $this->attempts = $attempts;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getAttempts()
    {
        return $this->attempts;
    }

    /**
     * @return bool
     */
    public function getAnonymousResult()
    {
        return $this->anonymousResult;
    }

    /**
     * @param bool $anonymousResult
     * @return static
     */
    public function setAnonymousResult($anonymousResult)
    {
        $this->anonymousResult = $anonymousResult;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getFinish()
    {
        return $this->finish;
    }

    /**
     * @param \Alaress_Date_Format $finish
     * @return $this
     */
    public function setFinish($finish)
    {
        $this->finish = $finish;
        return $this;
    }
}
