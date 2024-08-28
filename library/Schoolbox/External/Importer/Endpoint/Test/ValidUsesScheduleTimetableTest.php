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
use Schoolbox\External\Importer\Endpoint\ValidUsesScheduleTimetable;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\External\Importer\Endpoint\ValidUsesScheduleTimetable::class)]
class ValidUsesScheduleTimetableTest extends TestCase
{
    /**
     * @param string|null $externalDb
     * @param string|null $dataSource
     * @param bool        $expected
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIsSatisfiedByData')]
    public function testIsSatisfiedBy(?string $externalDb, ?string $dataSource, string $timetableView, bool $expected): void
    {
        $this->assertSame($expected, (new ValidUsesScheduleTimetable())->isSatisfiedBy($externalDb, $dataSource, $timetableView));
    }

    /**
     * Data provider for `getTestIsSatisfiedByData`
     *
     * @return iterable
     */
    public static function getTestIsSatisfiedByData(): iterable
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
        /* isams forces schedule timetable
         */
        yield 'SUDS external DB, iSAMS timetable, cyclic timetable view' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::ISAMS, 'table', true,
        ];
        yield 'SUDS external DB, iSAMS timetable, schedule timetable view' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::ISAMS, 'calendar', true,
        ];
        /* Kamar requires cyclic
         * trying to use kamar with schedule timetable is a human mistake and a misuse.
         */
        yield 'SUDS external DB, Kamar timetable, cyclic timetable view' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::KAMAR, 'table', false,
        ];
        yield 'SUDS external DB, Kamar timetable, schedule timetable view' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::KAMAR, 'calendar', true,
        ];
        /* sentral forces schedule timetable
         */
        yield 'SUDS external DB, Sentral timetable, cyclic timetable view' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::SENTRAL, 'table', true,
        ];
        yield 'SUDS external DB, Sentral timetable, schedule timetable view' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::SENTRAL, 'calendar', true,
        ];
        /* tassapi forces schedule timetable
         */
        yield 'SUDS external DB, TASS API timetable, cyclic timetable view' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::TASS_API, 'table', true,
        ];
        yield 'SUDS external DB, TASS API timetable, schedule timetable view' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::TASS_API, 'calendar', true,
        ];
        /* compass forces schedule timetable
         */
        yield 'SUDS external DB, Compass timetable, cyclic timetable view' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::COMPASS, 'table', true,
        ];
        yield 'SUDS external DB, Compass timetable, schedule timetable view' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::COMPASS, 'calendar', true,
        ];
        /* wonde forces schedule timetable
        */
        yield 'SUDS external DB, Wonde timetable, cyclic timetable view' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::WONDE, 'table', true,
        ];
        yield 'SUDS external DB, Wonde timetable, schedule timetable view' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::WONDE, 'calendar', true,
        ];

        yield 'SUDS external DB, CSV timetable, cyclic timetable view' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, Factory::CSV, 'table', false,
        ];
        yield 'SUDS external DB, empty timetable, cyclic timetable view' => [
            IConnector::EXTERNAL_TYPE_SCHOOLBOX, '', 'table', false,
        ];
    }
}
