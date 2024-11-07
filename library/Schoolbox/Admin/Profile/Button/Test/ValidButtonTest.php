<?php

/**
 * ValidButtonTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Profile\Button\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\Profile\Button\ValidButton;

class ValidButtonTest extends TestCase
{
    /**
     * Data provider for testValidButtonRequirements
     *
     * @return array
     */
    public static function providerValidButtonRequirements(): array
    {
        return [
            'Valid Custom 1' => [
                'custom',
                [
                    'text' => 'cat',
                    'url' => 'http://www.theage.com.au',
                    'campusAvailability' => [2,3,1],
                    'roleAvailability' => [1,7,5,6,4,8,3,2],
                    'campusVisibility' => [2,3,1],
                    'roleVisibility' => [1,7,5,6,4,8,3,2]
                ],
                true
            ],
            'Valid Custom 2' => [
                'custom',
                [
                    'text' => 'cat',
                    'url' => 'http://www.theage.com.au',
                    'campusAvailability' => [2],
                    'roleAvailability' => [1],
                    'campusVisibility' => [2],
                    'roleVisibility' => [1]
                ],
                true
            ],
            /* System Buttons have system-generated urls; they are not user-defined.
             * Urls from frontend are ignored entirely and weren't mandatory.
             * Urls are ignored in backend too, and will not update a model
             */
            'Valid System 1' => [
                'duework',
                [
                    'text' => 'cat',
                    'url' => '',
                    'campusAvailability' => [2],
                    'roleAvailability' => [1],
                    'campusVisibility' => [2],
                    'roleVisibility' => [1]
                ],
                true
            ],
            'Valid System 2' => [
                'duework',
                [
                    'text' => 'cat',
                    'url' => 'https://www.schoolbox.com.au',
                    'campusAvailability' => [2],
                    'roleAvailability' => [1],
                    'campusVisibility' => [2],
                    'roleVisibility' => [1]
                ],
                true
            ],
            'Invalid: missing text and url 1' => [
                'custom',
                [
                    'text' => null,
                    'url' => null,
                    'campusAvailability' => null,
                    'roleAvailability' => null,
                    'campusVisibility' => null,
                    'roleVisibility' => null
                ],
                false
            ],
            'Invalid: missing text and url 2' => [
                'custom',
                [
                    'text' => '',
                    'url' => '',
                    'campusAvailability' => '',
                    'roleAvailability' => '',
                    'campusVisibility' => '',
                    'roleVisibility' => ''
                ],
                false
            ],
            'Invalid: missing text and url 3' => [
                'custom',
                [
                    'text' => '',
                    'url' => '',
                    'campusAvailability' => [],
                    'roleAvailability' => [],
                    'campusVisibility' => [],
                    'roleVisibility' => []
                ],
                false
            ],
            'Invalid: missing text and url 4' => [
                'custom',
                [
                    'text' => '',
                    'url' => '',
                    'campusAvailability' => [1],
                    'roleAvailability' => [2],
                    'campusVisibility' => [3],
                    'roleVisibility' => [4]
                ],
                false
            ],
            'Invalid: missing url 1' => [
                'custom',
                [
                    'text' => 'cat',
                    'url' => '',
                    'campusAvailability' => [],
                    'roleAvailability' => [],
                    'campusVisibility' => [],
                    'roleVisibility' => []
                ],
                false
            ],
            'Invalid: missing url 2' => [
                'custom',
                [
                    'text' => 'cat',
                    'url' => '',
                    'campusAvailability' => [1],
                    'roleAvailability' => [2],
                    'campusVisibility' => [3],
                    'roleVisibility' => [4]
                ],
                false
            ],
            'Invalid: missing text 1' => [
                'custom',
                [
                    'text' => '',
                    'url' => 'http://www.theage.com.au',
                    'campusAvailability' => [1],
                    'roleAvailability' => [2],
                    'campusVisibility' => [3],
                    'roleVisibility' => [4]
                ],
                false
            ],
            'Valid: availability/visibility optional 1' => [
                'custom',
                [
                    'text' => 'cat',
                    'url' => 'http://www.theage.com.au',
                    'campusAvailability' => [],
                    'roleAvailability' => [],
                    'campusVisibility' => [],
                    'roleVisibility' => []
                ],
                true
            ],
            'Valid: availability/visibility optional 2' => [
                'custom',
                [
                    'text' => 'cat',
                    'url' => 'http://www.theage.com.au',
                    'campusAvailability' => [1],
                    'roleAvailability' => [],
                    'campusVisibility' => [],
                    'roleVisibility' => []
                ],
                true
            ],
            'Valid: availability/visibility optional 3' => [
                'custom',
                [
                    'text' => 'cat',
                    'url' => 'http://www.theage.com.au',
                    'campusAvailability' => [1],
                    'roleAvailability' => [2],
                    'campusVisibility' => [],
                    'roleVisibility' => []
                ],
                true
            ],
            'Valid: availability/visibility optional 4' => [
                'custom',
                [
                    'text' => 'cat',
                    'url' => 'http://www.theage.com.au',
                    'campusAvailability' => [1],
                    'roleAvailability' => [2],
                    'campusVisibility' => [3],
                    'roleVisibility' => []
                ],
                true
            ],

            'Valid: availability/visibility optional 5' => [
                'custom',
                [
                    'text' => 'cat',
                    'url' => 'http://www.theage.com.au',
                    'campusAvailability' => [],
                    'roleAvailability' => [2],
                    'campusVisibility' => [3],
                    'roleVisibility' => [4]
                ],
                true
            ],
            'Valid: availability/visibility optional 6' => [
                'custom',
                [
                    'text' => 'cat',
                    'url' => 'http://www.theage.com.au',
                    'campusAvailability' => [],
                    'roleAvailability' => [],
                    'campusVisibility' => [3],
                    'roleVisibility' => [4]
                ],
                true
            ],
            'Valid: availability/visibility optional 7' => [
                'custom',
                [
                    'text' => 'cat',
                    'url' => 'http://www.theage.com.au',
                    'campusAvailability' => [],
                    'roleAvailability' => [],
                    'campusVisibility' => [],
                    'roleVisibility' => [4]
                ],
                true
            ],
            'Valid: availability/visibility optional 8' => [
                'custom',
                [
                    'text' => 'cat',
                    'url' => 'http://www.theage.com.au',
                    'campusAvailability' => [],
                    'roleAvailability' => [],
                    'campusVisibility' => [],
                    'roleVisibility' => [4]
                ],
                true
            ],
        ];
    }

    /**
     * @param string $type
     * @param array $post
     * @param int   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidButtonRequirements')]
    public function testValidButtonRequirements(string $type, array $post, $expected): void
    {
        $validation = new ValidButton();

        $this->assertEquals($expected, $validation->isSatisfiedBy($type, $post));
    }
}
