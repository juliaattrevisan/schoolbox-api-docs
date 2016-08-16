<?php
/**
 * Task
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;


class Task extends AbstractModel
{
    /**
     * Calculate the final mark for this IAssessable
     *
     * Tasks aren't marked and always return null
     *
     * @return null
     */
    public function calculateMark()
    {
        return null;
    }
}