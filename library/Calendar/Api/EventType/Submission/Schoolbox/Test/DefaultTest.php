<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Calendar\Api\EventType\Submission\Schoolbox\Test;

use Calendar_Api_Config_Record;
use Calendar_Api_EventType_Submission_Schoolbox_Default;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Role;
use Schoolbox\User;

#[CoversClass(Calendar_Api_EventType_Submission_Schoolbox_Default::class)]
class DefaultTest extends TestCase
{
    public function testCalendarUsesViewerAndStudentForParticipantActivityVisibility(): void
    {
        $student = (new User())->setId(10)->setRole((new Role())->setType(Role::STUDENT));
        $viewer = (new User())->setId(20)->setRole((new Role())->setType(Role::PARENT));
        $assessment = $this->createStub(IAssessable::class);
        $assessments = new GenericCollection(IAssessable::class, [$assessment]);

        $assessmentDataSource = $this->createMock(AssessmentDataSource::class);
        $assessmentDataSource
            ->expects($this->once())
            ->method('findAllByStudentBetweenDates')
            ->with(
                $this->identicalTo($student),
                true,
                $this->isInstanceOf(\Alaress_Date_Format::class),
                $this->isInstanceOf(\Alaress_Date_Format::class)
            )
            ->willReturn($assessments);

        $visibility = $this->createMock(Visibility::class);
        $visibility
            ->expects($this->once())
            ->method('isVisibleToParticipantActivity')
            ->with(
                $this->identicalTo($assessment),
                $this->identicalTo($viewer),
                $this->identicalTo($student),
                true
            )
            ->willReturn(false);

        $eventTypeMapper = $this->createMock(EventTypeMapper::class);
        $eventTypeMapper
            ->expects($this->once())
            ->method('findFirstMeaningfulId')
            ->with(1)
            ->willReturn(1);

        $source = new class () extends Calendar_Api_EventType_Submission_Schoolbox_Default {
            public function __construct()
            {
            }

            protected function loadConnectorConfiguration(): Calendar_Api_Config_Record
            {
                $record = new Calendar_Api_Config_Record();
                $record->id = 1;
                $record->eventType = 'Submission';
                $record->connectorClass = 'Schoolbox';
                $record->config = '{"luEventType":1}';

                return $record;
            }
        };
        $this->setProperty($source, Calendar_Api_EventType_Submission_Schoolbox_Default::class, 'assessmentDataSource', $assessmentDataSource);
        $this->setProperty($source, Calendar_Api_EventType_Submission_Schoolbox_Default::class, 'visibility', $visibility);
        $this->setProperty($source, Calendar_Api_EventType_Submission_Schoolbox_Default::class, 'eventTypeMapper', $eventTypeMapper);
        $this->setProperty($source, \Calendar_Api_EventType_Abstract::class, 'connectorId', 1);
        $this->setProperty($source, \Calendar_Api_EventType_Abstract::class, 'user', $student);
        $this->setProperty($source, \Calendar_Api_EventType_Abstract::class, 'viewer', $viewer);

        self::assertCount(0, $source->getEvents(100, 200));
    }

    private function setProperty(object $object, string $class, string $property, mixed $value): void
    {
        (new \ReflectionProperty($class, $property))->setValue($object, $value);
    }
}
