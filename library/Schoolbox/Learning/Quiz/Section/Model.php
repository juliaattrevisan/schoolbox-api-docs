<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Quiz\Section;

use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Learning\Quiz\Section\Question\Model as QuizQuestion;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * Represents a set of Questions in a Quiz
 */
class Model implements ISoftDeletable
{
    use Id;
    use DeletedAtDate;

    /**
     * @var Quiz
     */
    private $quiz;

    /**
     * @var int
     */
    private $sequence;

    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $description;

    /**
     * @var bool
     */
    private $random;

    /**
     * @var int
     */
    private $pool;

    /**
     * @var QuizQuestion[]|GenericCollection<QuizQuestion>
     */
    private $questions;

    /**
     * Model constructor.
     */
    public function __construct()
    {
        $this->questions = new GenericCollection(QuizQuestion::class);
    }

    /* Getters / Setters
     */

    /**
     * @return Quiz
     */
    public function getQuiz()
    {
        return $this->quiz;
    }

    /**
     * @param Quiz $quiz
     * @return static
     */
    public function setQuiz(Quiz $quiz)
    {
        $this->quiz = $quiz;
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
     * @return bool
     */
    public function getRandom()
    {
        return $this->random;
    }

    /**
     * @param bool $random
     * @return static
     */
    public function setRandom($random)
    {
        $this->random = $random;
        return $this;
    }

    /**
     * @return int
     */
    public function getPool()
    {
        return $this->pool;
    }

    /**
     * @param int $pool
     * @return static
     */
    public function setPool($pool)
    {
        $this->pool = $pool;
        return $this;
    }

    /**
     * @return QuizQuestion[]|GenericCollection<QuizQuestion>
     */
    public function getQuestions()
    {
        return $this->questions;
    }

    /**
     * @param GenericCollection $questions
     * @return static
     */
    public function setQuestions(GenericCollection $questions)
    {
        if(QuizQuestion::class != $questions->getElementClass()){
            $msg = "Collection must be of type '".QuizQuestion::class."'. Found: ".$questions->getElementClass();
            throw new \DomainException($msg);
        }
        $this->questions = $questions;
        return $this;
    }
}