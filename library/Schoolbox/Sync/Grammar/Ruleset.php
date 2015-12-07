<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Grammar;

use Alaress\Rules\TokenizedRuleset;
use Schoolbox\External\Normalise\User\Base as ExternalUser;
use Schoolbox\Mapper;
use Schoolbox\Sync\Hydrator\HydratorInterface;
use Schoolbox\User as InternalUser;

class Ruleset extends TokenizedRuleset implements HydratorInterface
{

    /** @var Mapper */
    protected $mapper;

    /**
     * @param Mapper $mapper
     * @param string $context
     * @param $map
     */
    public function __construct(Mapper $mapper, $context, $map)
    {
        parent::__construct(new Tokenizer($context, $map));
        $this->mapper = $mapper;
    }

    /**
     * Hydrates the internal user by passing through to `applyTo()`.
     *
     * @param ExternalUser $input
     * @param InternalUser $previous
     *
     * @return InternalUser
     */
    public function hydrate(ExternalUser $input, InternalUser $previous)
    {
        return $this->applyTo($input, $previous);
    }

    /**
     * The $input for this method should be an NDS record
     *
     * @param mixed  $input    - the NDS record to manipulate
     * @param object $previous - an instance of the object mapped by the mapper
     * @return object          - an instance of the object mapped by the mapper
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
     * @param array  $sequences - the sequence of the rules to be applied
     * @param mixed  $input     - the NDS record to manipulate
     * @param object $previous  - an instance of the object mapped by the mapper
     * @return object           - an instance of the object mapped by the mapper
     */
    public function applyPartial(array $sequences, $input, $previous = null)
    {
        $object = $previous ?: $this->mapper->createModel();

        $map = $this->mapper->getMap();

        $rules = $this->getRules();

        foreach ($rules as $sequence => $rule) {

            if (!in_array($sequence, $sequences)) {
                continue;
            }

            $data = $rule->apply($input, $previous);

            if (is_array($data)) {
                foreach ($data as $property => $value) {

                    // Apply tokenization to returned value. The value returned should be a primitive,
                    // which will then by hydrated into the model by the mapper
                    $value   = $this->tokenizer->parseToken($input, $value);
                    $value   = trim($value);
                    $mapping = $map->getOffset($property);

                    // treat collections special-like
                    if ($mapping['type'] == Mapper::TYPE_COLLECTION) {

                        // first, read the value out of the object
                        $array = $this->mapper->getModelProperty($object, $property);

                        // if the value we got isn't in this array, append it
                        if (!in_array($value, $array)) {
                            $array[] = $value;
                        }

                        // reassign the value to the now (maybe) changed array
                        $value = $array;
                    }

                    $this->applyModelPropertyValue($object, $property, $value);
                }
            }
        }

        return $object;
    }


    /**
     * @param object $object   - the object to update with the new value
     * @param string $property - the property of the object to change
     * @param mixed  $value    - the PRIMITIVE value to apply, as derived by the tokenizer
     */
    protected function applyModelPropertyValue($object, $property, $value)
    {
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
    function getRulesBySequence()
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
     * @param callable $effect
     * @param bool     $negate
     * @return Rule
     */
    protected function createRule($effect, $negate = false)
    {
        return new Rule($effect, $negate);
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