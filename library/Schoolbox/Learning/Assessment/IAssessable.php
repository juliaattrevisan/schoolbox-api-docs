<?php
/**
 * IAssessable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;


interface IAssessable
{

    /**
     * Calculate the final mark for the Assessable
     *
     * @return mixed
     */
    public function calculateMark();
}