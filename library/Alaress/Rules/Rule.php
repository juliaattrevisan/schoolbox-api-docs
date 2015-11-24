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

class Rule
{

    /** @var bool */
    protected $negate;

    /** @var SplObjectStorage|Condition[] */
    protected $conditions;

    /** @var Callable */
    protected $effect;


    /**
     * @param Callable $effect
     * @param bool     $negate
     */
    public function __construct($effect, $negate = false)
    {
        $this->conditions = new SplObjectStorage();
        $this->negate     = $negate;
        $this->effect     = $effect;
    }


    /**
     * Accepts an arbitrary number of arguments
     *
     * @param mixed $_
     * @return mixed
     */
    public function apply($_ = null)
    {
        // no conditions means always fire
        if (!$this->conditions->count()) {
            $fire = true;

        } else {
            $fire = true;
            // iterate conditions and ensure all match
            foreach ($this->conditions as $condition) {
                // failfast
                if (!call_user_func_array([ $condition, 'test' ], func_get_args())) {
                    $fire = false;
                    // we don't need to keep testing other conditions if one has failed
                    break;
                }
            }
        }

        // pit fire against negate
        if ($fire ^ $this->negate) {
            return call_user_func($this->effect);
        }

        return null;
    }


    /**
     * @param Condition $condition
     * @return self
     */
    public function addCondition(Condition $condition)
    {
        $this->conditions->attach($condition);
        return $this;
    }


    /**
     * How specific is this rule? Used for precedence.
     *
     * @return int
     */
    public function getSpecificity()
    {
        return $this->conditions->count();
    }


    /**
     * @return Condition[]
     */
    public function getConditions()
    {
        $all = array();

        // cast SPLOS to array
        foreach ($this->conditions as $c) {
            $all[] = $c;
        }

        return $all;
    }

}

