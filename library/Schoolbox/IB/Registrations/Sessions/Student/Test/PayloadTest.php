<?php

/**
 * IB registration session student payload test
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Sessions\Student\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\GenericCollection;
use Schoolbox\IB\Registrations\Sessions\Student\Model as RegistrationSessionStudentModel;
use Schoolbox\IB\Registrations\Sessions\Student\Payload;
use Schoolbox\Language\Model as LanguageModel;
use Schoolbox\Nationality\Model as NationalityModel;
use Schoolbox\User;

class PayloadTest extends TestCase
{
    public function testBuildsCompletePayload(): void
    {
        $payload = (new Payload())->build(
            $this->buildStudent(),
            [
                'StudentBirthDate' => '2009-04-12',
                'StudentGender' => 'F',
            ],
            ' Example School '
        );

        $this->assertSame(123, $payload['userId']);
        $this->assertSame(456, $payload['registrationSessionStudentId']);
        $this->assertSame(RegistrationSessionStudentModel::STATUS_CONFIRMED, $payload['status']);
        $this->assertSame('Alex, Nguyen', $payload['userName']);
        $this->assertFalse($payload['hasMissingDetails']);
        $this->assertSame([], $payload['missingFields']);
        $this->assertArrayNotHasKey('firstName', $payload);
        $this->assertArrayNotHasKey('lastName', $payload);
        $this->assertArrayNotHasKey('dateOfBirth', $payload);
        $this->assertArrayNotHasKey('email', $payload);
        $this->assertArrayNotHasKey('gender', $payload);
        $this->assertArrayNotHasKey('language1', $payload);
        $this->assertArrayNotHasKey('language2', $payload);
        $this->assertArrayNotHasKey('nationality1', $payload);
        $this->assertArrayNotHasKey('nationality2', $payload);
        $this->assertArrayNotHasKey('schoolName', $payload);
        $this->assertArrayNotHasKey('reference', $payload);
        $this->assertArrayNotHasKey('isComplete', $payload);
    }

    public function testBuildsMissingFieldsForIncompletePayload(): void
    {
        $student = (new RegistrationSessionStudentModel())
            ->setUser(
                (new User())
                    ->setId(123)
                    ->setFirstname('')
                    ->setLastname('Nguyen')
                    ->setLocalEmail(false)
                    ->setAltEmail('')
                    ->setLanguageCollection(LanguageModel::createCollection())
                    ->setNationalityCollection(NationalityModel::createCollection())
            );

        $payload = (new Payload())->build($student, [], '');

        $this->assertSame('', $payload['userName']);
        $this->assertTrue($payload['hasMissingDetails']);
        $this->assertSame(
            [
                'userName',
                'firstName',
                'dateOfBirth',
                'email',
                'gender',
                'language1',
                'nationality1',
                'schoolName',
            ],
            $payload['missingFields']
        );
    }

    public function testGroupsStudentsByStatus(): void
    {
        $students = [
            ['userId' => 123, 'status' => RegistrationSessionStudentModel::STATUS_PREPARATION],
            ['userId' => 456, 'status' => RegistrationSessionStudentModel::STATUS_CONFIRMED],
            ['userId' => 789, 'status' => RegistrationSessionStudentModel::STATUS_PREPARATION],
        ];

        $groups = (new Payload())->groupByStatus($students);

        $this->assertEquals(
            (object) [
                '0' => [
                    ['userId' => 123, 'status' => RegistrationSessionStudentModel::STATUS_PREPARATION],
                    ['userId' => 789, 'status' => RegistrationSessionStudentModel::STATUS_PREPARATION],
                ],
                '2' => [
                    ['userId' => 456, 'status' => RegistrationSessionStudentModel::STATUS_CONFIRMED],
                ],
            ],
            $groups
        );
    }

    public function testGroupsEmptyStudentsAsEmptyObject(): void
    {
        $this->assertEquals((object) [], (new Payload())->groupByStatus([]));
    }

    public function testReturnsRegistrationStatuses(): void
    {
        $this->assertSame(
            [
                [
                    'value' => RegistrationSessionStudentModel::STATUS_PREPARATION,
                    'key' => 'preparation',
                    'label' => 'Preparation',
                ],
                [
                    'value' => RegistrationSessionStudentModel::STATUS_UNCONFIRMED,
                    'key' => 'unconfirmed',
                    'label' => 'Unconfirmed',
                ],
                [
                    'value' => RegistrationSessionStudentModel::STATUS_CONFIRMED,
                    'key' => 'confirmed',
                    'label' => 'Confirmed',
                ],
                [
                    'value' => RegistrationSessionStudentModel::STATUS_IN_PROGRESS,
                    'key' => 'inProgress',
                    'label' => 'In Progress',
                ],
                [
                    'value' => RegistrationSessionStudentModel::STATUS_FAILED,
                    'key' => 'failed',
                    'label' => 'Failed',
                ],
                [
                    'value' => RegistrationSessionStudentModel::STATUS_REGISTERED,
                    'key' => 'registered',
                    'label' => 'Registered',
                ],
            ],
            (new Payload())->getStatuses()
        );
    }

    private function buildStudent(): RegistrationSessionStudentModel
    {
        return (new RegistrationSessionStudentModel())
            ->setId(456)
            ->setStatus(RegistrationSessionStudentModel::STATUS_CONFIRMED)
            ->setUser(
                (new User())
                    ->setId(123)
                    ->setFirstname('Alex')
                    ->setLastname('Nguyen')
                    ->setLocalEmail(false)
                    ->setAltEmail('alex.nguyen@example.edu')
                    ->setLanguageCollection(
                        new GenericCollection(LanguageModel::class, [
                            (new LanguageModel())->setCode('ENG')->setName('English'),
                            (new LanguageModel())->setCode('FRA')->setName('French'),
                        ])
                    )
                    ->setNationalityCollection(
                        new GenericCollection(NationalityModel::class, [
                            (new NationalityModel())->setCode('AUS')->setName('Australian'),
                            (new NationalityModel())->setCode('NZL')->setName('New Zealander'),
                        ])
                    )
            );
    }
}
