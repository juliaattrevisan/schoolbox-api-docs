<?php
/**
 * Metadata
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics;

use Schoolbox\Collection;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Resource\Category as Folder;

class Metadata {

    /**
     * A Collection of UnitInstances
     *
     * @var Collection
     */
    private $allUnitInstances = null;

    /**
     * Title of the page
     *
     * @var string
     */
    private $pageTitle = 'Course Statistics';

    /**
     * A Collection of Folder instances which are using the same Course
     *
     * @var null
     */
    private $allFolders = null;

    /**
     * The originating Course folder, used to spawn the Units used in the
     * current Folder
     *
     * @var Folder
     */
    private $courseFolder = null;

    /**
     * Constructor
     *
     * @param Collection $allUnitInstances
     */
    public function __construct(Collection $allUnitInstances) {
        $this->setAllUnitInstances($allUnitInstances);

        $this->process();
    }

    /**
     * Process
     */
    private function process() {

        /* Find title of the course by deriving the parent folder
         * of a unit_category_id
         */
        if ($this->getAllUnitInstances()->count()) {
            try {
                $unitInstanceMapper = new UnitInstanceMapper();

                /* Assumption: A Folder using a Course cannot have Units from
                 * a different Course (no hybrid Courses)
                 *
                 * Under that assumption, all Units associated with a Folder
                 * must have come from the same originating Course Folder
                 */
                $firstUnit = $this->getAllUnitInstances()->first();

                $this->setCourseFolder($unitInstanceMapper->findCourseForUnit($firstUnit));
                $this->setAllFolders($unitInstanceMapper->findAllFoldersUsingCourse($this->getCourseFolder()));
                $this->setPageTitle($this->getCourseFolder()->getName());
            }
            catch (\Exception $e) {
            }
        }
    }

    /* Getters/Setters
     */

    /**
     * Set all folders
     *
     * @param null $allFolders
     */
    private function setAllFolders($allFolders)
    {
        $this->allFolders = $allFolders;
    }

    /**
     * Get all folders
     *
     * @return null
     */
    private function getAllFolders()
    {
        return $this->allFolders;
    }

    /**
     * Set all unit instances
     *
     * @param \Schoolbox\Collection $allUnitInstances
     */
    private function setAllUnitInstances($allUnitInstances)
    {
        $this->allUnitInstances = $allUnitInstances;
    }

    /**
     * Get all unit instances
     *
     * @return \Schoolbox\Collection
     */
    private function getAllUnitInstances()
    {
        return $this->allUnitInstances;
    }

    /**
     * Set course folder
     *
     * @param \Schoolbox\LearningAnalytics\Folder $courseFolder
     */
    private function setCourseFolder($courseFolder)
    {
        $this->courseFolder = $courseFolder;
    }

    /**
     * Get course folder
     *
     * @return \Schoolbox\LearningAnalytics\Folder
     */
    private function getCourseFolder()
    {
        return $this->courseFolder;
    }

    /**
     * Set page title
     *
     * @param string $pageTitle
     */
    private function setPageTitle($pageTitle)
    {
        $this->pageTitle = $pageTitle;
    }

    /**
     * Get page title
     *
     * @return string
     */
    public function getPageTitle()
    {
        return $this->pageTitle;
    }
}