<?php

/**
 * Quiz Factory
 *
 * Builder for Question Types. This class spawns the various QuestionType and
 * AnswerType models (handlers) depending on a type_id.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Assess_Factory extends Evaluation_Factory
{
    /** @var Assess_Response  */
    private $responseHandler;
/** @var Assess_Question  */
    private $questionHandler;
/** @var Assess_Section  */
    private $sectionHandler;
    public function __construct()
    {
        parent::__construct();
        $this->responseHandler = new Assess_Response();
        $this->questionHandler = new Assess_Question();
        $this->sectionHandler  = new Assess_Section();
    }

    /**
     * @return string
     */
    protected function getFactoryName()
    {
        return 'Assess';
    }

    /**
     * @return Assess_Response
     */
    protected function getResponseHandler()
    {
        return $this->responseHandler;
    }

    /**
     * @return Assess_Question
     */
    protected function getQuestionHandler()
    {
        return $this->questionHandler;
    }

    /**
     * @return Assess_Section
     */
    protected function getSectionHandler()
    {
        return $this->sectionHandler;
    }
}
