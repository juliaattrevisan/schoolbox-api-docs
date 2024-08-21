<?php

/**
 * ValidationTest
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
    public static function provider_validButtonRequirements(): array
    {

        return [
            'Valid 1' => [
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
            'Valid 2' => [
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
            'Invalid: missing all pieces 1' => [
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
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validButtonRequirements')]
    public function testValidButtonRequirements(array $post, $expected): void
    {
        $validation = new ValidButton();

        $this->assertEquals($expected, $validation->isSatisfiedBy($post));
    }
}
