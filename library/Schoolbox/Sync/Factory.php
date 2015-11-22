<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync;


use Alaress\Rules\Lang\Map;
use OutOfBoundsException;
use OutOfRangeException;
use ReflectionException;
use Schoolbox\Sync\Model\RuleMapper;
use Schoolbox\Sync\Rules\UserRuleset;
use Schoolbox\UserMapper;

class Factory
{
    const CONDITION_NAMESPACE = '\Schoolbox\Sync\Rules\Condition';
    const EFFECT_NAMESPACE    = '\Alaress\Rules\Lang';

    /** @var UserMapper */
    protected $userMapper;

    /** @var RuleMapper */
    protected $ruleMapper;


    /**
     * @param UserMapper $userMapper
     * @param RuleMapper $ruleMapper
     */
    public function __construct(UserMapper $userMapper = null, RuleMapper $ruleMapper = null)
    {
        $this->userMapper = $userMapper ?: UserMapper::getInstance();
        $this->ruleMapper = $ruleMapper ?: RuleMapper::getInstance();
    }


    /**
     * Accepts a roletype, and encapsulates the data retrieval and parsing to return the corresponding Ruleset
     *
     * @param string $roletype
     * @return UserRuleset
     */
    public function build($roletype)
    {
        $context = ucfirst($roletype);

        $set = new UserRuleset($this->userMapper, $context);

        foreach ($this->findRulesForRoleType($roletype) as $rule) {

            $set->newRule(
                $rule['conditions'],
                $rule['effects'],
                $rule['negate']
            );

        }

        return $set;
    }


    /**
     * Retrieves and prepares the rule data for the given roletype
     *
     * @param string $roletype
     * @param bool   $includeDisabled
     * @return array
     */
    protected function findRulesForRoleType($roletype, $includeDisabled = false)
    {
        $rules = [];

        $search = [
            'role_type' => $roletype,
        ];

        if (!$includeDisabled) {
            $search['enabled'] = true;
        }

        /**
         * pull these rules out in sequence
         * @var Model\Rule[] $dbRules
         */
        $dbRules = $this->ruleMapper->findByColumnList($search, [
            'order' => [
                'sequence' => 'ASC'
            ]
        ]);

        foreach ($dbRules as $i => $rule) {

            $rules[$i] = [
                'conditions' => [],
                'effects' => [],
                'negate' => false
            ];

            foreach ($rule->getConditions() as $condition) {
                $rules[$i]['conditions'][] = $this->buildCondition($condition->getConditionType()->getName(), $condition->getArguments());
            }

            foreach ($rule->getEffects() as $effect) {
                $rules[$i]['effects'][$effect->getUserProperty()] = $this->buildEffect($effect->getEffectType()->getName(), $effect->getArguments());
            }

//            $rules[$i]['negate'] = isset($rule['negate']) && $rule['negate'];
        }

        return $rules;
    }


    /**
     * Attempt to build a Condition from the given type and arguments
     *
     * @param string $type
     * @param array  $args
     * @throws OutOfBoundsException
     * @throws OutOfRangeException
     * @return \Alaress\Rules\Condition
     */
    protected function buildCondition($type, array $args)
    {
        try {
            return $this->reflectivelyBuild(self::CONDITION_NAMESPACE, $type, $args);
        } catch (ReflectionException $e) {
            throw new OutOfBoundsException("Condition type '$type' not found.", 0, $e);
        }
    }


    /**
     * Attempt to build an effect from the given type and arguments
     *
     * @param string $type
     * @param array  $args
     * @return bool|string|Map
     */
    protected function buildEffect($type, array $args)
    {
        try {
            // attempt to treat as language construct
            return $this->reflectivelyBuild(self::EFFECT_NAMESPACE, $type, $args);
        } catch (ReflectionException $e) {
            // no? ok, it's a string literal
            return $args[0];
        }
    }


    /**
     * @param string $namespace
     * @param string $classname
     * @param array $arguments
     * @return object
     */
    protected function reflectivelyBuild($namespace, $classname, array $arguments)
    {
        $fqcn = $namespace . '\\' . $classname;

        $rc = new \ReflectionClass($fqcn);

        $expectedCount = $rc->getConstructor()->getNumberOfParameters();

        $actualCount = count($arguments);

        if ($expectedCount != $actualCount) {
            throw new OutOfRangeException("$classname expects $expectedCount arguments, got $actualCount");
        }

        return $rc->newInstanceArgs($arguments);
    }

}