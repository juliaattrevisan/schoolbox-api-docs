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
use Schoolbox\Learning\Course\DataModel\Implementation;
use Schoolbox\Learning\Course\DataModel\Specification;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder;

class Metadata
{

    /**
     * The param bag from Aura
     * 
     * @var array
     */
    protected $params = array();

    /**
     * The siblings of selectedCourseImplementation (including that implementation as well)
     *
     * @var Collection|Implementation\Unit\Model[]
     */
    protected $unitImplementations;

    /**
     * The course specification that the selectedCourseImplementation was spawned from
     *
     * @var Specification\Model
     */
    protected $courseSpecification;

    /**
     * The class folder (optional)
     *
     * @var Folder\Model
     */
    protected $selectedClassFolder;

    /**
     * Selected Term to filter
     *
     * @var Term
     */
    protected $term;

    /**
     * The name of the Aura route that was spawned
     *
     * @var string
     */
    private $routeUsed;


    /**
     * Metadata constructor.
     *
     * @param array                                  $params               bag of holding
     * @param Specification\Model                    $courseSpecification  spec from which all data on this page inherits
     * @param Collection|Implementation\Unit\Model[] $unitImplementations  units that match the term filter
     * @param Term                                   $term                 term to filter
     * @param Folder\Model                           $selectedClassFolder  if given, the class folder to filter
     * @param string                                 $routeUsed            pass directly through to twig for route lookups
     */
    public function __construct(array $params, Specification\Model $courseSpecification, Collection $unitImplementations, Term $term, $routeUsed, Folder\Model $selectedClassFolder = null)
    {
        $this->setParams($params);

        $this->setSelectedClassFolder($selectedClassFolder);

        $this->setUnitImplementations($unitImplementations);
        $this->setCourseSpecification($courseSpecification);

        $this->setTerm($term);
        $this->setRouteUsed($routeUsed);
    }

    /**
     * Get route
     *
     * Used to determine which screen you wanted (either 'folder' or 'course')
     * Each screen uses slightly different data
     *
     * Passed directly through to twig
     *
     * @return string|null
     */
    public function getRoute()
    {
        return $this->params['route'];
    }

    /**
     * Get the selected task type to filter by
     *
     * @return string|null
     */
    public function getSelectedActivityTypeFilter()
    {
        return $this->params['taskId'];
    }

    /* Getters/Setters
     */

    /**
     * Set all folders
     *
     * @param Specification\Model $courseSpecification
     */
    protected function setCourseSpecification(Specification\Model $courseSpecification)
    {
        $this->courseSpecification = $courseSpecification;
    }

    /**
     * Get all folders
     *
     * @return Specification\Model
     */
    public function getCourseSpecification()
    {
        return $this->courseSpecification;
    }

    /**
     * Set all unit instances
     *
     * @param \Schoolbox\Collection|Implementation\Unit\Model[] $unitImplementations
     */
    protected function setUnitImplementations(Collection $unitImplementations)
    {
        $this->unitImplementations = $unitImplementations;
    }

    /**
     * Get the unique list of folders that relate to the unit implementations held internally
     *
     * @return Collection|Folder\Model[]
     */
    public function getUnitImplementationFolders()
    {
        $folders = [];

        foreach ($this->getUnitImplementations()->getColumnValues('folder') as $folder) {
            /** @var Folder\Model $folder */
            if($folder->getId()) {
                $folders[$folder->getId()] = $folder;
            }
        }

        return new Collection($folders);
    }


    /**
     * Get the unique list of units that relate to the given folder
     *
     * @return Collection|Implementation\Unit\Model[]
     */
    public function getSelectedFolderUnitImplementations()
    {
        if (!$this->hasSelectedClassFolder()) {
            return new Collection();
        }

        $units = [];

        foreach ($this->getUnitImplementations() as $unit) {
            if ($unit->getFolder()->getId() == $this->getSelectedClassFolder()->getId()) {
                $units[$unit->getId()] = $unit;
            }
        }

        return new Collection($units);
    }


    /**
     * @return bool
     */
    public function hasSelectedClassFolder()
    {
        return $this->getSelectedClassFolder() instanceof Folder\Model;
    }


    /**
     * Get all unit instances
     *
     * @return \Schoolbox\Collection|Implementation\Unit\Model[]
     */
    public function getUnitImplementations()
    {
        return $this->unitImplementations;
    }

    /**
     * Set course folder
     *
     * @param Folder\Model $selectedClassFolder
     */
    protected function setSelectedClassFolder(Folder\Model $selectedClassFolder)
    {
        $this->selectedClassFolder = $selectedClassFolder;
    }

    /**
     * Get course folder
     *
     * @return Folder\Model
     */
    public function getSelectedClassFolder()
    {
        return $this->selectedClassFolder;
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
     * Get term
     *
     * @return Term
     */
    public function getTerm()
    {
        return $this->term;
    }

    /**
     * Set term
     *
     * @param Term $term
     */
    protected function setTerm($term)
    {
        $this->term = $term;
    }

    /**
     * @return string
     */
    public function getRouteUsed()
    {
        return $this->routeUsed;
    }

    /**
     * @param string $routeUsed
     * @return static
     */
    public function setRouteUsed($routeUsed)
    {
        $this->routeUsed = $routeUsed;
        return $this;
    }

}
