<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Grammar;

use Alaress\Rules\TokenizedRuleset;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Normalise\User\Base as ExternalUser;
use Schoolbox\Mapper;
use Schoolbox\Sync\Effect\Fragment\Lookup;
use Schoolbox\Sync\Hydrator\HydratorInterface;
use Schoolbox\User as InternalUser;

/**
 * Class Ruleset
 *
 * @package Schoolbox\Sync\Grammar
 */
class Ruleset extends TokenizedRuleset implements HydratorInterface
{
    /**
     * @var Mapper
     */
    protected $mapper;


    /**
     * @var LoggerInterface
     */
    protected $log;


    /**
     * @param Mapper $mapper
     * @param string $context
     * @param array  $map
     * @param LoggerInterface $log
     */
    public function __construct(Mapper $mapper, $context, $map, LoggerInterface $log)
    {
        parent::__construct(new Tokenizer($context, $map));
        $this->mapper = $mapper;
        $this->log    = $log;
    }


    /**
     * Hydrates the internal user by passing through to `applyTo()`.
     *
     * @param ExternalUser $input
     * @param InternalUser $previous
     *
     * @return InternalUser
     * @throws Mapper\Exception
     */
    public function hydrate(ExternalUser $input, InternalUser $previous)
    {
        $context = [
            'externalUserId' => $input->getId(),
        ];

        if ($previous->getId()) {
            $context['internalUserId'] = $previous->getId();
        }

        $result = $this->applyTo($input, $previous);

        // TODO log whether or not this hydrator mutated this user, and if so, how? [SB-10513]
        if ($previous) {
            $diff = $this->mapper->diff($previous, $result, ['old', 'new']);
        } else {
            $diff = $this->mapper->dehydrate($result);
        }

        $diff = array_filter($diff, function ($value) {
            return !is_null($value);
        });

        if (count($diff)) {
            $this->log->info("New values set", array_merge($context, [ 'diff' => $diff ]));
        } else {
//            $this->log->info("No change to user", $context);
        }

        return $result;
    }


    /**
     * The $input for this method should be an NDS record
     *
     * @param mixed  $input    - the NDS record to manipulate
     * @param object $previous - an instance of the object mapped by the mapper
     * @return object          - an instance of the object mapped by the mapper
     * @throws Mapper\Exception
     */
    public function applyTo($input, $previous = null)
    {
        $precedences = array_keys($this->getRules());

        return $this->applyPartial($precedences, $input, $previous);
    }


    /**
     * Apply specific rules from the ruleset
     *
     * The $input for this method should be an NDS record
     *
     * @param array        $sequences - the sequence of the rules to be applied
     * @param ExternalUser $input     - the NDS record to manipulate
     * @param InternalUser $previous  - an instance of the object mapped by the mapper
     * @return InternalUser           - an instance of the object mapped by the mapper
     * @throws Mapper\Exception
     */
    public function applyPartial(array $sequences, $input, $previous = null)
    {
        $object                         = $previous ?: $this->mapper->createModel();
        $rules                          = $this->getRules();
        $context                        = [];
        $propertiesWithAmbiguousEffects = [];
        foreach ($rules as $sequence => $rule) {
            if (!in_array($sequence, $sequences)) {
                continue;
            }

            $data = $rule->apply($input, $previous);

            $context = [
                'externalUserId' => $input->getId(),
            ];

            if ($previous->getId()) {
                $context['internalUserId'] = $previous->getId();
            }

            $context['rulestring'] = $rule->getSummary();

            // Did the rule's conditions match the user?
            if (is_array($data)) {
                foreach ($data as $property => $token) {
                    // Apply tokenization to returned value. The value returned should be a primitive,
                    // which will then by hydrated into the model by the mapper
                    $value = $this->tokenizer->parseToken($input, $token, false);

                    /* Group Modifiers
                     */
                    if (in_array($property, ['userAccessRead', 'userAccessWrite', 'userAccessRemove'])) {
                        if ($value) {
                            // wrap value in an array if not already one
                            $value = (array)$value;

                            /* Only the setters perform mutations when accessing these three Group Mutator properties
                             */
                            $this->applyModelPropertyValue($object, $property, $value);
                            $context['new data'][$property] = $value;
                        }
                    } elseif ($this->isCollection($property)) {
                        // treat collections special-like

                        // first, read the value out of the object
                        $modelProperty = $this->mapper->getModelProperty($object, $property) ?? [];

                        // wrap value in an array if not already one
                        $value = (array)$value;

                        foreach ($value as $subvalue) {
                            // if the value we got isn't in this array, append it

                            if (!is_array($subvalue)) {
                                $subvalue = [$subvalue];
                            }

                            foreach ($subvalue as $subvalueCurrent) {
                                if (strlen(trim($subvalueCurrent)) && !in_array($subvalueCurrent, $modelProperty)) {
                                    $modelProperty[] = $subvalueCurrent;
                                }
                            }
                        }

                        // reassign the value to the now (maybe) changed array
                        $value = $modelProperty;

                        $this->applyModelPropertyValue($object, $property, $value);
                        $context['new data'][$property] = $value;
                    } elseif ($this->isModel($property)) {
                        $value = (array)$value;
                        // Did the effect for this model property return more
                        // than one value?
                        // This indicates that the SIS value matched multiple
                        // conditions for a mapping effect: since a model
                        // property may have one and only one value, we have to
                        // guess which value should be used. Let's just use the
                        // last value.
                        if (count($value) > 1) {
                            // $rule doesn't contain any information about the
                            // rule as stored in the DB, only its effects:
                            // therefore I can't log which rule should change.
                            // I also don't know the user's name with certainty,
                            // as it might not have been set by UPS yet: just
                            // log for now that it has an ambiguous effect.
                            $propertiesWithAmbiguousEffects[$property] = $property;
                        }
                        $value = trim(end($value));
                        $this->applyModelPropertyValue($object, $property, $value);
                        $context['new data'][$property] = $value;
                    } else {
                        if (is_array($value)) {
                            $value = implode(',', $value);
                        }
                        // ensure lack of whitespace
                        $value = trim($value);

                        $this->applyModelPropertyValue($object, $property, $value);
                        $context['new data'][$property] = $value;
                    }
                }

                // TODO log that the rule applied [SB-10514], and provide the new set of values [SB-10515]
                $this->log->debug("Rule matched and applied", $context);
            } else {
                // TODO log that the rule did not apply [SB-10514]
                $this->log->debug("Rule did not match", $context);
            }
        }

        // Log warnings for ambiguous effects here
        if (!empty($propertiesWithAmbiguousEffects)) {
            $this->log->warning(
                sprintf(
                    'An effect matches more than one value for %s (%s) for %s "%s": using the last match, but consider revising rules which set this property',
                    $object->formatName(),
                    $object->getExternalId(),
                    (count($propertiesWithAmbiguousEffects) > 1 ? 'properties' : 'property'),
                    implode(',', $propertiesWithAmbiguousEffects),
                ),
                $context,
            );
        }

        return $object;
    }


    /**
     * Is this property a collection property?
     *
     * Collection properties are assigned differently to scalar properties: each
     * value to be assigned is added to the collection.
     *
     * @param string $property
     * @return bool
     */
    protected function isCollection(string $property): bool
    {
        $map = $this->mapper->getMap();
        $mapping = $map->getOffset($property);

        return $mapping['type'] == Mapper::TYPE_COLLECTION;
    }


    /**
     * Is this property a model property?
     *
     * Model properties are assigned differently to scalar properties: if there
     * is more than one value, a warning is raised and the last value is used.
     * (Multiple values occur if the SIS value matches more than one mapping
     * condition.)
     *
     * @param string $property
     * @return bool
     */
    protected function isModel(string $property): bool
    {
        $map = $this->mapper->getMap();
        $mapping = $map->getOffset($property);

        return $mapping['type'] == Mapper::TYPE_MODEL;
    }


    /**
     * @param object $object   - the object to update with the new value
     * @param string $property - the property of the object to change
     * @param mixed  $value    - the PRIMITIVE value to apply, as derived by the tokenizer
     */
    protected function applyModelPropertyValue($object, $property, $value)
    {
        if (in_array($property, ['year', 'residentialHouse', 'schoolHouse']) && (int)$value === Lookup::NOT_SET_ID) {
            $set_method = $this->mapper->getSetMethodName($property);
            $object->$set_method(null);
            return;
        }

        $this->mapper->setModelProperty($object, $property, $value);
    }


    /**
     * Compatibility method, defaults to sequence
     *
     * @param bool $byPrecedence
     * @return Rule[]
     */
    public function getRules($byPrecedence = false)
    {
        if ($byPrecedence) {
            return $this->getRulesByPrecedence();
        }

        return $this->getRulesBySequence();
    }


    /**
     * @return Rule[]
     */
    public function getRulesBySequence()
    {
        $all = [];

        // spit them out in the order they came in
        foreach ($this->rules as $r) {
            $all[] = $r;
        }

        return $all;
    }


    /**
     * @return Rule[]
     */
    public function getRulesByPrecedence()
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

        return $all;
    }


    /**
     * @param callable $effect The rule's conditions and effects
     * @param bool     $negate If true: run if the conditions are NOT met
     * @param string   $label  A text label for the rule
     * @return Rule
     */
    protected function createRule($effect, $negate = false, string $label = '')
    {
        return new Rule($effect, $negate, $label);
    }

    /**
     * Rulesets have no relevant publicly visible name.
     *
     * @return string
     */
    public function getName()
    {
        return '';
    }

    /**
     * Rulesets have no relevant publicly visible description.
     *
     * @return string
     */
    public function getDescription()
    {
        return '';
    }
}
