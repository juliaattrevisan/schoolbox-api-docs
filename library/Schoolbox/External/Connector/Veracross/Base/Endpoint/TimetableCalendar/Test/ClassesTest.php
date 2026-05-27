<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\TimetableCalendar\Test;

use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvement;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\TimetableCalendar\Classes;
use Schoolbox\External\Connector\Watchlist;
use Schoolbox\External\Event\Mapper as EventMapper;
use Schoolbox\External\Event\Model as EventModel;
use Schoolbox\External\Event\Overrides\Mapper as OverridesMapper;
use Schoolbox\External\Event\Overrides\Model as OverrideModel;

require_once dirname(__DIR__) . '/Classes.php';

class ClassesTest extends TestCase
{
    public function testApplyTeacherInvolvementCreatesStaffOverridesForEvent(): void
    {
        $event = (new EventModel())
            ->setId(456)
            ->setTitle('English')
            ->setDay('Monday')
            ->setPeriod('Period 1')
            ->setStartAt(Alaress_Date_Format::newDatabaseDate('2026-05-27 09:00:00'))
            ->setFinishAt(Alaress_Date_Format::newDatabaseDate('2026-05-27 10:00:00'));

        $classEntry = (new ClassEntry())->setId(789);

        $classInvolvementMapper = $this->createMock(ClassInvolvementMapper::class);
        $classInvolvementMapper->expects(self::once())
            ->method('createModel')
            ->willReturn(new ClassInvolvement());
        $classInvolvementMapper->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(ClassInvolvement::class));

        $clearOverride = new OverrideModel();
        $addOverride = new OverrideModel();
        $savedOverrides = [];

        $overridesMapper = $this->createMock(OverridesMapper::class);
        $overridesMapper->expects(self::exactly(2))
            ->method('createModel')
            ->willReturnOnConsecutiveCalls($clearOverride, $addOverride);
        $overridesMapper->expects(self::exactly(2))
            ->method('save')
            ->with(self::callback(function (OverrideModel $override) use (&$savedOverrides): bool {
                $savedOverrides[] = $override;
                return true;
            }));

        $strategy = new Classes(
            $this->createStub(LoggerInterface::class),
            true,
            $this->createStub(IApi::class),
            $this->createStub(Watchlist::class),
            [],
            $this->createStub(EventMapper::class),
            $this->createStub(ClassEntryMapper::class),
            $classInvolvementMapper,
            $overridesMapper,
            Alaress_Date_Format::newDatabaseDate('2026-05-27 00:00:00'),
            Alaress_Date_Format::newDatabaseDate('2026-05-27 23:59:59')
        );

        $method = new \ReflectionMethod($strategy, 'applyTeacherInvolvement');
        $method->invoke($strategy, [
            'teachers' => [
                [
                    'id' => 'T123',
                    'name' => 'Jane Smith',
                ],
            ],
        ], $event, $classEntry);

        self::assertSame([$clearOverride, $addOverride], $savedOverrides);
        self::assertSame(456, $clearOverride->getEventId());
        self::assertNull($clearOverride->getExternalId());
        self::assertSame(OverrideModel::CLEAR_STAFF, $clearOverride->getAction());
        self::assertSame(1, $clearOverride->getSequence());

        self::assertSame(456, $addOverride->getEventId());
        self::assertSame('T123', $addOverride->getExternalId());
        self::assertSame(OverrideModel::ADD, $addOverride->getAction());
        self::assertSame(2, $addOverride->getSequence());
    }
}
