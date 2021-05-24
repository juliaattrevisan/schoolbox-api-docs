<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\Bridge;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\Resource\Folder;
use UnexpectedValueException;

use function preg_match;

/**
 * Responsible for providing the link between a Canvas Course and a Schoolbox Folder+UnitSpec.
 */
class Course
{
    /** @var IConnector */
    protected $canvasDb;

    /** @var IConnector */
    protected $schoolboxDb;


    /**
     * @param IConnector $canvasDb
     * @param IConnector $schoolboxDb
     */
    public function __construct(IConnector $canvasDb, IConnector $schoolboxDb)
    {
        $this->canvasDb = $canvasDb;
        $this->schoolboxDb = $schoolboxDb;
    }


    /**
     * Returns a value to prefix to the canvas id.
     *
     * @return string
     */
    private static function canvasIdPrefix(): string
    {
        return 'canvas-course';
    }


    /**
     * Return a predicate that can find a Schoolbox folder for specified Canvas Course
     *
     * @param int         $courseCanvasId
     * @param string|null $folderTableAlias
     * @return Builder
     * @throws InvalidColumnException
     */
    public function equalsCanvasCourse(int $courseCanvasId, string $folderTableAlias = null): Builder
    {
        return (new Builder($this->schoolboxDb->getQuoter(), $folderTableAlias ?? 'folder'))
            ->regex('name', "^.+\\({$this::canvasIdPrefix()}-{$courseCanvasId}\\)$");
    }


    /**
     * Returns a predicate that will match any Schoolbox folder that represents a Canvas Account
     *
     * @param string $folderTableAlias
     * @return Builder
     * @throws InvalidColumnException
     */
    public function equalsAnyCanvasCourse(string $folderTableAlias = 'folder'): Builder
    {
        return (new Builder($this->schoolboxDb->getQuoter(), $folderTableAlias))
            ->regex('name', "^.+\\({$this::canvasIdPrefix()}-\\d+\\)$");
    }


    /**
     * Returns a Canvas `course`.`canvas_id` from Schoolbox folder
     *
     * @param Folder\Model $folder
     * @return int
     * @throws UnexpectedValueException
     */
    public function getCourseCanvasIdFromFolder(Folder\Model $folder): int
    {
        $folderName = $folder->getName();
        if (preg_match("/^.+\({$this::canvasIdPrefix()}-(\d+)\)$/", $folderName, $matches)) {
            return (int)$matches[1];
        }
        throw new UnexpectedValueException("Unable to determine `course.canvas_id` from folder name '$folderName'");
    }


    /**
     * Consistently generate a Schoolbox folder name for a Canvas Course
     *
     * @param string $courseEnrollmentTermName
     * @param int    $courseCanvasId
     * @return string
     */
    public function generateLinkedFolderName(string $courseEnrollmentTermName, int $courseCanvasId): string
    {
        return "$courseEnrollmentTermName ({$this::canvasIdPrefix()}-{$courseCanvasId})";
    }

    /**
     * Returns a predicate to match Canvas Course and CourseSections that should be synchronised
     *
     * @param string $courseAlias
     * @param string $sectionAlias
     * @return Builder
     * @throws InvalidColumnException
     */
    public function getCanvasCoursesToImport(string $courseAlias = 'c', string $sectionAlias = 'cs'): Builder
    {
        $pb = (new Builder($this->canvasDb->getQuoter(), ''))
            ->in("{$courseAlias}.workflow_state", ['available', 'completed']);
        return (new Builder($this->canvasDb->getQuoter(), ''))
            ->all($pb);
    }
}
