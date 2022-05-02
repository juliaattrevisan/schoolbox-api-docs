<?php

/**
 * Model for online assessment attempts.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Quiz\Attempt;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\User;
use Alaress_Date_Format;
use Schoolbox\Learning\Quiz\Attempt\Answer\Model as QuizAnswer;
use Alaress_Date_NullDate;

class Model implements ISoftDeletable
{
    use Id;

    // TODO 17.0 update calls to getDelete() to getDeletedAt() and uncomment next line
    //use DeletedAtDate;

    /**
     * @var Quiz
     */
    private $quiz;

    /**
     * @var User
     */
    private $user;

    /**
     * @var Alaress_Date_Format
     */
    private $start;

    /**
     * @var Alaress_Date_Format
     */
    private $finish;

    /**
     * The teacher who reviewed this attempt. Null if not yet reviewed.
     *
     * @var User|null
     */
    private $reviewer;

    /**
     * @var Alaress_Date_Format
     */
    private $reviewed;

    /**
     * @var Alaress_Date_Format
     */
    private $deleted;

    /**
     * @var QuizAnswer[]|GenericCollection<QuizAnswer>
     */
    private $answers;

    /**
     * @var null|float
     */
    private ?float $mark = null;

    /**
     * @var null|float
     */
    private ?float $max_mark = null;

    /**
     * The mark that the user has received for this attempt.
     *
     * @return float
     */
    public function getMarkOLD(): float
    {
        $mark = 0;
        foreach ($this->answers as $answer) {
            $mark += $answer->getMark();
        }
        return $mark;
    }

    /**
     * The total number of marks available for this attempt.
     *
     * @return float
     */
    public function getTotalMark(): float
    {
        $totalMark = 0;
        foreach ($this->answers as $answer) {
            $totalMark += $answer->getQuestion()->getMarks();
        }
        return $totalMark;
    }

    /**
     * The total mark value of this attempt which has been graded.
     *
     * @return float
     */
    public function getTotalMarkGraded(): float
    {
        // Is this reviewed? If so then 100% of marks are graded.
        if ($this->isReviewed()) {
            return $this->getTotalMark();
        }
        $totalMark = 0;
        foreach ($this->answers as $answer) {
            if ($answer->getAutoMarked()) {
                $totalMark += $answer->getQuestion()->getMarks();
            }
        }
        return $totalMark;
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
    public function setQuiz(Quiz $quiz)
    {
        $this->quiz = $quiz;
        return $this;
    }

    /**
     * @return Quiz
     */
    public function getQuiz()
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
     * Returns whether the attempt has been finished.
     *
     * @return bool
     */
    public function isFinished(): bool
    {
        return !(null === $this->finish || $this->finish instanceof Alaress_Date_NullDate);
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
     * Is this attempt reviewed?
     *
     * @return bool
     */
    public function isReviewed()
    {
        return ($this->getReviewed() instanceof \Alaress_Date_Format && !$this->getReviewed()->isNull());
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
     * @return QuizAnswer[]|GenericCollection<QuizAnswer>
     */
    public function getAnswers()
    {
        return $this->answers;
    }

    /**
     * @param QuizAnswer[]|GenericCollection<QuizAnswer> $answers
     * @return static
     */
    public function setAnswers(GenericCollection $answers)
    {
        $answers->assertCollectionOf(QuizAnswer::class);
        $this->answers = $answers;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getReviewer()
    {
        return $this->reviewer;
    }

    /**
     * @param User|null $reviewer
     * @return static
     */
    public function setReviewer(User $reviewer = null)
    {
        $this->reviewer = $reviewer;
        return $this;
    }

    /**
     * @param null|float $mark
     * @return static
     */
    public function setMark(?float $mark): self
    {
        $this->mark = $mark;
        return $this;
    }

    /**
     * @return null|float
     */
    public function getMark(): ?float
    {
        return $this->mark;
    }

    /**
     * @param null|float $max_mark
     * @return static
     */
    public function setMaxMark(?float $max_mark): self
    {
        $this->max_mark = $max_mark;
        return $this;
    }

    /**
     * @return null|float
     */
    public function getMaxMark(): ?float
    {
        return $this->max_mark;
    }
}
