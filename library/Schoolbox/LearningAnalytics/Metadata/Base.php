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
use Schoolbox\Resource\CategoryMapper as FolderMapper;

abstract class Base {

    /**
     * An array of params from aura
     * 
     * @var array
     */
    protected $params = array();

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
     * This is used for navigational purposes
     *
     * @var Collection
     */
    protected $allFolders = null;

    /**
     * A Collection of Folder instances which should be used to aggregate
     * numeric data (a subset of $allFolders)
     *
     * @var Collection
     */
    protected $allAggregateFolders = null;

    /**
     * The originating Course folder, used to spawn the Units used in the
     * current Folder
     *
     * @var Folder
     */
    protected $courseFolder = null;

    /**
     * The folder corresponding to the id in the url
     *
     * @var Folder
     */
    protected $selectedFolder = null;

    /**
     * Constructor
     *
     * @param array $params
     * @param Collection $allUnitInstances
     */
    public function __construct($params, Collection $allUnitInstances) {
        $this->setParams($params);

        $this->setAllUnitInstances($allUnitInstances);
        $this->setAllFolders(new Collection());
        $this->setAllAggregateFolders(new Collection());

        $this->process();
    }

    /**
     * Process
     */
    protected function process() {
        $folderMapper = new FolderMapper();
        $params = $this->getParams();

        try {
            $this->setSelectedFolder($folderMapper->findById($params['folderId']));
        }
        catch (\Exception $e) {

            /* TODO: Error, folder does not exist
             */
        }

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

                /* Only aggregate for one folder in 'folder' route
                 * Otherwise aggregate for all folders
                 */
                if ($this->getRoute() == 'folder') {
                    $this->getAllAggregateFolders()->add($this->getSelectedFolder());
                }
                else {
                    $this->setAllAggregateFolders($this->getAllFolders());
                }
            }
            catch (\Exception $e) {
            }
        }
    }

    /**
     * Get route
     *
     * Used to determine which screen you wanted (either 'folder' or 'course')
     * Each screen uses slightly different data
     *
     * Passed directly through to twig
     *
     * @return string
     */
    public function getRoute() {
        return $this->params['route'];
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
    public function getAllFolders()
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

    /**
     * Set params
     *
     * @param array $params
     */
    protected function setParams($params)
    {
        $this->params = $params;
    }

    /**
     * Get params
     *
     * @return array
     */
    protected function getParams()
    {
        return $this->params;
    }

    /**
     * Set all aggregate folders
     *
     * @param \Schoolbox\Collection $allAggregateFolders
     */
    protected function setAllAggregateFolders($allAggregateFolders)
    {
        $this->allAggregateFolders = $allAggregateFolders;
    }

    /**
     * Get all aggregate folders
     *
     * @return \Schoolbox\Collection
     */
    public function getAllAggregateFolders()
    {
        return $this->allAggregateFolders;
    }

    /**
     * Set selected folder
     *
     * @param \Schoolbox\Resource\Category $selectedFolder
     */
    protected function setSelectedFolder($selectedFolder)
    {
        $this->selectedFolder = $selectedFolder;
    }

    /**
     * Get selected folder
     *
     * @return \Schoolbox\Resource\Category
     */
    public function getSelectedFolder()
    {
        return $this->selectedFolder;
    }
}