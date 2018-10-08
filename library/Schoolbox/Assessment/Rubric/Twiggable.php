<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric;


/**
 * Interface Twiggable
 *
 * @deprecated this has no further use now that everything that uses it is deprecated
 */
interface Twiggable
{

    /**
     * Convert yourself into a Twig context array
     *
     * @return array
     */
    public function toContext();

}
