<?php

/**
 * Survey_Factory
 *
 * Builder for Question Types. This class spawns the various QuestionType and
 * AnswerType models (handlers) depending on a type_id.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Survey_Factory extends Evaluation_Factory
{
    private $responseHandler;
    private $questionHandler;
    private $sectionHandler;
    public function __construct()
    {
        parent::__construct();
        $this->responseHandler = new Survey_Response();
        $this->questionHandler = new Survey_Question();
        $this->sectionHandler  = new Survey_Section();
    }

    /**
     * @return string
     */
    protected function getFactoryName()
    {
        return 'Survey';
    }

    protected function getResponseHandler()
    {
        return $this->responseHandler;
    }

    protected function getQuestionHandler()
    {
        return $this->questionHandler;
    }

    /**
     * @return Survey_Section
     */
    protected function getSectionHandler()
    {
        return $this->sectionHandler;
    }
}
