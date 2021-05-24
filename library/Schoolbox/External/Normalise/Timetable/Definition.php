<?php

/**
 * Definition
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Normalise\Timetable;

use Schoolbox\Collection;
use Schoolbox\Campus;
use Schoolbox\External\Normalise\Timetable\Period\Base as PeriodBase;

class Definition
{
    /**
     * A Collection of Period instances
     *
     * @var Collection
     */
    private $allPeriods = null;

    /**
     * A Campus instance
     *
     * @var Campus
     */
    private $campus = null;

    /**
     * Definition constructor.
     * @param Campus $campus
     */
    public function __construct(Campus $campus)
    {
        $this->setAllPeriods(new Collection());
        $this->setCampus($campus);
    }

    /**
     * Add a Period instance
     *
     * @param PeriodBase $period
     * @return $this
     */
    public function addPeriod(PeriodBase $period)
    {
        $this->getAllPeriods()->add($period);

        return $this;
    }

    /**
     * Return ancient array format
     *
     * @return array
     */
    public function decompile()
    {
        $decompiled = [
            'desc' => [],
            'start' => [],
            'end' => [],
            'number' => [],
            'sequence' => [],
            'periodCount' => 0,
            'dayCount' => 0
        ];

        foreach ($this->getAllPeriods() as $currentPeriod) {
            $periodIndex = $currentPeriod->getNumber() - 1;

            $decompiled['desc'][$periodIndex] = $currentPeriod->getName();
            $decompiled['start'][$periodIndex] = $currentPeriod->getStart();
            $decompiled['end'][$periodIndex] = $currentPeriod->getEnd();
            $decompiled['number'][$periodIndex] = $currentPeriod->getNumber();
            $decompiled['sequence'][$periodIndex] = $currentPeriod->isTeaching() ? 1 : 2;
        }

        $decompiled['periodCount'] = $this->getAllPeriods()->count();

        /* TODO: This seems to be done differently in library/External
         * Need to double-check the difference and do properly for
         * the Schoolbox Connector (and others?)
         */
        $decompiled['dayCount'] = $this->getCampus()->getTimetableDay();

        return $decompiled;
    }

    /* Getters/Setters
     */

    /**
     * Get all periods
     *
     * @return null
     */
    private function getAllPeriods()
    {
        return $this->allPeriods;
    }

    /**
     * Set all periods
     *
     * @param null $allPeriods
     */
    private function setAllPeriods($allPeriods)
    {
        $this->allPeriods = $allPeriods;
    }

    /**
     * Get campus
     *
     * @return Campus
     */
    private function getCampus()
    {
        return $this->campus;
    }

    /**
     * Set campus
     *
     * @param Campus $campus
     * @return $this
     */
    private function setCampus($campus)
    {
        $this->campus = $campus;

        return $this;
    }
}
