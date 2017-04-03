<?php
/**
 * This is just enough of a mapper to get the survey component rendering. This is in no way complete, and is only being
 * used to read data out of the table. This should be blown to smithereeens(sp?) when Quiz/Surveys are sucked out of
 * the refactoring airlock.
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Survey\Attempt;

use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Survey\Model as Survey;
use Schoolbox\User;

class Model
{
    use Id;

    /** @var Alaress_Date_Format */
    protected $start;

    /** @var Alaress_Date_Format */
    protected $finish;

    /** @var Survey $survey */
    protected $survey;

    /** @var User $user */
    protected $user;

    /** @var Collection */
    protected $responses;
    
    /**
     * @param Alaress_Date_Format $start
     * @return self
     */
    public function setStart($start)
    {
        $this->start = $start;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getStart()
    {
        return $this->start;
    }
    
    /**
     * @param Alaress_Date_Format $finish
     * @return self
     */
    public function setFinish($finish)
    {
        $this->finish = $finish;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getFinish()
    {
        return $this->finish;
    }

    /**
     * @param int $sequence
     * @return self
     */
    public function setSequence($sequence)
    {
        $this->sequence = $sequence;
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
     * @param Survey $survey
     * @return self
     */
    public function setSurvey($survey)
    {
        $this->survey = $survey;
        return $this;
    }

    /**
     * @return Survey
     */
    public function getSurvey()
    {
        return $this->survey;
    }

    /**
     * @param User $user
     * @return self
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param Collection $responses
     * @return self
     */
    public function setResponses($responses)
    {
        $this->responses = $responses;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getResponses()
    {
        return $this->responses;
    }

}
