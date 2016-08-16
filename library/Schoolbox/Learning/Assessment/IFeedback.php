<?php
/**
 * IReview
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;


interface IFeedback
{

    /**
     * Calculate the final mark for the Review instance
     *
     * @return mixed
     */
    public function calculateMark();
}