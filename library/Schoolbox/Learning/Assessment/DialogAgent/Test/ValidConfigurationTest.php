<?php

/**
 * ValidConfigurationTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DialogAgent\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\DialogAgent;
use Schoolbox\Learning\Assessment\DialogAgent\Model;
use Schoolbox\Test\MockConnector;

/**
 * NextRoutes test
 */
class ValidConfigurationTest extends TestCase
{
    /**
     * @var MockConnector
     */
    private \Schoolbox\Test\MockConnector $mockDatabase;


    /**
     * @return void
     */
    public function setUp(): void
    {
        $this->mockDatabase = new MockConnector($this);
    }


    /**
     * @param mixed $input
     * @param int   $expected
     */
    #[DataProvider('providerValidConfiguration')]
    public function testValidConfiguration($input, $expected)
    {
        $this->mockDatabase->setNextFetch($input['mockfetch']);
        $outcome = (new DialogAgent\ValidConfiguration($this->mockDatabase))->isSatisfiedBy($input['model']);

        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testValidConfiguration
     *
     * @return array
     */
    public static function providerValidConfiguration(): array
    {
        return [
            /* The three basic values *and* the scenario must be configured
             */
            'Valid 1' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(1)
                        ->setOpeningMessage('meow')
                        ->setRules('moo'),
                    'mockfetch' => [
                        'numInvalid' => 0
                    ],
                ],
                true
            ],
            /* A null scenario is a case of broken data: dialog agent is misconfigured
             */
            'Invalid 1: null scenario' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(null)
                        ->setOpeningMessage(null)
                        ->setRules(null),
                    'mockfetch' => [
                        'numInvalid' => 0
                    ],
                ],
                false
            ],
            /* Opening Messages must be meaningful: null, empty strings, whitespaces are all meaningless
             */
            'Invalid 2a: meaningless opening message (null)' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(null)
                        ->setOpeningMessage(null)
                        ->setRules(null),
                    'mockfetch' => [
                        'numInvalid' => 0
                    ]
                ],
                false
            ],
            'Invalid 2b: meaningless opening message (null)' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(null)
                        ->setOpeningMessage(null)
                        ->setRules(null),
                    'mockfetch' => [
                        'numInvalid' => 123
                    ]
                ],
                false
            ],
            'Invalid 3a: meaningless opening message (empty)' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(null)
                        ->setOpeningMessage('')
                        ->setRules(null),
                    'mockfetch' => [
                        'numInvalid' => 0
                    ]
                ],
                false
            ],
            'Invalid 3b: meaningless opening message (empty)' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(null)
                        ->setOpeningMessage('')
                        ->setRules(null),
                    'mockfetch' => [
                        'numInvalid' => 123
                    ]
                ],
                false
            ],
            'Invalid 4a: meaningless opening message (whitespace only)' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(null)
                        ->setOpeningMessage('           ')
                        ->setRules(null),
                    'mockfetch' =>[
                        'numInvalid' => 0
                    ]
                ],
                false
            ],
            'Invalid 4b: meaningless opening message (whitespace only)' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(null)
                        ->setOpeningMessage('           ')
                        ->setRules(null),
                    'mockfetch' => [
                        'numInvalid' => 123
                    ]
                ],
                false
            ],
            /* Rules need to be provided
             */
            'Invalid 5a: meaningless rules (null)' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(null)
                        ->setOpeningMessage(null)
                        ->setRules(null),
                    'mockfetch' =>[
                        'numInvalid' => 0
                    ]
                ],
                false
            ],
            'Invalid 5b: meaningless rules (null)' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(null)
                        ->setOpeningMessage(null)
                        ->setRules(null),
                    'mockfetch' =>[
                        'numInvalid' => 123
                    ]
                ],
                false
            ],
            'Invalid 6a: meaningless rules (empty)' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(null)
                        ->setOpeningMessage(null)
                        ->setRules(''),
                    'mockfetch' =>[
                        'numInvalid' => 0
                    ]
                ],
                false
            ],
            'Invalid 6b: meaningless rules (empty)' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(null)
                        ->setOpeningMessage(null)
                        ->setRules(''),
                    'mockfetch' =>[
                        'numInvalid' => 123
                    ]
                ],
                false
            ],
            'Invalid 7a: meaningless rules (whitespace only)' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(null)
                        ->setOpeningMessage(null)
                        ->setRules('           '),
                    'mockfetch' =>[
                        'numInvalid' => 0
                    ]
                ],
                false
            ],
            'Invalid 7b: meaningless rules (whitespace only)' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(null)
                        ->setOpeningMessage(null)
                        ->setRules('           '),
                    'mockfetch' =>[
                        'numInvalid' => 123
                    ]
                ],
                false
            ],
            /* Partial configuration
             */
            'Partial 1a' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(1)
                        ->setOpeningMessage(null)
                        ->setRules(null),
                    'mockfetch' =>[
                        'numInvalid' => 0
                    ]
                ],
                false
            ],
            'Partial 1b' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(1)
                        ->setOpeningMessage(null)
                        ->setRules(null),
                    'mockfetch' =>[
                        'numInvalid' => 123
                    ]
                ],
                false
            ],
            'Partial 2a' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(1)
                        ->setOpeningMessage('cat')
                        ->setRules(null),
                    'mockfetch' =>[
                        'numInvalid' => 0
                    ]
                ],
                false
            ],
            'Partial 2b' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(1)
                        ->setOpeningMessage('cat')
                        ->setRules(null),
                    'mockfetch' =>[
                        'numInvalid' => 123
                    ]
                ],
                false
            ],
            'Partial 3a' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(null)
                        ->setOpeningMessage('cat')
                        ->setRules('horse'),
                    'mockfetch' =>[
                        'numInvalid' => 0
                    ]
                ],
                false
            ],
            'Partial 3b' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(null)
                        ->setOpeningMessage('cat')
                        ->setRules('horse'),
                    'mockfetch' =>[
                        'numInvalid' => 123
                    ]
                ],
                false
            ],
            'Partial 4a' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(null)
                        ->setOpeningMessage(null)
                        ->setRules('horse'),
                    'mockfetch' =>[
                        'numInvalid' => 0
                    ]
                ],
                false
            ],
            'Partial 4b' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(null)
                        ->setOpeningMessage(null)
                        ->setRules('horse'),
                    'mockfetch' =>[
                        'numInvalid' => 123
                    ]
                ],
                false
            ],
            /* All scenario fields must have bodies
             */
            'Partial 5a' => [
                [
                    'model' => (new Model())
                        ->setScenarioId(117)
                        ->setOpeningMessage('cat')
                        ->setRules('horse'),
                    'mockfetch' =>[
                        'numInvalid' => 1
                    ]
                ],
                false
            ],
        ];
    }
}
