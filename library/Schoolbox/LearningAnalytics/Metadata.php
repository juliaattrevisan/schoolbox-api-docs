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
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Alaress_Date_Format;

class Metadata {

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
     * Start of date range filter
     *
     * @var Alaress_Date_Format
     */
    protected $from;

    /**
     * End of date range filter
     *
     * @var Alaress_Date_Format
     */
    protected $to;

    /**
     * Metadata constructor.
     *
     * @param $params
     * @param Folder $courseFolder
     * @param Collection $allFoldersUsingCourse
     * @param Collection $allUnitInstances
     * @param Alaress_Date_Format $from
     * @param Alaress_Date_Format $to
     */
    public function __construct($params, Folder $courseFolder, Collection $allFoldersUsingCourse, Collection $allUnitInstances, Alaress_Date_Format $from, Alaress_Date_Format $to) {
        $this->setParams($params);

        $this->setCourseFolder($courseFolder);
        $this->setPageTitle($this->getCourseFolder()->getName());

        $this->setAllUnitInstances($allUnitInstances);
        $this->setAllFolders($allFoldersUsingCourse);
        $this->setAllAggregateFolders(new Collection());

        $this->setFrom($from);
        $this->setTo($to);

        $this->process();
    }

    /**
     * Process
     */
    protected function process() {
        $folderMapper = new FolderMapper();
        $params = $this->getParams();

        /* Only aggregate for one folder in 'folder' route
         * That is defined by having a $params['folderId']
         *
         * Otherwise aggregate for all folders that used the same course
         */
        try {
            $this->setSelectedFolder($folderMapper->findById($params['folderId']));
            $this->getAllAggregateFolders()->add($this->getSelectedFolder());
        }
        catch (\Exception $e) {
            $this->setAllAggregateFolders($this->getAllFolders());
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

    /**
     * Get the selected task type to filter by
     *
     * @return mixed
     */
    public function getSelectedTaskIdFilter() {
        return $this->params['taskId'];
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
    public function getAllUnitInstances()
    {
        return $this->allUnitInstances;
    }

    /**
     * Set course folder
     *
     * @param Folder $courseFolder
     */
    protected function setCourseFolder($courseFolder)
    {
        $this->courseFolder = $courseFolder;
    }

    /**
     * Get course folder
     *
     * @return Folder
     */
    public function getCourseFolder()
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
    public function getParams()
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
     * @param Folder $selectedFolder
     */
    protected function setSelectedFolder($selectedFolder)
    {
        $this->selectedFolder = $selectedFolder;
    }

    /**
     * Get selected folder
     *
     * @return Folder
     */
    public function getSelectedFolder()
    {
        return $this->selectedFolder;
    }

    /**
     * Get From
     *
     * @return Alaress_Date_Format
     */
    protected function getFrom()
    {
        return $this->from;
    }

    /**
     * Set From
     *
     * @param Alaress_Date_Format $from
     */
    protected function setFrom($from)
    {
        $this->from = $from;
    }

    /**
     * Get To
     *
     * @return Alaress_Date_Format
     */
    protected function getTo()
    {
        return $this->to;
    }

    /**
     * Set To
     *
     * @param Alaress_Date_Format $to
     */
    protected function setTo($to)
    {
        $this->to = $to;
    }
}