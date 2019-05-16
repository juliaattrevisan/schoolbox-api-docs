<?php
/**
 * Timetable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Isams\Base\Importer;

use Psr\Log\LoggerInterface;
use Schoolbox\External\TimetableCycle;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\UPS\Batchxml;

class Timetable implements ImporterInterface
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
    private $api;


    /**
     * @var TimetableCycleMapper
     */
    private $timetableCycleMapper;


    /**
     * TimetableCycle constructor.
     *
     * @param LoggerInterface      $log
     * @param bool                 $perform
     * @param Batchxml             $batchxml
     * @param TimetableCycleMapper $timetableCycleMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        Batchxml $batchxml,
        TimetableCycleMapper $timetableCycleMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $batchxml;
        $this->timetableCycleMapper = $timetableCycleMapper;

    }

    /**
     * Import data from xml into local schema
     */
    public function import(): void
    {
        $this->timetableCycleMapper->getDatabase()->query("DELETE FROM {$this->timetableCycleMapper->getTable()}");

        $cycles = $this->api->fetchXml('/iSAMS/TimetableManager/Structure/Week');

        /* where is this value?
         */
        $dayLength = 9;

        foreach ($cycles as $cycle) {

            /** @var TimetableCycle $timetableCycle */
            $timetableCycle = $this->timetableCycleMapper->createModel();
            $timetableCycle->setId((string)$cycle->xpath('@Id')[0]);
            $timetableCycle->setTitle((string)$cycle->Name);
            $timetableCycle->setCycleLength(\count($cycle->xpath('Days/Day')));
            $timetableCycle->setDayLength($dayLength);
            $timetableCycle->setRegular(1);

            $this->timetableCycleMapper->save($timetableCycle);
        }
    }
}