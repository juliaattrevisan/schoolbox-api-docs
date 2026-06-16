<?php

/**
 * @author    Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Fixtures\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Socs\Fixtures\Lookup\Person;

#[\PHPUnit\Framework\Attributes\CoversClass(Person::class)]
class PersonTest extends TestCase
{
    public function testFetchUserReturnsUserIdFromGeneratedLookup(): void
    {
        $person = new class extends Person {
            public function __construct()
            {
            }

            public function generate(): array
            {
                return ['SOCS-123' => 456];
            }
        };

        $this->assertSame(456, $person->fetchUser('SOCS-123'));
        $this->assertNull($person->fetchUser('missing'));
    }
}
