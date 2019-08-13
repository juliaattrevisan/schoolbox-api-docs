<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */
declare(strict_types=1);

namespace External\Canvas\Sync;

use Alaress\Database\Connector\IConnector;
use Alaress_Date_Format;
use External_ClassInterface;
use PDO;
use Alaress\Database;
use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Learning\MarkScheme\None;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use Schoolbox\Resource\Folder;
use Schoolbox\Mapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnexpectedValueException;

/**
 * Responsible for injecting data into SB database using Canvas and Synergetic connections
 */
class Importer
{
    /** @var External_ClassInterface */
    private $externalClass;

    /** @var IConnector */
    private $schoolboxDb;

    /** @var IConnector */
    private $canvasDb;

    /** @var Assessment\MapperProxy */
    private $assessmentMapperProxy;

    /** @var IConfigLoader */
    private $config;

    /** @var LoggerInterface */
    private $log;

    /**
     * Indexed by folderCode
     *
     * @var GenericCollection|Folder\Model[]
     */
    private $folderCache;

    public function __construct(
        External_ClassInterface $externalClass,
        IConnector $schoolboxDb,
        IConnector $canvasDb,
        Assessment\MapperProxy $assessmentMapperProxy,
        IConfigLoader $config,
        LoggerInterface $log
    )
    {
        $this->externalClass = $externalClass;
        $this->schoolboxDb = $schoolboxDb;
        $this->canvasDb = $canvasDb;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->log = $log;
        $this->folderCache = new GenericCollection(Folder\Model::class);
        $this->config = $config;
    }

    /**
     * Performs import
     */
    public function import(): void
    {
        $this->importAssessments();
        $this->importGrades();

        // Find users who are in both systems
//        $canvasUsers = $this->canvasDb->executeQuery("SELECT sis_user_id FROM pseudonym_dim WHERE sis_user_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
//        $schoolboxUsers = $this->schoolboxDb->executeQuery("SELECT synergy_id FROM user WHERE synergy_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Imports assessments from canvas database to schoolbox
     */
    private function importAssessments(): void
    {
        // Only import assessments that belong to courses that match folders already in SB
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
                   ad.visibility,
                   ad.submission_types,
                   ad.workflow_state,
                   ad.created_at,
                   ad.updated_at,
                   ad.all_day,
                   ad.all_day_date,
                   ad.muted,
                   cd.sis_source_id
            FROM assignment_dim ad
            JOIN assignment_fact af ON af.assignment_id = ad.id
            JOIN course_dim cd ON cd.id = ad.course_id
            WHERE 
                  cd.sis_source_id IN ({$sisBindings->getPlaceholder()}) AND
                  cd.workflow_state != 'deleted' AND
                  (ad.due_at IS NOT NULL OR (ad.all_day AND ad.all_day_date IS NOT NULL))
            ";

        $statement = $this->canvasDb->executeQuery($sql, $sisBindings->getParams());
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch()) {

            $folderCode = $row['sis_source_id'];
            $assignmentCanvasId = $row['canvas_id'];
            $assignmentTitle = $row['title'];

            // Is this a create or update?
            /** @var DueWork\Model|null $existing */
            $existing = $this->assessmentMapperProxy->findByColumn(Assessment\Mapper::COL_EXTERNAL_ID, $assignmentCanvasId)->first();
            if ($existing) {
                // Update
                $dueWork = $existing;
                $this->log->debug("Matching assessment found in schoolbox with external id '$assignmentCanvasId', updating...");
            } else {
                // Create
                $dueWork = (new DueWork\Model())->setExternalId($assignmentCanvasId);
                $this->log->debug("No matching assessment found in schoolbox with external id '$assignmentCanvasId', creating...");
            }

            $folder = $this->fetchFolderByCode($folderCode);
            if (!$folder) {
                if ($existing) {
                    // Assignment moved in Canvas to a folder that doesn't exist in Schoolbox, delete
                    $this->log->warning(sprintf(
                        "Assignment moved to a folder that does not exist in Schoolbox, deleting (%s)...",
                        $existing->getExternalId()
                    ));
                    try {
                        $this->assessmentMapperProxy->delete($existing);
                    } catch (Mapper\Exception $e) {
                        $this->log->warning("Failed to delete: " . $e->getMessage());
                    }
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

            $markSchema = $this->deriveMarkSchema($row['grading_type']);
            $dueWork
                ->setTitle($assignmentTitle)
                ->setDescription($row['description'] ?? '')
                ->setWeight(1.0)
                ->setSelfReviewability(SubmissionBox::SELF_REVIEWABILITY_NONE)
                ->setMarkVisibility($row['visibility'] === 'everyone' ? IAssessable::MARK_VISIBILITY_PARENT_STUDENT : IAssessable::MARK_VISIBILITY_PRIVATE)
                ->setPublishAt(new Alaress_Date_Format($row['unlock_at'], Alaress_Date_Format::DATABASE_FORMAT))
                ->setOpenAt(new Alaress_Date_Format($row['unlock_at'], Alaress_Date_Format::DATABASE_FORMAT))
                ->setDueAt($dueAt)
                ->setCloseAt($dueAt)
                ->setMaxMark($row['points_possible'] === null ? $row['points_possible'] : (float)$row['points_possible'])
                ->setContext($folder)
                ->setCreator($creator)
                ->setMarkScheme($markSchema);

            try {
                $this->assessmentMapperProxy->save($dueWork);
                $this->log->info("Assessment saved. Schoolbox ID ({$dueWork->getId()}), Canvas ID ({$dueWork->getExternalId()})");
            } catch (Mapper\Exception $e) {
                $this->log->warning("Failed to save assessment (canvas_id: {$row['canvas_id']}): " . $e->getMessage());
            }
        }
    }

    /**
     * @param string $folderCode
     * @return Folder\Model|null
     * @throws Database\Predicate\InvalidColumnException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function fetchFolderByCode(string $folderCode): ?Folder\Model
    {
        if (!$this->folderCache->keyExists($folderCode)) {
            $folder = Folder\Mapper::getInstance()->findForCampusAndClass('', $folderCode);
            $this->folderCache[$folderCode] = $folder;
        }
        return $this->folderCache[$folderCode];
    }

    /**
     * Finds a suitable user to set as this assignments owner.
     * Order of preference:
     * * Canvas user who has a 'TeacherEnrollment' for the assignment's course
     * * Synergetic teacher of the assignment's course
     * * Alaress user
     *
     * @param int $assignmentCanvasId
     * @param string $folderCode
     * @return User
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
        if (count($externalIds)) {
            $columnList = ['synergy_id' => $externalIds, 'g.group_type' => Role::STAFF];
            try {
                $user = UserMapper::getInstance()->findByColumnList($columnList, ['limit' => 1])->first();
                if ($user) {
                    return $user;
                }
            } catch (Mapper\Exception $e) {
                $this->log->warning('Failed to find user with synergy_id in :' . implode(',', $externalIds));
            }
        }

        $externalIds = $this->externalClass->getTeachers($folderCode);
        $columnList = ['synergy_id' => $externalIds, 'g.group_type' => Role::STAFF];
        try {
            $user = UserMapper::getInstance()->findByColumnList($columnList, ['limit' => 1])->first();
            if ($user) {
                return $user;
            }
        } catch (Mapper\Exception $e) {
            $this->log->warning('Failed to find user with synergy_id in :' . implode(',', $externalIds));
        }

        return UserMapper::getInstance()->findByUsername('alaress');
    }

    /**
     * Gets mark schema based on grading_type
     * @param string|null $gradingType
     * @return IMarkScheme
     */
    private function deriveMarkSchema(?string $gradingType): IMarkScheme
    {
        switch ($gradingType) {
            case null:
            case 'not_graded':
                return new None();
                break;
            case 'points':
            case 'gpa_scale':
                // TODO: get from points_possible??
                return new TotalMarkFractional(100);
                break;
            case 'pass_fail':
//                return new Completion(); is not supported by Due work Translator
                return new Percentage();
                break;
            case 'letter_grade':
                return new LetterGrade($this->config->get('default_grade_group'));
                break;
            case 'percent':
                return new Percentage();
                break;
            default:
                return new None();
        }
    }

    private function importGrades(): void
    {
    }

    /**
     * Returns array mapping folder codes to folder ids
     *
     * @return array
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
        foreach ($this->schoolboxDb->getQueryIterator($sql) as $row) {
            $folderCodes[$row['fc.code']] = $row['f.id'];
        }
        return $folderCodes;
    }
}