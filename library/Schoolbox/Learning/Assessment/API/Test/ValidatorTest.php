<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\API\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Learning\Assessment\API\Validator;

class ValidatorTest extends TestCase
{
    #[DataProvider('nonPositiveIdFilterProvider')]
    public function testValidateFilterRejectsNonPositiveIds(array $filter, string $expectedMessage): void
    {
        $this->expectException(SchoolboxException::class);
        $this->expectExceptionMessage($expectedMessage);

        (new Validator())->validateFilter($filter);
    }

    public static function nonPositiveIdFilterProvider(): array
    {
        return [
            'assessmentId negative integer' => [
                ['assessmentId' => -1],
                "Filter 'assessmentId' must have a positive integer value.",
            ],
            'assessmentId zero string' => [
                ['assessmentId' => '0'],
                "Filter 'assessmentId' must have a positive integer value.",
            ],
            'folder id zero integer' => [
                ['folder' => ['id' => 0]],
                "Filter 'folder' property 'id' must have a positive integer value.",
            ],
            'folder item id negative integer' => [
                ['folder' => [['id' => -1]]],
                "Filter 'folder' item property 'id' must have a positive integer value.",
            ],
            'workType id negative integer' => [
                ['workType' => ['id' => -1]],
                "Filter 'workType' property 'id' must have a positive integer value.",
            ],
            'yearLevel id zero string' => [
                ['yearLevel' => ['id' => '0']],
                "Filter 'yearLevel' property 'id' must have a positive integer value.",
            ],
        ];
    }
}
