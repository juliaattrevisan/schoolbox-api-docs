<?php

/**
 * Survey_Question_Bank
 *
 * Manages saved questions in the QuestionBank
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Survey_Question_Bank extends Evaluation_Question_Bank
{



    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->setup();
    }

    /**
     * Hook method
     */
    protected function setup()
    {
        $this->setQuestionHandler(new Survey_Question());
        $this->setAnswerHandler(new Survey_Answer());
        $this->setContext();
    }
}
