<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace External\Canvas\Sync\ImportStage;

use Alaress\Database;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use External\Canvas\Sync\Bridge;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use UnderflowException;
use UnexpectedValueException;
use function array_key_exists;
use function array_map;
use function count;

/**
 * Responsible for importing Canvas Courses
 *
 * Note this stage can execute prior to class folders being created in Schoolbox
 *
 * Maps the following entities from Canvas to Schoolbox:
 * * Courses are grouped by code into 'CourseGroups' => CourseSpec + UnitSpec
 */
class Courses
{
    /** @var IConnector */
    private $canvasDb;

    /** @var LoggerInterface */
    private $log;

    /** @var Bridge\Account */
    private $accountBridge;

    /** @var Bridge\CourseGroup */
    private $courseGroupBridge;

    /** @var Bridge\Course */
    private $courseBridge;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var CourseSpec\Mapper */
    private $courseSpecMapper;

    /** @var UnitSpec\Mapper */
    private $unitSpecMapper;

    // CourseGroups => CourseSpec
    private $countCourseGroupsSynchronised;
    private $countCourseGroupsCreated;
    private $countCourseGroupsUpdated;
    private $countCourseGroupsMoved;
    private $countCourseGroupsDeleted;

    // Courses => UnitSpecs
    private $countCoursesSynchronised;
    private $countCoursesCreated;
    private $countCoursesUpdated;
    private $countCoursesMoved;
    private $countCoursesDeleted;


    /**
     * @param IConnector         $canvasDb
     * @param Folder\Mapper      $folderMapper
     * @param CourseSpec\Mapper  $courseSpecMapper
     * @param UnitSpec\Mapper    $unitSpecMapper
     * @param LoggerInterface    $log
     * @param Bridge\Account     $accountBridge
     * @param Bridge\Course      $courseBridge
     * @param Bridge\CourseGroup $courseGroupBridge
     */
    public function __construct(
        IConnector $canvasDb,
        Folder\Mapper $folderMapper,
        CourseSpec\Mapper $courseSpecMapper,
        UnitSpec\Mapper $unitSpecMapper,
        LoggerInterface $log,
        Bridge\Account $accountBridge,
        Bridge\Course $courseBridge,
        Bridge\CourseGroup $courseGroupBridge
    ) {
        $this->canvasDb          = $canvasDb;
        $this->log               = $log;
        $this->folderMapper      = $folderMapper;
        $this->courseSpecMapper  = $courseSpecMapper;
        $this->unitSpecMapper    = $unitSpecMapper;
        $this->accountBridge     = $accountBridge;
        $this->courseGroupBridge = $courseGroupBridge;
        $this->courseBridge      = $courseBridge;
    }


    /**
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @throws PDOException
     */
    public function synchronise(): void
    {
        $this->log->notice("Synchronising Canvas Courses + Assignments => Schoolbox CourseSpec, UnitSpec, SubmissionBoxSpec...");
        $this->resetCounts();

        // Fetch all relevant courses and their assignments
        // If multiple courses with same code over multiple years, split into timeless courseSpec + per-time unitSpec

        // Note that we need to fetch similar columns from both `assignment` + `quiz` because a quiz may or may not
        // have an assignment, and an assignment may or may not have a quiz.

        $canvasCourseIds   = $this->getCanvasCourseIdsToImport();
        $courseFilterPb    = (new Builder($this->canvasDb, ''))->in('course.canvas_id', $canvasCourseIds);
        $courseFilterWhere = $courseFilterPb->getWhere()[0];
        $sql               = "
        SELECT
               account.canvas_id           'account.canvas_id',
               course.name                 'course.name',
               course.canvas_id            'course.canvas_id',
               course.code                 'course.code',
               enrollment_term.name        'enrollment_term.name'
        FROM course_dim course
        JOIN enrollment_term_dim enrollment_term ON enrollment_term.id = course.enrollment_term_id
        JOIN account_dim account ON course.account_id = account.id
        WHERE {$courseFilterWhere}
        ORDER BY account.canvas_id, course.code, enrollment_term.date_start, course.canvas_id
        ";
        $statement         = $this->canvasDb->executeQuery($sql, $courseFilterPb->getParams());

        // Parse results into hierarchical structure
        $accountsData = [];
        while ($row = $statement->fetch()) {
            $accountCanvasId = (int)$row['account.canvas_id'];
            $courseCode      = $row['course.code'];
            $courseCanvasId  = (int)$row['course.canvas_id'];

            // First time we've seen this account?
            if (!array_key_exists($accountCanvasId, $accountsData)) {
                $accountsData[$accountCanvasId] = [
                    'courseGroups' => [],
                ];
            }
            // First time we've seen course group?
            if (!array_key_exists($courseCode, $accountsData[$accountCanvasId]['courseGroups'])) {
                $accountsData[$accountCanvasId]['courseGroups'][$courseCode] = [
                    'courses' => [],
                ];
            }
            // First time we've seen course?
            if (!array_key_exists($courseCanvasId, $accountsData[$accountCanvasId]['courseGroups'][$courseCode]['courses'])) {
                $accountsData[$accountCanvasId]['courseGroups'][$courseCode]['courses'][$courseCanvasId] = [
                    'name'     => $row['course.name'],
                    'termName' => $row['enrollment_term.name'],
                ];
            }
        }

        // Keep track of all entities synchronized.
        // We will later delete all entities in Schoolbox that appear to have been imported from Canvas
        // but appear to no longer exist.
        $synchronizedCourseGroupFolderIds = [];
        $synchronizedCourseFolderIds      = [];

        foreach ($accountsData as $accountCanvasId => $accountData) {
            foreach ($accountData['courseGroups'] as $courseGroupCode => $courseGroupData) {
                $mostRecentCourse                   = end($courseGroupData['courses']);
                $mostRecentCourseName               = $mostRecentCourse['name'];
                $courseSpecFolder                   = $this->processCanvasCourseGroup($accountCanvasId, $mostRecentCourseName, $courseGroupCode);
                $synchronizedCourseGroupFolderIds[] = $courseSpecFolder->getId();

                foreach ($courseGroupData['courses'] as $courseCanvasId => $courseData) {
                    $unitSpecFolder                = $this->processCanvasCourse($courseSpecFolder, $courseCanvasId, $courseData['termName']);
                    $synchronizedCourseFolderIds[] = $unitSpecFolder->getId();
                }
            }
        }

        $this->deleteUnSynchronizedCourses($synchronizedCourseFolderIds);
        $this->deleteUnSynchronizedCourseGroups($synchronizedCourseGroupFolderIds);

        $this->printCounts();
    }


    /**
     * Returns the canvas ids of the Canvas courses to import
     *
     * Course must:
     *   * Have workflow_state 'available' or 'completed'
     *   * Have 1+ CourseSections where the Sections:
     *     * Has a SisSourceId LIKE 'Y2019C%NOT', ... where year is from 2019 to current calendar year + 1
     *
     * @return int[]
     * @throws Database\Exception
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    private function getCanvasCourseIdsToImport(): array
    {
        $pb          = $this->courseBridge->getCanvasCoursesToImport();
        $whereClause = $pb->getWhere()[0];
        $sql         = "
            SELECT DISTINCT c.canvas_id
            FROM course_dim c
            JOIN course_section_dim cs ON cs.course_id = c.id
            WHERE {$whereClause}
        ";
        $canvasIds   = $this->canvasDb->executeQuery($sql, $pb->getParams())->fetchAll(PDO::FETCH_COLUMN);
        return array_map(static function (string $canvasId) {
            return (int)$canvasId;
        }, $canvasIds);
    }


    /**
     * Synchronise a course group from canvas to schoolbox.
     *
     * @param int    $accountCanvasId      Enable us to locate the faculty folder where this course resides
     * @param string $mostRecentCourseName Most recent name of the Course in the CourseGroup
     * @param string $courseCode           Provides link to SB CourseSpec.folder.name
     * @return Folder\Model
     * @throws Database\Exception
     * @throws UnexpectedValueException
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\Exception
     * @throws PDOException
     */
    private function processCanvasCourseGroup(
        int $accountCanvasId,
        string $mostRecentCourseName,
        string $courseCode
    ): Folder\Model {
        $this->countCourseGroupsSynchronised++;

        $accountFolder = $this->findAccountFolder($accountCanvasId);
        $courseFolder  = $this->courseGroupBridge->findCourseSpecFolder($this->folderMapper, $accountCanvasId, $courseCode);

        // Create update Schoolbox CourseSpec
        if (!$courseFolder) {
            $courseFolder = $this->createCourseFolder($mostRecentCourseName, $courseCode, $accountFolder);
            $this->countCourseGroupsCreated++;
        } else {
            $updateRequired = false;
            if ($courseFolder->getParent()->getId() !== $accountFolder->getId()) {
                $this->countCourseGroupsMoved++;
                $this->log->debug("Folder '{$courseFolder->getName()}' moved from (folder.id: {$courseFolder->getParent()->getId()}) to (folder.id: {$accountFolder->getId()})");
                $courseFolder->setParent($accountFolder);
                $updateRequired = true;
            }
            $courseFolderName = $this->getCourseSpecFolderName($mostRecentCourseName, $courseCode);
            if ($courseFolder->getName() !== $courseFolderName) {
                $courseFolder->setName($courseFolderName);
                $this->countCourseGroupsUpdated++;
                $this->log->debug("Folder '{$courseFolder->getName()}' renamed to '$courseFolderName'");
                $updateRequired = true;
            }
            if ($updateRequired) {
                $this->folderMapper->save($courseFolder);
            }
        }
        return $courseFolder;
    }


    /**
     * Sync Canvas Course with Schoolbox UnitSpec
     *
     * @param Folder\Model $courseSpecFolder
     * @param int          $courseCanvasId
     * @param string       $termName
     * @return Folder\Model
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function processCanvasCourse(
        Folder\Model $courseSpecFolder,
        int $courseCanvasId,
        string $termName
    ): Folder\Model {
        $this->countCoursesSynchronised++;

        $unitSpecFolderPredicate = $this->courseBridge->equalsCanvasCourse($courseCanvasId)->isNull('us.deleted_at');
        $unitSpecFolder          = $this->folderMapper->findByPredicates($unitSpecFolderPredicate)->first();

        if (!$unitSpecFolder) {
            $unitSpecFolder = $this->createUnitSpecFolder($courseSpecFolder, $courseCanvasId, $termName);
            $this->countCoursesCreated++;
        } else {
            $unitSpecFolderName = $this->getUnitSpecFolderName($termName, $courseCanvasId);
            $updateRequired     = false;
            if ($unitSpecFolder->getParent()->getId() !== $courseSpecFolder->getId()) {
                $updateRequired = true;
                $this->countCoursesMoved++;
                $this->log->debug("Folder '{$unitSpecFolder->getName()}' moved from (folder.id: {$unitSpecFolder->getParent()->getId()}) to (folder.id: {$courseSpecFolder->getId()})");
                $unitSpecFolder->setParent($courseSpecFolder);
            }
            if ($unitSpecFolder->getName() !== $unitSpecFolderName) {
                $updateRequired = true;
                $this->countCoursesUpdated++;
                $this->log->debug("Folder '{$unitSpecFolder->getName()}' renamed to '$unitSpecFolderName'");
                $unitSpecFolder->setName($unitSpecFolderName);
            }
            if ($updateRequired) {
                $this->folderMapper->save($unitSpecFolder);
            }
        }
        return $unitSpecFolder;
    }


    /**
     * Returns the Schoolbox Folder that relates to a Canvas Account
     *
     * @param int $accountCanvasId
     * @return Folder\Model
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws Mapper\Exception\NotFound
     */
    private function findAccountFolder(int $accountCanvasId): Folder\Model
    {
        $accountFolderPb = $this->accountBridge->equalsAccountFolder($accountCanvasId);
        return $this->folderMapper->findOneByPredicates($accountFolderPb);
    }


    /**
     * Create, persist + return a new CourseSpec+Folder that aligns to a Canvas CourseGroup
     *
     * @param string       $courseName
     * @param string       $courseCode
     * @param Folder\Model $accountFolder
     * @return Folder\Model
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function createCourseFolder(string $courseName, string $courseCode, Folder\Model $accountFolder): Folder\Model
    {
        $folderName = $this->getCourseSpecFolderName($courseName, $courseCode);

        $folder = $this->createFolder($folderName, $accountFolder);

        $courseSpec = (new CourseSpec\Model())->setFolder($folder);
        $folder->setCourseSpecification($courseSpec);

        $this->courseSpecMapper->save($courseSpec);

        $this->log->debug("Created Schoolbox CourseSpec+Folder '$folderName' for Canvas CourseGroup (courseCode: '$courseCode')");

        return $folder;
    }


    /**
     * Create, persist + return a new UnitSpec + Folder that aligns to a Canvas Course
     *
     * @param Folder\Model $courseSpecFolder
     * @param int          $courseCanvasId
     * @param string       $termName
     * @return Folder\Model
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function createUnitSpecFolder(Folder\Model $courseSpecFolder, int $courseCanvasId, string $termName): Folder\Model
    {
        $folderName = $this->getUnitSpecFolderName($termName, $courseCanvasId);
        $folder     = $this->createFolder($folderName, $courseSpecFolder);
        $unitSpec   = (new UnitSpec\Model())->setFolder($folder)->setCourse($courseSpecFolder->getCourseSpecification());
        $folder->setUnitSpecification($unitSpec);
        $this->unitSpecMapper->save($unitSpec);
        $this->log->debug("Created Schoolbox UnitSpec+Folder '$folderName' for Canvas Course (canvasId: $courseCanvasId)");
        return $folder;
    }


    /**
     * Insert new folder and return new Folder model
     *
     * @param string       $name
     * @param Folder\Model $parent
     * @return Folder\Model
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     * @throws PDOException
     */
    private function createFolder(string $name, Folder\Model $parent): Folder\Model
    {
        $folder = (new Folder\Model())
            ->setName($name)
            ->setParent($parent);
        $this->folderMapper->save($folder);
        return $folder;
    }


    /**
     * Generate a Schoolbox folder name for a given Canvas CourseName + CourseCode
     *
     * @param string $courseName
     * @param string $courseCode
     * @return string
     */
    private function getCourseSpecFolderName(string $courseName, string $courseCode): string
    {
        return $this->courseGroupBridge->generateLinkedFolderName($courseName, $courseCode);
    }


    /**
     * Generate a Schoolbox folder name for a given Canvas Course date + CanvasId
     *
     * @param string $termName
     * @param int    $courseCanvasId
     * @return string
     */
    private function getUnitSpecFolderName(string $termName, int $courseCanvasId): string
    {
        return $this->courseBridge->generateLinkedFolderName($termName, $courseCanvasId);
    }


    /**
     * Delete any folder+courseSpec in SB that were previously imported from Canvas, but no longer exist in Canvas.
     *
     * @param array $synchronizedCourseGroupFolderIds
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnderflowException
     */
    private function deleteUnSynchronizedCourseGroups(array $synchronizedCourseGroupFolderIds): void
    {
        $pb = $this->courseGroupBridge->equalsAnyCourseGroupFolder();
        if (count($synchronizedCourseGroupFolderIds)) {
            $pb->notIn('folder.id', $synchronizedCourseGroupFolderIds);
        }

        foreach ($this->folderMapper->findByPredicates($pb) as $folder) {
            $this->log->debug("Deleting un-synchronised course group folder '{$folder->getTitle()}' (id: {$folder->getId()})");
            $this->folderMapper->delete($folder);
            $this->countCourseGroupsDeleted++;
        }
    }


    /**
     * Delete any folder+unitSpec in SB that were previously imported from Canvas, but no longer exist in Canvas.
     *
     * @param array $synchronizedCourseFolderIds
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnderflowException
     */
    private function deleteUnSynchronizedCourses(array $synchronizedCourseFolderIds): void
    {
        $pb = $this->courseBridge->equalsAnyCanvasCourse();
        if (count($synchronizedCourseFolderIds)) {
            $pb->notIn('id', $synchronizedCourseFolderIds);
        }

        foreach ($this->folderMapper->findByPredicates($pb) as $folder) {
            $this->log->debug("Deleting un-synchronised course folder '{$folder->getTitle()}' (id: {$folder->getId()})");
            $this->folderMapper->delete($folder);
            $this->countCoursesDeleted++;
        }
    }

    /**
     * Reset all counts to 0
     */
    private function resetCounts(): void
    {
        $this->countCourseGroupsSynchronised = 0;
        $this->countCourseGroupsCreated      = 0;
        $this->countCourseGroupsUpdated      = 0;
        $this->countCourseGroupsMoved        = 0;
        $this->countCourseGroupsDeleted      = 0;

        $this->countCoursesSynchronised = 0;
        $this->countCoursesCreated      = 0;
        $this->countCoursesUpdated      = 0;
        $this->countCoursesMoved        = 0;
        $this->countCoursesDeleted      = 0;
    }


    /**
     * Print counts of things checked/changes
     */
    private function printCounts(): void
    {
        $this->log->notice($this->countCourseGroupsSynchronised . ' Canvas CourseGroups synchronised');
        $this->log->notice($this->countCourseGroupsCreated . ' Schoolbox CourseSpecs created');
        $this->log->notice($this->countCourseGroupsUpdated . ' Schoolbox CourseSpecs updated');
        $this->log->notice($this->countCourseGroupsMoved . ' Schoolbox CourseSpecs moved');
        $this->log->notice($this->countCourseGroupsDeleted . ' Schoolbox CourseSpecs deleted');

        $this->log->notice($this->countCoursesSynchronised . ' Canvas courses synchronised');
        $this->log->notice($this->countCoursesCreated . ' Schoolbox UnitSpecs created');
        $this->log->notice($this->countCoursesUpdated . ' Schoolbox UnitSpecs updated');
        $this->log->notice($this->countCoursesMoved . ' Schoolbox UnitSpecs moved');
        $this->log->notice($this->countCoursesDeleted . ' Schoolbox UnitSpecs deleted');
    }
}
