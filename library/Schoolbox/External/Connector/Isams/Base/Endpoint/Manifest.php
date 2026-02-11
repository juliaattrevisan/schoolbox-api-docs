<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN;
use Schoolbox\External\Connector\Isams\Base\Endpoint\TimetableCalendar\CycleUnroller;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Manifest
 *
 * A diagnostic inventory of things we expect to be here, but might not exist due to misconfiguration
 * or some other mistake.
 *
 * @package Schoolbox\External\Connector\Veracross\Base\Endpoint
 */
class Manifest implements IStrategy
{
    /**
     * ApiUsage constructor.
     *
     * @param LoggerInterface $log
     * @param array $api
     */
    public function __construct(
        private LoggerInterface $log,
        private array $api
    ) {
    }


    /**
     * @inheritDoc
     */
    public function import(): void
    {
        $this->log->info('');
        $this->log->info('Diagnostic Manifest');
        $this->log->info('');

        /* Add more diagnostic manifests as needed: each should render their own little table thing
         */
        $this->divisions();
    }


    /**
     * Dump a list of division usage via TimetableAllocations
     *
     * @return void
     */
    private function divisions(): void
    {


        /* This is currently duped from the CycleUnroller for symmetry. This hasn't needed to change in a long time.
         *
         * TODO Dedupe this meaningfully somehow
         */
        $year = (int)date('Y');

        $currentWeek = (int)\Alaress_Date_Format::now()->getUsingFormat('W');
        $weeksInYear = (new \DateTime("December 28th, {$year}"))->format('W');

        $pastThreshold = max($currentWeek - CycleUnroller::PAST_THRESHOLD, 1);
        $futureThreshold = min($currentWeek + CycleUnroller::FUTURE_THRESHOLD, $weeksInYear);

        /* A nice heading for this manifest
         */
        $this->log->info(sprintf(
            '    Manifest for TimetableAllocation nodes for %s and Weeks %s-%s.',
            $year,
            $pastThreshold,
            $futureThreshold
        ));

        $this->log->info('');

        $headings = [
            'divisionId' => 'Division',
            'count' => 'Count'
        ];

        $data = [];

        /* Find the longest value for this column of data
         */
        $maxDivisionIdLength = strlen($headings['divisionId']);
        $maxCountLength = strlen($headings['count']);

        /* Find all TimetableAllocations within these bounds
         */
        $timetableAllocations = $this->api[DSN::MODULE_BATCHXML]->fetchXml("/iSAMS/TimetableManager/TimetableAllocations/TimetableAllocation[ISOYear = {$year} and Week >= {$pastThreshold} and Week <= {$futureThreshold}]");

        foreach ($timetableAllocations as $timetableAllocation) {
            $divisionId = (string)$timetableAllocation->Division;

            if (!array_key_exists($divisionId, $data)) {
                $data[$divisionId] = 0;
            }

            $data[$divisionId]++;
        }

        /* Extra padding for aesthetic reasons only
         */
        $maxDivisionIdLength += 5;
        $maxCountLength += 5;

        $this->log->info(sprintf(
            '        %s%s',
            str_pad($headings['divisionId'], $maxDivisionIdLength, ' ', STR_PAD_RIGHT),
            str_pad($headings['count'], $maxCountLength, ' ', STR_PAD_LEFT),
        ));

        $this->log->info('        ' . str_pad('', $maxDivisionIdLength + $maxCountLength, '-'));

        foreach ($data as $divisionId => $count) {
            $this->log->info(sprintf(
                '        %s%s',
                str_pad((string)$divisionId, $maxDivisionIdLength, ' ', STR_PAD_RIGHT),
                str_pad((string)$count, $maxCountLength, ' ', STR_PAD_LEFT),
            ));
        }

        $this->log->info('');
    }
}
