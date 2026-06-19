<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Event\Test;

use Alaress\Web\Request\Values as WebRequestValues;
use Alaress_Date_Locale_Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\Event\Validator;

class ValidatorTest extends TestCase
{
    #[DataProvider('nonPositiveIdProvider')]
    public function testValidateForPostModifyRejectsNonPositiveObjectStubIds(int|string $id): void
    {
        $this->expectException(SchoolboxException::class);
        $this->expectExceptionMessage("Value for 'type.id' should be a positive integer.");

        (new Validator(new Alaress_Date_Locale_Config()))->validateForPostModify(
            new Event(),
            new WebRequestValues(['type' => ['id' => $id]])
        );
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
