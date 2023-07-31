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
    public function testIsSatisfiedBy(?string $externalDb, ?string $dataSource, bool $expected): void
    {
        $this->assertSame($expected, (new ValidUsesSudsTimetable())->isSatisfiedBy($externalDb, $dataSource));
    }

    /**
     * Data provider for `getTestIsSatisfiedByData`
     *
     * @return iterable
     */
    public function getTestIsSatisfiedByData(): iterable
    {
        yield 'null external DB, null timetable' => [
            null, null, false,
        ];
        yield 'null external DB, SUDS timetable' => [
            null, Factory::COMPASS, false,
        ];
        yield 'null external DB, non-SUDS timetable' => [
            null, Factory::KAMAR, false,
        ];

        yield 'non-SUDS external DB, null timetable' => [
            IConnector::EXTERNAL_TYPE_SYNERGETIC, null, false,
        ];
        yield 'non-SUDS external DB, SUDS timetable' => [
            IConnector::EXTERNAL_TYPE_SYNERGETIC, Factory::COMPASS, false,
        ];
        yield 'non-SUDS external DB, non-SUDS timetable' => [
            IConnector::EXTERNAL_TYPE_SYNERGETIC, Factory::KAMAR, false,
        ];

        yield 'SUDS external DB, null timetable' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, null, false,
        ];
        yield 'SUDS external DB, iSAMS timetable' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::ISAMS, true,
        ];
        yield 'SUDS external DB, Kamar timetable' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::KAMAR, false,
        ];
        yield 'SUDS external DB, Sentral timetable' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::SENTRAL, true,
        ];
        yield 'SUDS external DB, TASS API timetable' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::TASS_API, true,
        ];
        yield 'SUDS external DB, Compass timetable' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::COMPASS, true,
        ];
        yield 'SUDS external DB, Wonde timetable' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::WONDE, true,
        ];
        yield 'SUDS external DB, CSV timetable' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::CSV, false,
        ];
        yield 'SUDS external DB, empty timetable' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, '', false,
        ];
    }
}
