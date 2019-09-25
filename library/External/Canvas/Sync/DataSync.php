<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace External\Canvas\Sync;

use Alaress\Database\Connector\IConnector;
use Alaress_Date_Format;
use InvalidArgumentException;
use PDO;
use Alaress\Database;
use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Resource\Folder;
use Schoolbox\Mapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnexpectedValueException;
use function preg_match;

/**
 * Synchronises staging database to schoolbox database
 */
class DataSync
{
    /** @var IConnector */
    private $canvasDb;

    /** @var IConnector */
    private $schoolboxDb;

    /** @var IConnector */
    private $externalDb;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var ConfigMapper */
    private $configMapper;

    /** @var IConnector */
    private $persist;

    /** @var LoggerInterface */
    private $log;


    /**
     * @param IConnector      $canvasDb
     * @param IConnector      $schoolboxDb
     * @param IConnector      $externalDb
     * @param Folder\Mapper   $folderMapper
     * @param ConfigMapper    $configMapper
     * @param bool            $persist Whether to persist changes to schoolbox database
     * @param LoggerInterface $log
     */
    public function __construct(
        IConnector $canvasDb,
        IConnector $schoolboxDb,
        IConnector $externalDb,
        Folder\Mapper $folderMapper,
        ConfigMapper $configMapper,
        bool $persist,
        LoggerInterface $log
    )  {
        $this->canvasDb    = $canvasDb;
        $this->schoolboxDb = $schoolboxDb;
        $this->externalDb  = $externalDb;
        $this->folderMapper = $folderMapper;
        $this->configMapper = $configMapper;
        $this->persist     = $persist;
        $this->log         = $log;
    }


    /**
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws \UnderflowException
     */
    public function synchronise(): void
    {
        $this->schoolboxDb->beginTransaction();

        $sectionToCodeMap = new CourseSectionToFolderCodeMap(
            $this->canvasDb,
            $this->schoolboxDb,
            $this->folderMapper,
            $this->log
        );

        $sectionToCodeMap->execute();

$test = 1;

        (new Importer\CourseAccounts(
            $this->canvasDb,
            $this->schoolboxDb,
            $this->folderMapper,
            $this->configMapper,
            $this->log
        ))->synchronise();

        $test = 1;
        (new Importer\Courses(
            $this->canvasDb,
            $this->schoolboxDb,
            $this->log,
            $sectionToCodeMap
        ))->synchronise();

//        (new Importer\CourseSections(
//            $this->canvasDb,
//            $this->schoolboxDb,
//            $this->log
//        ))->synchronise();
//
//
//


//        $this->syncCourseSpecs();
//        $this->syncCourseAndUnitImpls();
//        $this->syncAssessments();
        if($this->persist){
            $this->schoolboxDb->commit();
        } else {
            $this->schoolboxDb->rollBack();
        }
    }

    private function syncCourseSpecs(): void
    {

    }

    private function buildCourseFolderTree(): array
    {

    }

    private function findCourseSpecByExternalId(string $externalId): array
    {
        $sql = "
            SELECT id FROM course_specification WHERE external_id = :externalId
        ";
        // TODO
        return [];
    }

    private function updateCourse(string $year, string $semester, string $term, $existingCourse, $courseName): void
    {

    }

    private function createCourse($year, $semester, $term, $existingCourse, $courseName): void
    {

    }

    private function findFolderByParentAndName(int $parentId, string $name): ?int
    {

    }





    /**
     * Attempt to parse a term year from the term name
     *
     * eg 'Term 1 Semester 2 2001' => '2001'
     *
     * @param string $termName
     * @return string|null
     */
    private function parseTermYear(string $termName): ?string
    {
        if (preg_match('/(\d{4})/', $termName, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Attempt to parse a term year from the term name
     *
     * eg 'Term 1 Semester 2 2001' => '2'
     *
     * @param string $termName
     * @return string|null
     */
    private function parseTermSemester(string $termName): ?string
    {
        if (preg_match('/sem(ester)? *(\d+)/i', $termName, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Attempt to parse a term year from the term name
     *
     * eg 'Term 1 Semester 2 2001' => '1'
     *
     * @param string $termName
     * @return string|null
     */
    private function parseTermTerm(string $termName): ?string
    {
        if (preg_match('/term *(\d+)/i', $termName, $matches)) {
            return $matches[1];
        }
        return null;
    }





    /**
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\Exception
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws \LogicException
     */
    private function syncAssessments(): void
    {
        // Keep track of all canvas assessment ids to we can sync deletions from canvas to schoolbox
        $canvasAssessmentIds = [];

        // In Canvas, an assessment belongs to a course, and a course has multiple classes (known as course sections).
        // A single canvas course assessment therefore belongs to 0 or more schoolbox class assessments.
        // We need to fetch the assessment details from the course, and the list of class sis codes
        $schoolboxFolderCodes = $this->fetchSchoolboxFolderCodes();
        $sisBindings = Database\Predicate\Builder::getBindings(array_keys($schoolboxFolderCodes), 'cd_sis_source_id');
        $sql = "
            SELECT 
                   ad.canvas_id,
                   ad.title,
                   ad.description,
                   ad.unlock_at,
                   ad.due_at,
                   ad.points_possible,
                   ad.grading_type,
                   ad.submission_types,
                   ad.workflow_state,
                   ad.created_at,
                   ad.updated_at,
                   ad.all_day,
                   ad.all_day_date,
                   ad.muted,
                   GROUP_CONCAT(csd.sis_source_id)
            FROM assignment_dim ad
            JOIN assignment_fact af ON af.assignment_id = ad.id
            JOIN course_dim cd ON cd.id = ad.course_id
            JOIN course_section_dim csd ON csd.course_id = cd.id
            WHERE 
                  cd.workflow_state != 'deleted' AND
                  (ad.due_at IS NOT NULL OR (ad.all_day AND ad.all_day_date IS NOT NULL))
            GROUP BY ad.canvas_id
            ";
        $statement = $this->canvasDb->executeQuery($sql, $sisBindings->getParams());
        /** @noinspection PhpAssignmentInConditionInspection */
        while($row = $statement->fetch()){
            $assignmentCanvasId = $row['canvas_id'];
            $assignmentTitle    = $row['title'];
            $assignmentDescription = $row['description'] ?? '';

            // Is this a create or update?
            /** @var DueWork\Model|null $existing */
            $existing = $this->assessmentMapperProxy->findByColumn(Assessment\Mapper::COL_EXTERNAL_ID, $assignmentCanvasId)->first();
            if($existing){
                // Update
                $dueWork = $existing;
                $this->log->debug("Matching assessment found in schoolbox with external id '$assignmentCanvasId', updating...");
            } else {
                // Create
                $dueWork = (new DueWork\Model())->setExternalId($assignmentCanvasId);
                $this->log->debug("No matching assessment found in schoolbox with external id '$assignmentCanvasId', creating...");
            }
            $folder = $this->fetchFolderByCode($folderCode);
            if(!$folder){
                if($existing){
                    // Assignment moved in Canvas to a folder that doesn't exist in Schoolbox, delete
                    $this->log->warning(sprintf(
                        "Assignment moved to a folder that does not exist in Schoolbox, deleting (%s)...",
                        $existing->getExternalId()
                    ));
                    $this->assessmentMapperProxy->delete($existing);
                } else {
                    // Don't create if no folder exists in schoolbox to insert assessment into
                    $this->log->warning(sprintf(
                        "No folder found for code '%s', skipping assessment '%s' (%s)",
                        $folderCode,
                        $assignmentTitle,
                        $assignmentCanvasId
                    ));
                }
                continue;
            }
            $dueDateTime = $row['due_at'] ?? $row['all_day_date'] . " 23:59:00";
            $dueAt = new Alaress_Date_Format($dueDateTime, Alaress_Date_Format::DATABASE_FORMAT);
            $creator = $this->fetchAssignmentCreator((int)$assignmentCanvasId, $folderCode);
            $markType = $this->deriveMarkType($row['ad.grading_type']);
            $dueWork
                ->setTitle($assignmentTitle)
                ->setDescription($assignmentDescription)
                ->setWeight(1.0)
                ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_NONE)
                ->setPublishAt()
                ->setOpenAt()
                ->setDueAt($dueAt)
                ->setCloseAt()
                ->setMaxMark($row['points_possible'] === null ? $row['points_possible'] : (float)$row['points_possible'])
                ->setContext($folder)
                ->setCreator($creator)
                ->setMarkType($markType);
            $this->assessmentMapperProxy->save($dueWork);
            $this->log->info("Assessment saved. Schoolbox ID ({$dueWork->getId()}), Canvas ID ({$dueWork->getExternalId()})");
        }
    }

    /**
     * Find a suitable user to set as this assignments owner.
     * Order of preference:
     * * Canvas user who has a 'TeacherEnrollment' for the assignment's course
     * * Synergetic teacher of the assignment's course
     * * Alaress user
     *
     * @param int          $assignmentCanvasId
     * @param string $folderCode
     * @return User
     * @throws Mapper\Exception
     * @throws Database\Exception
     */
    private function fetchAssignmentCreator(int $assignmentCanvasId, string $folderCode): User
    {
        // First canvas user who is teaching course, and has Staff user record in Schoolbox
        // TODO should we check enrollment.role too?
        $sql = "
            SELECT pd.sis_user_id
            FROM assignment_dim ad
            JOIN enrollment_dim ed ON ed.course_id = ad.course_id
            JOIN pseudonym_dim pd ON pd.user_id = ed.user_id
            WHERE ed.type = 'TeacherEnrollment' AND ad.canvas_id = :ad_canvas_id AND pd.sis_user_id IS NOT NULL
        ";
        $externalIds = $this->canvasDb->executeQuery($sql, ['ad_canvas_id' => $assignmentCanvasId])->fetchAll(PDO::FETCH_COLUMN);
        if(count($externalIds)){
            $columnList = ['synergy_id' => $externalIds, 'g.group_type' => Role::STAFF];
            $user = UserMapper::getInstance()->findByColumnList($columnList, ['limit' => 1])->first();
            if($user){
                return $user;
            }
        }
        $externalIds = $this->externalClass->getTeachers($folderCode);
        $columnList = ['synergy_id' => $externalIds, 'g.group_type' => Role::STAFF];
        $user = UserMapper::getInstance()->findByColumnList($columnList, ['limit' => 1])->first();
        if($user){
            return $user;
        }
        return UserMapper::getInstance()->findByUsername('alaress');
    }

    private function deriveMarkType(?string $gradingType): SubmissionBox\MarkType
    {
        $markTypes = SubmissionBox\MarkTypeMapper::getInstance()->findAll()->indexBy('slug');
        switch ($gradingType) {
            case null:
            case 'not_graded':
                return $markTypes[SubmissionBox\MarkType::SLUG_NONE];
                break;
            case 'points':
                return $markTypes[SubmissionBox\MarkType::SLUG_TOTAL];
                break;
            case 'pass_fail':
                return $markTypes[SubmissionBox\MarkType::SLUG_COMPLETION];
                break;
            case 'letter_grade':
                return $markTypes[SubmissionBox\MarkType::SLUG_LETTER];
                break;
            case 'percent':
                return $markTypes[SubmissionBox\MarkType::SLUG_PERCENT];
                break;
            case 'gpa_scale':
                return $markTypes[SubmissionBox\MarkType::SLUG_TOTAL];
                break;
        }
        return null;
    }

    /**
     * Returns array mapping folder codes to folder ids
     *
     * @return int[]
     */
    private function fetchSchoolboxFolderCodes(): array
    {
        $sql = "
            SELECT fc.code 'fc.code', f.id 'f.id'
            FROM folder f 
            JOIN folder_code fc on f.id = fc.folder_id
            WHERE f.deleted_at IS NULL AND fc.deleted_at IS NULL
        ";
        $folderCodes = [];
        foreach($this->schoolboxDb->getQueryIterator($sql) as $row){
            $folderCodes[$row['fc.code']] = (int)$row['f.id'];
        }
        return $folderCodes;
    }


    /**
     * Conditionally execute a SQL query on Schoolbox database
     *
     * @param string $sql
     * @param array  $params
     * @throws Database\Exception
     */
    private function executeIfPersisting(string $sql, array $params = []): void
    {
        if ($this->persist) {
            $this->schoolboxDb->executeQuery($sql, $params);
        }
    }
}
