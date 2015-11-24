<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Rules;


use Alaress\Rules\Lang\Construct;
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
     * @param Condition[]     $conditions the list of conditions that must pass for this rule to take effect
     * @param Construct|mixed $response   the output of this rule if the conditions pass, which is either with a Construct to be applied, or a literal value
     * @param bool            $negate     whether or not to negate
     */
    public function newRule(array $conditions, $response, $negate = false)
    {
        $rule = $this->createRule(function () use ($response) {
            return $response;
        }, $negate);

        foreach ($conditions as $condition) {
            $rule->addCondition($condition);
        }

        $this->attach($rule);
    }


    /**
     * @param mixed $input
     * @return mixed
     */
    public abstract function applyTo($input);


    /**
     * @param callable $effect
     * @param bool     $negate
     * @return Rule
     */
    protected abstract function createRule($effect, $negate = false);

}
