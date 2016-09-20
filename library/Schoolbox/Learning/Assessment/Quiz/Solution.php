<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz;

class Solution
{
    /** @var array */
    private $solution = [];


    public function __construct(array $solution)
    {
        $this->solution = $solution;
    }
}
