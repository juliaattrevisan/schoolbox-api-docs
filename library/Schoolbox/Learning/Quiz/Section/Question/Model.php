<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Quiz\Section\Question;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Quiz\Section\Model as QuizSection;
use Schoolbox\Learning\Quiz\Section\Question\QuestionType\Model as QuizQuestionType;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Learning\Quiz\Attempt\Answer\Model as QuizAnswer;
use Schoolbox\Learning\Quiz\Section\Question\Solution\Model as QuizSolution;
use \DomainException;

class Model implements ISoftDeletable
{
    use Id;
    use DeletedAtDate;

    /**
     * @var QuizSection
     */
    private $section;

    /**
     * 1-based
     *
     * @var int
     */
    private $sequence;

    /**
     * @var QuizQuestionType
     */
    private $type;

    /**
     * @var float
     */
    private $marks;

    /**
     * @var string
     */
    private $question;

    /**
     * @var array
     */
    private $config;

    /**
     * @var Collection|GenericCollection<QuizAnswer>
     */
    private $answers;

    /**
     * @var QuizSolution|null
     */
    private $solution;

    /**
     * Model constructor.
     */
    public function __construct()
    {
        $this->answers = new GenericCollection(QuizAnswer::class);
    }

    /**
     * Clears any Answers from the clone, and clones this Question's Solution
     * @return void
     */
    function __clone()
    {
        // clear ID
        $this->id = null;

        // Clear answers
        $this->setAnswers(new GenericCollection(QuizAnswer::class));

        // Clone Solution to clear its IDs, so subsequent save is INSERT instead of UPDATE
        if($this->solution instanceof QuizSolution){
            $cloneSolution = clone $this->solution;
            $cloneSolution->setQuestion($this);
            $this->setSolution($cloneSolution);
        }
    }

    /* Getters / Setters
     */

    /**
     * @return QuizSection
     */
    public function getSection()
    {
        return $this->section;
    }

    /**
     * @param QuizSection $section
     * @return static
     */
    public function setSection(QuizSection $section)
    {
        $this->section = $section;
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
     * @return QuizQuestionType
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param QuizQuestionType $type
     * @return static
     */
    public function setType(QuizQuestionType $type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return float
     */
    public function getMarks()
    {
        return $this->marks;
    }

    /**
     * @param float $marks
     * @return static
     */
    public function setMarks($marks)
    {
        $this->marks = $marks;
        return $this;
    }

    /**
     * @return string
     */
    public function getQuestion()
    {
        return $this->question;
    }

    /**
     * @param string $question
     * @return static
     */
    public function setQuestion($question)
    {
        $this->question = $question;
        return $this;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param array $config
     * @return static
     */
    public function setConfig(array $config = null)
    {
        $this->config = $config;
        return $this;
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
        $this->answers->replaceWith($answers);
        return $this;
    }

    /**
     * @return QuizSolution|null
     */
    public function getSolution()
    {
        return $this->solution;
    }

    /**
     * @param QuizSolution|null $solution
     * @return static
     */
    public function setSolution(QuizSolution $solution = null)
    {
        $this->solution = $solution;
        return $this;
    }
}
