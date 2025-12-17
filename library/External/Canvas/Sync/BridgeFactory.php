<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */

declare(strict_types=1);

namespace External\Canvas\Sync;

use Alaress\Database\Connector\IConnector;
use External\Canvas\Importer\CanvasDb;
use External\Canvas\Sync\Bridge\Assignment;
use External\Canvas\Sync\Bridge\AssignmentDates;
use External\Canvas\Sync\Bridge\Course;
use External\Canvas\Sync\Bridge\CourseSection;
use Psr\Log\LoggerInterface;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;

/**
 * @deprecated, not overloading for each school now...
 * Creates a bridge class for a canvas entity
 */
class BridgeFactory
{
    /**
     * Constructor
     * @param CanvasDb $canvasDb
     */
    public function __construct(private CanvasDb $canvasDb)
    {
    }

    /**
     * Gets class for assignment
     * @param GenericCollection $markTypes
     * @param GenericCollection $workTypes
     * @return Assignment
     */
    public function getAssignment(GenericCollection $markTypes, GenericCollection $workTypes): Assignment
    {
        return new Bridge\Assignment($markTypes, $workTypes, $this->canvasDb);
    }

    /**
     * Gets class for assignmentDates
     * @return AssignmentDates
     */
    public function getAssignmentDates(): AssignmentDates
    {
        return new Bridge\AssignmentDates();
    }

    /**
     * Gets class for course
     * @param CanvasDb $canvasDb
     * @param IConnector $schoolboxDb
     * @return Course
     */
    public function getCourse(CanvasDb $canvasDb, IConnector $schoolboxDb): Course
    {
        return new Bridge\Course($canvasDb, $schoolboxDb);
    }

    /**
     * Gets class for course section
     * @param CanvasDb        $canvasDb
     * @param IConnector      $schoolboxDb
     * @param Folder\Mapper   $folderMapper
     * @param Course          $courseBridge
     * @param LoggerInterface $log
     * @return CourseSection
     */
    public function getCourseSection(
        IConnector $schoolboxDb,
        Folder\Mapper $folderMapper,
        Course $courseBridge,
        LoggerInterface $log
    ): CourseSection {
        return new Bridge\CourseSection($this->canvasDb, $schoolboxDb, $folderMapper, $courseBridge, $log);
    }
}
