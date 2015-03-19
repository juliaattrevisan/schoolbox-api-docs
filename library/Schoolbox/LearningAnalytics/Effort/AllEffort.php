<?php
/**
 * AllEffort
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics\Effort;

use Schoolbox\LearningAnalytics\Registry;
use Schoolbox\LearningAnalytics\Metadata\Base as Metadata;
use Schoolbox\LearningAnalytics\Results\Averages;
use Schoolbox\Resource\CategoryLogMapper as FolderMapper;

class AllEffort {

    /**
     * An instance of LearningAnalytics\Registry
     *
     * @var Registry
     */
    private $registry = null;

    /**
     * Request metadata
     *
     * @var Metadata
     */
    private $metadata = null;

    /**
     * All the grade averages for students
     *
     * @var Averages
     */
    private $allAverages = null;

    /**
     * An associative array of hits-gradeAverage data
     * for a user
     *
     * @var array
     */
    private $tally = array();

    /**
     * Constructor
     *
     * @param Registry $registry
     * @param Metadata $metadata
     * @param Averages $allAverages
     */
    public function __construct(Registry $registry, Metadata $metadata, Averages $allAverages) {
        $this->setRegistry($registry);
        $this->setMetadata($metadata);
        $this->setAllAverages($allAverages);

        $this->process();
    }

    /**
     * Process
     */
    private function process() {

        /* Find a unified list of all students who participated in this course
         */
        $unified = array();

        foreach ($this->getRegistry()->getAllStudentRegistry() as $currentStudentCollection) {
            foreach ($currentStudentCollection as $currentStudent) {
                $unified[$currentStudent->getId()] = $currentStudent;
            }
        }

        /* Find all the hits this person made to each homepage using the course
         */
        $folderLogMapper = new FolderMapper();

        /* Unify $allGradeAverages and $tally
         */
        $allGradeAverages = $this->getAllAverages()->findDescending('average');
        $tally = array();

        foreach ($unified as $userId => $user) {
            $tally[$userId]['user'] = $user;
            $tally[$userId]['average'] = 0;
            $tally[$userId]['hits'] = 0;

            foreach($this->getMetadata()->getAllAggregateFolders() as $currentFolder) {
                $allHits = $folderLogMapper->findByColumnList(array(
                    'user' => $userId,
                    'cid' => $currentFolder->getId()
                ));

                foreach ($allHits as $currentHit) {
                    $tally[$userId]['hits'] += $currentHit->getHitCount();
                    $tally[$userId]['average'] = (float)$allGradeAverages[$userId]['average'];
                }
            }
        }

        $this->setTally($tally);
    }

    /* Getters/Setters
     */

    /**
     * Set registry
     *
     * @param \Schoolbox\LearningAnalytics\Registry $registry
     */
    private function setRegistry($registry)
    {
        $this->registry = $registry;
    }

    /**
     * Get registry
     *
     * @return \Schoolbox\LearningAnalytics\Registry
     */
    private function getRegistry()
    {
        return $this->registry;
    }

    /**
     * Set all averages
     *
     * @param \Schoolbox\LearningAnalytics\Results\Averages $allAverages
     */
    private function setAllAverages($allAverages)
    {
        $this->allAverages = $allAverages;
    }

    /**
     * Get all averages
     *
     * @return \Schoolbox\LearningAnalytics\Results\Averages
     */
    private function getAllAverages()
    {
        return $this->allAverages;
    }

    /**
     * Set metadata
     *
     * @param \Schoolbox\LearningAnalytics\Metadata\Base $metadata
     */
    private function setMetadata($metadata)
    {
        $this->metadata = $metadata;
    }

    /**
     * Get metadata
     *
     * @return \Schoolbox\LearningAnalytics\Metadata\Base
     */
    private function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * Set tally
     *
     * @param array $tally
     */
    private function setTally($tally)
    {
        $this->tally = $tally;
    }

    /**
     * Get tally
     *
     * @return array
     */
    public function getTally()
    {
        return $this->tally;
    }
}