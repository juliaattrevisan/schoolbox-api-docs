<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint\Test;

use External\IConnector;
use PHPUnit\Framework\TestCase;
use Schoolbox\External\Importer\Endpoint\Factory;
use Schoolbox\External\Importer\Endpoint\ValidUsesSudsTimetable;

/**
 * @covers \Schoolbox\External\Importer\Endpoint\ValidUsesSudsTimetable
 */
class ValidUsesSudsTimetableTest extends TestCase
{
    /**
     * @dataProvider getTestIsSatisfiedByData
     * @covers \Schoolbox\External\Importer\Endpoint\ValidUsesSudsTimetable::isSatisfiedBy
     * @param string|null $externalDb
     * @param string|null $dataSource
     * @param bool        $expected
     * @return void
     */
    public function testIsSatisfiedBy(?string $externalDb, ?string $dataSource, string $timetableView, bool $expected): void
    {
        $this->assertSame($expected, (new ValidUsesSudsTimetable())->isSatisfiedBy($externalDb, $dataSource, $timetableView));
    }

    /**
     * Data provider for `getTestIsSatisfiedByData`
     *
     * @return iterable
     */
    public function getTestIsSatisfiedByData(): iterable
    {
        yield 'null external DB, null timetable' => [
            null, null, 'table', false,
        ];
        yield 'null external DB, SUDS timetable' => [
            null, Factory::COMPASS, 'table', false,
        ];
        yield 'null external DB, non-SUDS timetable' => [
            null, Factory::KAMAR, 'table', false,
        ];

        yield 'non-SUDS external DB, null timetable' => [
            IConnector::EXTERNAL_TYPE_SYNERGETIC, null, 'table', false,
        ];
        yield 'non-SUDS external DB, SUDS timetable' => [
            IConnector::EXTERNAL_TYPE_SYNERGETIC, Factory::COMPASS, 'table', false,
        ];
        yield 'non-SUDS external DB, non-SUDS timetable' => [
            IConnector::EXTERNAL_TYPE_SYNERGETIC, Factory::KAMAR, 'table', false,
        ];

        yield 'SUDS external DB, null timetable' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, null, 'table', false,
        ];
        yield 'SUDS external DB, iSAMS timetable' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::ISAMS, 'table', true,
        ];
        yield 'SUDS external DB, Kamar timetable' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::KAMAR, 'table', false,
        ];
        yield 'SUDS external DB, Sentral timetable' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::SENTRAL, 'table', true,
        ];
        yield 'SUDS external DB, TASS API timetable' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::TASS_API, 'table', true,
        ];
        yield 'SUDS external DB, Compass timetable' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::COMPASS, 'table', true,
        ];
        yield 'SUDS external DB, Wonde timetable' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::WONDE, 'table', true,
        ];
        yield 'SUDS external DB, CSV timetable' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::CSV, 'table', false,
        ];
        yield 'SUDS external DB, empty timetable' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, '', 'table', false,
        ];
    }
}
