<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Assessment\Course\Import;

use Schoolbox\Learning\Course;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Assessment\Course\Unit\DeprecatedModel as Unit;
use Schoolbox\Assessment\Course\Unit\DeprecatedMapper as UnitMapper;
use Schoolbox\Permission\Access\Access;


class Import
{

    /** @var UnitMapper */
    private $unitMapper;

    /** @var FolderMapper */
    private $folderMapper;

    /** @var Access */
    private $access;

    /**
     * Constructor
     * TODO: Can possibly just inject these from Controller
     */
    public function __construct()
    {
        $this->setAccess(Access::getInstance());
        $this->setFolderMapper(FolderMapper::getInstance());
        $this->setUnitMapper(UnitMapper::getInstance());
    }

    /**
     * Get a list of all the Folders which should
     * receive the selected Course
     *
     * Searches by both name and class code
     *
     * @param string $query
     * @return array
     */
    public function findMatchingClasses($query)
    {
        $data = [];
        $allFolders = $this->getFolderMapper()->searchByName($query);

        /* Swap keys for selectize compatibility -_-
         */
        foreach ($allFolders as $currentFolder) {

            // Make sure the user has access to the folder before we return it
            if ($this->getAccess()->getFolderAccess($_SESSION["user"], [$currentFolder->getId()]) >= UserAccess::ACCESS_WRITE) {

                $data[] = [
                    'id' => $currentFolder->getId(),
                    'name' => $currentFolder->getTitle(),
                ];
            }
        }

        return $data;
    }

    /**
     * Generate selectize data for the main import screen
     *
     * @param Course\Specification $courseSpec
     * @param Folder\Model         $targetClass
     * @return array
     * @deprecated 165
     */
    public function importSelectize(Course\Specification $courseSpec = null, Folder\Model $targetClass = null)
    {
        $data = [

            /* Course object
             */
            'course'       => null,

            /* All Units associated with this Course
             */
            'units'        => [],

            /* All Tasks associated with Units
             */
            'tasks'        => [],

            /* Course Folder as selectize-compatible
             */
            'sources'      => [],

            /* Folder to which this Course shall be applied
             */
            'destinations' => []
        ];

        /* A course was already chosen
         */
        try {

            if ($courseSpec) {

                $data['course'] = $courseSpec;

                $data['sources'] = [
                    'id'   => $courseSpec->getFolder()->getId(),
                    'name' => $courseSpec->getFolder()->getTitle()
                ];

                /* Bolt on the Unit/Task data
                 */
                $data = array_merge($data, $this->findUnitsAndTasks($courseSpec->getId()));
            }
        } catch (\Exception $e) {

            /* TODO: No course found
             */
        }

        /* A class was already chosen
         */
        try {
            if ($targetClass) {
                $pair = $targetClass->findValueLabelPair();

                $data['destinations'] = [
                    'id'   => $pair['value'],
                    'name' => $pair['label'],
                ];
            }
        } catch (MapperException $e) {

            /* TODO: No destination found
             */
        }

        return $data;
    }

    /**
     * Find Units and Tasks for a particular Course
     *
     * @param $courseId
     * @return array
     */
    public function findUnitsAndTasks($courseId)
    {
        $data = [

            /* All Units associated with this Course
             */
            'units' => [],

            /* All Tasks associated with Units
             */
            'tasks' => [],
        ];

        /* A course was already chosen
         */
        try {
            $data['units'] = $this->getUnitMapper()->findByCourseId($courseId);

            foreach ($data['units'] as $unit) {
                /** @var Unit $unit */
                $data['tasks'][$unit->getId()] = $unit->getTasks();
            }
        } catch (\Exception $e) {

            /* TODO: No course found
             */
        }

        return $data;
    }

    /* Getters/Setters
     */


    /**
     * @return UnitMapper
     */
    public function getUnitMapper()
    {
        return $this->unitMapper;
    }

    /**
     * @param UnitMapper $unitMapper
     * @return self
     */
    public function setUnitMapper($unitMapper)
    {
        $this->unitMapper = $unitMapper;
        return $this;
    }

    /**
     * Get folder mapper
     *
     * @return FolderMapper
     */
    private function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * Set folder mapper
     *
     * @param FolderMapper $folderMapper
     * @return self
     */
    private function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
        return $this;
    }

    /**
     * @return Access
     */
    private function getAccess()
    {
        return $this->access;
    }

    /**
     * @param Access $access
     * @return self
     */
    private function setAccess($access)
    {
        $this->access = $access;
        return $this;
    }    

}
