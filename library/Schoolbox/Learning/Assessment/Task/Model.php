<?php
/**
 * Task
 *
 * A generic "thing to do" that doesn't have a Mark value. A Task
 * typically represents things like "read this book", "prepare this
 * thing before the class" etc.
 *
 * Tasks can be self-assessed (like a to-do list for students)
 * or they can be asssessed by a teacher ("student read the book", etc)
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\Task;

use \Schoolbox\Learning\Assessment\AbstractAssessment;
use \Schoolbox\Learning\Assessment\Type;

class Model extends AbstractAssessment
{
    /**
     * The "type" of the Due Work
     * (Essay, Homework, etc)
     *
     * For Quiz, it'll just say "Quiz"
     * Similarly for Task
     *
     * @var Type
     */
    protected $type;

    /**
     * Model constructor.
     */
    public function __construct()
    {
        parent::__construct();

        /* Ram in a default Type for Task
         */
        $type = new Type();
        $type->setName('Task');

        $this->setType($type);
    }

    /* Getters/Setters
     */

    /**
     * @return Type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param Type $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }
}