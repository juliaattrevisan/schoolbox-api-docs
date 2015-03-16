<?php
/**
 * Metadata
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics\Metadata;

use Schoolbox\Collection;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Resource\Category as Folder;

abstract class Base {

    /**
     * A Collection of UnitInstances
     *
     * @var Collection
     */
    protected $allUnitInstances = null;

    /**
     * Default title of the page
     *
     * @var string
     */
    protected $pageTitle = 'Statistics';

    /**
     * A Collection of Folder instances which are using the same Course
     *
     * @var null
     */
    protected $allFolders = null;

    /**
     * The originating Course folder, used to spawn the Units used in the
     * current Folder
     *
     * @var Folder
     */
    protected $courseFolder = null;

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
    protected function process() {

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
    protected function setAllFolders($allFolders)
    {
        $this->allFolders = $allFolders;
    }

    /**
     * Get all folders
     *
     * @return null
     */
    protected function getAllFolders()
    {
        return $this->allFolders;
    }

    /**
     * Set all unit instances
     *
     * @param \Schoolbox\Collection $allUnitInstances
     */
    protected function setAllUnitInstances($allUnitInstances)
    {
        $this->allUnitInstances = $allUnitInstances;
    }

    /**
     * Get all unit instances
     *
     * @return \Schoolbox\Collection
     */
    protected function getAllUnitInstances()
    {
        return $this->allUnitInstances;
    }

    /**
     * Set course folder
     *
     * @param \Schoolbox\LearningAnalytics\Folder $courseFolder
     */
    protected function setCourseFolder($courseFolder)
    {
        $this->courseFolder = $courseFolder;
    }

    /**
     * Get course folder
     *
     * @return \Schoolbox\LearningAnalytics\Folder
     */
    protected function getCourseFolder()
    {
        return $this->courseFolder;
    }

    /**
     * Set page title
     *
     * @param string $pageTitle
     */
    protected function setPageTitle($pageTitle)
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