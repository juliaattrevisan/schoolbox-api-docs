<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Calendar\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Campus;
use Schoolbox\Socs\Calendar\CampusMatcher;

#[CoversClass(CampusMatcher::class)]
class CampusMatcherTest extends TestCase
{
    /**
     * @param string[] $schoolNames
     * @param int[]    $expectedCampusIds
     */
    #[DataProvider('campusNameProvider')]
    public function testMatchesSchoolsToCampusNamesExactly(array $schoolNames, array $expectedCampusIds): void
    {
        $matcher = new CampusMatcher();

        self::assertSame(
            $expectedCampusIds,
            $matcher->matchCampusIds(
                [
                    (new Campus())->setId(1)->setName('Junior Campus'),
                    (new Campus())->setId(2)->setName('Senior Campus'),
                ],
                $schoolNames,
            ),
        );
    }

    /**
     * @return array<string,array{schoolNames:string[],expectedCampusIds:int[]}>
     */
    public static function campusNameProvider(): array
    {
        return [
            'exact match' => [
                'schoolNames' => ['Senior Campus'],
                'expectedCampusIds' => [2],
            ],
            'case sensitive' => [
                'schoolNames' => ['senior campus'],
                'expectedCampusIds' => [],
            ],
            'missing campus' => [
                'schoolNames' => ['Missing Campus'],
                'expectedCampusIds' => [],
            ],
            'deduplicates matches' => [
                'schoolNames' => ['Senior Campus', 'Senior Campus'],
                'expectedCampusIds' => [2],
            ],
        ];
    }
}
