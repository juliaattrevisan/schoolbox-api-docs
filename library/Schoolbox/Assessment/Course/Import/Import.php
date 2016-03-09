<?php

namespace Schoolbox\Assessment\Course\Import;

use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Assessment\Course\Mapper as CourseMapper;
use Schoolbox\Assessment\Course\TaskMapper;

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
    /**
     * A CourseMapper instance
     *
     * @var CourseMapper
     */
    private $courseMapper = null;

    /**
     * A FolderMapper instance
     *
     * @var FolderMapper
     */
    private $folderMapper = null;

    /**
     * A TaskMapper instance
     *
     * @var TaskMapper
     */
    private $taskMapper = null;

    /**
     * Constructor
     * TODO: Can possibly just inject these from Controller
     */
    public function __construct()
    {
        $this->setCourseMapper(CourseMapper::getInstance());
        $this->setFolderMapper(FolderMapper::getInstance());
        $this->setTaskMapper(TaskMapper::getInstance());
    }
    /**
     * Get a list of all the Folders which should
     * receive the selected Course
     */
    public function importClasses($query)
    {
        $data = [];
        $allFolders = $this->getFolderMapper()->findByName($query);

        /* Swap keys for selectize compatibility -_-
         */
        foreach ($allFolders as $currentFolder) {
            $data[] = [
                'id' => $currentFolder->getId(),
                'name' => $currentFolder->getName()
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
                $folder = $this->getCourseMapper()->findCourseById($courseId);
                $folder->setParent($this->getCourseMapper()->findById($folder->getParent()->getId()));

                $data['folder'] = $folder;

                $data['sources'] = [
                    'id'   => $folder->getId(),
                    'name' => $folder->getName()
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
                $folder = $this->getFolderMapper()->findByCode($classCode);
                $pair   = $folder->findValueLabelPair();

                $data['destinations'] = [
                    'id'   => $pair['value'],
                    'name' => $pair['label'],
                ];
            }
        } catch (\Exception $e) {

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
            $data['units'] = $this->getCourseMapper()->findByParent($courseId);

            foreach ($data['units'] as $unit) {
                $data['tasks'][$unit->getId()] = $this->getTaskMapper()->findByCategory($unit);
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
     */
    private function setCourseMapper($courseMapper)
    {
        $this->courseMapper = $courseMapper;
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
     */
    private function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }

    /**
     * Get task mapper
     *
     * @return null
     */
    private function getTaskMapper()
    {
        return $this->taskMapper;
    }

    /**
     * Set task mapper
     *
     * @param null $taskMapper
     */
    private function setTaskMapper($taskMapper)
    {
        $this->taskMapper = $taskMapper;
    }
}