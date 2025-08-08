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
use Schoolbox\Learning\Learnosity\Helper;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\User;
use Alaress_Date_Format;
use Schoolbox\Learning\Quiz\Attempt\Answer\Model as QuizAnswer;

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
    private Alaress_Date_Format $start;

    /**
     * @var Alaress_Date_Format|null
     */
    private ?Alaress_Date_Format $finish = null;

    /**
     * The teacher who reviewed this attempt. Null if not yet reviewed.
     *
     * @var User|null
     */
    private $reviewer;

    /**
     * @var Alaress_Date_Format|null
     */
    private ?Alaress_Date_Format $reviewed = null;

    /**
     * @var Alaress_Date_Format
     */
    private $deleted;

    /**
     * @var QuizAnswer[]|GenericCollection<QuizAnswer>
     */
    private $answers;

    /**
     * @var null|string
     */
    private ?string $mark = null;

    /**
     * @var null|float
     */
    private ?float $max_mark = null;

    /**
     * @var float|null
     */
    private ?float $normalisedMark = null;


    /**
     * @var string|null
     */
    private ?string $comment = null;


    /**
     * @var string|null
     */
    private ?string $learnosityReference = null;

    /**
     * The status of the Learnosity score calculation.
     *
     * @var Helper::LEARNOSITY_PROCESS_STATUS_*|null
     */
    private ?string $learnosityStatus = null;

    /**
     * The number of times the Learnosity score calculation has been polled.
     */
    private ?int $learnosityPollCount = null;

    /**
     * Is this deleted?
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->getDeleted() instanceof Alaress_Date_Format;
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
     * @param Alaress_Date_Format|null $finish
     *
     * @return static
     */
    public function setFinish(?Alaress_Date_Format $finish): self
    {
        $this->finish = $finish;
        return $this;
    }

    /**
     * @return Alaress_Date_Format|null
     */
    public function getFinish(): ?Alaress_Date_Format
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
        return null !== $this->finish;
    }


    /**
     * @param Alaress_Date_Format|null $reviewed
     *
     * @return static
     */
    public function setReviewed(?Alaress_Date_Format $reviewed): self
    {
        $this->reviewed = $reviewed;
        return $this;
    }

    /**
     * @return Alaress_Date_Format|null
     */
    public function getReviewed(): ?Alaress_Date_Format
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
        return $this->getReviewed() instanceof \Alaress_Date_Format;
    }

    /**
     * @param Alaress_Date_Format $start
     *
     * @return static
     */
    public function setStart(Alaress_Date_Format $start): self
    {
        $this->start = $start;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getStart(): Alaress_Date_Format
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
    public function setDeleted(?Alaress_Date_Format $deleted)
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
     * @return QuizAnswer[]|GenericCollection<QuizAnswer>|null
     */
    public function getAnswers()
    {
        return $this->answers;
    }

    /**
     * @param GenericCollection<QuizAnswer> $answers
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
    public function setReviewer(?User $reviewer = null)
    {
        $this->reviewer = $reviewer;
        return $this;
    }

    /**
     * @param null|string $mark
     * @return static
     */
    public function setMark(?string $mark): self
    {
        $this->mark = $mark;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getMark(): ?string
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

    /**
     * @return float|null
     */
    public function getNormalisedMark(): ?float
    {
        return $this->normalisedMark;
    }

    /**
     * @param float|null $normalisedMark
     * @return Model
     */
    public function setNormalisedMark(?float $normalisedMark): self
    {
        $this->normalisedMark = $normalisedMark;
        return $this;
    }


    /**
     * Re-derive the normalised mark from $this->>getMark()
     * and $this->>getMaxMark(), saving you manually calculating
     * this percentage everywhere else
     *
     * @param null|float $numerator
     * @param null|float $denominator
     * @return float|null
     */
    public function calculateNormalisedMark(?float $numerator, ?float $denominator): ?float
    {
        /* Unknown error scenario, usually via SanitiseScores' edge cases
         * where we couldn't detect inputs in an expected manner; this should
         * never happen intentionally
         */
        if ((null === $numerator) || (null === $denominator)) {
            return null;
        }

        /* No marks available
         */
        if (0.0 === $denominator) {
            return null;
        }

        return 100 * $numerator / $denominator;
    }


    /**
     * The total mark value of this attempt which has been graded
     * the total available marks the user could have received.
     *
     * @return float
     */
    public function getTotalMarkGraded(): float
    {
        // Is this reviewed? If so then 100% of marks are graded.
        if ($this->isReviewed()) {
            return $this->getMaxMark();
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
     * @return string|null
     */
    public function getLearnosityReference(): ?string
    {
        return $this->learnosityReference;
    }


    /**
     * @param string|null $learnosityReference
     * @return Model
     */
    public function setLearnosityReference(?string $learnosityReference): self
    {
        $this->learnosityReference = $learnosityReference;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }


    /**
     * @param string|null $comment
     * @return Model
     */
    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLearnosityStatus(): ?string
    {
        return $this->learnosityStatus;
    }

    /**
     * @param string|null $learnosityStatus
     * @return $this
     */
    public function setLearnosityStatus(?string $learnosityStatus): self
    {
        $this->learnosityStatus = $learnosityStatus;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getLearnosityPollCount(): ?int
    {
        return $this->learnosityPollCount;
    }

    /**
     * @param int|null $learnosityPollCount
     * @return $this
     */
    public function setLearnosityPollCount(?int $learnosityPollCount): self
    {
        $this->learnosityPollCount = $learnosityPollCount;
        return $this;
    }
}
