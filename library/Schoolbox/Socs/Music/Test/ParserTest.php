<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Music\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sabre\VObject\ParseException;
use Schoolbox\Socs\Music\Lesson;
use Schoolbox\Socs\Music\Parser;

#[CoversClass(Parser::class)]
#[CoversClass(Lesson::class)]
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

    public function testParsesMusicLessons(): void
    {
        $lessons = (new Parser())->parse(new \SimpleXMLElement(<<<'XML'
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
</lessons>
XML));

        self::assertCount(1, $lessons);
        self::assertSame('98765', $lessons[0]->id);
        self::assertSame('Cello lesson', $lessons[0]->title);
        self::assertSame('Cello', $lessons[0]->instrument);
        self::assertSame('Music Room 2', $lessons[0]->location);
        self::assertSame('STAFF-7', $lessons[0]->staffExternalId);
        self::assertSame('STUDENT-9', $lessons[0]->studentExternalId);
        self::assertSame('2026-05-19 09:30:00 +10:00', $lessons[0]->start->format('Y-m-d H:i:s P'));
        self::assertSame('2026-05-19 10:00:00 +10:00', $lessons[0]->end->format('Y-m-d H:i:s P'));
    }

    public function testInvalidRootFails(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('SOCS music lesson XML feed must use lessons as the root element');

        (new Parser())->parse(new \SimpleXMLElement('<SOCSCalendar />'));
    }
}
