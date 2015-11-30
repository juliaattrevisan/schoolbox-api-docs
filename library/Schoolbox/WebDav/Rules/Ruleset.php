<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\WebDav\Rules;

use Alaress\Rules\Condition;
use Alaress\Rules\TokenizedRuleset;
use Schoolbox\User;

class Ruleset extends TokenizedRuleset {

    /** @var Tokenizer */
    protected $tokenizer;


    /**
     * @param string $uri
     * @param string $username
     * @param string $password
     * @param array  $paths
     */
    public function __construct($uri, $username = '{:username}', $password = '{:password}', $paths = array())
    {
        parent::__construct(new Tokenizer());

        // default rule to set base vars
        $this->newRule(array(), array(
            'baseUri'  => $uri,
            'userName' => $username,
            'password' => $password,
        ));

        // if default paths are defined, include paths
        if (count($paths)) {
            $this->newRule(array(), array(
                'paths' => $paths
            ));
        }
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


    /**
     * @param User $user
     * @return mixed
     */
    public function applyTo($user)
    {
        $result = array();

        foreach ($this->rules as $rule) {
            $data = $rule->apply($user);
            if (is_array($data)) {
                foreach ($data as $k => $v) {
                    if (is_array($v)) {
                        foreach ($v as $i => $j) {
                            $result[$k][sha1($i)] = $j;
                        }
                    } else {
                        $result[$k] = $v;
                    }
                }
            }
        }

        $settings = $this->tokenizer->populateSettings(array(), $result, $user);

        if (isset($settings['paths'])) {
            $settings['paths'] = array_values($settings['paths']);
        } else {
            $settings['paths'] = array();
        }

        return $settings;
    }


    /**
     * @return Rule[]
     */
    public function getRules()
    {
        $all = array();

        // cast SPLOS to array
        foreach ($this->rules as $r) {
            $all[] = $r;
        }

        return $all;
    }

}
