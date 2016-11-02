<?php
/**
 * Model for online assessment attempts.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Quiz\Attempt;

use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\ISoftDeletable;
use Schoolbox\User;
use \Alaress_Date_Format;

class Model implements ISoftDeletable
{

    /**
     * @var int
     */
    protected $id;

    /**
     * @var Quiz
     */
    protected $assessment;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var Alaress_Date_Format
     */
    protected $start;

    /**
     * @var Alaress_Date_Format
     */
    protected $finish;

    /**
     * @var Alaress_Date_Format
     */
    protected $reviewed;

    /**
     * @var Alaress_Date_Format
     */
    protected $deleted;

    /**
     * Is this deleted?
     *
     * @return bool
     */
    public function isDeleted()
    {
        return !$this->getDeleted()->isNull();
    }

    /* Getters / Setters
     */

    /**
     * @param  Quiz $assessment
     *
     * @return $this
     */
    public function setAssessment(Quiz $assessment)
    {
        $this->assessment = $assessment;
        return $this;
    }

    /**
     * @return Quiz
     */
    public function getAssessment()
    {
        return $this->assessment;
    }

    /**
     * @param  Alaress_Date_Format $finish
     *
     * @return $this
     */
    public function setFinish(Alaress_Date_Format $finish)
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
     * @param  int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param  Alaress_Date_Format $reviewed
     *
     * @return $this
     */
    public function setReviewed(Alaress_Date_Format $reviewed)
    {
        $this->reviewed = $reviewed;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getReviewed()
    {
        return $this->reviewed;
    }

    /**
     * @param  Alaress_Date_Format $start
     *
     * @return $this
     */
    public function setStart(Alaress_Date_Format $start)
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
     * @param User $user
     *
     * @return $this
     */
    public function setUser(User $user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return \Schoolbox\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param  Alaress_Date_Format $deleted
     *
     * @return $this
     */
    public function setDeleted(Alaress_Date_Format $deleted)
    {
        $this->deleted = $deleted;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getDeleted()
    {
        return $this->deleted;
    }
}
