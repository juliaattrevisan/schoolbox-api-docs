<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz;

class Question
{
    // TODO - This is all just placeholder for testing. Will be replaced by SA-701

    /** @var array */
    private $question = [];


    public function __construct(array $question)
    {
        $this->question = $question;
    }

    /**
     * @return float
     */
    public function getMarks()
    {
        return $this->question['marks'];
    }

}
