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

namespace Schoolbox\Survey\Response;

use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Survey\Attempt\Model as SurveyAttempt;
use Schoolbox\Survey\Section\Model as SurveySection;
use Schoolbox\Survey\Question\Model as SurveyQuestion;

class Model
{
    use Id;

    /** @var string $response */
    protected $response;
    
    /** @var SurveyAttempt $surveyAttempt */
    protected $attempt;

    /** @var SurveySection $surveySection */
    protected $section;

    /** @var SurveyQuestion $surveyQuestion */
    protected $question;

    /**
     * @param string $response
     * @return self
     */
    public function setResponse($response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * @return string
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param SurveyAttempt $attempt
     * @return self
     */
    public function setAttempt($attempt)
    {
        $this->attempt = $attempt;
        return $this;
    }

    /**
     * @return SurveyAttempt
     */
    public function getAttempt()
    {
        return $this->attempt;
    }

    /**
     * @param SurveySection $section
     * @return self
     */
    public function setSection($section)
    {
        $this->section = $section;
        return $this;
    }

    /**
     * @return SurveySection
     */
    public function getSection()
    {
        return $this->section;
    }

    /**
     * @param SurveyQuestion $question
     * @return self
     */
    public function setQuestion($question)
    {
        $this->question = $question;
        return $this;
    }

    /**
     * @return SurveyQuestion
     */
    public function getQuestion()
    {
        return $this->question;
    }    
}
