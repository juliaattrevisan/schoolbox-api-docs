<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Model as Folder;

class Metadata
{

    /**
     * The siblings of selectedCourseImplementation (including that implementation as well)
     *
     * @var GenericCollection|UnitImplementation[]
     */
    private $unitImplementations;

    /**
     * The class folder (optional)
     *
     * @var null|Folder
     */
    private $selectedClassFolder;


    /**
     * @param Collection|UnitImplementation[]  $unitImplementations  units that match the term filter
     * @param Folder                           $selectedClassFolder  if given, the class folder to filter
     */
    public function __construct(Collection $unitImplementations, Folder $selectedClassFolder = null)
    {
        $this->selectedClassFolder = $selectedClassFolder;
        $this->unitImplementations = $unitImplementations;
    }


    /* Getters/Setters
     */

    /**
     * Get the unique list of folders that relate to the unit implementations held internally
     *
     * @return Collection|Folder[]
     */
    public function getUnitImplementationFolders()
    {
        $folders = [];

        foreach ($this->unitImplementations->getColumnValues('folder') as $folder) {
            /** @var Folder $folder */
            if($folder->getId()) {
                $folders[$folder->getId()] = $folder;
            }
        }

        return new Collection($folders);
    }


    /**
     * Get the unique list of units that relate to the given folder
     *
     * @return Collection|UnitImplementation[]
     */
    public function getSelectedFolderUnitImplementations()
    {
        if (!$this->hasSelectedClassFolder()) {
            return new Collection();
        }

        $units = [];

        foreach ($this->unitImplementations as $unit) {
            if ($unit->getFolder()->getId() === $this->selectedClassFolder->getId()) {
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
        return $this->selectedClassFolder instanceof Folder;
    }
}
