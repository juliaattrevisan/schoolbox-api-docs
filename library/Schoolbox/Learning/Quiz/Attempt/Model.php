<?php
/**
 * Model for online assessment attempts.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Quiz\Attempt;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\User;
use \Alaress_Date_Format;
use Schoolbox\Learning\Quiz\Attempt\Answer\Model as QuizAnswer;
use \DomainException;

class Model implements ISoftDeletable
{
    use Id;

    // TODO 17.0 update calls to getDelete() to getDeletedAt() and uncomment next line
    //use DeletedAtDate;

    /**
     * @var Quiz
     */
    protected $quiz;

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
     * @var QuizAnswer[]|GenericCollection<QuizAnswer>
     */
    private $answers;

    /**
     * Model constructor.
     */
    public function __construct()
    {
        $this->answers = new GenericCollection(QuizAnswer::class);
    }

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
     * @param  Quiz $quiz
     * @return $this
     */
    public function setAssessment(Quiz $quiz)
    {
        $this->quiz = $quiz;
        return $this;
    }

    /**
     * @return Quiz
     */
    public function getAssessment()
    {
        return $this->quiz;
    }

    /**
     * @param  Alaress_Date_Format $finish
     * @return $this
     */
    public function setFinish(Alaress_Date_Format $finish)
    {
        $this->finish = $finish;
        return $this;
    }

    /**
     * The timestamp when user clicked 'Finish' while attempting the Quiz
     *
     * @return Alaress_Date_Format
     */
    public function getFinish()
    {
        return $this->finish;
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

    /**
     * @return QuizAnswer[]|GenericCollection<QuizQuestion>
     */
    public function getAnswers()
    {
        return $this->answers;
    }

    /**
     * @param GenericCollection $answers
     * @return static
     * @throws DomainException if new Generic Collection is of incorrect type
     */
    public function setAnswers(GenericCollection $answers)
    {
        $this->answers->assertSameItemClass($answers);
        $this->answers = $answers;
        return $this;
    }
}
