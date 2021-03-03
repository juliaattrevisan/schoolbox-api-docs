<?php
/**
 * CodeCodecTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Timetable\ValidEmployeeExternalId;


/**
 * Class ValidEmployeeExternalIdTest
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Test
 */
class ValidEmployeeExternalIdTest extends TestCase
{

    /**
     * Data provider for testValidEmployeeExternalId
     *
     * @return array
     */
    public function provider_validEmployeeExternalId(): array
    {
        return [
            'Employee 1' => [
                'E1000145',
                true
            ],

            'Employee 2' => [
                'EA10293',
                true
            ],

            'Employee 3' => [
                'D99001',
                false
            ],

            'Employee 4' => [
                '',
                false
            ],
        ];
    }


    /**
     * Test valid employee prefix
     *
     * @dataProvider provider_validEmployeeExternalId
     * @param string $input
     * @param bool $expected
     */
    public function testValidEmployeeExternalId(string $input, bool $expected): void
    {
        $check = new ValidEmployeeExternalId();

        $this->assertEquals($expected, $check->isSatisfiedBy($input));
    }
}

