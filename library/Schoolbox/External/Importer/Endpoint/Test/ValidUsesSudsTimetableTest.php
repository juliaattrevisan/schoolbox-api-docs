<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint\Test;

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
     * @param string $lissUsername
     * @param string $lissPassword
     * @param string $dataSource
     * @param bool   $expected
     * @return void
     */
    public function testIsSatisfiedBy(string $lissUsername, string $lissPassword, string $dataSource, bool $expected): void
    {
        $this->assertSame($expected, (new ValidUsesSudsTimetable())->isSatisfiedBy($lissUsername, $lissPassword, $dataSource));
    }

    /**
     * Data provider for `getTestIsSatisfiedByData`
     *
     * @return iterable
     */
    public function getTestIsSatisfiedByData(): iterable
    {
        /* Most likely synonymous with Live DB connector only, using the cyclic grid
         */
        yield 'empty liss username, empty liss password, empty suds selection' => [
            '', '', '', false,
        ];

        /* SUDS Connector supplies the timetable data by itself
         */
        yield 'empty liss username, empty liss password, SUDS timetable' => [
            '', '', Factory::COMPASS, true,
        ];

        /* SUDS LISS supplies the timetable data on top of some other SUDS Connector
         */
        yield 'supplied liss username, supplied liss password, SUDS timetable' => [
            'qwerty', 'abcdefg', Factory::COMPASS, true,
        ];

        /* KAMAR populates the cyclic grid
         */
        yield 'empty liss username, empty liss password, non-SUDS timetable' => [
            '', '', Factory::KAMAR, false,
        ];

        /* Partial LISS configuration; returns false
         * because you can't successfully populate Schedule Timetable like this
         */
        yield 'partial liss configuration 1, null timetable' => [
            'aaaaaaa', '', '', false
        ];

        /* Partial LISS configuration; returns false
         * because you can't successfully populate Schedule Timetable like this
         */
        yield 'partial liss configuration 2, null timetable' => [
            '', 'bbbbbbb', '', false
        ];

        /* Partial LISS configuration; returns false
         * because you can't successfully populate Schedule Timetable like this
         */
        yield 'partial liss configuration 3, SUDS timetable' => [
            'aaaaaaa', '', Factory::COMPASS, false
        ];

        /* Partial LISS configuration; returns false
         * because you can't successfully populate Schedule Timetable like this
         */
        yield 'partial liss configuration 4, SUDS timetable' => [
            '', 'bbbbbbb', Factory::COMPASS, false
        ];

        /* Partial LISS configuration; returns false
         * because you can't successfully populate Schedule Timetable like this
         */
        yield 'partial liss configuration 5, non-SUDS timetable' => [
            'aaaaaaa', '', Factory::KAMAR, false
        ];

        /* Partial LISS configuration; returns false
         * because you can't successfully populate Schedule Timetable like this
         */
        yield 'partial liss configuration 6, non-SUDS timetable' => [
            '', 'bbbbbbb', Factory::KAMAR, false
        ];

        /* SUDS Connectors that populate Schedule Timetable
         */
        yield 'iSAMS timetable' => [
            'a b c d e f g', 'ppyyqq 1 2 3', Factory::ISAMS, true,
        ];

        yield 'Sentral timetable' => [
            'a b c d e f g', 'ppyyqq 1 2 3', Factory::SENTRAL, true,
        ];

        yield 'TASS API timetable' => [
            'a b c d e f g', 'ppyyqq 1 2 3', Factory::TASS_API, true,
        ];

        yield 'Compass timetable' => [
            'a b c d e f g', 'ppyyqq 1 2 3', Factory::COMPASS, true,
        ];

        yield 'Wonde timetable' => [
            'a b c d e f g', 'ppyyqq 1 2 3', Factory::WONDE, true,
        ];

        yield 'LISS timetable' => [
            'a b c d e f g', 'ppyyqq 1 2 3', Factory::LISS, true,
        ];

        yield 'Veracross timetable' => [
            'a b c d e f g', 'ppyyqq 1 2 3', Factory::VERACROSS, true,
        ];

        /* Special cases for Cyclic grid fulfilment
         * Supplying liss credentials enables Schedule Timetable
         * Otherwise it fulfils Cyclic Timetable
         */
        yield 'KAMAR timetable 1' => [
            'a b c d e f g', 'ppyyqq 1 2 3', Factory::KAMAR, true,
        ];

        yield 'KAMAR timetable 2' => [
            '', '', Factory::KAMAR, false,
        ];

        yield 'CSV timetable 1' => [
            'a b c d e f g', 'ppyyqq 1 2 3', Factory::CSV, true,
        ];

        yield 'CSV timetable 2' => [
            '', '', Factory::CSV, false,
        ];
    }
}
