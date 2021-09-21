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
use InvalidArgumentException;
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
     * @throws InvalidArgumentException
     */
    public function getAssignment(GenericCollection $markTypes, GenericCollection $workTypes): Assignment
    {
        switch ($this->siteSchoolCode) {
            case 'STALOY':
                return new Bridge\Staloy\Assignment($markTypes, $workTypes);
            case 'MTC':
                return new Bridge\Mtc\Assignment($markTypes, $workTypes);
        }
        throw new InvalidArgumentException("No Assignment class found for '{$this->siteSchoolCode}'");
    }

    /**
     * Gets class for course
     * @param IConnector $canvasDb
     * @param IConnector $schoolboxDb
     * @return Course
     * @throws InvalidArgumentException
     */
    public function getCourse(IConnector $canvasDb, IConnector $schoolboxDb): Course
    {
        switch ($this->siteSchoolCode) {
            case 'STALOY':
                return new Bridge\Staloy\Course($canvasDb, $schoolboxDb);
            case 'MTC':
                return new Bridge\Mtc\Course($canvasDb, $schoolboxDb);
        }
        throw new InvalidArgumentException("No Course class found for '{$this->siteSchoolCode}'");
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
        if ($this->siteSchoolCode === 'STALOY') {
            return new Bridge\Staloy\CourseSection($canvasDb, $schoolboxDb, $folderMapper, $courseBridge, $log);
        }
        return new Bridge\CourseSection($canvasDb, $schoolboxDb, $folderMapper, $courseBridge, $log);
    }
}
