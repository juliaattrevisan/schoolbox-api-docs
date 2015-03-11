<?php
/**
 * Averages
 *
 * A list of userId-average values
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics\Results;

use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Collection;

class Averages {

    /**
     * A collection of User instances
     *
     * @var Collection
     */
    private $allStudents = null;

    /**
     * Unit instance
     *
     * @var UnitInstance
     */
    private $unit = null;

    /**
     * An associative array of userId/average values
     *
     * @var array
     */
    private $allAverages = array();

    /**
     * Constructor
     *
     * @param Collection $allStudents
     * @param UnitInstance $unit
     */
    public function __construct(Collection $allStudents, UnitInstance $unit) {
        $this->setAllStudents($allStudents);
        $this->setUnit($unit);

        $this->process();
    }

    /**
     * Process
     */
    private function process() {
        $results = new Results($this->getAllStudents(), $this->getUnit());
        $allTasks = $results->getAllValues();

        $allUsers = array();

        /* Find all the results for each user and regroup
         * all the normalised grade values on a per-user
         * basis instead of a per-task basis
         */
        foreach ($allTasks as $currentTask) {
            foreach ($currentTask as $currentResult) {
                $allUsers[$currentResult['user']->getId()][] = $currentResult['value'];
            }
        }

        /* Tally averages
         */
        $allAverages = array();

        foreach ($allUsers as $userId => $currentUser) {
            $num = count($currentUser);

            if ($num) {
                $average = array_sum($currentUser) / $num;

                $allAverages[$userId] = $average;
            }
        }

        $this->setAllAverages($allAverages);
    }

    /**
     * Sort in ascending order
     *
     * @return array
     */
    public function findAscending() {
        $temp = $this->getAllAverages();

        uasort($temp, function($a, $b) {
            return $a - $b;
        });

        return $temp;
    }

    /**
     * Sort in descending order
     *
     * @return array
     */
    public function findDescending() {
        $temp = $this->getAllAverages();

        uasort($temp, function($a, $b) {
            return $b - $a;
        });

        return $temp;
    }

    /* Getters/Setters
     */

    /**
     * Set all students
     *
     * @param \Schoolbox\Collection $allStudents
     */
    private function setAllStudents($allStudents)
    {
        $this->allStudents = $allStudents;
    }

    /**
     * Get all students
     *
     * @return \Schoolbox\Collection
     */
    private function getAllStudents()
    {
        return $this->allStudents;
    }

    /**
     * Set unit
     *
     * @param UnitInstance $unit
     */
    private function setUnit($unit)
    {
        $this->unit = $unit;
    }

    /**
     * Get unit
     *
     * @return UnitInstance
     */
    private function getUnit()
    {
        return $this->unit;
    }

    /**
     * Set all averages
     *
     * @param array $allAverages
     */
    private function setAllAverages($allAverages)
    {
        $this->allAverages = $allAverages;
    }

    /**
     * Get all averages
     *
     * @return array
     */
    private function getAllAverages()
    {
        return $this->allAverages;
    }
}