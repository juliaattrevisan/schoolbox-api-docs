<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\File\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\File\Mimetype;

/**
 * Test for mimetype methods.
 * @covers \Schoolbox\File\Mimetype
 */
class MimetypeTest extends TestCase
{
    /**
     * @dataProvider getTestMatchesData
     * @covers       \Schoolbox\File\Mimetype::matches
     * @param Mimetype    $mimetype
     * @param string|null $filter
     * @param bool        $expected
     * @return void
     */
    public function testMatches(Mimetype $mimetype, ?string $filter, bool $expected): void
    {
        $this->assertSame($expected, $mimetype->matches($filter));
    }

    /**
     * Data provider for `testMatches`
     *
     * @return iterable
     */
    public static function getTestMatchesData(): iterable
    {
        $cases = [
            // Null filter: same as empty filter
            [
                'filter'    => null,
                'mimetypes' => [
                    ['mimetype' => null, 'expected' => true,],
                    ['mimetype' => '', 'expected' => true,],
                    ['mimetype' => 'image/jpeg', 'expected' => true,],
                    ['mimetype' => 'image/png', 'expected' => true,],
                    ['mimetype' => 'text/plain', 'expected' => true,],
                ],
            ],
            [
                'filter'    => '',
                'mimetypes' => [
                    ['mimetype' => null, 'expected' => true,],
                    ['mimetype' => '', 'expected' => true,],
                    ['mimetype' => 'image/jpeg', 'expected' => true,],
                    ['mimetype' => 'image/png', 'expected' => true,],
                    ['mimetype' => 'text/plain', 'expected' => true,],
                ],
            ],
            // Full wildcard filter: allows everything
            [
                'filter'    => '*/*',
                'mimetypes' => [
                    ['mimetype' => null, 'expected' => true,],
                    ['mimetype' => '', 'expected' => true,],
                    ['mimetype' => 'image/jpeg', 'expected' => true,],
                    ['mimetype' => 'image/png', 'expected' => true,],
                    ['mimetype' => 'text/plain', 'expected' => true,],
                ],
            ],
            // Single mimetype filters
            [
                'filter'    => 'image/jpeg',
                'mimetypes' => [
                    ['mimetype' => null, 'expected' => false,],
                    ['mimetype' => '', 'expected' => false,],
                    ['mimetype' => 'image/jpeg', 'expected' => true,],
                    ['mimetype' => 'image/png', 'expected' => false,],
                    ['mimetype' => 'text/plain', 'expected' => false,],
                ],
            ],
            [
                'filter'    => 'text/plain',
                'mimetypes' => [
                    ['mimetype' => null, 'expected' => false,],
                    ['mimetype' => '', 'expected' => false,],
                    ['mimetype' => 'image/jpeg', 'expected' => false,],
                    ['mimetype' => 'image/png', 'expected' => false,],
                    ['mimetype' => 'text/plain', 'expected' => true,],
                ],
            ],
            // Wildcard subtype filter:
            // should this match any mimetype with the base type?
            // currently it does not
            [
                'filter'    => 'image/*',
                'mimetypes' => [
                    ['mimetype' => null, 'expected' => false,],
                    ['mimetype' => '', 'expected' => false,],
                    ['mimetype' => 'image/jpeg', 'expected' => true,],
                    ['mimetype' => 'image/png', 'expected' => true,],
                    ['mimetype' => 'text/plain', 'expected' => false,],
                ],
            ],
            // Comma-separated filter
            [
                'filter'    => 'image/jpeg,image/png',
                'mimetypes' => [
                    ['mimetype' => null, 'expected' => false,],
                    ['mimetype' => '', 'expected' => false,],
                    ['mimetype' => 'image/jpeg', 'expected' => true,],
                    ['mimetype' => 'image/png', 'expected' => true,],
                    ['mimetype' => 'text/plain', 'expected' => false,],
                ],
            ],
            [
                'filter'    => 'image/jpeg,text/plain',
                'mimetypes' => [
                    ['mimetype' => null, 'expected' => false,],
                    ['mimetype' => '', 'expected' => false,],
                    ['mimetype' => 'image/jpeg', 'expected' => true,],
                    ['mimetype' => 'image/png', 'expected' => false,],
                    ['mimetype' => 'text/plain', 'expected' => true,],
                ],
            ],
            // SVG mimetype: maybe this is special somehow
            [
                'filter'    => 'image/svg+xml',
                'mimetypes' => [
                    ['mimetype' => null, 'expected' => false,],
                    ['mimetype' => '', 'expected' => false,],
                    ['mimetype' => 'image/jpeg', 'expected' => false,],
                    ['mimetype' => 'image/png', 'expected' => false,],
                    ['mimetype' => 'text/plain', 'expected' => false,],
                    ['mimetype' => 'image/svg+xml', 'expected' => true,],
                ],
            ],
        ];
        foreach ($cases as $case) {
            foreach ($case['mimetypes'] as $mimetypeData) {
                $key = sprintf(
                    'filter: %s, mimetype: %s',
                    isset($case['filter']) ? ('"' . $case['filter'] . '"') : 'null',
                    isset($mimetypeData['mimetype']) ? ('"' . $mimetypeData['mimetype'] . '"') : 'null',
                );
                $mimetype = new Mimetype();
                if (isset($mimetypeData['mimetype'])) {
                    $mimetype = $mimetype->setMime($mimetypeData['mimetype']);
                }
                yield $key => [$mimetype, $case['filter'], $mimetypeData['expected']];
            }
        }
    }
}
