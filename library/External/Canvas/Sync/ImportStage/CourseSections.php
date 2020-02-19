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
use Alaress_Date_Format;
use DomainException;
use External\Canvas\Sync\Bridge;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use UnderflowException;
use UnexpectedValueException;
use function array_column;
use function array_key_exists;
use function array_merge;
use function count;
use function implode;

/**
 * Synchronise Canvas CourseSections+Assignments+Overrides => Schoolbox CourseImpl+UnitImpl+AssessmentImpl+Plans+Participants
 */
class CourseSections
{
    /** @var IConnector */
    private $canvasDb;

    /** @var IConnector */
    private $schoolboxDb;

    /** @var CourseImpl\Mapper */
    private $courseImplMapper;

    /** @var CourseSpec\Mapper */
    private $courseSpecMapper;

    /** @var UnitImpl\Mapper */
    private $unitImplMapper;

    /** @var UnitSpec\Mapper */
    private $unitSpecMapper;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var LoggerInterface */
    private $log;

    /** @var Bridge\Course */
    private $courseBridge;

    /** @var Bridge\CourseGroup */
    private $courseGroupBridge;

    /** @var Bridge\CourseSection */
    private $courseSectionBridge;

    /** @var Bridge\CourseSectionDates */
    private $courseSectionDatesBridge;

    // Per-execution counts

    // Canvas source entities
    private $countCanvasSectionsSynchronised;

    // Schoolbox destination entities
    private $countCourseImplCreated;
    private $countCourseImplMoved;
    private $countCourseImplUpdated;
    private $countCourseImplDeleted;

    private $countUnitImplCreated;
    private $countUnitImplMoved;
    private $countUnitImplUpdated;
    private $countUnitImplDeleted;

    /**
     * @param IConnector                $canvasDb
     * @param IConnector                $schoolboxDb
     * @param CourseImpl\Mapper         $courseImplMapper
     * @param CourseSpec\Mapper         $courseSpecMapper
     * @param UnitImpl\Mapper           $unitImplMapper
     * @param UnitSpec\Mapper           $unitSpecMapper
     * @param Folder\Mapper             $folderMapper
     * @param LoggerInterface           $log
     * @param Bridge\Course             $courseBridge
     * @param Bridge\CourseGroup        $courseGroupBridge
     * @param Bridge\CourseSection      $courseSectionBridge
     * @param Bridge\CourseSectionDates $courseSectionDatesBridge
     */
    public function __construct(
        IConnector $canvasDb,
        IConnector $schoolboxDb,
        CourseImpl\Mapper $courseImplMapper,
        CourseSpec\Mapper $courseSpecMapper,
        UnitImpl\Mapper $unitImplMapper,
        UnitSpec\Mapper $unitSpecMapper,
        Folder\Mapper $folderMapper,
        LoggerInterface $log,
        Bridge\Course $courseBridge,
        Bridge\CourseGroup $courseGroupBridge,
        Bridge\CourseSection $courseSectionBridge,
        Bridge\CourseSectionDates $courseSectionDatesBridge
    ) {
        $this->canvasDb                 = $canvasDb;
        $this->schoolboxDb              = $schoolboxDb;
        $this->courseImplMapper         = $courseImplMapper;
        $this->courseSpecMapper         = $courseSpecMapper;
        $this->unitImplMapper           = $unitImplMapper;
        $this->unitSpecMapper           = $unitSpecMapper;
        $this->folderMapper             = $folderMapper;
        $this->log                      = $log;
        $this->courseBridge             = $courseBridge;
        $this->courseGroupBridge        = $courseGroupBridge;
        $this->courseSectionBridge      = $courseSectionBridge;
        $this->courseSectionDatesBridge = $courseSectionDatesBridge;
    }


    /**
     * @throws Database\Exception
     * @throws DomainException
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    public function synchronise(): void
    {
        $this->log->notice("Synchronising Canvas CourseSections => CourseImpl + UnitImpl...");
        $this->resetCounts();

        // All sections.canvasId that map to a Schoolbox folder
        $sectionSisSourceIds = $this->courseSectionBridge->getMappedSisSourceIds();

        $data = [];
        if (count($sectionSisSourceIds)) {
            $mySqlQuoter = new Database\Quoter\Mysql\Quoter();
            $pb          = (new Builder($mySqlQuoter, ''))
                ->in('section.sis_source_id', $sectionSisSourceIds);
            $whereClause = implode(' AND ', $pb->getWhere());

            $sql       = "
                SELECT
                    account.canvas_id                                   'account.canvas_id',
                    course.code                                         'course.code',
                    course.canvas_id                                    'course.canvas_id',
                    CONVERT_TZ(course.start_at, 'UTC', 'SYSTEM')        'course.start_at',
                    CONVERT_TZ(course.conclude_at, 'UTC', 'SYSTEM')     'course.conclude_at',
                    CONVERT_TZ(courseTerm.date_start, 'UTC', 'SYSTEM')  'courseTerm.date_start',
                    CONVERT_TZ(courseTerm.date_end, 'UTC', 'SYSTEM')    'courseTerm.date_end',
                    section.canvas_id                                   'section.canvas_id',
                    section.name                                        'section.name',
                    section.sis_source_id                               'section.sis_source_id',
                    CONVERT_TZ(section.start_at, 'UTC', 'SYSTEM')       'section.start_at',
                    CONVERT_TZ(section.end_at, 'UTC', 'SYSTEM')         'section.end_at',
                    CONVERT_TZ(sectionTerm.date_start, 'UTC', 'SYSTEM') 'sectionTerm.date_start',
                    CONVERT_TZ(sectionTerm.date_end, 'UTC', 'SYSTEM')   'sectionTerm.date_end'
                FROM course_section_dim section
                JOIN course_dim course ON course.id = section.course_id
                JOIN account_dim account ON account.id = course.account_id
                LEFT JOIN enrollment_term_dim sectionTerm ON section.enrollment_term_id = sectionTerm.id
                LEFT JOIN enrollment_term_dim courseTerm ON course.enrollment_term_id = courseTerm.id
                WHERE {$whereClause}
                ORDER BY account.canvas_id, course.code, course.canvas_id, section.canvas_id
            ";
            $statement = $this->canvasDb->executeQuery($sql, $pb->getParams());
            while ($row = $statement->fetch()) {
                $accountCanvasId = $row['account.canvas_id'];
                $courseGroupCode = $row['course.code'];
                $courseCanvasId  = $row['course.canvas_id'];
                $sectionCanvasId = $row['section.canvas_id'];

                if (!array_key_exists($accountCanvasId, $data)) {
                    $data[$accountCanvasId] = [];
                }
                if (!array_key_exists($courseGroupCode, $data[$accountCanvasId])) {
                    $data[$accountCanvasId][$courseGroupCode] = [];
                }
                if (!array_key_exists($courseCanvasId, $data[$accountCanvasId][$courseGroupCode])) {
                    $data[$accountCanvasId][$courseGroupCode][$courseCanvasId] = [
                        'start_at'        => $row['course.start_at'],
                        'conclude_at'     => $row['course.conclude_at'],
                        'term_date_start' => $row['courseTerm.date_start'],
                        'term_date_end'   => $row['courseTerm.date_end'],
                        'sections'        => [],
                    ];
                }
                if (!array_key_exists($sectionCanvasId, $data[$accountCanvasId][$courseGroupCode][$courseCanvasId]['sections'])) {
                    $data[$accountCanvasId][$courseGroupCode][$courseCanvasId]['sections'][$sectionCanvasId] = [
                        'name'            => $row['section.name'],
                        'sis_source_id'   => $row['section.sis_source_id'],
                        'start_at'        => $row['section.start_at'],
                        'end_at'          => $row['section.end_at'],
                        'term_date_start' => $row['sectionTerm.date_start'],
                        'term_date_end'   => $row['sectionTerm.date_end'],
                    ];
                }
            }
        }

        // Track synchronised entities so we can
        // 1) avoid re-sync of sections when iterating an assignment over sections, and
        // 2) delete any entities no longer in Canvas
        /** @var int[] */
        $courseImplSynchronised = [];
        /** @var int[] */
        $unitImplSynchronised = [];

        foreach ($data as $accountCanvasId => $accountData) {
            foreach ($accountData as $courseGroupCode => $courseGroupData) {
                $courseSpecFolder = $this->courseGroupBridge->findCourseSpecFolder($this->folderMapper, $accountCanvasId, $courseGroupCode);
                if ($courseSpecFolder === null) {
                    throw new UnexpectedValueException("Unable to find folder for account.canvasId '$accountCanvasId' and course.code '$courseGroupCode'");
                }
                foreach ($courseGroupData as $courseCanvasId => $courseData) {
                    $unitSpecFolderPredicate = $this->courseBridge->equalsCanvasCourse($courseCanvasId)->isNull('us.deleted_at');
                    $unitSpecFolder          = $this->folderMapper->findByPredicates($unitSpecFolderPredicate)->first();

                    if ($unitSpecFolder === null) {
                        throw new UnexpectedValueException("Unable to find folder for course.canvasId '$courseCanvasId'");
                    }
                    foreach ($courseData['sections'] as $sectionCanvasId => $section) {
                        $this->countCanvasSectionsSynchronised++;

                        $sectionSisSourceId = $section['sis_source_id'];
                        $classFolder        = $this->courseSectionBridge->getFolderByBySisSourceId($sectionSisSourceId);
                        if ($classFolder === null) {
                            throw new UnexpectedValueException("Unable to find folder for section.sis_source_id '$sectionSisSourceId'");
                        }
                        $courseImpl               = $this->syncCourseSectionToCourseImpl($courseSpecFolder->getCourseSpecification(), $classFolder);
                        $courseImplSynchronised[] = $courseImpl->getId();
                        $unitImpl                 = $this->syncCourseSectionWithUnitImpl(
                            $courseImpl,
                            $unitSpecFolder,
                            $section['name'],
                            $section['start_at'],
                            $section['end_at'],
                            $section['term_date_start'],
                            $section['term_date_end'],
                            $courseData['start_at'],
                            $courseData['conclude_at'],
                            $courseData['term_date_start'],
                            $courseData['term_date_end']
                        );
                        $unitImplSynchronised[]   = $unitImpl->getId();
                    }
                }
            }
        }

        $this->deleteUnSynchronisedUnitImpl($unitImplSynchronised);
        $this->deleteUnSynchronisedCourseImpl($courseImplSynchronised);

        $this->printCounts();
    }


    /**
     * Synchronised a Canvas CourseSection with Schoolbox CourseImpl
     *
     * @param CourseSpec\Model $courseSpec
     * @param Folder\Model     $classFolder
     * @return CourseImpl\Model
     * @throws Database\Exception
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws DomainException
     */
    private function syncCourseSectionToCourseImpl(CourseSpec\Model $courseSpec, Folder\Model $classFolder): CourseImpl\Model
    {
        $courseImpl = $this->findCourseImpl($classFolder);

        if (!$courseImpl) {
            $this->countCourseImplCreated++;
            $sisSourceId = $this->courseSectionBridge->getSisSourceIdForFolderId($classFolder->getId());
            $this->log->debug("Creating new CourseImpl for Canvas CourseSection (sisSourceId: $sisSourceId)");
            return $this->createCourseImpl($courseSpec, $classFolder);
        }

        $currentCourseSpec = $courseImpl->getSpecification();
        if ($currentCourseSpec->getId() !== $courseSpec->getId()) {
            $currentAccountId  = $this->courseGroupBridge->getAccountCanvasIdFromCourseSpecFolder($currentCourseSpec->getFolder());
            $currentCourseCode = $this->courseGroupBridge->getCourseCodeFromCourseSpecFolder($currentCourseSpec->getFolder());
            $newAccountId      = $this->courseGroupBridge->getAccountCanvasIdFromCourseSpecFolder($courseSpec->getFolder());
            $newCourseCode     = $this->courseGroupBridge->getCourseCodeFromCourseSpecFolder($courseSpec->getFolder());
            $this->log->debug(
                "Moving CourseImpl from Canvas Account (canvasId: $currentAccountId) + CourseGroup (courseCode: '$currentCourseCode') to " .
                "Account (canvasId: $newAccountId) + CourseGroup (courseCode: '$newCourseCode')"
            );
            $courseImpl->setSpecification($courseSpec);
            $sql = "UPDATE course_implementation SET course_specification_id = :spec_id WHERE id = :impl_id";
            $this->schoolboxDb->executeQuery($sql, [
                'spec_id' => $courseSpec->getId(),
                'impl_id' => $courseImpl->getId(),
            ]);
            $this->countCourseImplMoved++;
        }

        return $courseImpl;
    }


    /**
     * Synchronised a Canvas CourseSection with Schoolbox UnitImpl
     *
     * @param CourseImpl\Model $courseImpl
     * @param Folder\Model     $unitSpecFolder
     * @param string           $sectionName
     * @param string           $sectionStartAt
     * @param string           $sectionEndAt
     * @param string|null      $sectionTermDateStart
     * @param string|null      $sectionTermDateEnd
     * @param string|null      $courseStartAt
     * @param string|null      $courseConcludeAt
     * @param string|null      $courseTermDateStart
     * @param string|null      $courseTermDateEnd
     * @return UnitImpl\Model
     * @throws Database\Exception
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function syncCourseSectionWithUnitImpl(
        CourseImpl\Model $courseImpl,
        Folder\Model $unitSpecFolder,
        string $sectionName,
        ?string $sectionStartAt,
        ?string $sectionEndAt,
        ?string $sectionTermDateStart,
        ?string $sectionTermDateEnd,
        ?string $courseStartAt,
        ?string $courseConcludeAt,
        ?string $courseTermDateStart,
        ?string $courseTermDateEnd
    ): UnitImpl\Model {

        $unitImpl = $this->findUnitImpl($courseImpl);

        [$openDate, $dueDate] = $this->courseSectionDatesBridge->unitImplDates(
            $sectionStartAt,
            $sectionEndAt,
            $sectionTermDateStart,
            $sectionTermDateEnd,
            $courseStartAt,
            $courseConcludeAt,
            $courseTermDateStart,
            $courseTermDateEnd
        );

        if (!$unitImpl) {
            $this->countUnitImplCreated++;
            $sisSourceId = $this->courseSectionBridge->getSisSourceIdForFolderId($courseImpl->getFolder()->getId());
            $this->log->debug("Creating new UnitImpl for Canvas CourseSection '$sectionName' (sisSourceId: $sisSourceId)");
            return $this->createUnitImpl($courseImpl, $unitSpecFolder->getUnitSpecification(), $openDate, $dueDate);
        }

        $currentUnitSpec = $unitImpl->getSpecification();
        if ($currentUnitSpec->getId() !== $unitSpecFolder->getUnitSpecification()->getId()) {
            $currentCourseCanvasId = $this->courseBridge->getCourseCanvasIdFromFolder($currentUnitSpec->getFolder());
            $newCourseCanvasId     = $this->courseBridge->getCourseCanvasIdFromFolder($unitSpecFolder);
            $this->log->debug("Moving UnitImpl (id: {$unitImpl->getId()}) from Canvas Course (canvasId: $currentCourseCanvasId) to (canvasId: $newCourseCanvasId)");
            $unitImpl->setSpecification($unitSpecFolder->getUnitSpecification());
            $sql = "UPDATE course_unit_implementation SET course_unit_specification_id = :unit_spec_id WHERE id = :unit_impl_id";
            $this->schoolboxDb->executeQuery($sql, [
                'unit_spec_id' => $unitImpl->getSpecification()->getId(),
                'unit_impl_id' => $unitImpl->getId(),
            ]);
            $this->countUnitImplMoved++;
        }

        if (
            $unitImpl->getTitle() !== $unitSpecFolder->getName() ||
            $unitImpl->getOpenDate()->getDatabase() !== $openDate ||
            $unitImpl->getDueDate()->getDatabase() !== $dueDate
        ) {
            $this->countUnitImplUpdated++;
            $unitImpl->setTitle($unitSpecFolder->getName());
            $unitImpl->setOpenDate(Alaress_Date_Format::newDatabaseDate($openDate));
            $unitImpl->setDueDate(Alaress_Date_Format::newDatabaseDate($dueDate));
            $sql = "
                UPDATE course_unit_implementation
                SET
                    title     = :title,
                    open_date = :open_date,
                    due_date  = :due_date
                WHERE id = :id
            ";
            $this->schoolboxDb->executeQuery($sql, [
                'title'     => $unitImpl->getTitle(),
                'open_date' => $unitImpl->getOpenDate()->getDatabaseDate(),
                'due_date'  => $unitImpl->getDueDate()->getDatabaseDate(),
                'id'        => $unitImpl->getId(),
            ]);
        }

        return $unitImpl;
    }


    /**
     * Returns the course implementation for the specified Canvas CourseSection.SisSourceId, null if none found.
     *
     * @param Folder\Model $classFolder
     * @return CourseImpl\Model|null
     * @throws Database\Exception
     * @throws InvalidColumnException
     * @throws UnexpectedValueException
     * @throws Mapper\Exception
     * @throws DomainException
     */
    private function findCourseImpl(Folder\Model $classFolder): ?CourseImpl\Model
    {
        $courseSpecFolderPb    = $this->courseGroupBridge->equalsAnyCourseGroupFolder('csf');
        $courseSpecFolderWhere = $courseSpecFolderPb->getWhere()[0];

        // In Canvas, a CourseSection belongs to a Course.
        // In Schoolbox, this logically equates to a Class Folder always having a single CourseImpl,
        // which belongs to a CourseSpec that relates to a CanvasCourse.
        // Any Schoolbox CourseImpl whose CourseSpec does not relate to a Canvas CourseGroup can be ignored.
        $sql = "
            SELECT
                   {$this->courseImplMapper->getColumnString('ci')},
                   {$this->courseSpecMapper->getColumnString('cs')}
            FROM course_implementation ci
            JOIN course_specification cs on ci.course_specification_id = cs.id
            JOIN folder csf ON csf.id = cs.folder_id
            WHERE
                ci.deleted_at IS NULL AND
                csf.deleted_at IS NULL AND
                ci.folder_id = :ciFolderId AND
                $courseSpecFolderWhere
        ";

        $rows = $this->schoolboxDb->executeQuery($sql, array_merge($courseSpecFolderPb->getParams(), ['ciFolderId' => $classFolder->getId()]))->fetchAll();

        if (!count($rows)) {
            return null;
        }
        if (count($rows) > 1) {
            $courseSpecIds = implode(',', array_column($rows, 'ci.course_specification_id'));
            $sisSourceId   = $this->courseSectionBridge->getSisSourceIdForFolderId($classFolder->getId());
            throw new UnexpectedValueException(
                "Multiple CourseImplementations found for SisSourceId '$sisSourceId'. CourseSpecs: ($courseSpecIds)"
            );
        }
        $courseSpec = $this->courseSpecMapper->hydrate($rows[0], 'cs');
        $courseImpl = $this->courseImplMapper->hydrate($rows[0], 'ci');
        $courseSpec->setCourseImplementations($this->courseImplMapper->createCollection()->setOffset($courseImpl->getId(), $courseImpl));
        return $courseImpl->setSpecification($courseSpec);
    }


    /**
     * Returns the course implementation for the specified Canvas CourseSection.SisSourceId, null if none found.
     *
     * Hydrates:
     *   UnitImpl
     *   UnitImpl.UnitSpec
     *   UnitImpl.UnitSpec.Folder
     *
     * @param CourseImpl\Model $courseImpl
     * @return CourseImpl\Model|null
     * @throws Database\Exception
     * @throws UnexpectedValueException
     * @throws Mapper\Exception
     */
    private function findUnitImpl(CourseImpl\Model $courseImpl): ?UnitImpl\Model
    {
        // A Canvas CourseSection is mapped to a 1:1:1:1 of SB CourseSpec:CourseImpl:UnitImpl:ClassFolder.
        $sql = "
            SELECT
                   {$this->unitImplMapper->getColumnString('ui')},
                   {$this->unitSpecMapper->getColumnString('us')},
                   {$this->folderMapper->getColumnString('usf')}
            FROM course_unit_implementation ui
            JOIN course_unit_specification us on ui.course_unit_specification_id = us.id
            JOIN folder usf ON us.folder_id = usf.id
            WHERE
                  ui.course_implementation_id = :ciId AND
                  ui.deleted_at IS NULL
        ";

        $rows = $this->schoolboxDb->executeQuery($sql, ['ciId' => $courseImpl->getId()])->fetchAll();

        if (!count($rows)) {
            return null;
        }
        if (count($rows) > 1) {
            throw new UnexpectedValueException("Multiple UnitImplementations found for CourseImpl {$courseImpl->getId()}");
        }
        $unitImpl = $this->unitImplMapper->hydrate($rows[0], 'ui');
        $unitSpec = $this->unitSpecMapper->hydrate($rows[0], 'us')->setFolder($this->folderMapper->hydrate($rows[0], 'usf'));
        $unitSpec->setUnitImplementations($this->unitImplMapper->createCollection()->setOffset($unitImpl->getId(), $unitImpl));
        return $unitImpl->setSpecification($unitSpec);
    }


    /**
     * Create and return a CourseImpl for the specified folder and CourseSpec
     *
     * @param CourseSpec\Model $courseSpec
     * @param Folder\Model     $classFolder
     * @return CourseImpl\Model
     * @throws UnexpectedValueException
     * @throws PDOException
     * @throws Mapper\Exception
     */
    private function createCourseImpl(CourseSpec\Model $courseSpec, Folder\Model $classFolder): CourseImpl\Model
    {
        $courseImpl = (new CourseImpl\Model())
            ->setFolder($classFolder)
            ->setSpecification($courseSpec);
        $this->courseImplMapper->save($courseImpl);
        return $courseImpl;
    }


    /**
     * Create and return a CourseImpl for the specified folder and CourseSpec
     *
     * @param CourseImpl\Model $courseImpl
     * @param UnitSpec\Model   $unitSpec
     * @param string           $startAt
     * @param string           $endAt
     * @return UnitImpl\Model
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws Database\Exception
     */
    private function createUnitImpl(CourseImpl\Model $courseImpl, UnitSpec\Model $unitSpec, string $startAt, string $endAt): UnitImpl\Model
    {
        $unitImpl = (new UnitImpl\Model())
            ->setCourse($courseImpl)
            ->setSpecification($unitSpec)
            ->setTitle($unitSpec->getFolder()->getName())
            ->setOpenDate(Alaress_Date_Format::newDatabaseDate($startAt))
            ->setDueDate(Alaress_Date_Format::newDatabaseDate($endAt));

        $this->unitImplMapper->save($unitImpl);
        return $unitImpl;
    }


    /**
     * Resets all results to 0
     */
    private function resetCounts(): void
    {
        $this->countCanvasSectionsSynchronised = 0;

        $this->countCourseImplCreated = 0;
        $this->countCourseImplMoved   = 0;
        $this->countCourseImplUpdated = 0;
        $this->countCourseImplDeleted = 0;

        $this->countUnitImplCreated = 0;
        $this->countUnitImplMoved   = 0;
        $this->countUnitImplUpdated = 0;
        $this->countUnitImplDeleted = 0;
    }


    /**
     * Deletes any UnitImpl that appear to have been previously imported from a Canvas Course but no longer require synchronisation.
     *
     * @param array $unitImplSynchronised
     * @throws Database\Exception
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnderflowException
     */
    private function deleteUnSynchronisedUnitImpl(array $unitImplSynchronised): void
    {
        $pb = $this->courseBridge->equalsAnyCanvasCourse('usf');
        if (count($unitImplSynchronised)) {
            $pb->notIn('ui.id', $unitImplSynchronised);
        }
        $pb->isNull('ui.deleted_at');
        $whereClause = implode(' AND ', $pb->getWhere());

        $sql = "
            SELECT ui.id
            FROM course_unit_implementation ui
            JOIN course_unit_specification us ON us.id = ui.course_unit_specification_id
            JOIN folder usf ON usf.id = us.folder_id
            WHERE {$whereClause}
        ";

        $unitImplIdsToDelete = $this->schoolboxDb->executeQuery($sql, $pb->getParams())->fetchAll(PDO::FETCH_COLUMN);

        $idColumn = $this->unitImplMapper->getIdentifierColumn();
        foreach ($this->unitImplMapper->findByColumn($idColumn, $unitImplIdsToDelete) as $unitImpl) {
            $this->log->debug("Deleting un-synchronised UnitImpl (id: {$unitImpl->getId()})");
            $this->unitImplMapper->delete($unitImpl);
            $this->countUnitImplDeleted++;
        }
    }


    /**
     * Deletes any CourseImpl that appear to have been previously imported from a Canvas Course but no longer require synchronisation.
     *
     * @param array $courseImplSynchronised
     * @throws Database\Exception
     * @throws InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnderflowException
     */
    private function deleteUnSynchronisedCourseImpl(array $courseImplSynchronised): void
    {
        $pb = $this->courseGroupBridge->equalsAnyCourseGroupFolder('csf');
        if (count($courseImplSynchronised)) {
            $pb->notIn('ci.id', $courseImplSynchronised);
        }
        $pb->isNull('ci.deleted_at');
        $whereClause = implode(' AND ', $pb->getWhere());

        $sql = "
            SELECT ci.id
            FROM course_implementation ci
            JOIN course_specification cs ON cs.id = ci.course_specification_id
            JOIN folder csf ON csf.id = cs.folder_id
            WHERE {$whereClause}
        ";

        $courseImplIdsToDelete = $this->schoolboxDb->executeQuery($sql, $pb->getParams())->fetchAll(PDO::FETCH_COLUMN);

        $idColumn = $this->courseImplMapper->getIdentifierColumn();
        foreach ($this->courseImplMapper->findByColumn($idColumn, $courseImplIdsToDelete) as $courseImpl) {
            $this->log->debug("Deleting un-synchronised CourseImpl (id: {$courseImpl->getId()})");
            $this->courseImplMapper->delete($courseImpl);
            $this->countCourseImplDeleted++;
        }
    }


    /**
     * Logs the results of this stage
     */
    private function printCounts(): void
    {
        $this->log->notice($this->countCanvasSectionsSynchronised . ' Canvas CourseSections synchronised');

        $this->log->notice($this->countCourseImplCreated . ' Schoolbox CourseImpl created');
        $this->log->notice($this->countCourseImplMoved . ' Schoolbox CourseImpl moved');
        $this->log->notice($this->countCourseImplUpdated . ' Schoolbox CourseImpl updated');
        $this->log->notice($this->countCourseImplDeleted . ' Schoolbox CourseImpl deleted');


        $this->log->notice($this->countUnitImplCreated . ' Schoolbox UnitImpl created');
        $this->log->notice($this->countUnitImplMoved . ' Schoolbox UnitImpl moved');
        $this->log->notice($this->countUnitImplUpdated . ' Schoolbox UnitImpl updated');
        $this->log->notice($this->countUnitImplDeleted . ' Schoolbox UnitImpl deleted');
    }
}
