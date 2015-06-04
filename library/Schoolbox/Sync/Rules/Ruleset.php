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
use Schoolbox\Mapper;

class Ruleset extends TokenizedRuleset
{

    /** @var Mapper */
    protected $mapper;

    /**
     * @param Mapper $mapper
     * @param string $context
     */
    public function __construct(Mapper $mapper, $context)
    {
        parent::__construct(new Tokenizer($context));
        $this->mapper = $mapper;
    }


    /**
     * The $input array for this method should be an External_User record
     *
     * @param array  $input    - the incoming record to manipulate
     * @param object $previous - an instance of the object mapped by the mapper
     * @return object          - an instance of the object mapped by the mapper
     */
    public function applyTo($input, $previous = null)
    {
        $object = $previous ?: $this->mapper->createModel();

        $map = $this->mapper->getMap();

        foreach ($this->rules as $rule) {
            $data = $rule->apply($input);

            if (is_array($data)) {
                foreach ($data as $property => $value) {

                    // Apply tokenization to returned value. The value returned should be a primitive,
                    // which will then by hydrated into the model by the mapper
                    $value   = $this->tokenizer->parseTokens([], $property, $value, $input);
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