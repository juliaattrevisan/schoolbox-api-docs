<?php
/**
 * ValidationTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
    public function provider_validButtonRequirements(): array {

        return [
            'Valid 1' => [
                [
                    'title' => 'cat',
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
                    'title' => 'cat',
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
                    'title' => null,
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
                    'title' => '',
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
                    'title' => '',
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
                    'title' => 'cat',
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
                    'title' => 'cat',
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
                    'title' => 'cat',
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
                    'title' => 'cat',
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
                    'title' => 'cat',
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
                    'title' => '',
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
                    'title' => 'cat',
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
                    'title' => 'cat',
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
                    'title' => 'cat',
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
                    'title' => 'cat',
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
                    'title' => 'cat',
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
                    'title' => '',
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
     * @dataProvider provider_validButtonRequirements
     * @param int   $expected
     */
    public function testValidButtonRequirements(array $post, $expected): void
    {
        $validation = new ValidButton();

        $this->assertEquals($expected, $validation->isSatisfiedBy($post));
    }
}
