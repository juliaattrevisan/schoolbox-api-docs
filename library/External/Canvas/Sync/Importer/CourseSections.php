<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace External\Canvas\Sync\Importer;

use Alaress\Database;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use PDO;
use Psr\Log\LoggerInterface;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Resource\Folder;
use UnexpectedValueException;
use function array_map;
use function array_reverse;
use function count;
use function in_array;
use function preg_match;
use function preg_replace;

/**
 * Synchronise Canvas CourseSections with Schoolbox ClassFolder+CourseImpl+UnitImpl
 */
class CourseSections
{

    /** @var IConnector */
    private $canvasDb;

    /** @var IConnector */
    private $schoolboxDb;

    /** @var LoggerInterface */
    private $log;

    // Per-execution counts
    private $countCanvasCourseSections;
    private $countCanvasCourseSectionsNoCodeDetectable;
    private $countCanvasCourseSectionsMatchedToFolderCodes;

    private $countCourseGroupsSynchronised;
    private $countCourseGroupsCreated;
    private $countCourseGroupsUpdated;
    private $countCourseGroupsMoved;
    private $countCourseGroupsDeleted;

    private $countCoursesSynchronised;
    private $countCoursesCreated;
    private $countCoursesUpdated;
    private $countCoursesMoved;
    private $countCoursesDeleted;

    /**
     * @param IConnector      $canvasDb
     * @param IConnector      $schoolboxDb
     * @param LoggerInterface $log
     */
    public function __construct(
        IConnector $canvasDb,
        IConnector $schoolboxDb,
        LoggerInterface $log
    ) {
        $this->canvasDb       = $canvasDb;
        $this->schoolboxDb    = $schoolboxDb;
        $this->log            = $log;
    }


    /**
     * @throws Database\Exception
     * @throws UnexpectedValueException
     */
    public function synchronise(): void
    {
        $this->log->info("Synchronising canvas courses...");
        $this->resetCounts();

        // 1. Find all classes (course-sections) that are importable, and belong to courses that have assessments
        $sql           = "
        SELECT csd.canvas_id, csd.sis_source_id
        FROM course_section_dim csd
        JOIN course_dim cd ON cd.id = csd.course_id
        JOIN assignment_dim ad ON ad.course_id = cd.id
        WHERE 
              cd.name LIKE '%notif%' AND 
              cd.workflow_state != 'deleted' AND 
              csd.workflow_state != 'deleted' AND
              csd.sis_source_id IS NOT NULL
        GROUP BY csd.id
        ";
        $canvasClasses = $this->canvasDb->executeQuery($sql)->fetchAll();

        // 2. Get all SB folder codes
        $sql         = "SELECT code FROM folder_code WHERE deleted_at IS NULL";
        $folderCodes = $this->schoolboxDb->executeQuery($sql)->fetchAll(PDO::FETCH_COLUMN);

        // Remove campus prefix from SB folder codes
        $folderCodesNoCampus = array_map(static function (string $folderCode) {
            return preg_replace("#^((J/)|(S/))#", '', $folderCode);
        }, $folderCodes);


        // Remove any folderCodes that are now ambiguous
        $uniqueFolderCodesNoCampus = [];
        $ambiguousFolderCodes                 = [];
        foreach ($folderCodesNoCampus as $folderCodeNoCampus) {
            if (in_array($folderCodeNoCampus, $uniqueFolderCodesNoCampus, true)) {
                $ambiguousFolderCodes[] = $folderCodeNoCampus;
            } else {
                $uniqueFolderCodesNoCampus[] = $folderCodeNoCampus;
            }
        }
        foreach ($ambiguousFolderCodes as $ambiguousFolderCode) {
            unset($uniqueFolderCodesNoCampus[array_search($ambiguousFolderCode, $uniqueFolderCodesNoCampus, true)]);
        }

        // 3. Try to find SB foldersCodes that matches the Canvas class
        $regexNotMatch                  = [];
        $regexMatchButNoFolderCodeMatch = [];
        $regexMatchAndFolderCodeMatch   = [];

        foreach ($canvasClasses as $canvasClass) {
            $canvasId    = $canvasClass['canvas_id'];
            $sisSourceId = $canvasClass['sis_source_id'];
            $this->log->debug("Processing canvas section. CanvasId '$canvasId', SisSourceId: '$sisSourceId'");
            if (!preg_match("/^Y\d{4}C(.+)$/", $sisSourceId, $matches)) {           // TODO should 'Y2019C12MAS2' be a match? 33 cases of no 'NOT' suffix, some are 'UNUSED'
                $regexNotMatch[] = $sisSourceId;
                $this->log->debug('Unable to parse a FolderCode from SisSourceId');
                continue;
            }
            $sisCode = preg_replace("/NOT$/", '', $matches[1]);
            if (in_array($sisCode, $uniqueFolderCodesNoCampus, true)) {
                $this->log->debug("FolderCode '$sisCode' match found");
                $regexMatchAndFolderCodeMatch[] = $canvasId;
            } else {
                $this->log->debug("No FolderCode match found");
                $regexMatchButNoFolderCodeMatch[] = $sisSourceId;
            }
        }
        $this->countCanvasCourseSections                     = count($canvasClasses);
        $this->countCanvasCourseSectionsNoCodeDetectable     = count($regexNotMatch);
        $this->countCanvasCourseSectionsMatchedToFolderCodes = count($regexMatchButNoFolderCodeMatch);

        // Fetch course data just for canvas classes that we are able to match to schoolbox folders.
        $courseSectionBindings = Builder::getBindings($regexMatchAndFolderCodeMatch, 'canvas_id');

        // Fetch all course that contain assessments that need to be imported.
        // If multiple courses with same code over multiple years, split into timeless courseSpec + per-time unitSpec
        $sql       = "
        SELECT 
               account.canvas_id 'account.canvas_id',
               course.name 'course.name', 
               course.canvas_id 'course.canvas_id', 
               course.code 'course.code',
               enrollment_term.name 'enrollment_term.name'
        FROM course_dim course
        JOIN enrollment_term_dim enrollment_term ON enrollment_term.id = course.enrollment_term_id
        JOIN account_dim account ON course.account_id = account.id
        JOIN course_section_dim section ON section.course_id = course.id
        JOIN assignment_dim assignment ON assignment.course_id = course.id
        WHERE section.canvas_id IN ({$courseSectionBindings->getPlaceholder()})
        GROUP BY course.id
        ORDER BY course.code, enrollment_term.date_start DESC
        ";
        $statement = $this->canvasDb->executeQuery($sql, $courseSectionBindings->getParams());

        // Initialise to empty
        /**
         * @var string $accountName
         * @var string $accountCanvasId
         * @var string $courseGroupName
         * @var string $courseGroupCode
         */
        $accountCanvasId = $courseGroupName = $courseGroupCode = null;
        $courseData  = [];

        while ($row = $statement->fetch()) {
            // Have we reached a new logical course group (not just same course for different year)
            if ($courseGroupCode !== null && $row['course.code'] !== $courseGroupCode) {
                $this->processCanvasCourseGroup($accountCanvasId, $courseGroupName, $courseGroupCode, $courseData);
                $courseData = [];
            }

            // First time we've seen the course group?
            if (!count($courseData)) {
                $accountCanvasId = $row['account.canvas_id'];
                $courseGroupName = $row['course.name'];
                $courseGroupCode = $row['course.code'];
            }
            $courseData[] = [
                'canvasId'  => $row['course.canvas_id'],
                'termName' => $row['enrollment_term.name'],
            ];
        }
        if ($courseGroupCode !== null) {
            $this->processCanvasCourseGroup($accountCanvasId, $courseGroupName, $courseGroupCode, $courseData);
        }

        $this->deleteUnSynchronizedCourseGroups();
        $this->deleteUnSynchronizedCourses();

        $this->printCounts();
    }


    /**
     * Synchronise a course group from canvas to schoolbox.
     *
     * @param string $accountCanvasId Enable us to locate the faculty folder where this course resides
     * @param string $courseName      Most recent name of the Course in the CourseGroup
     * @param string $courseCode      Will map to a CourseSpec
     * @param array  $courseData      Will map to UnitSpecs. Most recent to oldest
     * @throws Database\Exception
     * @throws UnexpectedValueException
     * @internal string[] $courseData[]['canvasId']
     * @internal string[] $courseData[]['termName']
     */
    private function processCanvasCourseGroup(
        string $accountCanvasId,
        string $courseName,
        string $courseCode,
        array $courseData
    ): void {
        $this->countCourseGroupsSynchronised++;

        $accountFolder = $this->findAccountFolder($accountCanvasId);
        $courseFolder = $this->findCourseSpecFolder($courseCode);

        // Create update Schoolbox CourseSpec
        if(!$courseFolder){
            $courseFolder = $this->createCourseFolder($courseName, $courseCode, $accountFolder);
            $this->countCourseGroupsCreated++;
        } else {
            $updateRequired = false;
            if($courseFolder->getParent()->getId() !== $accountFolder->getId()){
                $courseFolder->setParent($accountFolder);
                $this->countCourseGroupsMoved++;
                $this->log->debug("Folder '{$courseFolder->getName()}' moved from {$courseFolder->getParent()->getId()} to {$accountFolder->getId()}");
                $updateRequired = true;
            }
            $courseFolderName = $this->getCourseSpecFolderName($courseName, $courseCode);
            if($courseFolder->getName() !== $courseFolderName){
                $courseFolder->setName($courseFolderName);
                $this->countCourseGroupsUpdated++;
                $this->log->debug("Folder '{$courseFolder->getName()}' renamed to '$courseFolderName'");
                $updateRequired = true;
            }
            if($updateRequired){
                $this->updateFolder($courseFolder->getId(), $courseFolder->getName(), $courseFolder->getParent()->getId());
            }
        }

        foreach(array_reverse($courseData) as $courseDatum){
            $this->processCanvasCourse($courseFolder, $courseDatum['canvasId'], $courseDatum['termName']);
        }
    }


    /**
     * Sync Canvas Course with Schoolbox UnitSpec
     *
     * @param Folder\Model $courseSpecFolder
     * @param string       $courseCanvasId
     * @param string       $termName
     * @return Folder\Model
     * @throws Database\Exception
     * @throws UnexpectedValueException
     */
    private function processCanvasCourse(Folder\Model $courseSpecFolder, string $courseCanvasId, string $termName): Folder\Model
    {
        $this->countCoursesSynchronised++;

        $unitSpecFolder = $this->findUnitSpecFolder($courseCanvasId);
        if(!$unitSpecFolder){
            $unitSpecFolder = $this->createUnitSpecFolder($courseSpecFolder, $courseCanvasId, $termName);
            $this->countCoursesCreated++;
        } else {
            $unitSpecFolderName = $this->getUnitSpecFolderName($termName, $courseCanvasId);
            $updateRequired = false;
            if($unitSpecFolder->getParent()->getId() !== $courseSpecFolder->getId()){
                $updateRequired = true;
                $this->countCoursesMoved++;
                $this->log->debug("Folder '{$unitSpecFolder->getName()}' moved from {$unitSpecFolder->getParent()->getId()} to {$courseSpecFolder->getId()}");
                $unitSpecFolder->setParent($courseSpecFolder);
            }
            if($unitSpecFolder->getName() !== $unitSpecFolderName){
                $updateRequired = true;
                $this->countCoursesUpdated++;
                $this->log->debug("Folder '{$unitSpecFolder->getName()}' renamed to '$unitSpecFolderName'");
                $unitSpecFolder->setName($unitSpecFolderName);
            }
            if($updateRequired){
                $this->updateFolder($unitSpecFolder->getId(), $unitSpecFolder->getName(), $unitSpecFolder->getParent()->getId());
            }
        }
        return $unitSpecFolder;
    }


    /**
     * Returns the Schoolbox Folder that relates to a Canvas Account
     *
     * @param string $accountCanvasId
     * @return Folder\Model
     * @throws Database\Exception
     */
    private function findAccountFolder(string $accountCanvasId): Folder\Model
    {
        $sql = "SELECT id, name FROM folder WHERE name REGEXP '\\\\(canvas-account-{$accountCanvasId}\\\\)' AND deleted_at IS NULL";
        $row = $this->schoolboxDb->executeQuery($sql)->fetchAll()[0];
        return (new Folder\Model())->setId((int)$row['id'])->setName($row['name']);
    }


    /**
     * Returns the Schoolbox Course Folder+CourseSpec that aligns to a Canvas CourseGroup
     *
     * @param string $courseCode
     * @return Folder\Model|null
     * @throws Database\Exception
     * @throws UnexpectedValueException
     */
    private function findCourseSpecFolder(string $courseCode): ?Folder\Model
    {
        $sql = "
            SELECT 
                   f.id 'f.id', 
                   f.name 'f.name', 
                   f.parent 'f.parent',
                   cs.id 'cs.id'
            FROM folder f 
            JOIN course_specification cs on f.id = cs.folder_id
            WHERE 
                  f.name REGEXP '\\\\(canvas-course-group-{$courseCode}\\\\)' AND 
                  f.deleted_at IS NULL AND 
                  cs.deleted_at IS NULL
        ";

        $rows = $this->schoolboxDb->executeQuery($sql)->fetchAll();

        if(!count($rows)){
            return null;
        }
        if(count($rows) > 1){
            throw new UnexpectedValueException("Multiple Schoolbox course folders found for canvas course code '$courseCode'");
        }
        $row = $rows[0];

        return (new Folder\Model())
            ->setId((int)$row['f.id'])
            ->setName($row['f.name'])
            ->setParent((new Folder\Model())->setId((int)$row['f.parent']))
            ->setCourseSpecification((new CourseSpec\Model())->setId((int)$row['cs.id']));
    }


    /**
     * Find the Schoolbox UnitSpec Folder that aligns to the specified canvas course
     *
     * @param string $courseCanvasId
     * @return Folder\Model|null
     * @throws Database\Exception
     * @throws UnexpectedValueException
     */
    private function findUnitSpecFolder(string $courseCanvasId): ?Folder\Model
    {
        $sql = "
            SELECT 
                   f.id 'f.id', 
                   f.name 'f.name', 
                   f.parent 'f.parent',
                   us.id 'us.id'
            FROM folder f 
            JOIN course_unit_specification us on f.id = us.folder_id
            WHERE 
                  f.name REGEXP '\\\\(canvas-course-{$courseCanvasId}\\\\)' AND 
                  f.deleted_at IS NULL AND 
                  us.deleted_at IS NULL
        ";

        $rows = $this->schoolboxDb->executeQuery($sql)->fetchAll();

        if(!count($rows)){
            return null;
        }
        if(count($rows) > 1){
            throw new UnexpectedValueException("Multiple Schoolbox course folders found for canvas course canvasId '$courseCanvasId'");
        }
        $row = $rows[0];

        return (new Folder\Model())
            ->setId((int)$row['f.id'])
            ->setName($row['f.name'])
            ->setParent((new Folder\Model())->setId((int)$row['f.parent']))
            ->setUnitSpecification((new UnitSpec\Model())->setId((int)$row['us.id']));
    }


    /**
     * Create, persist + return a new CourseSpec+Folder that aligns to a Canvas CourseGroup
     *
     * @param string       $courseName
     * @param string       $courseCode
     * @param Folder\Model $accountFolder
     * @return Folder\Model
     * @throws Database\Exception
     * @throws UnexpectedValueException
     */
    private function createCourseFolder(string $courseName, string $courseCode, Folder\Model $accountFolder): Folder\Model
    {
        $folderName = $this->getCourseSpecFolderName($courseName, $courseCode);

        $folder = $this->createFolder($folderName, $accountFolder->getId())->setParent($accountFolder);

        $sql = "INSERT INTO course_specification(folder_id, created_at, updated_at) VALUES (:folder_id, NOW(), NOW())";
        $this->schoolboxDb->executeQuery($sql, ['folder_id' => $folder->getId()]);

        $this->log->debug("Created Schoolbox CourseSpec+Folder '$folderName' for Canvas CourseGroup '$courseCode'");

        return $folder->setCourseSpecification((new CourseSpec\Model())->setId((int)$this->schoolboxDb->lastInsertId()));
    }


    /**
     * Create, persist + return a new UnitSpec + Folder that aligns to a Canvas Course
     *
     * @param Folder\Model $courseSpecFolder
     * @param string       $courseCanvasId
     * @param string       $termName
     * @return Folder\Model
     * @throws Database\Exception
     */
    private function createUnitSpecFolder(Folder\Model $courseSpecFolder, string $courseCanvasId, string $termName): Folder\Model
    {
        $folderName = $this->getUnitSpecFolderName($termName, $courseCanvasId);
        $folder = $this->createFolder($folderName, $courseSpecFolder->getId())->setParent($courseSpecFolder);

        $sql = "INSERT INTO course_unit_specification(folder_id, course_specification_id, created_at, updated_at) VALUES (:folderId, :courseSpecId, NOW(), NOW())";
        $this->schoolboxDb->executeQuery($sql, ['folderId' => $folder->getId(), 'courseSpecId' => $courseSpecFolder->getCourseSpecification()->getId()]);

        $this->log->debug("Created Schoolbox UnitSpec+Folder '$folderName' for Canvas Course '$courseCanvasId'");

        return $folder->setUnitSpecification((new UnitSpec\Model())->setId((int)$this->schoolboxDb->lastInsertId()));
    }


    /**
     * Insert new folder and return new Folder model
     *
     * @param string $name
     * @param int    $parentId
     * @return Folder\Model
     * @throws Database\Exception
     */
    private function createFolder(string $name, int $parentId): Folder\Model
    {
        $sql = "INSERT INTO folder(name, parent, inherit) VALUES (:name, :parent, 1)";
        $this->schoolboxDb->executeQuery($sql, ['name' => $name, 'parent' => $parentId]);

        return (new Folder\Model())
            ->setId((int)$this->schoolboxDb->lastInsertId())
            ->setName($name);
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
        return "$courseName (canvas-course-group-{$courseCode})";
    }


    /**
     * Generate a Schoolbox folder name for a given Canvas Course date + CanvasId
     *
     * @param string $termName
     * @param string $courseCanvasId
     * @return string
     */
    private function getUnitSpecFolderName(string $termName, string $courseCanvasId): string
    {
        return "$termName (canvas-course-{$courseCanvasId})";
    }


    /**
     * Update a folder in the Schoolbox DB
     *
     * @param int    $id
     * @param string $name
     * @param int    $parentId
     * @throws Database\Exception
     */
    private function updateFolder(int $id, string $name, int $parentId): void
    {
        $sql = "UPDATE folder SET name = :name, parent = :parent WHERE id = :id";
        $this->schoolboxDb->executeQuery($sql, [
            'id'     => $id,
            'name'   => $name,
            'parent' => $parentId,
        ]);
    }


    /**
     * Delete any folder+courseSpec in SB that were previously imported from Canvas, but no longer exist in Canvas.
     */
    private function deleteUnSynchronizedCourseGroups(): void
    {
        // TODO
    }


    /**
     * Delete any folder+unitSpec in SB that were previously imported from Canvas, but no longer exist in Canvas.
     */
    private function deleteUnSynchronizedCourses(): void
    {
        // TODO
    }

    private function resetCounts(): void
    {
        $this->countCourseGroupsSynchronised = 0;
        $this->countCourseGroupsCreated = 0;
        $this->countCourseGroupsUpdated = 0;
        $this->countCourseGroupsMoved = 0;
        $this->countCourseGroupsDeleted = 0;

        $this->countCoursesSynchronised = 0;
        $this->countCoursesCreated = 0;
        $this->countCoursesUpdated = 0;
        $this->countCoursesMoved = 0;
        $this->countCoursesDeleted = 0;
    }

    private function printCounts(): void
    {
        $this->log->info($this->countCourseGroupsSynchronised . ' canvas course groups synchronised');
        $this->log->info($this->countCourseGroupsCreated . ' canvas course groups created');
        $this->log->info($this->countCourseGroupsUpdated . ' canvas course groups updated');
        $this->log->info($this->countCourseGroupsMoved. ' canvas course groups moved');
        $this->log->info($this->countCourseGroupsDeleted . ' canvas course groups deleted');

        $this->log->info($this->countCoursesSynchronised . ' canvas courses synchronised');
        $this->log->info($this->countCoursesCreated . ' canvas courses created');
        $this->log->info($this->countCoursesUpdated . ' canvas courses updated');
        $this->log->info($this->countCoursesMoved . ' canvas courses moved');
        $this->log->info($this->countCoursesDeleted . ' canvas courses deleted');

    }
}
