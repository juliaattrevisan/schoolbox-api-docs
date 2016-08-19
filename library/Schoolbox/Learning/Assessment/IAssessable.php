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
     * Return the name of the "type" of Assessable this is
     *
     * For Due Works, it'll be "Essay, Homework, etc"
     * For Quiz, it'll just be "Quiz"
     * For Task, it'll just be "Task"
     *
     * @return mixed
     */
    public function getTypeName();

}