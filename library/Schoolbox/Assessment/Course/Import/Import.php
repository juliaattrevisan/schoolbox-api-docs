<?php

namespace Schoolbox\Assessment\Course\Import;

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Assessment\Course\Mapper as CourseMapper;
use Schoolbox\Assessment\Course\Unit\Model as Unit;
use Schoolbox\Assessment\Course\Unit\Mapper as UnitMapper;

/**
 * Import
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Import
{
    /** @var CourseMapper */
    private $courseMapper;

    /** @var UnitMapper */
    private $unitMapper;

    /** @var FolderMapper */
    private $folderMapper;


    /**
     * Constructor
     * TODO: Can possibly just inject these from Controller
     */
    public function __construct()
    {
        $this->setCourseMapper(CourseMapper::getInstance());
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
    public function importClasses($query)
    {
        $data = [];
        $allFolders = $this->getFolderMapper()->searchByName($query);

        /* Swap keys for selectize compatibility -_-
         */
        foreach ($allFolders as $currentFolder) {
            $name = $currentFolder->getName();
            if ($currentFolder->getCodeString()) {
                $name .= " ({$currentFolder->getCodeString()})";
            }

            $data[] = [
                'id' => $currentFolder->getId(),
                'name' => $name,
            ];
        }

        return $data;
    }

    /**
     * Generate selectize data for the main import screen
     *
     * @param $courseId
     * @param $classCode
     * @return array
     */
    public function importSelectize($courseId, $classCode)
    {
        $data = [

            /* Course Folder
             */
            'folder'       => null,

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
            if ($courseId) {
                $course = $this->getCourseMapper()->findById($courseId);
                $course->setParent($this->getFolderMapper()->findById($course->getParent()->getId()));

                $data['folder'] = $course;

                $data['sources'] = [
                    'id'   => $course->getId(),
                    'name' => $course->getName()
                ];

                /* Bolt on the Unit/Task data
                 */
                $data = array_merge($data, $this->findUnitsAndTasks($courseId));
            }
        } catch (\Exception $e) {

            /* TODO: No course found
             */
        }

        /* A class was already chosen
         */
        try {
            if ($classCode) {
                try {
                    $course = $this->getFolderMapper()->findByCode($classCode);
                } catch (MapperException $e) {
                    $course = $this->getFolderMapper()->findById($classCode);
                }
                $pair   = $course->findValueLabelPair();

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
     * Get course mapper
     *
     * @return CourseMapper
     */
    private function getCourseMapper()
    {
        return $this->courseMapper;
    }

    /**
     * Set course mapper
     *
     * @param CourseMapper $courseMapper
     * @return self
     */
    private function setCourseMapper($courseMapper)
    {
        $this->courseMapper = $courseMapper;
        return $this;
    }

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

}
