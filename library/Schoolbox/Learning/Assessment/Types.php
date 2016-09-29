<?php
/**
 * Types
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;


class Types extends \Schoolbox\Collection
{
    /**
     * Assessable types
     */
    const ASSESSMENT_PROJECT = 0;
    const ASSESSMENT_DUE_WORK = 1;
    const ASSESSMENT_TASK = 2;
    const ASSESSMENT_QUIZ = 3;

    /**
     * Set of key-value pairs
     *
     * @var array
     */
    private $types = [];

    /**
     * Types constructor.
     *
     * @param array $stack
     */
    public function __construct($stack = [])
    {
        parent::__construct($stack);

        $this->setTypes([
            'project' => self::ASSESSMENT_PROJECT,
            'dueWork' => self::ASSESSMENT_DUE_WORK,
            'task' => self::ASSESSMENT_TASK,
            'quiz' => self::ASSESSMENT_QUIZ
        ]);
    }

    /**
     * Get the string representation from numeric representation
     *
     * @param $index
     * @return mixed
     */
    public function getFromIndex($index)
    {
        $flipped = array_flip($this->types);

        return $flipped[$index];
    }

    /**
     * Get the numeric representation from string representation
     *
     * @param $title
     * @return mixed
     */
    public function getFromTitle($title)
    {
        return $this->types[$title];
    }

    /**
     * Is the $index valid?
     *
     * @param $index
     * @return bool
     */
    public function isValidIndex($index)
    {
        if ($index !== null) {
            return in_array($index, array_values($this->getTypes()));
        }

        return false;
    }

    /**
     * $index is a project assessment type
     *
     * @param $index
     * @return bool
     */
    public function isProject($index)
    {
        return $index == self::ASSESSMENT_PROJECT;
    }

    /* Getters/Setters
     */

    /**
     * @return array
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * @param array $types
     */
    private function setTypes($types)
    {
        $this->types = $types;
    }
}