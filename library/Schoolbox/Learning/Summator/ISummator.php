<?php
/**
 * ISummation
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Summator;

use \Schoolbox\Collection;

interface ISummator
{
    /**
     * Execute summation strategy upon a Collection of
     * IAssessable instances
     *
     * @param Collection $allAssessables
     * @return mixed
     */
    public function summate(Collection $allAssessables);
}