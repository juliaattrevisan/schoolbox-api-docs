<?php
/**
 * ProgressGradeSummary
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport\IB;

use Alaress_Probe as Probe;

class ProgressGradeSummary extends Base {

    /**
     * Heading for this table
     *
     * @var string
     */
    protected $heading = 'Grade Summary';

    /**
     * Hook method
     *
     * @return mixed
     */
    protected function setup()
    {
        $this->setYear(date('Y'));
        $this->setHeading('Progress Grade Summary');
    }

    /**
     * Returns data for twig rendering
     *
     * @return mixed
     */
    public function findData()
    {
        $this->explorerReporting();
    }

    /**
     * Explorer, reporting!
     */
    private function explorerReporting() {
        $explorer = $this->getDb()->getAll("
            SELECT TOP 20 *
            FROM SynergyOne.dbo.uvSchoolBoxIBResults
        ");

        new Probe($explorer);
    }
}