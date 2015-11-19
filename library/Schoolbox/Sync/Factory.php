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
use Schoolbox\Sync\Rules\UserRuleset;
use Schoolbox\UserMapper;

class Factory
{
    /** @var UserMapper */
    protected $mapper;


    /**
     * TODO expose hidden dependencies
     */
    public function __construct()
    {
        $this->mapper = UserMapper::getInstance();

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

        $set = new UserRuleset($this->mapper, $context);

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
     * @return array
     */
    protected function findRulesForRoleType($roletype)
    {
        // TODO db queries
        $fakeStructure = [
            [
                'conditions' => [
                    [
                        'type' => 'UserExists',
                        'args' => [ true ]
                    ]
                ],
                'effects' => [
                    'enabled' => [
                        'type' => 'literal',
                        'args' => [ true ]
                    ]
                ]
            ],
            [
                'conditions' => [
                    [
                        'type' => 'ExternalUserValueIn',
                        'args' => [ 'StudentYearLevel', [ 0, 1, 2, 3, 4, 5, 6 ], false ]
                    ]
                ],
                'effects' => [
                    'role' => [
                        'type' => 'literal',
                        'args' => [ 5 ]
                    ]
                ]
            ],
            [
                'conditions' => [
                ],
                'effects' => [
                    'schoolHouse' => [
                        'type' => 'Map',
                        'args' => [
                            '{:StudentHouse}', [
                                'Da' => 1,
                                'Fo' => 2,
                                'Oa' => 3,
                                'Th' => 4
                            ]
                        ]
                    ]
                ]
            ]
        ];


        $rules = [];

        foreach ($fakeStructure as $i => $rule) {

            $rules[$i] = [
                'conditions' => [],
                'effects' => [],
                'negate' => false
            ];

            foreach ($rule['conditions'] as $condition) {
                $rules[$i]['conditions'][] = $this->buildCondition($condition['type'], $condition['args']);
            }

            foreach ($rule['effects'] as $property => $effect) {
                $rules[$i]['effects'][$property] = $this->buildEffect($effect['type'], $effect['args']);
            }

            $rules[$i]['negate'] = isset($rule['negate']) && $rule['negate'];
        }

        return $rules;
    }


    /**
     * Attempt to build a Condition from the given type and arguments
     *
     * @param string $type
     * @param array  $args
     * @throws \OutOfBoundsException
     * @throws \OutOfRangeException
     * @return \Alaress\Rules\Condition
     */
    protected function buildCondition($type, array $args)
    {
        switch ($type) {
            case 'UserExists':

                if (count($args) != 1) {
                    throw new \OutOfRangeException("Expected 1 argument, got " . count($args));
                }

                return new Rules\Condition\UserExists($args[0]);

            case 'ExternalUserValueIn':

                if (count($args) != 3) {
                    throw new \OutOfRangeException("Expected 3 arguments, got " . count($args));
                }

            return new Rules\Condition\ExternalUserValueIn($args[0], $args[1], $args[2]);

        }

        throw new \OutOfBoundsException("Condition type '$type' not found.");
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
        switch ($type) {
            case 'Map':

                if (count($args) != 2) {
                    throw new \OutOfRangeException("Expected 2 arguments, got " . count($args));
                }

                return new Map($args[0], $args[1]);

            case 'literal':
            default:
                return $args[0];
        }
    }


}