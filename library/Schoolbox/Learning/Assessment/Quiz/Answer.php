<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz;

/**
 * Represents the teacher-defined correct solution to a Quiz Question
 */
class Answer
{
    /** @var array */
    private $answer = [];


    public function __construct(array $answer)
    {
        $this->answer = $answer;
    }
}
