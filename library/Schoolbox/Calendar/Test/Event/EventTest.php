<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Test\Event;

use Alaress_Date_Format;
use Alaress_Date_NullDate;
use PHPUnit\Framework\TestCase;
use Schoolbox\Calendar\Event;

/**
 * Test for the event object.
 *
 * @covers \Schoolbox\Calendar\Event
 */
class EventTest extends TestCase
{
    /**
     * @dataProvider getTestIsPersonalLevelData
     * @covers       \Schoolbox\Calendar\Event::isPersonalLevel
     *
     * @param Event $event
     * @param bool  $expected
     */
    public function testIsPersonalLevel(Event $event, bool $expected): void
    {
        $this->assertSame($expected, $event->isPersonalLevel());
    }


    /**
     * @dataProvider getTestIsGroupLevelData
     * @covers       \Schoolbox\Calendar\Event::isGroupLevel
     *
     * @param Event $event
     * @param bool  $expected
     */
    public function testIsGroupLevel(Event $event, bool $expected): void
    {
        $this->assertSame($expected, $event->isGroupLevel());
    }


    /**
     * @dataProvider getTestIsCampusLevelData
     * @covers       \Schoolbox\Calendar\Event::isCampusLevel
     *
     * @param Event $event
     * @param bool  $expected
     */
    public function testIsCampusLevel(Event $event, bool $expected): void
    {
        $this->assertSame($expected, $event->isCampusLevel());
    }


    /**
     * @dataProvider getTestIsPublishedData
     * @covers       \Schoolbox\Calendar\Event::isPublished
     *
     * @param Event                    $event
     * @param Alaress_Date_Format|null $date
     * @param bool                     $expected
     */
    public function testIsPublished(Event $event, ?Alaress_Date_Format $date, bool $expected): void
    {
        $this->assertSame($expected, $event->isPublished($date));
    }


    /**
     * Test level event builder
     *
     * @return array
     */
    private function getTestLevelEvents(): array
    {
        return [
            Event::ACCESS_PERSONAL => (new Event())->setAccess(Event::ACCESS_PERSONAL),
            Event::ACCESS_GROUP    => (new Event())->setAccess(Event::ACCESS_GROUP),
            Event::ACCESS_CAMPUS   => (new Event())->setAccess(Event::ACCESS_CAMPUS),
        ];
    }


    /**
     * Data provider for personal level test
     *
     * @return array
     */
    public function getTestIsPersonalLevelData(): array
    {
        $events = $this->getTestLevelEvents();
        return [
            Event::ACCESS_PERSONAL => [$events[Event::ACCESS_PERSONAL], true],
            Event::ACCESS_GROUP    => [$events[Event::ACCESS_GROUP], false],
            Event::ACCESS_CAMPUS   => [$events[Event::ACCESS_CAMPUS], false],
        ];
    }


    /**
     * Data provider for group level test
     *
     * @return array
     */
    public function getTestIsGroupLevelData(): array
    {
        $events = $this->getTestLevelEvents();
        return [
            Event::ACCESS_PERSONAL => [$events[Event::ACCESS_PERSONAL], false],
            Event::ACCESS_GROUP    => [$events[Event::ACCESS_GROUP], true],
            Event::ACCESS_CAMPUS   => [$events[Event::ACCESS_CAMPUS], false],
        ];
    }


    /**
     * Data provider for campus level test
     *
     * @return array
     */
    public function getTestIsCampusLevelData(): array
    {
        $events = $this->getTestLevelEvents();
        return [
            Event::ACCESS_PERSONAL => [$events[Event::ACCESS_PERSONAL], false],
            Event::ACCESS_GROUP    => [$events[Event::ACCESS_GROUP], false],
            Event::ACCESS_CAMPUS   => [$events[Event::ACCESS_CAMPUS], true],
        ];
    }


    /**
     * Data provider for campus level test
     *
     * @return iterable
     */
    public function getTestIsPublishedData(): iterable
    {
        $now      = Alaress_Date_Format::now();
        $nullDate = new Alaress_Date_NullDate();
        $lastYear = Alaress_Date_Format::now()->addYears(-1);
        $nextYear = Alaress_Date_Format::now()->addYears(1);
        return [
            // Null publish date: always published
            'null publish, check next year'      => [
                (new Event())->setPublishDateTime(null),
                $nextYear,
                true,
            ],
            'null publish, check now'            => [
                (new Event())->setPublishDateTime(null),
                null,
                true,
            ],
            'null publish, check last year'      => [
                (new Event())->setPublishDateTime(null),
                $lastYear,
                true,
            ],

            // Published now
            'now publish, check next year'       => [
                (new Event())->setPublishDateTime($now),
                $nextYear,
                true,
            ],
            'now publish, check now'             => [
                (new Event())->setPublishDateTime($now),
                null,
                true,
            ],
            'now publish, check last year'       => [
                (new Event())->setPublishDateTime($now),
                $lastYear,
                false,
            ],

            // Published last year
            'last year publish, check next year' => [
                (new Event())->setPublishDateTime($lastYear),
                $nextYear,
                true,
            ],
            'last year publish, check now'       => [
                (new Event())->setPublishDateTime($lastYear),
                null,
                true,
            ],
            'last year publish, check last year' => [
                (new Event())->setPublishDateTime($lastYear),
                $lastYear,
                true,
            ],

            // Published next year
            'next year publish, check next year' => [
                (new Event())->setPublishDateTime($nextYear),
                $nextYear,
                true,
            ],
            'next year publish, check now'       => [
                (new Event())->setPublishDateTime($nextYear),
                null,
                false,
            ],
            'next year publish, check last year' => [
                (new Event())->setPublishDateTime($nextYear),
                $lastYear,
                false,
            ],

            // Deprecated null date publish: always published
            'null date publish, check next year' => [
                (new Event())->setPublishDateTime($nullDate),
                $nextYear,
                true,
            ],
            'null date publish, check now'       => [
                (new Event())->setPublishDateTime($nullDate),
                null,
                true,
            ],
            'null date publish, check last year' => [
                (new Event())->setPublishDateTime($nullDate),
                $lastYear,
                true,
            ],

            // Deprecated null date now: treat as now
            'last year publish, check null date' => [
                (new Event())->setPublishDateTime($lastYear),
                $nullDate,
                true,
            ],
            'now publish, check null date'       => [
                (new Event())->setPublishDateTime($now),
                $nullDate,
                true,
            ],
            'next year publish, check null date' => [
                (new Event())->setPublishDateTime($nextYear),
                $nullDate,
                false,
            ],
        ];
    }
}
