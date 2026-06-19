<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIUser\Test;

use Alaress\Web\Request\Values as WebRequestValues;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\APIUser\Validator;
use Schoolbox\Application\Exception\Exception as SchoolboxException;

class ValidatorTest extends TestCase
{
    #[DataProvider('nonPositiveIdProvider')]
    public function testValidateForApiUserSearchRejectsNonPositiveLimits(int|string $limit): void
    {
        $this->expectException(SchoolboxException::class);
        $this->expectExceptionMessage("Parameter 'limit' should have a positive integer or positive integer string value.");

        (new Validator())->validateForApiUserSearch(new WebRequestValues([
            'limit' => $limit,
        ]));
    }

    #[DataProvider('nonPositiveIdProvider')]
    public function testValidateForApiUserSearchRejectsNonPositiveCursors(int|string $cursor): void
    {
        $this->expectException(SchoolboxException::class);
        $this->expectExceptionMessage("Parameter 'cursor' should have a positive integer or positive integer string value.");

        (new Validator())->validateForApiUserSearch(new WebRequestValues([
            'cursor' => $cursor,
        ]));
    }

    #[DataProvider('nonPositiveIdProvider')]
    public function testValidateForApiUserSearchRejectsNonPositiveCampusIds(int|string $id): void
    {
        $this->expectException(SchoolboxException::class);
        $this->expectExceptionMessage("Filter 'campus' property 'id' must have a positive integer value.");

        (new Validator())->validateForApiUserSearch(new WebRequestValues([
            'filter' => [
                'campus' => ['id' => $id],
            ],
        ]));
    }

    public static function nonPositiveIdProvider(): array
    {
        return [
            'negative integer' => [-1],
            'zero integer' => [0],
            'zero string' => ['0'],
        ];
    }
}
