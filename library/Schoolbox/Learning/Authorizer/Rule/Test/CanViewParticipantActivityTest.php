<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Authorizer\Rule\Test;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Authorizer\Rule\CanViewParticipantActivity;
use Schoolbox\User;

/**
 * Tests authorization of participant activities through summary routes.
 */
class CanViewParticipantActivityTest extends TestCase
{
    /**
     * Ensures the rule delegates the exact route viewer and viewee to participant-aware visibility.
     *
     * @return void
     */
    public function testAuthorizesVisibleParticipantActivity(): void
    {
        $assessment = (new DueWork\Model())->setId(1);
        $viewer     = (new User())->setId(2);
        $viewee     = (new User())->setId(3);
        $now        = new Alaress_Date_Format('2026-08-05 10:00:00', Alaress_Date_Format::DATABASE_FORMAT);

        $visibility = $this->createMock(Visibility::class);
        $visibility
            ->expects(self::once())
            ->method('isVisibleToParticipantActivity')
            ->with($assessment, $viewer, $viewee, false, $now)
            ->willReturn(true);

        $rule = new CanViewParticipantActivity($visibility, false, $now);

        $rule->authorize(
            [
                'assessment' => $assessment,
                'student' => $viewee,
                'studentId' => $viewee->getId(),
            ],
            $viewer
        );
    }

    /**
     * Ensures denied participant-aware visibility rejects the summary route.
     *
     * @return void
     */
    public function testRejectsHiddenParticipantActivity(): void
    {
        $assessment = (new DueWork\Model())->setId(1);
        $viewer     = (new User())->setId(2);
        $viewee     = (new User())->setId(3);

        $visibility = $this->createMock(Visibility::class);
        $visibility
            ->expects(self::once())
            ->method('isVisibleToParticipantActivity')
            ->with($assessment, $viewer, $viewee, true, null)
            ->willReturn(false);

        $rule = new CanViewParticipantActivity($visibility);

        $this->expectException(Unauthorized::class);

        $rule->authorize(
            [
                'assessment' => $assessment,
                'student' => $viewee,
                'studentId' => $viewee->getId(),
            ],
            $viewer
        );
    }

    /**
     * Ensures a missing logged-in viewer fails before visibility is evaluated.
     *
     * @return void
     */
    public function testRejectsMissingViewer(): void
    {
        $assessment = (new DueWork\Model())->setId(1);
        $viewee     = (new User())->setId(3);

        $visibility = $this->createMock(Visibility::class);
        $visibility
            ->expects(self::never())
            ->method('isVisibleToParticipantActivity');

        $rule = new CanViewParticipantActivity($visibility);

        $this->expectException(Unauthorized::class);
        $this->expectExceptionMessage('A user is required to perform this auth check');

        $rule->authorize(
            [
                'assessment' => $assessment,
                'student' => $viewee,
                'studentId' => $viewee->getId(),
            ]
        );
    }

    /**
     * Ensures another student's route cannot inherit visibility from the logged-in participant.
     *
     * @return void
     */
    public function testRejectsAnotherStudentRoute(): void
    {
        $assessment     = (new DueWork\Model())->setId(1);
        $viewer         = (new User())->setId(2);
        $anotherStudent = (new User())->setId(4);

        $visibility = $this->createMock(Visibility::class);
        $visibility
            ->expects(self::once())
            ->method('isVisibleToParticipantActivity')
            ->with($assessment, $viewer, $anotherStudent, true, null)
            ->willReturn(false);

        $rule = new CanViewParticipantActivity($visibility);

        $this->expectException(Unauthorized::class);

        $rule->authorize(
            [
                'assessment' => $assessment,
                'student' => $anotherStudent,
                'studentId' => $anotherStudent->getId(),
            ],
            $viewer
        );
    }
}
