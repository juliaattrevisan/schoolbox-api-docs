<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric;


interface Twiggable
{

    /**
     * Convert yourself into a Twig context array
     *
     * @return array
     */
    public function toContext();

}
