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
            'Invalid: missing all pieces 1' => [
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
            'Invalid: missing all pieces 2' => [
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
            'Invalid: missing all pieces 3' => [
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
            'Invalid: missing all pieces 4' => [
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
            'Invalid: missing all pieces 5' => [
                'custom',
                [
                    'text' => 'cat',
                    'url' => 'http://www.theage.com.au',
                    'campusAvailability' => [],
                    'roleAvailability' => [],
                    'campusVisibility' => [],
                    'roleVisibility' => []
                ],
                false
            ],
            'Invalid: missing all pieces 6' => [
                'custom',
                [
                    'text' => 'cat',
                    'url' => 'http://www.theage.com.au',
                    'campusAvailability' => [1],
                    'roleAvailability' => [],
                    'campusVisibility' => [],
                    'roleVisibility' => []
                ],
                false
            ],
            'Invalid: missing all pieces 7' => [
                'custom',
                [
                    'text' => 'cat',
                    'url' => 'http://www.theage.com.au',
                    'campusAvailability' => [1],
                    'roleAvailability' => [2],
                    'campusVisibility' => [],
                    'roleVisibility' => []
                ],
                false
            ],
            'Invalid: missing all pieces 8' => [
                'custom',
                [
                    'text' => 'cat',
                    'url' => 'http://www.theage.com.au',
                    'campusAvailability' => [1],
                    'roleAvailability' => [2],
                    'campusVisibility' => [3],
                    'roleVisibility' => []
                ],
                false
            ],
            'Invalid: missing all pieces 9' => [
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
            'Invalid: missing all pieces 10' => [
                'custom',
                [
                    'text' => 'cat',
                    'url' => 'http://www.theage.com.au',
                    'campusAvailability' => [],
                    'roleAvailability' => [2],
                    'campusVisibility' => [3],
                    'roleVisibility' => [4]
                ],
                false
            ],
            'Invalid: missing all pieces 11' => [
                'custom',
                [
                    'text' => 'cat',
                    'url' => 'http://www.theage.com.au',
                    'campusAvailability' => [],
                    'roleAvailability' => [],
                    'campusVisibility' => [3],
                    'roleVisibility' => [4]
                ],
                false
            ],
            'Invalid: missing all pieces 12' => [
                'custom',
                [
                    'text' => 'cat',
                    'url' => 'http://www.theage.com.au',
                    'campusAvailability' => [],
                    'roleAvailability' => [],
                    'campusVisibility' => [],
                    'roleVisibility' => [4]
                ],
                false
            ],
            'Invalid: missing all pieces 13' => [
                'custom',
                [
                    'text' => 'cat',
                    'url' => 'http://www.theage.com.au',
                    'campusAvailability' => [],
                    'roleAvailability' => [],
                    'campusVisibility' => [],
                    'roleVisibility' => [4]
                ],
                false
            ],
            'Invalid: missing all pieces 14' => [
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
            'Invalid: missing all pieces 15' => [
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
        ];
    }

    /**
     * @param int   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidButtonRequirements')]
    public function testValidButtonRequirements(array $post, $expected): void
    {
        $validation = new ValidButton();

        $this->assertEquals($expected, $validation->isSatisfiedBy($post));
    }
}
