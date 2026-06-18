<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Calendar\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Schoolbox\Campus;
use Schoolbox\Socs\Calendar\CampusMatcher;

#[CoversClass(CampusMatcher::class)]
class CampusMatcherTest extends TestCase
{
    public function testMatchesSchoolsToCampusNamesExactly(): void
    {
        $matcher = new CampusMatcher();

        self::assertSame(
            [2],
            $matcher->matchCampusIds(
                [
                    (new Campus())->setId(1)->setName('Junior Campus'),
                    (new Campus())->setId(2)->setName('Senior Campus'),
                ],
                ['Senior Campus', 'senior campus', 'Missing Campus'],
            ),
        );
    }
}
