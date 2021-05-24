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
use Schoolbox\Learning\Assessment\WorkType;

/**
 * Creates a bridge class for a canvas entity
 */
class BridgeFactory
{
    /** @var string */
    private $siteLocation;

    private const BASE = "External\\Canvas\\Sync\\Bridge\\";

    /**
     * Constructor
     * @param string $siteLocation
     */
    public function __construct(string $siteLocation)
    {
        $this->siteLocation = ucfirst($siteLocation);
    }

    /**
     * Generates class name depending on the site location
     * @param string $filename
     * @return string
     */
    private function getClassName(string $filename): string
    {
        $className = self::BASE . $this->siteLocation . '\\' . $filename;
        if (!class_exists($className)) {
            $className = self::BASE . $filename;
        }
        return $className;
    }

    /**
     * Gets class for assignment
     * @param GenericCollection $markTypes
     * @param GenericCollection $workTypes
     * @return Assignment
     */
    public function getAssignment(GenericCollection $markTypes, GenericCollection $workTypes): Assignment
    {
        $className = $this->getClassName('Assignment');
        return new $className($markTypes, $workTypes);
    }

    /**
     * Gets class for course
     * @param IConnector $canvasDb
     * @param IConnector $schoolboxDb
     * @return Course
     */
    public function getCourse(IConnector $canvasDb, IConnector $schoolboxDb): Course
    {
        $className = $this->getClassName('Course');
        return new $className($canvasDb, $schoolboxDb);
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
        $className = $this->getClassName('CourseSection');
        return new $className($canvasDb, $schoolboxDb, $folderMapper, $courseBridge, $log);
    }
}
