<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Importer\Timetable;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Isams\Base\Importer\Batchxml;
use Schoolbox\External\Connector\Isams\Base\Importer\IStrategy;

/**
 * Class Identifier
 *
 * Get a quick list of all the timetable ids that you need to shank into ext_timetable_code
 * This is used to bridge the gap between isams and schoolbox concepts of campus-specific timetables
 *
 * @package Schoolbox\External\Connector\Isams\Base\Importer\Timetable
 */
class Identifier implements IStrategy
{

    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private $perform;


    /**
     * @var Batchxml
     */
    private $batchxml;


    /**
     * TimetableClass constructor.
     * @param LoggerInterface        $log
     * @param bool                   $perform
     * @param Batchxml               $batchxml
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Batchxml $batchxml
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->batchxml = $batchxml;
    }

    /**
     * Import data from xml into local schema
     */
    public function import(): void
    {
        /* Get a list of all the timetables to process
         */
        $timetables = $this->batchxml->fetchXml('/iSAMS/TimetableManager/PublishedTimetables/Timetable');

        /* If there aren't any timetables, log it and exit
         */
        if (!\count($timetables)) {
            $this->log->warning('No PublishedTimetables detected.');
            return;
        }

        foreach ($timetables as $timetable) {
            $this->log->info(sprintf(
                'Found timetable %s: assign %s to ext_timetable_code',
                trim((string)$timetable->Name),
                trim((string)$timetable->xpath('@Id')[0]->Id)
            ));
        }
    }
}
