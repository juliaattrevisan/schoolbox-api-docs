<?php

/**
 * IB registration session student CSV export test
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Sessions\Student\Export\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Schoolbox\GenericCollection;
use Schoolbox\IB\Registrations\Sessions\Student\Export\Csv;
use Schoolbox\IB\Registrations\Sessions\Student\Model as RegistrationSessionStudentModel;
use Schoolbox\Language\Model as LanguageModel;
use Schoolbox\Nationality\Model as NationalityModel;
use Schoolbox\User;

class CsvTest extends TestCase
{
    public function testHeadingsMatchIbisTemplate(): void
    {
        $this->assertSame(
            [
                'firstName' => 'First Name',
                'lastName' => 'Last Name',
                'dateOfBirth' => 'Date of Birth',
                'gender' => 'Gender',
                'nationality1' => '1st Nationality',
                'language1' => '1st Language',
            ],
            (new Csv())->headings()
        );
    }

    public function testBuildRowUsesNamesForNationalityAndLanguage(): void
    {
        $student = (new RegistrationSessionStudentModel())
            ->setUser(
                (new User())
                    ->setFirstname(' Legal ')
                    ->setPreferredName(' Alex ')
                    ->setLastname(' Nguyen ')
                    ->setLanguageCollection(
                        new GenericCollection(LanguageModel::class, [
                            (new LanguageModel())->setCode('ENG')->setName(' English '),
                        ])
                    )
                    ->setNationalityCollection(
                        new GenericCollection(NationalityModel::class, [
                            (new NationalityModel())->setCode('AUS')->setName(' Australian '),
                        ])
                    )
            );

        $row = (new Csv())->buildRow(
            $student,
            [
                'StudentBirthDate' => '2009-04-12T00:00:00',
                'StudentGender' => ' F ',
            ]
        );

        $this->assertSame(
            [
                'firstName' => 'Alex',
                'lastName' => 'Nguyen',
                'dateOfBirth' => '2009-04-12',
                'gender' => 'F',
                'nationality1' => 'Australian',
                'language1' => 'English',
            ],
            $row
        );
    }

    public function testBuildRowUsesEmptyStringsForMissingValues(): void
    {
        $this->assertSame(
            [
                'firstName' => '',
                'lastName' => '',
                'dateOfBirth' => '',
                'gender' => '',
                'nationality1' => '',
                'language1' => '',
            ],
            (new Csv())->buildRow(new RegistrationSessionStudentModel(), [])
        );
    }

    #[DataProvider('invalidDateOfBirthProvider')]
    public function testBuildRowUsesEmptyStringForInvalidDateOfBirth(mixed $dateOfBirth): void
    {
        $row = (new Csv())->buildRow(
            new RegistrationSessionStudentModel(),
            ['StudentBirthDate' => $dateOfBirth]
        );

        $this->assertSame('', $row['dateOfBirth']);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidDateOfBirthProvider(): array
    {
        return [
            'null' => [null],
            'false' => [false],
            'empty string' => [''],
            'blank string' => ['   '],
            'non-date string' => ['not-a-date'],
            'calendar-invalid date string' => ['2024-02-30'],
            'zero timestamp' => [0],
            'negative timestamp' => [-1],
        ];
    }
}
