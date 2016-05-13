<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Rule;


trait ComponentTrait
{

    /** @var int */
    protected $id;

    /** @var array */
    protected $arguments;

    /** @var Model */
    protected $rule;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @param int $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }


    /**
     * @return Model
     */
    public function getRule()
    {
        return $this->rule;
    }


    /**
     * @param Model $rule
     * @return static
     */
    public function setRule($rule)
    {
        $this->rule = $rule;
        return $this;
    }


    /**
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }


    /**
     * @param array $arguments
     * @return static
     */
    public function setArguments($arguments)
    {
        // rather than typehint and have it blow up, at least using an exception is recoverable
        if (!is_array($arguments)) {
            throw new \InvalidArgumentException('Arguments must be an array, got ' . gettype($arguments));
        }

        /* Square brackets persist in the db but we don't want that format
         * Deal with the bad data by removing it, temporarily, as re-saving
         * rulesets will repair the bad data for us anyway
         */
        foreach ($arguments as $current) {
            $this->arguments[] = is_string($current) ? str_replace('[]', '', $current) : $current;
        }

        return $this;
    }

}