<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace External\Canvas\Sync\Bridge;

use Alaress\Database;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Resource\Folder;
use UnexpectedValueException;
use function count;
use function preg_match;

/**
 * Responsible for providing the link between a Canvas Course and a Schoolbox Folder+UnitSpec.
 */
class Course
{

    /** @var IConnector */
    private $schoolboxDb;


    /**
     * @param IConnector $schoolboxDb
     */
    public function __construct(IConnector $schoolboxDb)
    {
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
     * Return a predicate that can find a Schoolbox folder for specified Canvas Account
     *
     * @param int         $accountCanvasId
     * @param string|null $folderTableAlias
     * @return Builder
     * @throws InvalidColumnException
     */
    public function equalsCanvasCourse(int $accountCanvasId, string $folderTableAlias = null): Builder
    {
        return (new Builder($this->schoolboxDb, $folderTableAlias ?? 'folder'))
            ->regex('name', "^.+\\({$this::canvasIdPrefix()}-{$accountCanvasId}\\)$");
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
        return (new Builder($this->schoolboxDb, $folderTableAlias))
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
     * Find the Schoolbox UnitSpec Folder that aligns to the specified canvas course
     *
     * @param int $courseCanvasId
     * @return Folder\Model|null
     * @throws InvalidColumnException
     * @throws UnexpectedValueException
     * @throws Database\Exception
     */
    public function findUnitSpecFolder(int $courseCanvasId): ?Folder\Model
    {
        $canvasCourseFolderPb    = $this->equalsCanvasCourse($courseCanvasId, 'f');
        $canvasCourseFolderWhere = $canvasCourseFolderPb->getWhere()[0];
        $sql                     = "
            SELECT 
                   f.id 'f.id', 
                   f.name 'f.name', 
                   f.parent 'f.parent',
                   us.id 'us.id'
            FROM folder f 
            JOIN course_unit_specification us on f.id = us.folder_id
            WHERE 
                  $canvasCourseFolderWhere AND 
                  f.deleted_at IS NULL AND 
                  us.deleted_at IS NULL
        ";

        $rows = $this->schoolboxDb->executeQuery($sql, $canvasCourseFolderPb->getParams())->fetchAll();

        if (!count($rows)) {
            return null;
        }
        if (count($rows) > 1) {
            throw new UnexpectedValueException("Multiple Schoolbox course folders found for canvas course canvasId '$courseCanvasId'");
        }
        $row = $rows[0];

        $folder   = (new Folder\Model())
            ->setId((int)$row['f.id'])
            ->setName($row['f.name'])
            ->setParent((new Folder\Model())->setId((int)$row['f.parent']));
        $unitSpec = (new UnitSpec\Model())
            ->setId((int)$row['us.id'])
            ->setFolder($folder);
        return $folder->setUnitSpecification($unitSpec);
    }
}
