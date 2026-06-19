<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Event\Test;

use Alaress\Web\Request\Values;
use Alaress_Date_Locale_Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\Event\Validator;

#[\PHPUnit\Framework\Attributes\CoversClass(Validator::class)]
class ValidatorTest extends TestCase
{
    #[DataProvider('invalidMaxAttendeesProvider')]
    public function testValidateForPostModifyRejectsInvalidMaxAttendees(
        mixed $maxAttendees,
        string $expectedMessage
    ): void {
        $validator = new Validator(new Alaress_Date_Locale_Config());

        $this->expectException(SchoolboxException::class);
        $this->expectExceptionMessage($expectedMessage);

        $validator->validateForPostModify(
            new Event(),
            new Values(['maxAttendees' => $maxAttendees])
        );
    }

    public static function invalidMaxAttendeesProvider(): array
    {
        return [
            'negative string' => ['-1', "Value for key 'maxAttendees' should be an integer."],
            'negative integer' => [-1, "Value for key 'maxAttendees' should be a positive integer."],
            'zero string' => ['0', "Value for key 'maxAttendees' should be a positive integer."],
            'zero integer' => [0, "Value for key 'maxAttendees' should be a positive integer."],
        ];
    }
}
