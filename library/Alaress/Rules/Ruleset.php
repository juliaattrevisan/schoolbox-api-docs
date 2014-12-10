<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Rules;


use SplObjectStorage;

abstract class Ruleset
{

    /** @var SplObjectStorage|Rule[] */
    protected $rules;


    /**
     *
     */
    public function __construct()
    {
        $this->rules = new SplObjectStorage();
    }

    /**
     * @param Rule $rule
     * @return self
     */
    public function addRule(Rule $rule)
    {
        return $this->attach($rule);
    }


    /**
     * @param Rule $rule
     * @param mixed $data
     * @return $this
     */
    protected function attach(Rule $rule, $data = null)
    {
        $this->rules->attach($rule, $data);
        return $this;
    }


    /**
     * @param mixed $input
     * @return mixed
     */
    public abstract function applyTo($input);

}
