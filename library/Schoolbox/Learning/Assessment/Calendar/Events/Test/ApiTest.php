<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Calendar\Events\Test;

use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;
use Schoolbox\Filter\Filters\Assessment\IdFinder;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Calendar\Events\Api;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\User;

/**
 * Tests for the Assessment Calendar Events API.
 */
class ApiTest extends TestCase
{
    /**
     * Verifies that getEvents() filters out assessments not visible to the viewer.
     */
    public function testGetEventsChecksVisibilityForViewer(): void
    {
        $student = $this->buildUser(10);
        $viewer = $this->buildUser(20);
        $assessment = $this->createStub(IAssessable::class);
        $assessments = new GenericCollection(IAssessable::class, [$assessment]);
        $filters = [];

        $assessmentIdFinder = $this->createMock(IdFinder::class);
        $assessmentIdFinder
            ->expects($this->once())
            ->method('findIdsByFilters')
            ->with($filters, $student)
            ->willReturn([123]);

        $assessmentDataSource = $this->createMock(AssessmentDataSource::class);
        $assessmentDataSource
            ->expects($this->once())
            ->method('findAllBetweenDates')
            ->with(
                $this->isInstanceOf(Alaress_Date_Format::class),
                $this->isInstanceOf(Alaress_Date_Format::class),
                $student,
                [123]
            )
            ->willReturn($assessments);
        $assessmentDataSource
            ->expects($this->once())
            ->method('loadParticipantsIntoAssessmentCollection')
            ->with($assessments, $student, true, true)
            ->willReturn($assessments);

        $visibility = $this->createMock(Visibility::class);
        $visibility
            ->expects($this->once())
            ->method('isVisibleToUser')
            ->with($assessment, $this->identicalTo($viewer), true)
            ->willReturn(false);

        $api = new Api($student, $viewer, $assessmentDataSource, $visibility, $assessmentIdFinder, $filters, false);

        self::assertCount(0, $api->getEvents(100, 200));
    }

    /**
     * Verifies that getEvents() uses the configured visibility viewer rather than the default viewer
     * when one has been explicitly provided.
     */
    public function testGetEventsChecksVisibilityForConfiguredVisibilityViewer(): void
    {
        $student = $this->buildUser(10);
        $viewer = $this->buildUser(20);
        $assessment = $this->createStub(IAssessable::class);
        $assessments = new GenericCollection(IAssessable::class, [$assessment]);
        $filters = [];

        $assessmentIdFinder = $this->createMock(IdFinder::class);
        $assessmentIdFinder
            ->expects($this->once())
            ->method('findIdsByFilters')
            ->with($filters, $student)
            ->willReturn([123]);

        $assessmentDataSource = $this->createMock(AssessmentDataSource::class);
        $assessmentDataSource
            ->expects($this->once())
            ->method('findAllBetweenDates')
            ->with(
                $this->isInstanceOf(Alaress_Date_Format::class),
                $this->isInstanceOf(Alaress_Date_Format::class),
                $student,
                [123]
            )
            ->willReturn($assessments);
        $assessmentDataSource
            ->expects($this->once())
            ->method('loadParticipantsIntoAssessmentCollection')
            ->with($assessments, $student, true, true)
            ->willReturn($assessments);

        $visibility = $this->createMock(Visibility::class);
        $visibility
            ->expects($this->once())
            ->method('isVisibleToUser')
            ->with($assessment, $this->identicalTo($student), true)
            ->willReturn(false);

        $api = new Api($student, $viewer, $assessmentDataSource, $visibility, $assessmentIdFinder, $filters, false, $student);

        self::assertCount(0, $api->getEvents(100, 200));
    }

    /**
     * Verifies that getEvent() loads student participation data before checking visibility for the viewer.
     */
    public function testGetEventLoadsStudentParticipationAndChecksVisibilityForViewer(): void
    {
        $student = $this->buildUser(10);
        $viewer = $this->buildUser(20);
        $assessment = $this->createStub(IAssessable::class);

        $assessmentDataSource = $this->createMock(AssessmentDataSource::class);
        $assessmentDataSource
            ->expects($this->once())
            ->method('findById')
            ->with(123)
            ->willReturn($assessment);
        $assessmentDataSource
            ->expects($this->once())
            ->method('loadParticipantsIntoAssessmentCollection')
            ->with(
                $this->callback(
                    /**
                     * Confirms the data source receives a collection containing only the requested assessment.
                     */
                    static function (GenericCollection $assessments) use ($assessment): bool {
                        return $assessments->count() === 1 && $assessments->first() === $assessment;
                    }
                ),
                $student,
                true,
                true
            )
            ->willReturnArgument(0);

        $visibility = $this->createMock(Visibility::class);
        $visibility
            ->expects($this->once())
            ->method('isVisibleToUser')
            ->with($assessment, $this->identicalTo($viewer), true)
            ->willReturn(false);

        $api = new Api(
            $student,
            $viewer,
            $assessmentDataSource,
            $visibility,
            $this->createStub(IdFinder::class),
            [],
            false
        );

        self::assertCount(0, $api->getEvent(123));
    }

    /**
     * Verifies that getEvent() uses the configured visibility viewer rather than the default viewer
     * when one has been explicitly provided.
     */
    public function testGetEventChecksVisibilityForConfiguredVisibilityViewer(): void
    {
        $student = $this->buildUser(10);
        $viewer = $this->buildUser(20);
        $assessment = $this->createStub(IAssessable::class);

        $assessmentDataSource = $this->createMock(AssessmentDataSource::class);
        $assessmentDataSource
            ->expects($this->once())
            ->method('findById')
            ->with(123)
            ->willReturn($assessment);
        $assessmentDataSource
            ->expects($this->once())
            ->method('loadParticipantsIntoAssessmentCollection')
            ->with(
                new GenericCollection(IAssessable::class, [$assessment]),
                $student,
                true,
                true
            )
            ->willReturnArgument(0);

        $visibility = $this->createMock(Visibility::class);
        $visibility
            ->expects($this->once())
            ->method('isVisibleToUser')
            ->with($assessment, $this->identicalTo($student), true)
            ->willReturn(false);

        $api = new Api(
            $student,
            $viewer,
            $assessmentDataSource,
            $visibility,
            $this->createStub(IdFinder::class),
            [],
            false,
            $student
        );

        self::assertCount(0, $api->getEvent(123));
    }

    /**
     * Creates a User instance with the given ID for use in tests.
     */
    private function buildUser(int $id): User
    {
        return (new User())->setId($id);
    }
}
