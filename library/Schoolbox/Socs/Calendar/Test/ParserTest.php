<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Calendar\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sabre\VObject\ParseException;
use Schoolbox\Socs\Calendar\Event;
use Schoolbox\Socs\Calendar\Parser;

#[CoversClass(Parser::class)]
#[CoversClass(Event::class)]
class ParserTest extends TestCase
{
    private string $previousTimezone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('Australia/Melbourne');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousTimezone);

        parent::tearDown();
    }

    public function testParsesCalendarEventsWithSchools(): void
    {
        $events = (new Parser())->parse(new \SimpleXMLElement(<<<'XML'
<SOCSCalendar>
    <CalendarEvent>
        <EventID>17983747</EventID>
        <StartDate>19/05/2026</StartDate>
        <StartTime>07:00</StartTime>
        <EndDate>19/05/2026</EndDate>
        <EndTime>08:15</EndTime>
        <Title>S: Breakfast 2 TUE 0700-0815</Title>
        <Description>Breakfast club</Description>
        <Location>Dining Hall</Location>
        <Category>Co-Curricular</Category>
        <Schools>
            <string>Senior Campus</string>
            <string>Junior Campus</string>
        </Schools>
    </CalendarEvent>
</SOCSCalendar>
XML));

        self::assertCount(1, $events);
        self::assertSame('17983747', $events[0]->uid);
        self::assertSame('S: Breakfast 2 TUE 0700-0815', $events[0]->title);
        self::assertSame('2026-05-19 07:00:00', $events[0]->start->format('Y-m-d H:i:s'));
        self::assertSame('2026-05-19 08:15:00', $events[0]->end->format('Y-m-d H:i:s'));
        self::assertFalse($events[0]->allDay);
        self::assertSame(['Senior Campus', 'Junior Campus'], $events[0]->schools);
    }

    public function testParsesCalendarTimesInLocalTimezone(): void
    {
        $events = (new Parser())->parse(new \SimpleXMLElement(<<<'XML'
<SOCSCalendar>
    <CalendarEvent>
        <EventID>17983749</EventID>
        <StartDate>19/05/2026</StartDate>
        <StartTime>07:00</StartTime>
        <EndTime>08:15</EndTime>
        <Title>Local time event</Title>
        <Schools>Senior Campus</Schools>
    </CalendarEvent>
</SOCSCalendar>
XML));

        self::assertSame('Australia/Melbourne', $events[0]->start->getTimezone()->getName());
        self::assertSame('2026-05-19 07:00:00 +10:00', $events[0]->start->format('Y-m-d H:i:s P'));
    }

    public function testParsesCommaSeparatedSchoolsElementAsCampusNames(): void
    {
        $events = (new Parser())->parse(new \SimpleXMLElement(<<<'XML'
<SOCSCalendar>
    <CalendarEvent>
        <EventID>17983748</EventID>
        <StartDate>19/05/2026</StartDate>
        <Title>All day event</Title>
        <Schools>Senior Campus, Junior Campus, Missing Campus</Schools>
    </CalendarEvent>
</SOCSCalendar>
XML));

        self::assertTrue($events[0]->allDay);
        self::assertSame(['Senior Campus', 'Junior Campus', 'Missing Campus'], $events[0]->schools);
    }

    public function testInvalidRootFails(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('SOCS calendar XML feed must use SOCSCalendar as the root element');

        (new Parser())->parse(new \SimpleXMLElement('<Calendar />'));
    }
}
