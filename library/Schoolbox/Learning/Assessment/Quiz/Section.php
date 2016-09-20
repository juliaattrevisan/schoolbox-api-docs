<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz;

use Schoolbox\Collection;

class Section
{
    /** @var array */
    private $section;

    /** @var Collection|Question[] */
    private $questions;


    public function __construct(array $section)
    {
        $this->section = $section;
        $this->questions = new Collection();
    }

    /* Getters / Setters
     */

    /**
     * @return Collection|Question[]
     */
    public function getQuestions()
    {
        return $this->questions;
    }

    /**
     * @param Collection|Question[] $questions
     * @return $this
     */
    public function setQuestions(Collection $questions)
    {
        $this->questions = $questions;
        return $this;
    }


}
