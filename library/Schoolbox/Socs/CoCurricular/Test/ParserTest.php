<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\CoCurricular\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabre\VObject\ParseException;
use Schoolbox\Socs\CoCurricular\Club;
use Schoolbox\Socs\CoCurricular\DataSet;
use Schoolbox\Socs\CoCurricular\Event;
use Schoolbox\Socs\CoCurricular\Parser;

#[CoversClass(Parser::class)]
#[CoversClass(Club::class)]
#[CoversClass(Event::class)]
#[CoversClass(DataSet::class)]
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

    public function testParsesClubsEventsAndRegistrationUnionsByClubId(): void
    {
        $data = (new Parser())->parse(
            new \SimpleXMLElement(<<<'XML'
<clubs>
    <club>
        <clubid>101</clubid>
        <clubname>Chess</clubname>
        <category>Senior Activities</category>
    </club>
    <club>
        <clubid>202</clubid>
        <clubname>Chess</clubname>
        <category>Senior Activities</category>
    </club>
</clubs>
XML),
            new \SimpleXMLElement(<<<'XML'
<events>
    <event>
        <clubid>101</clubid>
        <eventid>501</eventid>
        <title>Chess training</title>
        <location>Library</location>
        <startdate>24/07/2026</startdate>
        <starttime>15:30</starttime>
        <endtime>17:00</endtime>
        <alldayevent>False</alldayevent>
        <recurringid>series-1</recurringid>
    </event>
    <event>
        <clubid>101</clubid>
        <eventid>502</eventid>
        <title>Chess tournament</title>
        <location>Hall</location>
        <startdate>25/07/2026</startdate>
        <starttime />
        <endtime />
        <alldayevent>True</alldayevent>
        <recurringid />
    </event>
    <event>
        <clubid>202</clubid>
        <eventid>503</eventid>
        <title>Chess training</title>
        <location>Room 4</location>
        <startdate>24/07/2026</startdate>
        <starttime>16:00</starttime>
        <endtime>17:00</endtime>
        <alldayevent>False</alldayevent>
        <recurringid>series-2</recurringid>
    </event>
</events>
XML),
            new \SimpleXMLElement(<<<'XML'
<registers>
    <pupil><eventid>501</eventid><pupilid>PUPIL-1</pupilid></pupil>
    <pupil><eventid>502</eventid><pupilid>PUPIL-2</pupilid></pupil>
    <pupil><eventid>502</eventid><pupilid>PUPIL-1</pupilid></pupil>
    <pupil><eventid>503</eventid><pupilid>PUPIL-3</pupilid></pupil>
</registers>
XML),
        );

        self::assertSame('Chess - Senior Activities', $data->clubsById['101']->getDisplayName());
        self::assertSame('Chess - Senior Activities', $data->clubsById['202']->getDisplayName());
        self::assertSame(['PUPIL-1', 'PUPIL-2'], $data->pupilIdsByClubId['101']);
        self::assertSame(['PUPIL-3'], $data->pupilIdsByClubId['202']);
        self::assertSame(
            '2026-07-24 15:30:00 +10:00',
            $data->eventsById['501']->start->format('Y-m-d H:i:s P')
        );
        self::assertSame(
            '2026-07-24 17:00:00 +10:00',
            $data->eventsById['501']->finish->format('Y-m-d H:i:s P')
        );
        self::assertSame(
            '2026-07-25 00:00:00 +10:00',
            $data->eventsById['502']->start->format('Y-m-d H:i:s P')
        );
        self::assertSame(
            '2026-07-25 23:59:59 +10:00',
            $data->eventsById['502']->finish->format('Y-m-d H:i:s P')
        );
    }

    #[DataProvider('invalidRootProvider')]
    public function testRejectsInvalidRoots(
        string $clubsXml,
        string $eventsXml,
        string $registersXml,
        string $message,
    ): void {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage($message);

        (new Parser())->parse(
            new \SimpleXMLElement($clubsXml),
            new \SimpleXMLElement($eventsXml),
            new \SimpleXMLElement($registersXml),
        );
    }

    /**
     * @return iterable<string,array{string,string,string,string}>
     */
    public static function invalidRootProvider(): iterable
    {
        yield 'clubs' => [
            '<not-clubs />',
            '<events />',
            '<registers />',
            'SOCS co-curricular clubs XML feed must use clubs as the root element',
        ];
        yield 'events' => [
            '<clubs />',
            '<not-events />',
            '<registers />',
            'SOCS co-curricular events XML feed must use events as the root element',
        ];
        yield 'registers' => [
            '<clubs />',
            '<events />',
            '<not-registers />',
            'SOCS co-curricular registers XML feed must use registers as the root element',
        ];
    }

    public function testRejectsMissingMandatoryClubFields(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('SOCS co-curricular club #1 is missing a category field');

        (new Parser())->parse(
            new \SimpleXMLElement(
                '<clubs><club><clubid>101</clubid><clubname>Chess</clubname></club></clubs>'
            ),
            new \SimpleXMLElement('<events />'),
            new \SimpleXMLElement('<registers />'),
        );
    }

    public function testRejectsFinishBeforeStart(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('SOCS co-curricular event 501 has an end time before its start time');

        (new Parser())->parse(
            new \SimpleXMLElement(
                '<clubs><club><clubid>101</clubid><clubname>Chess</clubname>'
                . '<category>Senior Activities</category></club></clubs>'
            ),
            new \SimpleXMLElement(
                '<events><event><clubid>101</clubid><eventid>501</eventid><title>Training</title>'
                . '<location>Library</location><startdate>24/07/2026</startdate>'
                . '<starttime>17:00</starttime><endtime>16:00</endtime>'
                . '<alldayevent>False</alldayevent><recurringid /></event></events>'
            ),
            new \SimpleXMLElement('<registers />'),
        );
    }
}
