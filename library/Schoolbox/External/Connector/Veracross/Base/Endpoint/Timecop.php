<?php

///**
// * @copyright   Copyright 2023, Schoolbox Pty Ltd
// * @link        https://schoolbox.com.au/
// */
//
//declare(strict_types=1);
//
//namespace Schoolbox\External\Connector\Veracross\Base\Endpoint;
//
//use DebugBar\DebugBarException;
//
///**
// * Maintains a list of things that occurred during a php runtime
// * for Veracross' connector. There are a lot of moving parts
// * and this Timecop logs when things happened.
// *
// * Loosely based on the real-life documentary Timecop, starring JCVD
// */
//class Timecop
//{
//    /**
//     * A stack of (time, text) tuples that describe when things happened
//     *
//     * @var array
//     */
//    private array $timeline = [];
//
//
//    /**
//     * Remember when something happened
//     *
//     * @param string $text
//     * @return void
//     */
//    public function regulate(string $text): void
//    {
//        $time = microtime(true);
//
//        $this->timeline[] = [
//            $time,
//            $text
//        ];
//    }
//
//
//    /**
//     * The whole diary of events, plopped out directly to screen
//     *
//     * @return void
//     * @throws DebugBarException
//     */
//    public function generateTimeline(): void
//    {
//        $previousTimestamp = null;
//        $diff = 0;
//
//        new \Alaress_Probe('event', 'diff from previous | time of event');
//
//        foreach ($this->timeline as $i => $row) {
//            if ($previousTimestamp) {
//                $diff = $row[0] - $previousTimestamp;
//            }
//
//            /* Nice formatting
//             */
//            $eventMessage = sprintf(
//                '%s%s',
//                str_pad((string)$i, 9),
//                $row[1]
//            );
//
//            $timeMessage = sprintf(
//                '%s%s',
//                str_pad((string)number_format($diff, 3), 9),
//                $row[0]
//            );
//
//            new \Alaress_Probe($eventMessage, $timeMessage);
//
//            $previousTimestamp = $row[0];
//        }
//
//        /* Total duration of the diary
//         */
//        $totalDuration = $previousTimestamp - $this->timeline[0][0];
//
//        new \Alaress_Probe('<strong>Total</strong>: ' . number_format($totalDuration, 3));
//    }
//}
