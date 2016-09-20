<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Task\Feedback;

use \Schoolbox\Learning\Assessment\AbstractFeedback;

class Model extends AbstractFeedback
{

    /**
     * Calculate the final mark for this IAssessable
     *
     * Tasks aren't marked and always return null
     *
     * @return null
     */
    public function getNormalisedMark()
    {
        return null;
    }
}