<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */

declare(strict_types=1);

namespace External\Canvas\Sync;

use Alaress\Database\Connector\IConnector;
use External\Canvas\Sync\Bridge\Assignment;
use External\Canvas\Sync\Bridge\Course;
use External\Canvas\Sync\Bridge\CourseSection;
use Psr\Log\LoggerInterface;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;

/**
 * Creates a bridge class for a canvas entity
 */
class BridgeFactory
{
    /** @var string */
    private string $siteSchoolCode;

    /**
     * Constructor
     * @param string $siteSchoolCode
     */
    public function __construct(string $siteSchoolCode)
    {
        $this->siteSchoolCode = ucfirst(strtolower($siteSchoolCode));
    }

    /**
     * Gets class for assignment
     * @param GenericCollection $markTypes
     * @param GenericCollection $workTypes
     * @return Assignment
     */
    public function getAssignment(GenericCollection $markTypes, GenericCollection $workTypes): Assignment
    {
        $class = sprintf('External\Canvas\Sync\Bridge\%s\Assignment', ucfirst($this->siteSchoolCode));
        if (class_exists($class)) {
            return new $class($markTypes, $workTypes);
        }
        return new Bridge\Assignment($markTypes, $workTypes);
    }

    /**
     * Gets class for course
     * @param IConnector $canvasDb
     * @param IConnector $schoolboxDb
     * @return Course
     */
    public function getCourse(IConnector $canvasDb, IConnector $schoolboxDb): Course
    {
        $class = sprintf('External\Canvas\Sync\Bridge\%s\Course', ucfirst($this->siteSchoolCode));
        if (class_exists($class)) {
            return new $class($canvasDb, $schoolboxDb);
        }
        return new Bridge\Course($canvasDb, $schoolboxDb);
    }

    /**
     * Gets class for course section
     * @param IConnector      $canvasDb
     * @param IConnector      $schoolboxDb
     * @param Folder\Mapper   $folderMapper
     * @param Course          $courseBridge
     * @param LoggerInterface $log
     * @return CourseSection
     */
    public function getCourseSection(
        IConnector $canvasDb,
        IConnector $schoolboxDb,
        Folder\Mapper $folderMapper,
        Course $courseBridge,
        LoggerInterface $log
    ): CourseSection {
        $class = sprintf('External\Canvas\Sync\Bridge\%s\CourseSection', ucfirst($this->siteSchoolCode));
        if (class_exists($class)) {
            return new $class($canvasDb, $schoolboxDb, $folderMapper, $courseBridge, $log);
        }
        return new Bridge\CourseSection($canvasDb, $schoolboxDb, $folderMapper, $courseBridge, $log);
    }
}
