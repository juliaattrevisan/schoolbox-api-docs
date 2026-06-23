<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Calendar\Api\EventType\Event\Socs\Test;

use Calendar\Event\Type\Day\Event;
use Calendar_Api_EventType_Event_Socs_Default;
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

#[CoversClass(Calendar_Api_EventType_Event_Socs_Default::class)]
#[CoversClass(Parser::class)]
#[CoversClass(CampusMatcher::class)]
class DefaultTest extends TestCase
{
    public function testSocsMusicCalendarClassesAutoload(): void
    {
        self::assertTrue(class_exists('Calendar_Api_Config_Connector_SocsMusic'));
        self::assertTrue(class_exists('Calendar_Api_EventType_Event_SocsMusic_Default'));
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
}
