<?php
/**
 * TimetableIdentifier
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Isams\Base\Importer;

use Psr\Log\LoggerInterface;
use Schoolbox\External\UPS\Batchxml;


class TimetableIdentifier implements ImporterInterface
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

        foreach ($timetables as $timetable) {
            $this->log->info(sprintf(
                'Found timetable %s: assign %s to ext_timetable_code',
                (string)$timetable->Name,
                (string)$timetable->xpath('@Id')[0]->Id
            ));
        }
    }
}