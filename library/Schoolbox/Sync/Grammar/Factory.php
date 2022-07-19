<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Grammar;

use Alaress\Rules\Lang\Map;
use OutOfBoundsException;
use OutOfRangeException;
use Psr\Log\LoggerInterface;
use ReflectionException;
use Schoolbox\Sync\Rule\Mapper as RuleMapper;
use Schoolbox\Sync\Rule\Model as Rule;
use Schoolbox\UserMapper;
use TypeError;

class Factory
{
    const CONDITION_NAMESPACE = '\Schoolbox\Sync\Grammar\Condition';
    const EFFECT_NAMESPACE    = '\Alaress\Rules\Lang';

    /** @var UserMapper */
    protected $userMapper;

    /** @var RuleMapper */
    protected $ruleMapper;

    /** @var array */
    protected $tokenMap = [];

    /** @var LoggerInterface */
    protected $log;


    /**
     * @param LoggerInterface $log
     * @param UserMapper      $userMapper
     * @param RuleMapper      $ruleMapper
     */
    public function __construct(LoggerInterface $log, UserMapper $userMapper = null, RuleMapper $ruleMapper = null)
    {
        $this->log        = $log;
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
        $search = [
            'role_type' => $roletype,
            'enabled'   => true,
        ];

//        if (!$includeDisabled) {
//            $search['enabled'] = true;
//        }

        /**
         * pull these rules out in sequence
         * @var Rule[] $dbRules
         */
        $dbRules = $this->ruleMapper->findByColumnList($search, [
            'order' => [
                'sequence' => 'ASC'
            ]
        ]);

        return $this->buildFromRuleCollection($roletype, $dbRules);
    }

    /**
     * Builds a ruleset out of a roletype and a collection of rule models.
     *
     * @param string $roletype
     * @param Rule[] $ruleCollection
     *
     * @return UserRuleset
     */
    public function buildFromRuleCollection($roletype, $ruleCollection)
    {
        $context = ucfirst($roletype);

        $ffactory = new FieldFactory();

        $map = $ffactory->buildMap($roletype);

        $set = new UserRuleset($this->userMapper, $context, $map, $this->log);

        foreach ($ruleCollection as $rule) {
            $translatedRule = $this->translateRuleModelToAddableRule($rule);
            $set->newRule(
                $translatedRule['conditions'],
                $translatedRule['effects'],
                $translatedRule['negate'],
                $rule->getName() !== '' ? $rule->getName() : ('Rule ' . $rule->getId()),
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
         * @var Rule[] $dbRules
         */
        $dbRules = $this->ruleMapper->findByColumnList($search, [
            'order' => [
                'sequence' => 'ASC'
            ]
        ]);

        foreach ($dbRules as $i => $rule) {
            $rules[$i] = $this->translateRuleModelToAddableRule($rule);
        }

        return $rules;
    }

    /**
     * Translates a rule model object to a rule that is addable to
     * `UserRuleset->newRule()`.
     *
     * @param Rule $rule
     *
     * @return array An array with the following keys:
     *               * conditions: a list of conditions
     *               * effects:    a list of effects
     *               * negate:     whether the condition is to be negated
     * @throws OutOfBoundsException if a condition's type couldn't be found
     * @throws OutOfRangeException  if an effect or condition couldn't be built
     * @noinspection PhpDocMissingThrowsInspection this should only throw the
     *               above two exception types
     */
    protected function translateRuleModelToAddableRule(Rule $rule)
    {
        $result = [
            'conditions' => [],
            'effects' => [],
            'negate' => false
        ];

        $i              = 1;
        $firstException = null;
        foreach ($rule->getConditions() as $condition) {
            try {
                $result['conditions'][] = $this->buildCondition(
                    $condition->getConditionType()->getName(),
                    $condition->getArguments(),
                    $condition->getRule()->getName(),
                );
            } catch (OutOfBoundsException | OutOfRangeException $e) {
                // Log that this condition is broken,
                // and provide information on how to fix it before rethrowing
                $this->log->error(
                    sprintf(
                        'Condition %d in rule "%s" is invalid: consider removing the condition',
                        $i,
                        $rule->getName() ?: ('Rule ' . $rule->getId()),
                    ),
                );
                $firstException ??= $e;
            } finally {
                $i++;
            }
        }

        foreach ($rule->getEffects() as $effect) {
            try {
                $result['effects'][$effect->getUserProperty()] = $this->buildEffect(
                    $effect->getEffectType()->getName(),
                    $effect->getArguments(),
                    $effect->getRule()->getName(),
                );
            } catch (OutOfBoundsException | OutOfRangeException $e) {
                // Log that this effect is broken,
                // and provide information on how to fix it before rethrowing
                $this->log->error(
                    sprintf(
                        'Effect on "%s" in rule "%s" is invalid: consider removing the rule',
                        $effect->getUserProperty(),
                        $rule->getName() ?: ('Rule ' . $rule->getId()),
                    ),
                );
                $firstException ??= $e;
            }
        }

        /* You can negate entire rules but we don't do it in UPS
         */
//        $result['negate'] = isset($rule['negate']) && $rule['negate'];

        // Was an exception raised? throw if so
        if ($firstException) {
            // Logically this cannot be anything other than
            // OutOfBoundsException or OutOfRangeException
            /** @noinspection PhpUnhandledExceptionInspection */
            throw $firstException;
        }
        return $result;
    }


    /**
     * Attempt to build a Condition from the given type and arguments
     *
     * @param string $type
     * @param array  $args
     * @param string $label
     * @return \Alaress\Rules\Condition
     */
    protected function buildCondition($type, array $args, string $label)
    {
        try {
            return $this->reflectivelyBuild(self::CONDITION_NAMESPACE, $type, $args, $label);
        } catch (ReflectionException $e) {
            throw new OutOfBoundsException("Condition type '$type' not found.", 0, $e);
        }
    }


    /**
     * Attempt to build an effect from the given type and arguments
     *
     * @param string $type
     * @param array  $args
     * @param string $label
     * @return bool|string|Map
     */
    protected function buildEffect($type, array $args, string $label)
    {
        try {
            // attempt to treat as language construct
            return $this->reflectivelyBuild(self::EFFECT_NAMESPACE, $type, $args, $label);
        } catch (ReflectionException $e) {
            // no? ok, it's a string literal
            return $args[0];
        }
    }


    /**
     * @param string $namespace
     * @param string $classname
     * @param array  $arguments
     * @param string $label
     * @return object
     * @throws OutOfRangeException if not enough params
     * @throws ReflectionException if something else went wrong in construction
     */
    protected function reflectivelyBuild($namespace, $classname, array $arguments, string $label)
    {
        $fqcn = $namespace . '\\' . $classname;

        $rc = new \ReflectionClass($fqcn);

        $expectedCount = 0;
        $constructor   = $rc->getConstructor();
        if ($constructor instanceof \ReflectionMethod) {
            $expectedCount = $constructor->getNumberOfParameters();
        }

        $actualCount = count($arguments);

        // Be a little more lenient with parameter counts;
        // only error if there's too few parameters provided.
        if ($expectedCount > $actualCount) {
            throw new OutOfRangeException("$classname expects $expectedCount arguments, got $actualCount");
        }

        // If there's too many parameters, just ignore the excess.
        // (Somehow, skip rules are getting created with a parameter which they
        // should not have.)
        $arguments = array_slice($arguments, 0, $expectedCount);
        try {
            return $rc->newInstanceArgs($arguments);
        } catch (TypeError $e) {
            // Cast Error to Exception to be picked up by custom exception handler and provide informative message to user.
            throw new OutOfRangeException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
