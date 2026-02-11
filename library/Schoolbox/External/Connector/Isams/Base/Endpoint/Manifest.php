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
        $headings = [
            'id' => 'Division',
            'name' => 'Name',
            'count' => 'Count'
        ];

        /* Find the longest value for this column of data
         */
        $maxDivisionIdLength = strlen($headings['id']);
        $maxNameLength = strlen($headings['name']);
        $maxCountLength = strlen($headings['count']);

        /* Find the existing divisions that schools have set up:
         * TimetableAllocations will add (potentially subset) counts to this encompassing set
         */
        $data = [];

        $existingDivisions = $this->api[DSN::MODULE_BATCHXML]->fetchXml("/iSAMS/SchoolManager/SchoolDivisions/SchoolDivision");

        foreach ($existingDivisions as $division) {
            $divisionId = trim((string)$division->xpath('@Id')[0]);
            $name = trim((string)$division->xpath('Name')[0]);

            $data[$divisionId] = [
                'name' => $name,
                'count' => 0
            ];

            $maxNameLength = max($maxNameLength, strlen($name));
        }

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

        /* Find all TimetableAllocations within these bounds
         */
        $timetableAllocations = $this->api[DSN::MODULE_BATCHXML]->fetchXml("/iSAMS/TimetableManager/TimetableAllocations/TimetableAllocation[ISOYear = {$year} and Week >= {$pastThreshold} and Week <= {$futureThreshold}]");

        foreach ($timetableAllocations as $timetableAllocation) {
            $divisionId = trim((string)$timetableAllocation->Division);

            /* This should already have initialised via the SchoolDivisions prefill, but you never know
             */
            if (!array_key_exists($divisionId, $data)) {
                $data[$divisionId]['count'] = 0;
            }

            $data[$divisionId]['count']++;
        }

        /* Extra padding for aesthetic reasons only
         */
        $maxDivisionIdLength += 5;
        $maxNameLength += 5;
        $maxCountLength += 5;

        $this->log->info(sprintf(
            '        %s%s%s',
            str_pad($headings['id'], $maxDivisionIdLength, ' ', STR_PAD_RIGHT),
            str_pad($headings['name'], $maxNameLength, ' ', STR_PAD_RIGHT),
            str_pad($headings['count'], $maxCountLength, ' ', STR_PAD_LEFT),
        ));

        $this->log->info('        ' . str_pad('', $maxDivisionIdLength + $maxNameLength + $maxCountLength, '-'));

        foreach ($data as $divisionId => $details) {
            $this->log->info(sprintf(
                '        %s%s%s',
                str_pad((string)$divisionId, $maxDivisionIdLength, ' ', STR_PAD_RIGHT),
                str_pad((string)$details['name'], $maxNameLength, ' ', STR_PAD_RIGHT),
                str_pad((string)$details['count'], $maxCountLength, ' ', STR_PAD_LEFT),
            ));
        }

        $this->log->info('');
    }
}
