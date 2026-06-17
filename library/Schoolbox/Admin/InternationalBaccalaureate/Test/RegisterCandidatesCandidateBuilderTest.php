<?php

/**
 * IBIS register candidates candidate builder test
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate\Test;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\InternationalBaccalaureate\RegisterCandidatesCandidateBuilder;
use Schoolbox\GenericCollection;
use Schoolbox\IB\Registrations\Sessions\Student\Model as RegistrationSessionStudentModel;
use Schoolbox\Language\Model as LanguageModel;
use Schoolbox\Nationality\Model as NationalityModel;
use Schoolbox\User;

class RegisterCandidatesCandidateBuilderTest extends TestCase
{
    public function testBuildsIbisCandidateWithNormalisedValues(): void
    {
        $student = (new RegistrationSessionStudentModel())
            ->setUser(
                (new User())
                    ->setId(123)
                    ->setFirstname('Test')
                    ->setLastname('User')
                    ->setLocalEmail(false)
                    ->setAltEmail('test.user@example.edu')
                    ->setLanguageCollection(
                        new GenericCollection(LanguageModel::class, [
                            (new LanguageModel())->setCode('ENG'),
                            (new LanguageModel())->setCode('FRE'),
                        ])
                    )
                    ->setNationalityCollection(
                        new GenericCollection(NationalityModel::class, [
                            (new NationalityModel())->setCode('AUS'),
                            (new NationalityModel())->setCode('GBR'),
                        ])
                    )
            );

        $candidate = (new RegisterCandidatesCandidateBuilder())->build(
            $student,
            [
                'StudentBirthDate' => strtotime('2009-04-12'),
                'StudentGender' => ' f ',
            ]
        );

        $this->assertSame(
            [
                'date_of_birth' => '12-APR-2009',
                'firstname' => 'Test',
                'lastname' => 'User',
                'email' => 'test.user@example.edu',
                'gender' => 'F',
                'language1' => 'ENG',
                'language2' => 'FRE',
                'nationality1' => 'AUS',
                'nationality2' => 'GBR',
                'reference_code' => '000123',
            ],
            $candidate
        );
    }

    public function testBuildRequiresGender(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Selected candidate is missing required IBIS data: gender.');

        (new RegisterCandidatesCandidateBuilder())->build(
            (new RegistrationSessionStudentModel())
                ->setUser(
                    (new User())
                        ->setId(123)
                        ->setFirstname('Test')
                        ->setLastname('User')
                        ->setLocalEmail(false)
                        ->setAltEmail('test.user@example.edu')
                        ->setLanguageCollection(
                            new GenericCollection(LanguageModel::class, [
                                (new LanguageModel())->setCode('ENG'),
                            ])
                        )
                        ->setNationalityCollection(
                            new GenericCollection(NationalityModel::class, [
                                (new NationalityModel())->setCode('AUS'),
                            ])
                        )
                ),
            [
                'StudentBirthDate' => strtotime('2009-04-12'),
                'StudentGender' => null,
            ]
        );
    }

    public function testBuildReportsAllMissingRequiredFields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Selected candidate is missing required IBIS data: email, gender, language1, nationality1.'
        );

        (new RegisterCandidatesCandidateBuilder())->build(
            (new RegistrationSessionStudentModel())
                ->setUser(
                    (new User())
                        ->setId(123)
                        ->setFirstname('Test')
                        ->setLastname('User')
                        ->setLocalEmail(false)
                        ->setAltEmail('')
                ),
            [
                'StudentBirthDate' => strtotime('2009-04-12'),
                'StudentGender' => null,
            ]
        );
    }
}
