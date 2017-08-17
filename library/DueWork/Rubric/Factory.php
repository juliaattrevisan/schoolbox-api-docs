<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/**
 * Class DueWork_Rubric_Factory
 * @deprecated use Schoolbox\Assessment\Rubric\Factory
 */
class DueWork_Rubric_Factory {

    private $criteria;

    /**
     * @deprecated
     *
     * @param array $criteria can either be an array of actual criteria, or just their IDs
     */
    public function __construct(array $criteria) {
        $this->criteria = $criteria;
    }
}
