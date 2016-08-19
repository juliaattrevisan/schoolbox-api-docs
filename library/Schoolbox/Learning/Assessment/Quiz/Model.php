<?php
/**
 * Quiz
 *
 * An Adapter class to wrap around the Quiz handler which sadly
 * uses array structures. This Adapter empowers it and makes it
 * adapt to the current IAssessable behaviours
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\Quiz;

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

        /* Ram in a default Type for Quiz
         */
        $type = new Type();
        $type->setName('Quiz');

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
