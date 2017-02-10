<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment;

use Alaress\IDatabase;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\Task\ICompletion;
use Schoolbox\Mapper\PredicateBuilder;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;
use Schoolbox\Learning\Quiz\Model as QuizModel;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Resource\Folder\Code\Model as FolderCode;
use \Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Model as QuizReview;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkSubmission;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\Task\Completion\Model as TaskCompletion;
use Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;

/**
 * Optimised DB queries to build objects for specific purposes
 */
class PendingFactory
{
    /** @var IDatabase */
    private $database;

    /** @var \External_UserInterface */
    private $externalUser;

    /** @var \External_ClassInterface */
    private $externalClass;

    /**
     * Indexed by assessment.id
     *
     * A Collection object rather than array as objects are returned to caller.
     *
     * @var IAssessable[]|Collection $assessmentsCache
     */
    private $assessmentsCache;

    /**
     * Indexed by course_specification.id
     * @var CourseSpecification[] $courseSpecsCache
     */
    private $courseSpecsCache = [];

    /**
     * Indexed by course_implementation.id
     * @var CourseImplementation[] $courseImplsCache
     */
    private $courseImplsCache = [];

    /**
     * Indexed by course_unit_specification.id
     * @var UnitSpecification[] $unitSpecsCache
     */
    private $unitSpecsCache = [];

    /**
     * Indexed by course_unit_implementation.id
     * @var UnitImplementation[] $unitImplsCache
     */
    private $unitImplsCache = [];

    /**
     * Each Folder requires an ID and Name. Indexed by folder.id
     * @var Folder[] $foldersCache
     */
    private $foldersCache = [];

    /**
     * Each Project requires an ID and Title. Indexed by assessment.id
     * @var Project[] $projectCache
     */
    private $projectCache = [];

    /**
     * Folder IDs of all Subjects either taught by this User or where this User has created Assessments
     * key = value = folder.id
     *
     * @var int[] $classIds
     */
    private $classIds = [];

    /**
     * Folder IDs of all Groups either administered by this User or where this User has created Assessments
     * key = value = folder.id
     *
     * @var int[] $groupIds
     */
    private $groupIds = [];

    /** @var int[] $quizAssessmentIds */
    private $quizAssessmentIds = [];

    /** @var int[] $dueWorkAssessmentIds */
    private $dueWorkAssessmentIds = [];

    /** @var int[] $taskAssessmentIds */
    private $taskAssessmentIds = [];

    /**
     * Maps a folder.id to set of non-unique classCodes.
     * Need to make sets unique before use.
     * Cannot ensure uniqueness by using classCodes as strings as class codes are not always valid array keys.
     *
     * @var string[][] $folderClassCodes
     */
    private $folderClassCodes = [];

    /**
     * Maps Subject/Group folder.id to sets of Student externalIds
     *
     * @var int[][] $folderToStudentListMap
     */
    private $folderToStudentListMap = [];

    /**
     * Set of all student.externalIds the specified User teaches, administers, or are in Groups or Subjects
     * where the User has created Assessments.
     * We need this so we know which Users to get SchoolBox details for
     * key = value = user.externalId
     *
     * @var string[]|int[] $studentExternalIds
     */
    private $studentExternalIds = [];

    /**
     * Set of all student.ids the specified User teaches, administers, or are in Groups or Subjects
     * where the User has created Assessments.
     * We need this so we know which Users to get SchoolBox details for
     * key = value = user.id
     *
     * @var int[] $studentInternalIds
     */
    private $studentInternalIds = [];

    /**
     * All known students.
     *
     * Indexed by user.externalId so we can find O(1) when assigning to Classes
     *
     * @var User[] $studentExtCache
     */
    private $studentExtCache = [];

    /**
     * All known students.
     *
     * Indexed by user.id so we can find O(1) when generating Participants
     *
     * @var User[] $studentIntCache
     */
    private $studentIntCache = [];

    /** @var Term $term */
    private $term;


    /**
     * Which User the Assessments produced by this factory relate to
     *
     * @var User $viewee
     */
    private $viewee;

    /**
     * Students with Responses/Feedbacks but Student not currently a member of Group/Class studentList
     *
     * Occurs if Student leaves a Group/Class after Response or Feedback created.
     *
     * If we detect these cases, and can't find the Student in our cache, we need to go back to
     * Schoolbox DB and get their data.
     *
     * key = val = user.id
     *
     * @var int[]
     */
    private $assessmentExtraStudentIds = [];

    /**
     * Used to determine if an assessment requires chasing
     *
     * @var \Alaress_Date_Format
     */
    private $toChaseLimit;

    /**
     * @var \Alaress_Date_NullDate
     */
    private $nullDate;

    /**
     * Should we include Tasks in the results ? As opposed to just Quiz + DueWork ?
     *
     * @var int
     */
    private $includeTasks;

    /**
     * @param User $viewee
     * @param \External_UserInterface|null $externalUser
     * @param \External_ClassInterface $externalClass
     * @param Term $term
     * @param \Alaress_Date_Format $toChaseLimit
     * @param bool $includeTasks
     */
    public function __construct(
        User $viewee,
        \External_UserInterface $externalUser = null,
        \External_ClassInterface $externalClass,
        Term $term,
        \Alaress_Date_Format $toChaseLimit,
        $includeTasks
    ) {
        $this->viewee = $viewee;
        $this->database = \Database::getInstance();
        $this->externalUser = $externalUser;
        $this->externalClass = $externalClass;
        $this->assessmentsCache = new Collection();
        $this->term = $term;
        $this->toChaseLimit = $toChaseLimit;
        $this->nullDate = new \Alaress_Date_NullDate();
        $this->includeTasks = $includeTasks;
    }


    /**
     * Get Assessments that belong to Classes taught by User, or Groups administered by User.
     *
     * Also get any Assessments created by the User.
     *
     * Note that Projects do not have a 'creator', so we don't have to handle odd case where
     * UserA creates ProjectX on UserB's class page, and UserB creates the Assessments in ProjectX.
     *
     * Assessments may reside:
     * * directly on Folders,
     * * in Projects on Folders,
     * * in UnitImplementations on Folders
     * * in Projects that are in UnitImplementation on Folders
     *
     * @return Collection|IAssessable[]
     */
    public function getPendingAssessments()
    {
        $this->getTaughtClasses();
        $this->getGroupsAdministered();
        $this->getAssessments();
        $this->getClassStudentLists();
        $this->getStudentsForClassesAndGroups();
        $this->createAssessmentParticipants();

        // We now have Assessments, linked to their Contexts, and with their Participants.
        // Let's populate and link up the Responses and Feedbacks to the Assessments and Participants

        // Need to handle Responses and Feedbacks for Users who are not in the Group or Class studentList.
        // - Run all DB Response/Feedback queries & store results in associative arrays
        // - iterate arrays to find missing students (if missing from assessment, but exists in student cache, we can skip)
        // - Get all missing students from DB, and add to studentCache
        // - Now process result arrays, inserting missing Participants when required.

        $quizResults = $this->getQuizResponsesAndFeedback();
        $dueWorkResponses = $this->getDueWorkResponses();
        $dueWorkFeedbackResults = $this->getDueWorkFeedbacks();
        $taskResults = $this->getTaskResponsesAndFeedback();

        $this->getMissingUsers();

        // Process the results, creating extra Participants where required, pulling User from caches
        $this->hydrateQuizResults($quizResults);
        $this->hydrateDueWorkResponses($dueWorkResponses);
        $this->hydrateDueWorkFeedbacks($dueWorkFeedbackResults);
        $this->hydrateTaskResults($taskResults);

        return $this->assessmentsCache;
    }

    /**
     * Ensure each logically same Folder is same object instance
     *
     * @param int $id
     * @param string $name
     */
    private function cacheFolder($id, $name)
    {
        $id = intval($id);
        if (!$this->foldersCache[$id] instanceof Folder) {
            $this->foldersCache[$id] = (new Folder())->setId($id)->setName($name);
        }
    }

    /**
     * Ensure each logically same Project is same object instance
     *
     * @param int $id
     * @param string $title
     */
    private function cacheProject($id, $title)
    {
        $id = intval($id);
        if (!$this->projectCache[$id] instanceof Project) {
            $this->projectCache[$id] = (new Project())->setId($id)->setTitle($title);
        }
    }

    /**
     * Ensure each logically same CourseSpecification is same object instance
     *
     * @param int $id
     */
    private function cacheCourseSpecification($id)
    {
        $id = intval($id);
        if (!$this->courseSpecsCache[$id] instanceof CourseSpecification) {
            $this->courseSpecsCache[$id] = (new CourseSpecification())->setId($id);
        }
    }

    /**
     * Ensure each logically same CourseSpecification is same object instance
     *
     * @param int $id
     */
    private function cacheCourseImplementation($id)
    {
        $id = intval($id);
        if (!$this->courseImplsCache[$id] instanceof CourseImplementation) {
            $this->courseImplsCache[$id] = (new CourseImplementation())->setId($id);
        }
    }

    /**
     * Ensure each logically same UnitSpecification is same object instance
     *
     * @param int $id
     */
    private function cacheUnitSpecification($id)
    {
        $id = intval($id);
        if (!$this->unitSpecsCache[$id] instanceof UnitSpecification) {
            $this->unitSpecsCache[$id] = (new UnitSpecification())->setId($id);
        }
    }

    /**
     * Ensure each logically same UnitImplementation is same object instance
     *
     * @param int $id
     */
    private function cacheUnitImplementation($id)
    {
        $id = intval($id);
        if (!$this->unitImplsCache[$id] instanceof UnitImplementation) {
            $this->unitImplsCache[$id] = (new UnitImplementation())->setId($id);
        }
    }

    /**
     * Adds the specified classCode to the specified folder's set.
     *
     * Does NOT ensure codes are only added once.
     *
     * @param int $folderId
     * @param string $code
     */
    private function addClassCodeToFolder($folderId, $code)
    {
        if (!is_array($this->folderClassCodes[$folderId])) {
            $this->folderClassCodes[$folderId] = [];
        }
        array_push($this->folderClassCodes[$folderId], $code);
    }

    /**
     * Get Assessments associated with Classes taught, or Groups administered, by User.
     * Also get Assessments created by User.
     *
     * Required Data:
     * * CourseSpec's Folder id/name
     * * UnitSpec's Folder id/name
     * * Subject/Group Folder id/name
     * * Project name
     * so we can build context breadcrumbs
     *
     * This is because UserA may create an Assessment on a pageX taught by UserB.
     * UserA is still interested in this Assessment, but not in other Assessments on pageX
     *
     * Case 1: Assessment resides directly on Folder                  => Folder name+id
     * Case 2: Assessment in Project that resides on Folder           => Folder name+id + Project name
     * Case 3: Assessment in UnitImpl imported on Folder              => Folder name+id + UnitSpec name+id + ClassSpec name+id
     * Case 4: Assessment in Project in UnitImpl imported on Folder   => Folder name+id + UnitSpec name+id + ClassSpec name+id + Project name
     */
    private function getAssessments()
    {
        // Get Assessments for Groups and Subjects administered/taught by User
        $folderIds = array_unique(array_merge($this->classIds, $this->groupIds));

        $taskSelect = $this->includeTasks ?
            "tk.id                    AS 'tk.id',
             tk.requires_confirmation AS 'tk.requires_confirmation'," : '';
        $taskJoin = $this->includeTasks ? 'LEFT JOIN task tk           ON tk.assessment_id = a.id AND tk.deleted_at IS NULL' : '';
        $taskUser = $this->includeTasks ? "OR tk.user_id = {$this->viewee->getId()}" : '';
        $taskId   = $this->includeTasks ? "OR tk.id IS NOT NULL" : '';

        $folderClause = count($folderIds) ? " OR f.id IN (" . implode(',', $folderIds) . ") " : '';
        $query1 = "
        SELECT 
                1            AS 'case',
                        
                NULL         AS 'cs.id',
                NULL         AS 'csf.id',
                NULL         AS 'csf.name',
                NULL         AS 'ci.id',
                f.id         AS 'f.id',
                f.name       AS 'f.name',                
                NULL         AS 'us.id',
                NULL         AS 'usf.id',
                NULL         AS 'usf.name',
                NULL         AS 'ui.id',
                NULL         AS 'p.id',
                NULL         AS 'p.title',
                
                a.id         AS 'a.id',
                a.title      AS 'a.title',
                a.due_date   AS 'a.due_date',
                
                qz.assess_id AS 'qz.id',
                dw.id        AS 'dw.id',
                $taskSelect
                
                fc.code      AS 'fc.code',
                ug.cid       AS 'g.id'                
            FROM assessment a 
            LEFT JOIN assess qz         ON qz.assessment_id = a.id AND qz.deleted_at IS NULL
            LEFT JOIN submission_box dw ON dw.assessment_id = a.id AND dw.deleted = 0
            $taskJoin
            
            JOIN assessment_folder af   ON af.assessment_id = a.id 
            JOIN folder f               ON f.id = af.folder_id AND f.deleted_at IS NULL
            
            LEFT JOIN folder_code fc    ON fc.folder_id = f.id AND fc.deleted_at IS NULL
            LEFT JOIN userGroups ug     ON ug.cid = f.id
            
            WHERE ((qz.user_id = {$this->viewee->getId()} OR dw.owner = {$this->viewee->getId()} $taskUser) $folderClause)
            AND (qz.assess_id IS NOT NULL OR dw.id IS NOT NULL $taskId)
            AND a.due_date >= STR_TO_DATE('" . $this->term->getStart()->getDatabaseDate() . "', '%Y-%m-%d')
        ";

        $folderClause = count($folderIds) ? " OR pf.id IN (" . implode(',', $folderIds) . ") " : '';
        $query2 = "
        SELECT 
                2            AS 'case',
                        
                NULL         AS 'cs.id',
                NULL         AS 'csf.id',
                NULL         AS 'csf.name',
                NULL         AS 'ci.id',
                pf.id        AS 'f.id',
                pf.name      AS 'f.name',                
                NULL         AS 'us.id',
                NULL         AS 'usf.id',
                NULL         AS 'usf.name',
                NULL         AS 'ui.id',
                pa.id        AS 'p.id',
                pa.title     AS 'p.title',
                
                a.id         AS 'a.id',
                a.title      AS 'a.title',
                a.due_date   AS 'a.due_date',
                
                qz.assess_id AS 'qz.id',
                dw.id        AS 'dw.id',
                $taskSelect
                
                fc.code      AS 'fc.code',
                ug.cid       AS 'g.id'                
            FROM assessment a 
            LEFT JOIN assess qz         ON qz.assessment_id = a.id AND qz.deleted_at IS NULL
            LEFT JOIN submission_box dw ON dw.assessment_id = a.id AND dw.deleted = 0
            $taskJoin
            
            JOIN project_assessment pra ON pra.assessment_id = a.id
            JOIN project p              ON pra.project_id = p.id
            JOIN assessment pa          ON p.assessment_id = pa.id
            JOIN assessment_folder paf  ON paf.assessment_id = pa.id
            JOIN folder pf              ON pf.id = paf.folder_id AND pf.deleted_at IS NULL
            
            LEFT JOIN folder_code fc    ON fc.folder_id = pf.id AND fc.deleted_at IS NULL
            LEFT JOIN userGroups ug     ON ug.cid = pf.id
            
            WHERE ((qz.user_id = {$this->viewee->getId()} OR dw.owner = {$this->viewee->getId()} $taskUser) $folderClause)
            AND (qz.assess_id IS NOT NULL OR dw.id IS NOT NULL $taskId)
            AND a.due_date >= STR_TO_DATE('" . $this->term->getStart()->getDatabaseDate() . "', '%Y-%m-%d')
        ";

        $folderClause = count($folderIds) ? " OR cif.id IN (" . implode(',', $folderIds) . ") " : '';
        $query3 = "
        SELECT 
                3            AS 'case',
                        
                cs.id        AS 'cs.id',
                csf.id       AS 'csf.id',
                csf.name     AS 'csf.name',
                ci.id        AS 'ci.id',
                cif.id       AS 'f.id',
                cif.name     AS 'f.name',                
                us.id        AS 'us.id',
                usf.id       AS 'usf.id',
                usf.name     AS 'usf.name',
                ui.id        AS 'ui.id',
                NULL         AS 'p.id',
                NULL         AS 'p.title',
                
                a.id         AS 'a.id',
                a.title      AS 'a.title',
                a.due_date   AS 'a.due_date',
                
                qz.assess_id AS 'qz.id',
                dw.id        AS 'dw.id',
                $taskSelect
                
                fc.code      AS 'fc.code',
                ug.cid       AS 'g.id'                
            FROM assessment a 
            LEFT JOIN assess qz                            ON qz.assessment_id = a.id AND qz.deleted_at IS NULL
            LEFT JOIN submission_box dw                    ON dw.assessment_id = a.id AND dw.deleted = 0
            $taskJoin
            
            JOIN assessment_course_unit_implementation aui ON aui.assessment_id = a.id
            JOIN course_unit_implementation ui             ON ui.id = aui.course_unit_implementation_id
            JOIN course_implementation ci                  ON ci.id = ui.course_implementation_id 
            JOIN folder cif                                ON cif.id = ci.folder_id AND cif.deleted_at IS NULL
            JOIN course_unit_specification us              ON us.id = ui.course_unit_specification_id
            JOIN folder usf                                ON usf.id = us.folder_id AND usf.deleted_at IS NULL
            JOIN course_specification cs                   ON cs.id = ci.course_specification_id
            JOIN folder csf                                ON csf.id = cs.folder_id AND csf.deleted_at IS NULL
            
            LEFT JOIN folder_code fc                       ON fc.folder_id = cif.id AND fc.deleted_at IS NULL
            LEFT JOIN userGroups ug                        ON ug.cid = cif.id
            
            WHERE ((qz.user_id = {$this->viewee->getId()} OR dw.owner = {$this->viewee->getId()} $taskUser) $folderClause)
            AND (qz.assess_id IS NOT NULL OR dw.id IS NOT NULL $taskId)
            AND a.due_date >= STR_TO_DATE('" . $this->term->getStart()->getDatabaseDate() . "', '%Y-%m-%d')
        ";

        // Assessment in Project in UnitImpl imported on Folder
        $folderClause = count($folderIds) ? " OR cif.id IN (" . implode(',', $folderIds) . ") " : '';
        $query4 = "
        SELECT 
                4            AS 'case',
                        
                cs.id        AS 'cs.id',
                csf.id       AS 'csf.id',
                csf.name     AS 'csf.name',
                ci.id        AS 'ci.id',
                cif.id       AS 'f.id',
                cif.name     AS 'f.name',                
                us.id        AS 'us.id',
                usf.id       AS 'usf.id',
                usf.name     AS 'usf.name',
                ui.id        AS 'ui.id',
                pra.id       AS 'p.id',
                pra.title    AS 'p.title',
                
                a.id         AS 'a.id',
                a.title      AS 'a.title',
                a.due_date   AS 'a.due_date',
                
                qz.assess_id AS 'qz.id',
                dw.id        AS 'dw.id',
                $taskSelect
                
                fc.code      AS 'fc.code',
                ug.cid       AS 'g.id'                
            FROM assessment a 
            LEFT JOIN assess qz                            ON qz.assessment_id = a.id AND qz.deleted_at IS NULL
            LEFT JOIN submission_box dw                    ON dw.assessment_id = a.id AND dw.deleted = 0
            $taskJoin
            
            JOIN project_assessment pa                     ON pa.assessment_id = a.id
            JOIN project p                                 ON p.id = pa.project_id
            JOIN assessment pra                            ON pra.id = p.assessment_id
            JOIN assessment_course_unit_implementation aui ON aui.assessment_id = pra.id
            JOIN course_unit_implementation ui             ON ui.id = aui.course_unit_implementation_id AND ui.deleted_at IS NULL
            JOIN course_implementation ci                  ON ci.id = ui.course_implementation_id       AND ci.deleted_at IS NULL
            JOIN folder cif                                ON cif.id = ci.folder_id                     AND cif.deleted_at IS NULL
            JOIN course_unit_specification us              ON us.id = ui.course_unit_specification_id   AND us.deleted_at IS NULL
            JOIN folder usf                                ON usf.id = us.folder_id                     AND usf.deleted_at IS NULL
            JOIN course_specification cs                   ON cs.id = ci.course_specification_id        AND cs.deleted_at IS NULL
            JOIN folder csf                                ON csf.id = cs.folder_id                     AND csf.deleted_at IS NULL
            
            LEFT JOIN folder_code fc                       ON fc.folder_id = cif.id AND fc.deleted_at IS NULL
            LEFT JOIN userGroups ug                        ON ug.cid = cif.id
            
            WHERE a.deleted_at IS NULL
            AND ((qz.user_id = {$this->viewee->getId()} OR dw.owner = {$this->viewee->getId()} $taskUser) $folderClause)
            AND (qz.assess_id IS NOT NULL OR dw.id IS NOT NULL $taskId)
            AND a.due_date >= STR_TO_DATE('" . $this->term->getStart()->getDatabaseDate() . "', '%Y-%m-%d')
        ";

        $statement = $this->database->prepare("$query1 UNION $query2 UNION $query3 UNION $query4");

        $statement->execute();
        $this->parseAssessments($statement);
    }


    /**
     * Iterate over results to build Assessment graph
     *
     * Extract and cache any GroupIds or ClassCodes to later retrieve the studentLists
     *
     * @param \PDOStatement $statement
     */
    private function parseAssessments($statement)
    {
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {

            $assessmentId = intval($row['a.id']);

            // Hydrate Assessment
            if (!$this->assessmentsCache[$assessmentId] instanceof IAssessable) {

                // Create Quiz / DueWork / Task object

                // Add ID to type array for later retrieval of Response / Feedback
                if (!is_null($row['qz.id'])) {
                    $this->quizAssessmentIds[$assessmentId] = $assessmentId;
                    $this->assessmentsCache[$assessmentId] = new Quiz();
                } elseif (!is_null($row['dw.id'])) {
                    $this->dueWorkAssessmentIds[$assessmentId] = intval($assessmentId);
                    $this->assessmentsCache[$assessmentId] = new DueWork();
                } elseif (!is_null($row['tk.id'])) {
                    $this->taskAssessmentIds[$assessmentId] = intval($assessmentId);
                    $this->assessmentsCache[$assessmentId] = (new Task())->setIsSelfAssessed(!$row['tk.requires_confirmation']);
                } else {
                    throw new \LogicException('Assessment must be Quiz, DueWork or Task: ' . print_r($row, true));
                }

                $assessment = $this->assessmentsCache[$assessmentId];
                $assessment->setId($assessmentId);
                $assessment->setTitle($row['a.title']);

                // It is VERY slow to create a new AlaressDateFormat from a DB formatted string.
                $assessment->setDueAt($this->getDateFromString($row['a.due_date']));

                $projectId = intval($row['p.id']);
                $projectTitle = $row['p.title'];
                $unitImplId = intval($row['ui.id']);
                $unitSpecId = intval($row['us.id']);
                $courseImplId = intval($row['ci.id']);
                $courseSpecId = $row['cs.id'];
                $folderId = intval($row['f.id']);
                $folderName = $row['f.name'];
                $unitSpecFolderId = intval($row['usf.id']);
                $unitSpecFolderName = $row['usf.name'];
                $courseSpecFolderId = intval($row['csf.id']);
                $courseSpecFolderName = $row['csf.name'];

                switch ($row['case']) {
                    // Case 1 - Assessment resides directly on Folder
                    case 1:
                        $this->cacheFolder($folderId, $folderName);
                        $assessment->setContext($this->foldersCache[$folderId]);
                        break;
                    // Case 2 - Assessment in Project that resides directly on Folder
                    case 2:
                        $this->cacheProject($projectId, $projectTitle);
                        $this->cacheFolder($folderId, $folderName);
                        $assessment->setContext($this->projectCache[$projectId]);
                        $this->projectCache[$projectId]->setContext($this->foldersCache[$folderId]);
                        break;
                    // Case 3 - Assessment in UnitImpl on Folder
                    case 3:
                        $this->cacheUnitImplementation($unitImplId);
                        $this->cacheUnitSpecification($unitSpecId);
                        $this->cacheCourseImplementation($courseImplId);
                        $this->cacheCourseSpecification($courseSpecId);
                        $this->cacheFolder($unitSpecFolderId, $unitSpecFolderName);
                        $this->cacheFolder($folderId, $folderName);
                        $this->cacheFolder($courseSpecFolderId, $courseSpecFolderName);

                        $assessment
                            ->setContext($this->unitImplsCache[$unitImplId]);
                        $this->unitImplsCache[$unitImplId]
                            ->setSpecification($this->unitSpecsCache[$unitSpecId])
                            ->setCourse($this->courseImplsCache[$courseImplId]);
                        $this->unitSpecsCache[$unitSpecId]
                            ->setFolder($this->foldersCache[$unitSpecFolderId]);
                        $this->courseImplsCache[$courseImplId]
                            ->setFolder($this->foldersCache[$folderId])
                            ->setSpecification($this->courseSpecsCache[$courseSpecId]);
                        $this->courseSpecsCache[$courseSpecId]
                            ->setFolder($this->foldersCache[$courseSpecFolderId]);
                        break;
                    // Case 4 - Assessment in Project in Unit Impl on Folder
                    case 4:
                        $this->cacheProject($projectId, $projectTitle);
                        $this->cacheUnitImplementation($unitImplId);
                        $this->cacheUnitSpecification($unitSpecId);
                        $this->cacheCourseImplementation($courseImplId);
                        $this->cacheCourseSpecification($courseSpecId);
                        $this->cacheFolder($unitSpecFolderId, $unitSpecFolderName);
                        $this->cacheFolder($folderId, $folderName);
                        $this->cacheFolder($courseSpecFolderId, $courseSpecFolderName);

                        $assessment
                            ->setContext($this->projectCache[$projectId]);
                        $this->projectCache[$projectId]
                            ->setContext($this->unitImplsCache[$unitImplId]);
                        $this->unitImplsCache[$unitImplId]
                            ->setSpecification($this->unitSpecsCache[$unitSpecId])
                            ->setCourse($this->courseImplsCache[$courseImplId]);
                        $this->unitSpecsCache[$unitSpecId]
                            ->setFolder($this->foldersCache[$unitSpecFolderId]);
                        $this->courseImplsCache[$courseImplId]
                            ->setFolder($this->foldersCache[$folderId])
                            ->setSpecification($this->courseSpecsCache[$courseSpecId]);
                        $this->courseSpecsCache[$courseSpecId]
                            ->setFolder($this->foldersCache[$courseSpecFolderId]);
                        break;
                    default:
                        throw new \LogicException('Cannot derive AssessmentContext: ' . print_r($row, true));
                }
            }

            // ClassCodes
            $classCode = $row['fc.code'];
            if (!is_null($classCode)) {
                $folder = $this->assessmentsCache[$assessmentId]->getContext()->getFolder();
                $folder->getCodes()->add(new FolderCode($classCode));
                $classFolderId = $folder->getId();
                $this->classIds[$classFolderId] = $classFolderId;
                $this->addClassCodeToFolder($classFolderId, $classCode);

            }
            // Group IDs
            $groupId = $row['g.id'];
            if (!is_null($groupId)) {
                $this->groupIds[$groupId] = intval($groupId);
            }
        }
    }

    /**
     * For each Class taught by this User, or where the User has created an assessment, get a list of students.
     *
     * Generate two structures:
     * 1. A list of all user.externalIds
     * 2. A map of classIds to sets of user.externalsIds
     */
    private function getClassStudentLists()
    {
        // Get Student Lists for each Class
        foreach ($this->classIds as $classId) {

            $uniqueCodes = array_unique($this->folderClassCodes[$classId]);

            $this->folderToStudentListMap[$classId] = [];
            $classList = $this->externalClass->getStudents(implode(',', $uniqueCodes));

            // Each Student in Subject
            if (is_array($classList)) {
                foreach ($classList as $student) {
                    $this->studentExternalIds[$student['synergy_id']] = $student['synergy_id'];
                    $this->folderToStudentListMap[$classId][$student['synergy_id']] = $student['synergy_id'];
                }
            }
        }
    }


    /**
     * Get Student data from SchoolBox database to build User objects.
     *
     * For classes we find the user using their externalId
     * For groups we find the users belonging to that group
     */
    private function getStudentsForClassesAndGroups()
    {
        if (empty($this->groupIds) && empty($this->studentExternalIds)) {
            return; // bug out
        }
        $query = [];

        // Get Students in Groups
        $groupPb = new PredicateBuilder($this->database, '');
        if (count($this->groupIds)) {
            $query[0] = "
                SELECT 
                    1             AS 'case',
                    ua.sid        AS 'g.id',
                    u.id          AS 'u.id',
                    u.username    AS 'u.username',
                    u.synergy_id  AS 'u.synergy_id',
                    u.firstname   AS 'u.firstname',
                    u.lastname    AS 'u.lastname',
                    u.local_email AS 'u.local_email',
                    u.alt_email   AS 'u.alt_email'
                FROM user u
                JOIN userAccess ua ON ua.uid = u.id
                JOIN groups g ON u.group_id = g.id
            ";
            $groupPb->equals('ua.sid', $this->groupIds);
            $groupPb->equals('ua.service', UserAccess::SERVICE_HOMEPAGE);
            $groupPb->equals('g.group_type', \Group::STUDENT_GROUP_TYPE);
            $query[0] .= ' WHERE ' . implode(' AND ', $groupPb->getWhere());
        }

        // Get Students in Classes
        $classPb = new PredicateBuilder($this->database, '');
        if (count($this->studentExternalIds)) {
            $query[1] = "
                SELECT
                    2             AS 'case',
                    NULL          AS 'g.id',
                    u.id          AS 'u.id',
                    u.username    AS 'u.username',
                    u.synergy_id  AS 'u.synergy_id',
                    u.firstname   AS 'u.firstname',
                    u.lastname    AS 'u.lastname',
                    u.local_email AS 'u.local_email',
                    u.alt_email   AS 'u.alt_email'
                FROM user u
            ";
            $classPb->equals('u.synergy_id', $this->studentExternalIds);
            $classPb->isNull('u.deleted_at');
            $query[1] .= ' WHERE ' . implode(' AND ', $classPb->getWhere());
        }
        $statement = $this->database->prepare(implode(' UNION ', $query));
        $statement->execute(array_merge($groupPb->getParams(), $classPb->getParams()));
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $this->cacheUser($row['u.id'], $row['u.username'], $row['u.synergy_id'], $row['u.firstname'],
                $row['u.lastname'], $row['u.local_email'], $row['u.alt_email']);

            // Was this user from a Group ?
            if (!is_null($row['g.id'])) {
                if (!is_array($this->folderToStudentListMap[$row['g.id']])) {
                    $this->folderToStudentListMap[$row['g.id']] = [];
                }
                $this->folderToStudentListMap[$row['g.id']][$row['u.synergy_id']] = intval($row['u.synergy_id']);
            }
        }
    }

    /**
     * For each Assessment, get its underlying context's folderId.
     * Each User associated with that folder becomes a Participant of the Assessment.
     */
    private function createAssessmentParticipants()
    {
        // We have all Student ExternalIds, grouped by Subject/Group FolderId
        // We also have a cache of all Student User objects, indexed by Student.ExternalId
        foreach ($this->assessmentsCache as $assessment) {
            $folderId = $assessment->getContext()->getFolder()->getId();

            $studentList = $this->folderToStudentListMap[$folderId];

            $participants = new Collection();
            if (is_array($studentList)) {
                foreach ($studentList as $studentExternalId) {
                    $student = $this->studentExtCache[$studentExternalId];

                    // Does this student (found in external SIS) exist in Schoolbox?
                    if($student instanceof User){
                        $participants[$student->getId()] = new Participant($student, $assessment);
                    }

                }
            }
            $assessment->setAllParticipants($participants);
        }
    }

    /**
     * Get any QuizAttempts or QuizReviews related to selected Quizzes
     *
     * @return array
     */
    private function getQuizResponsesAndFeedback()
    {
        // Bug out early if no quizzes
        if (!count($this->quizAssessmentIds)) {
            return [];
        }

        // Get QuizAttempts for specified Quizzes
        $pb = new PredicateBuilder($this->database, '');
        $pb->isNull('q.deleted_at');
        $pb->equals('q.availability', QuizModel::AVAILABILITY_ASSESS);
        $pb->equals('q.assessment_id', $this->quizAssessmentIds);
        $query = "
            SELECT
                q.assessment_id      AS 'q.assessment_id',
                qa.attempt_id        AS 'qa.attempt_id',
                qa.user_id           AS 'qa.user_id',
                qa.start             AS 'qa.start',
                qa.finish            AS 'qa.finish',
                qa.reviewed_at       AS 'qa.reviewed_at',
                min(qs.section_id)   AS 'qs.first_section_id'                                
            FROM assess q
            JOIN assess_attempt qa ON qa.assess_id = q.assess_id AND qa.deleted_at IS NULL
            JOIN assess_section qs ON qs.assess_id = q.assess_id AND qs.deleted_at IS NULL
            WHERE " . implode(' AND ', $pb->getWhere()) . "
            GROUP BY q.assessment_id, qa.user_id, qa.attempt_id
        ";

        $statement = $this->database->prepare($query);
        $statement->execute($pb->getParams());
        $quizResults = $statement->fetchAll(\PDO::FETCH_ASSOC);

        // Find Students who attempted Quizzes but are't in the current studentList
        $this->findExtraStudentResults($quizResults, 'qa.user_id', 'q.assessment_id');

        return $quizResults;
    }


    /**
     * Get any TaskCompletions or TaskFeedbacks related to selected Tasks
     *
     * @return array
     */
    private function getTaskResponsesAndFeedback()
    {
        // Bug out early if no Tasks
        if (!count($this->taskAssessmentIds)) {
            return [];
        }

        // Get Task Completions for all selected students
        $pb = new PredicateBuilder($this->database, '');
        $pb->isNull('t.deleted_at');
        $pb->equals('t.assessment_id', $this->taskAssessmentIds);
        $query = "
            SELECT 
                t.assessment_id         AS 't.assessment_id',
                t.requires_confirmation AS 't.requires_confirmation',
                tc.id                   AS 'tc.id',
                tc.student_id           AS 'tc.student_id',
                tc.marking_user_id      AS 'tc.marking_user_id',
                max(tc.completion_date) AS 'tc.completion_date'
            FROM task t
            JOIN task_completion tc ON tc.task_id = t.id
            WHERE " . implode(' AND ', $pb->getWhere()) . "
            GROUP BY t.assessment_id, tc.student_id" . "
            ORDER BY t.assessment_id";

        $statement = $this->database->prepare($query);
        $statement->execute($pb->getParams());
        $taskResults = $statement->fetchAll(\PDO::FETCH_ASSOC);

        // Find Students who attempted Tasks but are't in the current studentList
        $this->findExtraStudentResults($taskResults, 'tc.student_id', 't.assessment_id');

        return $taskResults;
    }


    /**
     * Get any DueWork Submissions related to selected DueWork
     *
     * @return array
     */
    private function getDueWorkResponses()
    {
        // Bug out early if no DueWork
        if (!count($this->dueWorkAssessmentIds)) {
            return [];
        }

        // Get Due Work Submissions for all selected students
        $pb = new PredicateBuilder($this->database, '');
        $pb->equals('sb.deleted', 0);
        $pb->notNull('ss.submitted_at');
        $pb->equals('sb.assessment_id', $this->dueWorkAssessmentIds);
        $query = "
            SELECT
                sb.assessment_id   AS 'sb.assessment_id',
                sb.mark_visibility AS 'sb.mark_visibility',
                ss.id              AS 'ss.id',
                ss.owner           AS 'ss.owner',
                ss.submitted_at    AS 'ss.submitted_at',
                ss.updated_at      AS 'ss.updated_at'
            FROM submission_box sb
            JOIN student_submission ss ON ss.box_id = sb.id
            WHERE " . implode(' AND ', $pb->getWhere()) . "
            ORDER BY sb.assessment_id";
        $statement = $this->database->prepare($query);
        $statement->execute($pb->getParams());
        $dueWorkSubmissions = $statement->fetchAll(\PDO::FETCH_ASSOC);

        // Find Students who submitted DueWorkSubmissions but are't in the current studentList
        $this->findExtraStudentResults($dueWorkSubmissions, 'ss.owner', 'sb.assessment_id');

        return $dueWorkSubmissions;
    }

    /**
     * Get any DueWork Feedback related to selected DueWork
     *
     * @return array
     */
    private function getDueWorkFeedbacks()
    {
        // Bug out early if no DueWork
        if (!count($this->dueWorkAssessmentIds)) {
            return [];
        }
        $pb = new PredicateBuilder($this->database, '');
        $pb->equals('sb.deleted', 0);
        $pb->notNull('sr.published_at');
        $pb->equals('sb.assessment_id', $this->dueWorkAssessmentIds);
        $query = "
            SELECT
                sb.assessment_id                 AS 'sb.assessment_id',
                sb.mark_visibility               AS 'sb.mark_visibility',
                sr.id                            AS 'sr.id',
                sr.marker                        AS 'sr.marker',
                sr.owner                         AS 'sr.owner',
                sr.updated_at                    AS 'sr.updated_at',
                sr.published_at                  AS 'sr.published_at',
                sr.is_confirmation_of_submission AS 'sr.is_confirmation_of_submission'
            FROM submission_box sb
            JOIN submission_return sr ON sr.box_id = sb.id
            WHERE " . implode(' AND ', $pb->getWhere()) . "
            ORDER BY sb.assessment_id";

        $statement = $this->database->prepare($query);
        $statement->execute($pb->getParams());
        $dueWorkFeedbacks = $statement->fetchAll(\PDO::FETCH_ASSOC);

        // Find Students who received DueWork Feedback but are't in the current studentList
        $this->findExtraStudentResults($dueWorkFeedbacks, 'sr.owner', 'sb.assessment_id');

        return $dueWorkFeedbacks;
    }


    /**
     * When retrieving Assessment Responses and Feedbacks, we may have
     * found references to Users not in the current Participant lists.
     *
     * In also unable to locate the missing students in our cache, we need to go back to SchoolBox DB.
     */
    private function getMissingUsers()
    {
        // Do we have Responses / Feedbacks for students that are not yet loaded?
        if (count($this->assessmentExtraStudentIds)) {
            $pb = new PredicateBuilder($this->database, '');
            $pb->equals('u.id', $this->assessmentExtraStudentIds);
            $query = "
                SELECT 
                    u.id          AS 'u.id',
                    u.username    AS 'u.username',
                    u.synergy_id  AS 'u.synergy_id',
                    u.firstname   AS 'u.firstname',
                    u.lastname    AS 'u.lastname',
                    u.local_email AS 'u.local_email',
                    u.alt_email   AS 'u.alt_email'
                FROM user u
                WHERE " . implode(' AND ', $pb->getWhere());
            $statement = $this->database->prepare($query);
            $statement->execute($pb->getParams());
            while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $this->cacheUser($row['u.id'], $row['u.username'], $row['u.synergy_id'], $row['u.firstname'],
                    $row['u.lastname'], $row['u.local_email'], $row['u.alt_email']);
            }
        }
    }

    /**
     * Create QuizResponses and QuizFeedbacks from DB results
     *
     * @param string[][] $quizResults
     * @var string[]    $quizResults[]
     * @var int         $quizResults[]['q.assessment_id']
     * @var int         $quizResults[]['qa.attempt_id']
     * @var int         $quizResults[]['qa.user_id']
     * @var string      $quizResults[]['qa.start']
     * @var string|null $quizResults[]['qa.finish']
     * @var string|null $quizResults[]['qa.reviewed_at']
     * @var int         $quizResults[]['qa.first_section_id']
     */
    private function hydrateQuizResults(array $quizResults)
    {
        $countRows = count($quizResults);
        if(!count($quizResults)){
            return;
        }
        $lastIdx = $countRows - 1;

        foreach ($quizResults as $rowIdx => $row) {
            $quiz = $this->assessmentsCache[$row['q.assessment_id']];
            $userId = $row['qa.user_id'];
            $response = new QuizAttempt();
            $response->setId($row['qa.attempt_id']);
            $response->setSubmittedBy($this->studentIntCache[$userId]);
            $response->setSubmittedOnBehalfOf($this->studentIntCache[$userId]);
            $finishDate = $this->getDateFromString($row['qa.finish']);
            $response->setSubmittedAt($finishDate);
            $response->setFirstSectionId($row['qs.first_section_id']);
            $response->setAssessable($quiz);
            $updatedAt = $finishDate->isNull() ? $this->getDateFromString($row['qa.start']) : $finishDate;
            $response->setUpdatedAt($updatedAt);

            $quiz->getResponses()->add($response);
            $participants = $quiz->getAllParticipants();

            // Are we missing the Participant?
            if (!$participants[$userId]) {
                $participants[$userId] = new Participant($this->studentIntCache[$userId], $quiz);
            }
            $participants[$userId]->getAllResponses()->add($response);

            // In this context, if not 'reviewed', then we interpret this as NO feedback
            if (!is_null($row['qa.reviewed_at'])) {
                $feedback = new QuizReview();
                $feedback->setId($row['qa.attempt_id']);
                $reviewedAt = $this->getDateFromString($row['qa.reviewed_at']);
                $feedback->setPublishedAt($reviewedAt);
                $feedback->setUpdatedAt($reviewedAt);

                $feedback->setResponse($response);
                $feedback->setAssessable($quiz);

                $response->getAllFeedback()->add($feedback);
                $quiz->getFeedbacks()->add($feedback);
                $participants[$userId]->getAllFeedbacks()->add($feedback);
            }

            // Have we reached the last row for an Assessment ?
            if($rowIdx == $lastIdx || $quiz->getId() != $quizResults[$rowIdx + 1]['q.assessment_id']){
                $this->checkAssessmentRelevance($quiz);
            }
        }
    }

    /**
     * Checks if the assessment requires marking or chasing, and discards if neither to save memory.
     *
     * @param IAssessable $assessment
     */
    protected function checkAssessmentRelevance(IAssessable $assessment)
    {
        // Does this assessment require marking or chasing ?
        if(!$this->isMarkingRequired($assessment) && !$this->isChasingRequired($assessment)){
            unset($this->assessmentsCache[$assessment->getId()]);
        }
    }


    /**
     * Returns whether the assessment has students who require marking
     *
     * @param IAssessable $assessment
     * @return bool
     */
    protected function isMarkingRequired(IAssessable $assessment)
    {
        return $assessment->numMarkingRequired() > 0;
    }

    /**
     * Returns whether the assessment contains participants who require chasing
     *
     * @param IAssessable $assessment
     * @return bool
     */
    protected function isChasingRequired(IAssessable $assessment)
    {
        return $assessment->getDueAt()->lt($this->toChaseLimit) && $assessment->numNoResponseOrFeedback() > 0;
    }

    /**
     * Create TaskResponses and TaskFeedbacks from DB results
     *
     * @param string[][] $taskResults
     * @var string $taskResults[]['t.assessment_id']
     * @var string $taskResults[]['t.requires_confirmation']
     * @var string $taskResults[]['tc.id']
     * @var string $taskResults[]['tc.student_id']
     * @var string $taskResults[]['tc.marking_user_id']
     * @var string $taskResults[]['tc.completion_date']
     */
    private function hydrateTaskResults(array $taskResults)
    {
        $countRows = count($taskResults);
        if(!count($taskResults)){
            return;
        }
        $lastIdx = $countRows - 1;

        foreach ($taskResults as $rowIdx => $row) {
            $task = $this->assessmentsCache[$row['t.assessment_id']];
            $userId = $row['tc.student_id'];
            $response = new TaskCompletion();
            $response->setId($row['tc.id']);
            $response->setSubmittedBy($this->studentIntCache[$userId]);
            $response->setAssessable($task);
            $completedAt = $this->getDateFromString($row['tc.completion_date']);
            $response->setCreatedAt($completedAt);
            $response->setUpdatedAt($completedAt);

            // TODO - sigh replace this with composition ASAP
            $response->markAs($row['tc.student_id'] == $row['tc.marking_user_id']
                ? ICompletion::STATUS_SELF_ASSESSED
                : ICompletion::STATUS_COMPLETED
            );

            $task->getResponses()->setOffset($response->getId(), $response);
            $participants = $task->getAllParticipants();

            // Are we missing the Participant?
            if (!$participants[$userId]) {
                $participants[$userId] = new Participant($this->studentIntCache[$userId], $task);
            }
            $participants[$userId]->getAllResponses()->setOffset($response->getId(), $response);

            // For tasks, if 'reviewer' != 'reviewee', then that is Teacher Feedback
            if ($userId != $row['tc.marking_user_id']) {
                $feedback = new TaskFeedback();
                $feedback->setId($row['tc.id']);
                $feedback->setPublishedAt($completedAt);
                $feedback->setUpdatedAt($completedAt);
                $feedback->setResponse($response);
                $feedback->setAssessable($task);

                $response->getAllFeedback()->setOffset($feedback->getId(), $feedback);
                $task->getFeedbacks()->setOffset($feedback->getId(), $feedback);
                $participants[$userId]->getAllFeedbacks()->setOffset($feedback->getId(), $feedback);
            }

            // Have we reached the last row for an Assessment ?
            if($rowIdx == $lastIdx || $task->getId() != $taskResults[$rowIdx + 1]['t.assessment_id']){
                $this->checkAssessmentRelevance($task);
            }
        }
    }

    /**
     * Create DueWorkResponses from DB results
     *
     * @param string[][] $dueWorkResponses
     * @var string[] $dueWorkResponses[]
     * @var int $dueWorkResponses['sb.assessment_id']
     * @var int $dueWorkResponses['sb.mark_visibility']
     * @var int $dueWorkResponses['ss.id']
     * @var int $dueWorkResponses['ss.owner']
     * @var int $dueWorkResponses['ss.submitted_at']
     * @var int $dueWorkResponses['ss.updated_at']
     */
    private function hydrateDueWorkResponses(array $dueWorkResponses)
    {
        foreach ($dueWorkResponses as $row) {

            $dueWork = $this->assessmentsCache[$row['sb.assessment_id']];
            $userId = $row['ss.owner'];
            $response = new DueWorkSubmission();
            $response->setId($row['ss.id']);
            $response->setSubmittedBy($this->studentIntCache[$userId]);
            $response->setSubmittedOnBehalfOf($this->studentIntCache[$userId]);
            $response->setAssessable($dueWork);
            $response->setSubmittedAt($this->getDateFromString($row['ss.submitted_at']));
            $response->setUpdatedAt($this->getDateFromString($row['ss.updated_at']));

            $dueWork->getResponses()->setOffset($response->getId(), $response);
            $participants = $dueWork->getAllParticipants();

            // Are we missing the Participant?
            if (!$participants[$userId]) {
                $participants[$userId] = new Participant($this->studentIntCache[$userId], $dueWork);
            }
            $participants[$userId]->getAllResponses()->setOffset($response->getId(), $response);
        }
    }

    /**
     * Create DueWorkFeedbacks from DB results
     *
     * @param array $dueWorkFeedbackResults
     */
    private function hydrateDueWorkFeedbacks(array $dueWorkFeedbackResults)
    {
        $countRows = count($dueWorkFeedbackResults);
        if(!count($dueWorkFeedbackResults)){
            return;
        }
        $lastIdx = $countRows - 1;

        foreach ($dueWorkFeedbackResults as $rowIdx => $row) {
            $dueWork = $this->assessmentsCache[$row['sb.assessment_id']];
            $userId = $row['sr.owner'];
            $feedback = new DueWorkFeedback();
            $feedback->setId($row['sr.id']);
            $feedback->setUpdatedAt($this->getDateFromString($row['sr.updated_at']));
            $feedback->setPublishedAt($this->getDateFromString($row['sr.published_at']));
            $feedback->setAssessable($dueWork);

            $dueWork->getFeedbacks()->setOffset($feedback->getId(), $feedback);
            $participants = $dueWork->getAllParticipants();

            // Are we missing the Participant?
            if (!$participants[$userId]) {
                $participants[$userId] = new Participant($this->studentIntCache[$userId], $dueWork);
            }
            $participants[$userId]->getAllFeedbacks()->setOffset($feedback->getId(), $feedback);

            // Have we reached the last row for an Assessment ?
            if($rowIdx == $lastIdx || $dueWork->getId() != $dueWorkFeedbackResults[$rowIdx + 1]['sb.assessment_id']){
                $this->checkAssessmentRelevance($dueWork);
            }
        }
    }

    /**
     * It's quite possible to have Responses or Feedback for Students not in the current studentList.
     * * Eg, student leaves a class after a submission.
     *
     * In this case, record their Id, and we'll fetch them from the DB and add them to the Assessment's Participant List.
     *
     * We may already have the User in our cache. If so, no need to fetch from DB.
     *
     * @param array $results
     * @param string $userKey
     * @param string $assessmentKey
     */
    private function findExtraStudentResults($results, $userKey, $assessmentKey)
    {
        foreach ($results as $row) {
            $studentId = intval($row[$userKey]);
            $participants = $this->assessmentsCache[$row[$assessmentKey]]->getAllParticipants();
            // Are we missing the student and student does not exists in student cache?
            if (!$participants[$studentId] && !$this->studentIntCache[$studentId]) {
                // Add to our TO-FETCH list.
                $this->assessmentExtraStudentIds[$studentId] = $studentId;
            }
        }
    }


    /**
     * Ensure logically same users refer to single instance of User object
     *
     * @param int $id
     * @param string $username
     * @param int $externalId
     * @param string $firstname
     * @param string $lastname
     * @param bool $localEmail
     * @param string $altEmail
     */
    private function cacheUser($id, $username, $externalId, $firstname, $lastname, $localEmail, $altEmail)
    {
        $id = intval($id);
        $externalId = intval($externalId);
        $localEmail = boolval($localEmail);

        $this->studentInternalIds[$id] = $id;
        $this->studentExternalIds[$externalId] = $externalId;

        if (!$this->studentExtCache[$externalId] instanceof User) {
            $this->studentExtCache[$externalId] = (new User())
                ->setId($id)
                ->setUsername($username)
                ->setExternalId($externalId)
                ->setFirstname($firstname)
                ->setLastname($lastname)
                ->setLocalEmail($localEmail)
                ->setAltEmail($altEmail);
        }

        if (!$this->studentIntCache[$id] instanceof User) {
            $this->studentIntCache[$id] = $this->studentExtCache[$externalId];
        }
    }

    /**
     * For some reason this is many times faster than having Alaress_Date_Format parse the string directly
     *
     * @param string|null $dateString
     * @param string $format
     * @return \Alaress_Date_Format
     */
    private function getDateFromString($dateString, $format = \Alaress_Date_Format::DATABASE_FORMAT)
    {
        if (empty($dateString)) {
            return $this->nullDate;
        }
        return new \Alaress_Date_Format($dateString, $format);
    }


    /**
     * Get all Classes taught by the specified User.
     *
     * If already available from Session, use that, otherwise get from External DB
     */
    private function getTaughtClasses()
    {
        // Do we already have the classes taught by this User?
        if ($classesTaught = $this->viewee->getSubjectCollection()) {
            // Get SubjectIds
            foreach ($classesTaught as $row) {
                $classId = intval($row['id']);
                $className = $row['name'];
                $classCode = $row['code'];
                $this->classIds[$classId] = $classId;
                $this->cacheFolder($classId, $className);
                $this->addClassCodeToFolder($classId, $classCode);
            }
        } else {
            // Get Classes Taught from External_DB
            $classes = $this->externalUser->getSubjectList($this->viewee);

            // Get ClassCodes + SchoolBox FolderIds
            if (is_array($classes) && !empty($classes)) {
                $folderCodes = array_map(
                    function ($row) {
                        return trim($row['code']);
                    }, $classes
                );
                $prefixedCodeClause = '';
                foreach ($folderCodes as $folderCode) {
                    $prefixedCodeClause .= ' OR fc.code LIKE "%/' . $folderCode . '"';
                }
                $wrappedRawCodes = array_map(
                    function ($code) {
                        return "'$code'";
                    }, $folderCodes
                );
                $query = "
                    SELECT 
                        f.id    AS 'f.id',
                        f.name  AS 'f.name',
                        fc.code AS 'fc.code'
                    FROM folder f
                    JOIN folder_code fc ON fc.folder_id = f.id AND fc.deleted_at IS NULL
                    AND (fc.code IN (" . implode(',', $wrappedRawCodes) . ") $prefixedCodeClause)
                ";

                $statement = $this->database->prepare($query);
                $statement->execute();
                while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                    $folderId = intval($row['f.id']);
                    $folderName = $row['f.name'];
                    $classCode = $row['fc.code'];
                    $this->classIds[$folderId] = $folderId;
                    $this->cacheFolder($folderId, $folderName);
                    $this->addClassCodeToFolder($folderId, $classCode);
                }
            }
        }
    }

    /**
     * Gets all Groups administered by the specified User and populate instance vars
     */
    private function getGroupsAdministered()
    {
        // Get Groups administered
        $pb = new PredicateBuilder($this->database, '');
        $pb->isNull('f.deleted_at');
        $pb->equals('ua.uid', $this->viewee->getId());
        $pb->greaterThan('ua.type', UserAccess::ACCESS_READ);
        $pb->equals('ua.service', UserAccess::SERVICE_HOMEPAGE);
        $query = "
            SELECT 
                f.id   AS 'f.id',
                f.name AS 'f.name'
            FROM folder f
            JOIN userAccess ua ON ua.sid = f.id
            WHERE " . implode(' AND ', $pb->getWhere());
        $statement = $this->database->prepare($query);
        $statement->execute($pb->getParams());

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $folderId = intval($row['f.id']);
            $folderName = $row['f.name'];
            $this->cacheFolder($folderId, $folderName);
            $this->groupIds[$folderId] = $folderId;
        }
    }
}
