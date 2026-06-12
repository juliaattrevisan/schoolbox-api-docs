<?php

/**
 * IB registration session student helper test
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Sessions\Student\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\IB\Registrations\Sessions\Student\Helper;
use Schoolbox\IB\Registrations\Sessions\Student\Model as RegistrationSessionStudentModel;

class HelperTest extends TestCase
{
    public function testGroupsStudentsByStatus(): void
    {
        $students = [
            ['userId' => 123, 'status' => RegistrationSessionStudentModel::STATUS_PREPARATION],
            ['userId' => 456, 'status' => RegistrationSessionStudentModel::STATUS_CONFIRMED],
            ['userId' => 789, 'status' => RegistrationSessionStudentModel::STATUS_PREPARATION],
        ];

        $groups = (new Helper())->groupByStatus($students);

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
        $this->assertEquals((object) [], (new Helper())->groupByStatus([]));
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
            (new Helper())->getStatuses()
        );
    }
}
