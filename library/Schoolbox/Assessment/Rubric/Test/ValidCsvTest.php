<?php

/**
 * ValidCsvTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@schoolbox.education>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\ValidCsv;

/**
 * ValidCsvTest
 */
class ValidCsvTest extends TestCase
{
    /**
     * @param mixed $input
     * @param int   $expected
     */
    #[DataProvider('providerValidCsv')]
    public function testValidCsv($input, $expected)
    {
        /* Set up a pseudo-resource as cheaply and as simply as I can devise
         */
        $stream = fopen('php://memory', 'w+');

        if ($stream) {
            fwrite($stream, $input);
            rewind($stream);
        }

        $outcome = (new ValidCsv())->isSatisfiedBy($stream);

        $this->assertEquals($expected, $outcome);

        fclose($stream);
    }

    /**
     * Data provider for testValidCsv
     *
     * @return array
     */
    public static function providerValidCsv(): array
    {
        return [
            'Empty input' => [
                '',
                false,
            ],
        ];
    }
}
