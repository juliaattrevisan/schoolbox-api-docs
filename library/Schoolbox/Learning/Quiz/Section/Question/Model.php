<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Quiz\Section\Question;

use Schoolbox\Learning\Quiz\Section\Model as QuizSection;
use Schoolbox\Learning\Quiz\Section\Question\QuestionType\Model as QuizQuestionType;
use Alaress_Date_Format;

class Model
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var QuizSection
     */
    private $section;

    /**
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
     * @var string
     * @todo Unserialise on first use and cache
     */
    private $config;

    /**
     * @var Alaress_Date_Format
     */
    private $deletedAt;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

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
     * @return string
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param string $config
     * @return static
     */
    public function setConfig($config)
    {
        $this->config = $config;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    /**
     * @param Alaress_Date_Format $deletedAt
     * @return static
     */
    public function setDeletedAt(Alaress_Date_Format $deletedAt)
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

}