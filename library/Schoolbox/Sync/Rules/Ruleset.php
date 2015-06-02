<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Rules;

use Alaress\Rules\TokenizedRuleset;
use Schoolbox\Collection;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Ruleset extends TokenizedRuleset
{

    /** @var UserMapper */
    protected $userMapper;

    /**
     * @param UserMapper $userMapper
     * @param string     $context
     */
    public function __construct(UserMapper $userMapper, $context)
    {
        parent::__construct(new Tokenizer($context));
        $this->userMapper = $userMapper;
    }


    /**
     * The $input array for this method should be an External_User record
     *
     * @param array $externalUser
     * @param User  $previous
     * @return User
     */
    public function applyTo($externalUser, $previous = null)
    {
        $user = $previous ?: $this->userMapper->createModel();

        foreach ($this->rules as $rule) {
            $data = $rule->apply($externalUser);

            if (is_array($data)) {
                foreach ($data as $key => $value) {

                    // apply tokenization to returned value
                    $value = $this->tokenizer->parseTokens([], $key, $value, $externalUser);

                    // and push the value into the object
                    $setValue = "set$key";

                    if (is_array($value)) {
                        $value = new Collection($value);
                    }

                    // blegh. TODO a better approach, anyone?
                    call_user_func([ $user, $setValue ], $value);
                }
            }
        }

        return $user;
    }


    /**
     * @return Rule[]
     */
    public function getRules()
    {
        $all = array();

        // the number of same-specificity rules we can handle. 100 should be plenty
        $size = 100;

        // cast SPLOS to array
        foreach ($this->rules as $r) {
            $prec = $r->getSpecificity() * $size;

            // increment for an available space
            for ($i = 0; $i < $size; ++$i) {
                if (!array_key_exists($prec + $i, $all)) {
                    break;
                }
            }

            $all[$prec + $i] = $r;
        }

        // sort the result
        ksort($all);

        return array_values($all);
    }


    /**
     * @param callable $effect
     * @param bool     $negate
     * @return Rule
     */
    protected function createRule($effect, $negate = false)
    {
        return new Rule($effect, $negate);
    }

}