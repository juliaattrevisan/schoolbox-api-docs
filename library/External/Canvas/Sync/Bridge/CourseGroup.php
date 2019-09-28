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
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use UnexpectedValueException;
use function count;
use function preg_match;

/**
 * Responsible for providing the link between set of Canvas Courses (aka CourseGroup) and a Schoolbox Folder.
 *
 * The set of Canvas Courses are those that:
 * * belong to the same canvas account, and
 * * share the same `course`.`code`
 */
class CourseGroup
{

    /** @var IConnector */
    private $schoolboxDb;

    /** @var Account */
    private $accountBridge;


    /**
     * @param IConnector $schoolboxDb
     * @param Account    $accountBridge
     */
    public function __construct(IConnector $schoolboxDb, Account $accountBridge)
    {
        $this->schoolboxDb   = $schoolboxDb;
        $this->accountBridge = $accountBridge;
    }


    /**
     * Returns a value to prefix to the course.code
     *
     * @return string
     */
    private static function codePrefix(): string
    {
        return 'canvas-course-group';
    }


    /**
     * Returns a predicate to find a Schoolbox folder for a Canvas Course Group.
     *
     * Note that we need the Canvas Course's Account to perform the match
     *
     * @param string $courseCode
     * @param int    $accountCanvasId
     * @param string $folderAlias
     * @param string $parentFolderAlias
     * @return Builder
     * @throws InvalidColumnException
     */
    public function equalsCourseGroupFolder(
        string $courseCode,
        int $accountCanvasId,
        string $folderAlias = 'folder',
        string $parentFolderAlias = 'parent'
    ): Builder {

        $innerPb = $this->accountBridge
            ->equalsAccountFolder($accountCanvasId, $parentFolderAlias)
            ->regex("$folderAlias.name", "^.+\\({$this::codePrefix()}-{$courseCode}\\)$");

        return (new Builder($this->schoolboxDb, $folderAlias))->all($innerPb);
    }


    /**
     * Returns a predicate to return all Schoolbox folder that represent a Canvas Course Group.
     *
     * @param string $folderAlias
     * @return Builder
     * @throws InvalidColumnException
     */
    public function equalsAnyCourseGroupFolder(string $folderAlias = 'folder'): Builder
    {
        return (new Builder($this->schoolboxDb, $folderAlias))
            ->regex("$folderAlias.name", "^.+\\({$this::codePrefix()}-[^)]+\\)$");
    }


    /**
     * @param string $courseName
     * @param string $courseCode
     * @return string
     */
    public function generateLinkedFolderName(string $courseName, string $courseCode): string
    {
        return "$courseName ({$this::codePrefix()}-{$courseCode})";
    }


    /**
     * @param Folder\Model $courseSpecFolder
     * @return int
     * @throws UnexpectedValueException
     */
    public function getAccountCanvasIdFromCourseSpecFolder(Folder\Model $courseSpecFolder): int
    {
        return $this->accountBridge->getAccountCanvasIdFromFolder($courseSpecFolder->getParent());
    }

    /**
     * Returns a Canvas `course`.`code` from Schoolbox folder name
     *
     * @param Folder\Model $courseSpecFolder
     * @return string
     * @throws UnexpectedValueException
     */
    public function getCourseCodeFromCourseSpecFolder(Folder\Model $courseSpecFolder): string
    {
        $folderName = $courseSpecFolder->getFolder()->getName();
        if (preg_match("/^.+\({$this::codePrefix()}-([^)]+)\)$/", $folderName, $matches)) {
            return $matches[1];
        }
        throw new UnexpectedValueException("Unable to determine `course.code` from folder name '$folderName'");
    }


    /**
     * Returns the Schoolbox Course Folder+CourseSpec that aligns to a Canvas CourseGroup
     *
     * Note that the courseCode may be used by different canvas accounts, so we need to check parent too
     *
     * Hydrates:
     *     folder
     *     folder.course_specification
     *
     * @param Folder\Mapper $folderMapper
     * @param int           $accountCanvasId
     * @param string        $courseCode
     * @return Folder\Model|null
     * @throws Database\Exception
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     */
    public function findCourseSpecFolder(Folder\Mapper $folderMapper, int $accountCanvasId, string $courseCode): ?Folder\Model
    {
        $courseGroupFolderPb    = $this->equalsCourseGroupFolder($courseCode, $accountCanvasId, 'f', 'p');
        $courseGroupFolderWhere = $courseGroupFolderPb->getWhere()[0];

        $sql = "
            SELECT 
                   {$folderMapper->getColumnString('f')},
                   cs.id    'cs.id'
            FROM folder f 
            JOIN folder p ON p.id = f.parent
            JOIN course_specification cs ON f.id = cs.folder_id
            WHERE 
                  {$courseGroupFolderWhere} AND
                  p.deleted_at IS NULL AND
                  f.deleted_at IS NULL AND 
                  cs.deleted_at IS NULL
        ";

        $rows = $this->schoolboxDb->executeQuery($sql, $courseGroupFolderPb->getParams())->fetchAll();

        if (!count($rows)) {
            return null;
        }
        if (count($rows) > 1) {
            throw new UnexpectedValueException(
                "Multiple Schoolbox course folders found for canvas account '$accountCanvasId' and course code '$courseCode'"
            );
        }
        $folder = $folderMapper->hydrate($rows[0], 'f');
        $courseSpec = (new CourseSpec\Model())
            ->setId((int)$rows[0]['cs.id'])
            ->setFolder($folder);
        return $folder->setCourseSpecification($courseSpec);
    }
}
