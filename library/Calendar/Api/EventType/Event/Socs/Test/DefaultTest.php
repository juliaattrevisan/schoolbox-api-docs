<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Calendar\Api\EventType\Event\Socs\Test;

use Calendar\Event\Type\Day\Event;
use Calendar_Api_EventType_Event_Socs_Default;
use Calendar_Api_EventType_Event_SocsMusic_Default;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Socs\Calendar\CampusMatcher;
use Schoolbox\Socs\Calendar\Parser;
use Schoolbox\Socs\HttpClient;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Sentry\State\HubInterface;

#[CoversClass(Calendar_Api_EventType_Event_Socs_Default::class)]
#[CoversClass(Calendar_Api_EventType_Event_SocsMusic_Default::class)]
#[CoversClass(Parser::class)]
#[CoversClass(CampusMatcher::class)]
class DefaultTest extends TestCase
{
    public function testSocsMusicLessonsUseTuitionEndpointAndOnlyShowForMatchedUsers(): void
    {
        $student = $this->createUser(91, 'STUDENT-9', 'Jill', 'Student');
        $staff = $this->createUser(71, 'STAFF-7', 'Sam', 'Staff');

        $eventTypeMapper = $this->createMock(EventTypeMapper::class);
        $eventTypeMapper->expects($this->once())->method('findFirstMeaningfulId')->willReturn(1);

        $userMapper = $this->createMock(UserMapper::class);
        $userMapper->expects($this->exactly(3))
            ->method('findByExternalId')
            ->willReturnMap([
                ['STAFF-7', \Schoolbox\Role::STAFF, $staff],
                ['STUDENT-9', \Schoolbox\Role::STUDENT, $student],
                ['STUDENT-10', \Schoolbox\Role::STUDENT, $this->createUser(92, 'STUDENT-10', 'Other', 'Student')],
            ]);

        $source = new Calendar_Api_EventType_Event_SocsMusic_Default(
            456,
            $student,
            new User(),
            new ArrayLoader([
                ConfigMapper::NAME_SOCS_ENABLED => true,
                ConfigMapper::NAME_SOCS_IMPORT_MUSIC_LESSONS => true,
                ConfigMapper::NAME_SOCS_ENDPOINT_HOST => 'https://socs.example.test',
                ConfigMapper::NAME_SOCS_ENDPOINT_ID => 'school',
                ConfigMapper::NAME_SOCS_ENDPOINT_TOKEN => 'token',
                ConfigMapper::NAME_SOCS_MUSIC_LESSONS_DEFAULT_EVENT_TYPE => 1,
            ]),
            $eventTypeMapper,
            new class extends HttpClient {
                public function __construct()
                {
                }

                public function getCached(string $path, array $query): \SimpleXMLElement
                {
                    Assert::assertSame(Calendar_Api_EventType_Event_SocsMusic_Default::TUITION_PATH, $path);
                    Assert::assertSame([
                        'data' => 'musiclessons',
                    ], $query);

                    return new \SimpleXMLElement(<<<'XML'
<lessons>
    <lesson>
        <lessonid>98765</lessonid>
        <startdate>19/05/2026</startdate>
        <starttime>09:30</starttime>
        <endtime>10:00</endtime>
        <instrument>Cello</instrument>
        <title>Cello lesson</title>
        <location>Music Room 2</location>
        <staffid>STAFF-7</staffid>
        <pupilid>STUDENT-9</pupilid>
    </lesson>
    <lesson>
        <lessonid>98766</lessonid>
        <startdate>19/05/2026</startdate>
        <starttime>11:30</starttime>
        <endtime>12:00</endtime>
        <instrument>Violin</instrument>
        <title>Other student's lesson</title>
        <location>Music Room 3</location>
        <staffid>STAFF-7</staffid>
        <pupilid>STUDENT-10</pupilid>
    </lesson>
</lessons>
XML);
                }
            },
            $userMapper,
            [[
                'eventTypeID' => 1,
                'eventType' => 'SOCS Music',
                'eventColour' => '#9ad39b',
            ]],
        );

        $events = $source->getEvents(strtotime('2026-05-19 00:00:00'), strtotime('2026-05-20 00:00:00'));

        self::assertCount(1, $events);
        $event = $events->first();
        self::assertInstanceOf(Event::class, $event);
        self::assertSame('socs-music-98765', $event->getEventId());
        self::assertSame(Event::PERSONAL_ACCESS, $event->getAccess());
        self::assertSame('Cello lesson', $event->getTitle());
        self::assertSame('Music Room 2', $event->getLocation());
        self::assertSame([[
            'id' => 71,
            'name' => 'Sam Staff',
            'url' => '/search/user/71',
        ]], $event->getSocsMusicStaff());
        self::assertSame([[
            'id' => 91,
            'name' => 'Jill Student',
            'url' => '/search/user/91',
        ]], $event->getSocsMusicStudents());
    }


    public function testSocsMusicLessonStillShowsForMatchedStaffWhenStudentIsUnmatched(): void
    {
        $staff = $this->createUser(71, 'STAFF-7', 'Sam', 'Staff');

        $eventTypeMapper = $this->createMock(EventTypeMapper::class);
        $eventTypeMapper->expects($this->once())->method('findFirstMeaningfulId')->willReturn(1);

        $userMapper = $this->createMock(UserMapper::class);
        $userMapper->expects($this->exactly(2))
            ->method('findByExternalId')
            ->willReturnCallback(function (string $externalId, string $roleType) use ($staff): User {
                if ($externalId === 'STAFF-7' && $roleType === \Schoolbox\Role::STAFF) {
                    return $staff;
                }

                throw new \Schoolbox\Mapper\Exception\NotFound();
            });

        $source = new Calendar_Api_EventType_Event_SocsMusic_Default(
            456,
            $staff,
            new User(),
            new ArrayLoader([
                ConfigMapper::NAME_SOCS_ENABLED => true,
                ConfigMapper::NAME_SOCS_IMPORT_MUSIC_LESSONS => true,
                ConfigMapper::NAME_SOCS_ENDPOINT_HOST => 'https://socs.example.test',
                ConfigMapper::NAME_SOCS_ENDPOINT_ID => 'school',
                ConfigMapper::NAME_SOCS_ENDPOINT_TOKEN => 'token',
                ConfigMapper::NAME_SOCS_MUSIC_LESSONS_DEFAULT_EVENT_TYPE => 1,
            ]),
            $eventTypeMapper,
            new class extends HttpClient {
                public function __construct()
                {
                }

                public function getCached(string $path, array $query): \SimpleXMLElement
                {
                    return new \SimpleXMLElement(<<<'XML'
<lessons>
    <lesson>
        <lessonid>98765</lessonid>
        <startdate>19/05/2026</startdate>
        <starttime>09:30</starttime>
        <endtime>10:00</endtime>
        <instrument>Cello</instrument>
        <title>Cello lesson</title>
        <location>Music Room 2</location>
        <staffid>STAFF-7</staffid>
        <pupilid>UNKNOWN-STUDENT</pupilid>
    </lesson>
</lessons>
XML);
                }
            },
            $userMapper,
            [[
                'eventTypeID' => 1,
                'eventType' => 'SOCS Music',
                'eventColour' => '#9ad39b',
            ]],
        );

        $events = $source->getEvents(strtotime('2026-05-19 00:00:00'), strtotime('2026-05-20 00:00:00'));

        self::assertCount(1, $events);
        $event = $events->first();
        self::assertInstanceOf(Event::class, $event);
        self::assertSame([['id' => 71, 'name' => 'Sam Staff', 'url' => '/search/user/71']], $event->getSocsMusicStaff());
        self::assertSame([], $event->getSocsMusicStudents());
    }


    public function testSocsCalendarFeedFailureReturnsEmptyCollection(): void
    {
        $campus = (new Campus())->setId(11)->setName('Seniors');
        $user = (new User())->setCampusCollection(new GenericCollection(Campus::class, [$campus]));

        $campusMapper = $this->createMock(CampusMapper::class);
        $campusMapper->expects($this->once())->method('findAll')->willReturn(new GenericCollection(Campus::class, [$campus]));

        $eventTypeMapper = $this->createMock(EventTypeMapper::class);
        $eventTypeMapper->expects($this->never())->method('findFirstMeaningfulId');

        $sentry = $this->createMock(HubInterface::class);
        $sentry->expects($this->once())
            ->method('captureException')
            ->with($this->isInstanceOf(\RuntimeException::class));

        $config = new \Calendar_Api_Config_Record();
        $config->setId(123)->setFailedCount(2);
        $configMapper = $this->createMock(\Calendar_Api_Config_Mapper::class);
        $configMapper->expects($this->once())->method('findById')->with(123)->willReturn($config);
        $configMapper->expects($this->once())->method('save')->with($this->callback(
            static fn (\Calendar_Api_Config_Record $record): bool => $record->getFailedCount() === 3
        ));

        $source = new Calendar_Api_EventType_Event_Socs_Default(
            123,
            $user,
            new User(),
            new ArrayLoader([
                ConfigMapper::NAME_SOCS_ENABLED => true,
                ConfigMapper::NAME_SOCS_IMPORT_CALENDAR_EVENTS => true,
                ConfigMapper::NAME_SOCS_ENDPOINT_HOST => 'https://socs.example.test',
                ConfigMapper::NAME_SOCS_ENDPOINT_ID => 'school',
                ConfigMapper::NAME_SOCS_ENDPOINT_TOKEN => 'token',
                ConfigMapper::NAME_SOCS_CALENDAR_DEFAULT_EVENT_TYPE => 1,
            ]),
            $eventTypeMapper,
            $campusMapper,
            new class extends HttpClient {
                public function __construct()
                {
                }

                public function getCached(string $path, array $query): \SimpleXMLElement
                {
                    throw new \RuntimeException('Bad SOCS config');
                }
            },
            [[
                'eventTypeID' => 1,
                'eventType' => 'SOCS',
                'eventColour' => '#9ad39b',
            ]],
            $sentry,
            $configMapper,
        );

        $events = $source->getEvents(strtotime('2026-06-18 00:00:00'), strtotime('2026-06-19 00:00:00'));

        self::assertCount(0, $events);
    }

    public function testSocsMusicFeedFailureReturnsEmptyCollection(): void
    {
        $eventTypeMapper = $this->createMock(EventTypeMapper::class);
        $eventTypeMapper->expects($this->never())->method('findFirstMeaningfulId');

        $userMapper = $this->createMock(UserMapper::class);
        $userMapper->expects($this->never())->method('findByExternalId');

        $sentry = $this->createMock(HubInterface::class);
        $sentry->expects($this->once())
            ->method('captureException')
            ->with($this->isInstanceOf(\RuntimeException::class));

        $config = new \Calendar_Api_Config_Record();
        $config->setId(456)->setFailedCount(4);
        $configMapper = $this->createMock(\Calendar_Api_Config_Mapper::class);
        $configMapper->expects($this->once())->method('findById')->with(456)->willReturn($config);
        $configMapper->expects($this->once())->method('save')->with($this->callback(
            static fn (\Calendar_Api_Config_Record $record): bool => $record->getFailedCount() === 5
        ));

        $source = new Calendar_Api_EventType_Event_SocsMusic_Default(
            456,
            $this->createUser(91, 'STUDENT-9', 'Jill', 'Student'),
            new User(),
            new ArrayLoader([
                ConfigMapper::NAME_SOCS_ENABLED => true,
                ConfigMapper::NAME_SOCS_IMPORT_MUSIC_LESSONS => true,
                ConfigMapper::NAME_SOCS_ENDPOINT_HOST => 'https://socs.example.test',
                ConfigMapper::NAME_SOCS_ENDPOINT_ID => 'school',
                ConfigMapper::NAME_SOCS_ENDPOINT_TOKEN => 'token',
                ConfigMapper::NAME_SOCS_MUSIC_LESSONS_DEFAULT_EVENT_TYPE => 1,
            ]),
            $eventTypeMapper,
            new class extends HttpClient {
                public function __construct()
                {
                }

                public function getCached(string $path, array $query): \SimpleXMLElement
                {
                    throw new \RuntimeException('Bad SOCS config');
                }
            },
            $userMapper,
            [[
                'eventTypeID' => 1,
                'eventType' => 'SOCS Music',
                'eventColour' => '#9ad39b',
            ]],
            $sentry,
            $configMapper,
        );

        $events = $source->getEvents(strtotime('2026-05-19 00:00:00'), strtotime('2026-05-20 00:00:00'));

        self::assertCount(0, $events);
    }


    public function testSocsMusicEmptyFeedFailureReturnsEmptyCollectionWithoutReporting(): void
    {
        $eventTypeMapper = $this->createMock(EventTypeMapper::class);
        $eventTypeMapper->expects($this->never())->method('findFirstMeaningfulId');

        $userMapper = $this->createMock(UserMapper::class);
        $userMapper->expects($this->never())->method('findByExternalId');

        $sentry = $this->createMock(HubInterface::class);
        $sentry->expects($this->never())->method('captureException');

        $configMapper = $this->createMock(\Calendar_Api_Config_Mapper::class);
        $configMapper->expects($this->never())->method('findById');
        $configMapper->expects($this->never())->method('save');

        $source = new Calendar_Api_EventType_Event_SocsMusic_Default(
            456,
            $this->createUser(91, 'STUDENT-9', 'Jill', 'Student'),
            new User(),
            new ArrayLoader([
                ConfigMapper::NAME_SOCS_ENABLED => true,
                ConfigMapper::NAME_SOCS_IMPORT_MUSIC_LESSONS => true,
                ConfigMapper::NAME_SOCS_ENDPOINT_HOST => 'https://socs.example.test',
                ConfigMapper::NAME_SOCS_ENDPOINT_ID => 'school',
                ConfigMapper::NAME_SOCS_ENDPOINT_TOKEN => 'token',
                ConfigMapper::NAME_SOCS_MUSIC_LESSONS_DEFAULT_EVENT_TYPE => 1,
            ]),
            $eventTypeMapper,
            new class extends HttpClient {
                public function __construct()
                {
                }

                public function getCached(string $path, array $query): \SimpleXMLElement
                {
                    throw new \RuntimeException('SOCS endpoint /socs/xml/tuition.ashx: returned an empty payload');
                }
            },
            $userMapper,
            [[
                'eventTypeID' => 1,
                'eventType' => 'SOCS Music',
                'eventColour' => '#9ad39b',
            ]],
            $sentry,
            $configMapper,
        );

        $events = $source->getEvents(strtotime('2026-05-19 00:00:00'), strtotime('2026-05-20 00:00:00'));

        self::assertCount(0, $events);
    }


    public function testEventsAreCampusFilterable(): void
    {
        $seniorCampus = (new Campus())->setId(11)->setName('Seniors (Y7-Y11)');
        $sixthCampus = (new Campus())->setId(12)->setName('Sixth (Y12-Y13)');

        $campusMapper = $this->createMock(CampusMapper::class);
        $campusMapper->expects($this->once())->method('findAll')->willReturn(new GenericCollection(Campus::class, [
            $seniorCampus,
            $sixthCampus,
        ]));

        $user = (new User())->setCampusCollection(new GenericCollection(Campus::class, [
            $seniorCampus,
            $sixthCampus,
        ]));

        $eventTypeMapper = $this->createMock(EventTypeMapper::class);
        $eventTypeMapper->expects($this->once())->method('findFirstMeaningfulId')->willReturn(1);

        $source = new Calendar_Api_EventType_Event_Socs_Default(
            123,
            $user,
            new User(),
            new ArrayLoader([
                ConfigMapper::NAME_SOCS_ENABLED => true,
                ConfigMapper::NAME_SOCS_IMPORT_CALENDAR_EVENTS => true,
                ConfigMapper::NAME_SOCS_ENDPOINT_HOST => 'https://socs.example.test',
                ConfigMapper::NAME_SOCS_ENDPOINT_ID => 'school',
                ConfigMapper::NAME_SOCS_ENDPOINT_TOKEN => 'token',
                ConfigMapper::NAME_SOCS_CALENDAR_DEFAULT_EVENT_TYPE => 1,
            ]),
            $eventTypeMapper,
            $campusMapper,
            new class extends HttpClient {
                public function __construct()
                {
                }

                public function getCached(string $path, array $query): \SimpleXMLElement
                {
                    Assert::assertSame(Calendar_Api_EventType_Event_Socs_Default::CALENDAR_PATH, $path);
                    Assert::assertSame([
                        'startdate' => '18 Jun 2026',
                        'enddate' => '19 Jun 2026',
                    ], $query);

                    return new \SimpleXMLElement(<<<'XML'
<SOCSCalendar>
    <CalendarEvent>
        <EventID>17986073</EventID>
        <StartDate>18/06/2026</StartDate>
        <StartTime>07:00</StartTime>
        <EndDate>18/06/2026</EndDate>
        <EndTime>08:15</EndTime>
        <Title>S: Breakfast Ad Hoc 4 THU 0700-0815</Title>
        <Schools>Sixth (Y12-Y13), Seniors (Y7-Y11)</Schools>
    </CalendarEvent>
</SOCSCalendar>
XML);
                }
            },
            [[
                'eventTypeID' => 1,
                'eventType' => 'SOCS',
                'eventColour' => '#9ad39b',
            ]],
        );

        $events = $source->getEvents(strtotime('2026-06-18 00:00:00'), strtotime('2026-06-19 00:00:00'));

        self::assertCount(1, $events);
        $event = $events->first();
        self::assertInstanceOf(Event::class, $event);
        self::assertSame('12,11', $event->getCampusList());
        self::assertSame(' campus12 campus11', $event->getFilterClasses());
    }

    private function createUser(int $id, string $externalId, string $firstname, string $lastname): User
    {
        return (new User())
            ->setId($id)
            ->setExternalId($externalId)
            ->setFirstname($firstname)
            ->setLastname($lastname);
    }
}
