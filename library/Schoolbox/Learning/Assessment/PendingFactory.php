<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Assessment\Course\Task\Completion;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkSubmission;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use \Schoolbox\Assessment\Term;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Model as QuizReview;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;
use Schoolbox\Learning\Assessment\Task\Response\Model as TaskResponse;
use Schoolbox\Learning\Quiz\Model as QuizModel;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

/**
 * Optimised DB queries to build graph of Assessments a user needs to mark or chase
 */
class PendingFactory
{

    /** @var IConnector */
    private $database;

    /** @var \External_ClassInterface */
    private $externalClass;

    /**
     * Indexed by assessment.id
     *
     * A Collection object rather than array as objects are passed by reference and can be returned to caller.
     *
     * @var IAssessable[]|GenericCollection $assessmentsCache
     */
    private $assessmentsCache;

    /**
     * Indexed by course_specification.id
     *
     * @var CourseSpecification[] $courseSpecsCache
     */
    private $courseSpecsCache = [];

    /**
     * Indexed by course_implementation.id
     *
     * @var CourseImplementation[] $courseImplsCache
     */
    private $courseImplsCache = [];

    /**
     * Indexed by course_unit_specification.id
     *
     * @var UnitSpecification[] $unitSpecsCache
     */
    private $unitSpecsCache = [];

    /**
     * Indexed by course_unit_implementation.id
     *
     * @var UnitImplementation[] $unitImplsCache
     */
    private $unitImplsCache = [];

    /**
     * Each Folder requires an ID and Name. Indexed by folder.id
     *
     * @var Folder[] $foldersCache
     */
    private $foldersCache = [];

    /**
     * Each Project requires an ID and Title. Indexed by assessment.id
     *
     * @var Project[] $projectCache
     */
    private $projectCache = [];

    /**
     * @var int[] $quizAssessmentIds
     */
    private $quizAssessmentIds = [];

    /**
     * @var int[] $dueWorkAssessmentIds
     */
    private $dueWorkAssessmentIds = [];

    /**
     * @var int[] $taskAssessmentIds
     */
    private $taskAssessmentIds = [];

    /**
     * Maps Subject/Group folder.id to sets of Student externalIds
     *
     * @var int[][] $folderToStudentListMap
     */
    private $folderToStudentListMap = [];

    /**
     * Set of all student.externalIds the specified User teaches or administers
     *
     * We need this so we know which Users to get SchoolBox details for
     * key = value = user.externalId
     *
     * @var string[]|int[] $studentExternalIds
     */
    private $studentExternalIds = [];

    /**
     * Set of all student.ids the specified User teaches or administers
     *
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

    /**
     * Contains stub teachers, typically used as the 'submittedBy' for SOBO responses, or for feedbacks.
     *
     * Indexed by user.id so we can find O(1) when generating Participants
     *
     * @var User[]
     */
    private $teacherCache;

    /**
     * Intentionally not a GenericCollection so we can insert NULL items
     * @var Collection|WorkType\Model[]
     */
    private $workTypes;

    /**
     * @var Term\Model[] $terms
     */
    private $terms;

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
     * A single instance we can re-use to prevent unnecessary object instantiation
     *
     * @var \Alaress_Date_NullDate
     */
    private $nullDate;

    /**
     * The assessment types to include in the report
     *
     * @var Collection|string[]
     */
    private $assessmentTypes;

    /**
     * @var FolderMapper
     */
    private $folderMapper;

    /**
     * Indexed by studentSubmission.id so we can link non-floating feedback to their response
     *
     * @var DueWorkSubmission[]
     */
    private $dueWorkSubmissionCache = [];


    /**
     * @param User                           $viewee
     * @param IConnector                     $database
     * @param \External_ClassInterface       $externalClass
     * @param GenericCollection|Term\Model[] $terms Only display assessments that are due in these terms
     * @param Collection|WorkType\Model[] $selectedWorkTypes
     * @param\Alaress_Date_Format           $toChaseLimit
     * @param Collection|string[]            $selectedAssessmentTypes
     * @param FolderMapper                   $folderMapper
     */
    public function __construct(
        User $viewee,
        IConnector $database,
        \External_ClassInterface $externalClass,
        GenericCollection $terms,
        Collection $selectedWorkTypes,
        \Alaress_Date_Format $toChaseLimit,
        Collection $selectedAssessmentTypes,
        FolderMapper $folderMapper

    ) {
        $this->viewee           = $viewee;
        $this->database         = $database;
        $this->externalClass    = $externalClass;
        $this->assessmentsCache = new GenericCollection(IAssessable::class);  // TODO SA-2631
        $this->terms            = $terms;
        $this->workTypes        = $selectedWorkTypes;
        $this->toChaseLimit     = $toChaseLimit;
        $this->nullDate         = new \Alaress_Date_NullDate();
        $this->assessmentTypes  = $selectedAssessmentTypes;
        $this->folderMapper     = $folderMapper;
    }


    /**
     * Get Assessments that belong to Classes taught by User, or Groups administered by User.
     *
     * Assessments may reside:
     * * directly on Folders,
     * * in Projects on Folders,
     * * in UnitImplementations on Folders
     * * in Projects that are in UnitImplementation on Folders
     *
     * @return GenericCollection|IAssessable[]
     * @throws \LogicException If assessment type is not recognised
     */
    public function getPendingAssessments()
    {
        $this->getClassesTaughtAndAdministered();
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

        $quizResults            = $this->getQuizResponsesAndFeedback();
        $dueWorkResponses       = $this->getDueWorkResponses();
        $dueWorkFeedbackResults = $this->getDueWorkFeedbacks();
        $taskResults            = $this->getTaskResponsesAndFeedback();

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
     * @param int    $id
     * @param string $name
     */
    private function cacheFolder($id, $name)
    {
        $id = (int)$id;
        if (!$this->foldersCache[$id] instanceof Folder) {
            $this->foldersCache[$id] = $this->folderMapper->createModel($id)->setName($name);
        }
    }


    /**
     * Returns a User object that may be partially hydrated, but at the least contains the user.id.
     *
     * @param $id
     * @return User
     */
    private function getStubUser($id)
    {
        if($this->studentIntCache[$id] instanceof User){
            return $this->studentIntCache[$id];
        }
        if(!$this->teacherCache[$id] instanceof User){
            $this->teacherCache[$id] = (new User())->setId($id);  // TODO SA-2631
        }
        return $this->teacherCache[$id];
    }


    /**
     * Ensure each logically same Project is same object instance
     *
     * @param int    $id
     * @param string $title
     */
    private function cacheProject($id, $title)
    {
        $id = (int)$id;
        if (!$this->projectCache[$id] instanceof Project) {
            $this->projectCache[$id] = (new Project())->setId($id)->setTitle($title); // TODO SA-2631
        }
    }


    /**
     * Ensure each logically same CourseSpecification is same object instance
     *
     * @param int $id
     */
    private function cacheCourseSpecification($id)
    {
        $id = (int)$id;
        if (!$this->courseSpecsCache[$id] instanceof CourseSpecification) {
            $this->courseSpecsCache[$id] = (new CourseSpecification())->setId($id); // TODO SA-2631
        }
    }


    /**
     * Ensure each logically same CourseSpecification is same object instance
     *
     * @param int $id
     */
    private function cacheCourseImplementation($id)
    {
        $id = (int)$id;
        if (!$this->courseImplsCache[$id] instanceof CourseImplementation) {
            $this->courseImplsCache[$id] = (new CourseImplementation())->setId($id); // TODO SA-2631
        }
    }


    /**
     * Ensure each logically same UnitSpecification is same object instance
     *
     * @param int $id
     */
    private function cacheUnitSpecification($id)
    {
        $id = (int)$id;
        if (!$this->unitSpecsCache[$id] instanceof UnitSpecification) {
            $this->unitSpecsCache[$id] = (new UnitSpecification())->setId($id); // TODO SA-2631
        }
    }


    /**
     * Ensure each logically same UnitImplementation is same object instance
     *
     * @param int $id
     */
    private function cacheUnitImplementation($id)
    {
        $id = (int)$id;
        if (!$this->unitImplsCache[$id] instanceof UnitImplementation) {
            $this->unitImplsCache[$id] = (new UnitImplementation())->setId($id); // TODO SA-2631
        }
    }


    /**
     * Build an assessment dueDate clause to inject into assessment-retrieval query
     *
     * @return string
     */
    private function buildDueDateClause()
    {
        // Is this filter being applied ?
        if(0 === $this->terms->count()){
            return ' TRUE ';
        }

        // Generate the dueDate filtering
        $orParts = [];
        foreach($this->terms as $term){
            $andPart = " (a.due_date >= '{$term->getStart()->getDatabaseDate()}' ";
            if(!$term->getActualFinish()->isNull()){
                $andPart .= " AND a.due_date <= '{$term->getActualFinish()->getDatabaseDate()}' ";
            }
            $andPart .= ')';
            $orParts[] = $andPart;
        }
        return implode(' OR ', $orParts);
    }



    /**
     * Build an assessment type clause to inject into assessment-retrieval query
     *
     * @return string[]
     * @throws \InvalidArgumentException If AssessmentType filter does not include one of Quiz|DueWork|Task
     */
    private function buildAssessmentTypeClause()
    {
        $selectClause = '';
        $joinClause = '';
        $idClauseArray = [];

        // Task
        if ($this->assessmentTypes->contains(AssessmentType::TYPE_TASK)) {
            $selectClause .= ",
                 tk.id                    AS 'tk.id',
                 tk.requires_confirmation AS 'tk.requires_confirmation'
            ";
            $joinClause .= '
                LEFT JOIN task tk           ON tk.assessment_id = a.id AND tk.deleted_at IS NULL
            ';
            $idClauseArray[] = 'tk.id IS NOT NULL';
        }

        // Quiz
        if ($this->assessmentTypes->contains(AssessmentType::TYPE_QUIZ)) {
            $selectClause .= ",
                 qz.assess_id AS 'qz.id'
            ";
            $joinClause .= '
                LEFT JOIN assess qz         ON qz.assessment_id = a.id AND qz.deleted_at IS NULL
            ';
            $idClauseArray[] = 'qz.assess_id IS NOT NULL';
        }

        // DueWork
        if ($this->assessmentTypes->contains(AssessmentType::TYPE_DUE_WORK)) {
            $selectClause .= ",
                 dw.id        AS 'dw.id'
            ";
            $joinClause .= '
                LEFT JOIN submission_box dw ON dw.assessment_id = a.id AND dw.deleted = 0
            ';
            $idClauseArray[] = 'dw.id IS NOT NULL';
        }

        if(0 === count($idClauseArray)){
            throw new \InvalidArgumentException('AssessmentType filter must include at least one from Quiz, DueWork or Task. None found');
        }

        return [$selectClause, $joinClause, implode(' OR ', $idClauseArray)];
    }


    /**
     * Build the assessment.workType filter
     * @return string
     */
    private function buildWorkTypeClause()
    {
        // If no workTypes selected, don't filter
        $select = '';
        $join   = '';
        $where  = ' TRUE ';
        if(0 === $this->workTypes->count()){
            return [$select, $join, $where];
        }


        // A subset of workTypes is in play, apply filter
        $select = ",
                 wt.id                    AS 'wt.id'
            ";
        $join = '
                LEFT JOIN lu_work_type wt         ON wt.id = a.work_type_id
            ';
        $where = [];

        foreach($this->workTypes as $workType){
            if(null === $workType){
                $where[] = ' wt.id IS NULL ';
            } else {
                $where[] = " wt.id = {$workType->getId()} ";
            }
        }
        return [$select, $join, implode(' OR ', $where)];
    }

    /**
     * Get Assessments associated with Classes taught, or Groups administered, by User.
     *
     * Required Data:
     * * CourseSpec's Folder id/name
     * * UnitSpec's Folder id/name
     * * Subject/Group Folder id/name
     * * Project name
     * so we can build context breadcrumbs
     *
     * Case 1: Assessment resides directly on Folder                  => Folder name+id
     * Case 2: Assessment in Project that resides on Folder           => Folder name+id + Project name
     * Case 3: Assessment in UnitImpl imported on Folder              => Folder name+id + UnitSpec name+id + ClassSpec name+id
     * Case 4: Assessment in Project in UnitImpl imported on Folder   => Folder name+id + UnitSpec name+id + ClassSpec name+id + Project name
     *
     * @throws \LogicException If assessment type is not recognised
     */
    private function getAssessments()
    {
        // Is user not teaching or administering any classes or groups?
        if(!count($this->foldersCache)){
            return;
        }

        $dueDateClause = $this->buildDueDateClause();
        list($aTypeSelect, $aTypeJoin, $aTypeClause) = $this->buildAssessmentTypeClause();
        list($workTypeSelect, $workTypeJoin, $workTypeClause) = $this->buildWorkTypeClause();

        $folderIds = array_keys($this->foldersCache);


        $query1       = "
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
                a.due_date   AS 'a.due_date'
                
                $aTypeSelect
                $workTypeSelect
            FROM assessment a 
            $aTypeJoin
            $workTypeJoin
            
            JOIN assessment_folder af   ON af.assessment_id = a.id 
            JOIN folder f               ON f.id = af.folder_id AND f.deleted_at IS NULL
            
            WHERE f.id IN (" . implode(',', $folderIds) . ")
            AND ($aTypeClause)
            AND ($dueDateClause)
            AND ($workTypeClause)
        ";

        $query2       = "
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
                a.due_date   AS 'a.due_date'
                
                $aTypeSelect
                $workTypeSelect
           
            FROM assessment a 
            $aTypeJoin
            $workTypeJoin
            
            JOIN project_assessment pra ON pra.assessment_id = a.id
            JOIN project p              ON pra.project_id = p.id
            JOIN assessment pa          ON p.assessment_id = pa.id
            JOIN assessment_folder paf  ON paf.assessment_id = pa.id
            JOIN folder pf              ON pf.id = paf.folder_id AND pf.deleted_at IS NULL
            
            WHERE pf.id IN (" . implode(',', $folderIds) . ")
            AND ($aTypeClause)
            AND ($dueDateClause)
            AND ($workTypeClause)
        ";

        $query3       = "
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
                a.due_date   AS 'a.due_date'
                
                $aTypeSelect
                $workTypeSelect
            FROM assessment a 

            $aTypeJoin
            $workTypeJoin
            
            JOIN assessment_course_unit_implementation aui ON aui.assessment_id = a.id
            JOIN course_unit_implementation ui             ON ui.id = aui.course_unit_implementation_id
            JOIN course_implementation ci                  ON ci.id = ui.course_implementation_id 
            JOIN folder cif                                ON cif.id = ci.folder_id AND cif.deleted_at IS NULL
            JOIN course_unit_specification us              ON us.id = ui.course_unit_specification_id
            JOIN folder usf                                ON usf.id = us.folder_id AND usf.deleted_at IS NULL
            JOIN course_specification cs                   ON cs.id = ci.course_specification_id
            JOIN folder csf                                ON csf.id = cs.folder_id AND csf.deleted_at IS NULL
            
            WHERE cif.id IN (" . implode(',', $folderIds) . ")
            AND ($aTypeClause)
            AND ($dueDateClause)
            AND ($workTypeClause)
        ";

        // Assessment in Project in UnitImpl imported on Folder
        $query4       = "
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
                a.due_date   AS 'a.due_date'
                
                $aTypeSelect
                $workTypeSelect
            FROM assessment a 
            
            $aTypeJoin
            $workTypeJoin
            
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
            
            WHERE a.deleted_at IS NULL
            AND cif.id IN (" . implode(',', $folderIds) . ")
            AND ($aTypeClause)
            AND ($dueDateClause)
            AND ($workTypeClause)
        ";

        $this->parseAssessments(
            $this->database->executeQueryIterator("$query1 UNION $query2 UNION $query3 UNION $query4")
        );
    }


    /**
     * Iterate over results to build Assessment graph
     *
     * Extract and cache any GroupIds or ClassCodes to later retrieve the studentLists
     *
     * @param \Traversable $statement
     * @throws \LogicException If assessment type is not recognised
     */
    private function parseAssessments(\Traversable $statement)
    {
        foreach ($statement as $row) {

            $assessmentId = (int)$row['a.id'];

            // Hydrate Assessment
            if (!$this->assessmentsCache[$assessmentId] instanceof IAssessable) {

                // Create Quiz / DueWork / Task object

                // Add ID to type array for later retrieval of Response / Feedback
                if (null !== $row['qz.id']) {
                    $this->quizAssessmentIds[$assessmentId] = $assessmentId;
                    $this->assessmentsCache[$assessmentId]  = new Quiz();
                } elseif (null !== $row['dw.id']) {
                    $this->dueWorkAssessmentIds[$assessmentId] = (int)$assessmentId;
                    $this->assessmentsCache[$assessmentId]     = new DueWork();
                } elseif (null !== $row['tk.id']) {
                    $this->taskAssessmentIds[$assessmentId] = (int)$assessmentId;
                    $this->assessmentsCache[$assessmentId]  = (new Task())->setIsSelfAssessable(!$row['tk.requires_confirmation']);
                } else {
                    throw new \LogicException('Assessment must be Quiz, DueWork or Task. row: ' . print_r($row, true));
                }

                $assessment = $this->assessmentsCache[$assessmentId];
                $assessment->setId($assessmentId);
                $assessment->setTitle($row['a.title']);

                // It is VERY slow to create a new AlaressDateFormat from a DB formatted string.
                $assessment->setDueAt($this->getDateFromString($row['a.due_date']));

                $projectId            = (int)$row['p.id'];
                $projectTitle         = $row['p.title'];
                $unitImplId           = (int)$row['ui.id'];
                $unitSpecId           = (int)$row['us.id'];
                $courseImplId         = (int)$row['ci.id'];
                $courseSpecId         = (int)$row['cs.id'];
                $folderId             = (int)$row['f.id'];
                $folderName           = $row['f.name'];
                $unitSpecFolderId     = (int)$row['usf.id'];
                $unitSpecFolderName   = $row['usf.name'];
                $courseSpecFolderId   = (int)$row['csf.id'];
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
        }
    }


    /**
     * For each Class taught by this User, use its folder codes to retrieve a list of students
     *
     * Generates two structures:
     * 1. A list of all user.externalIds
     * 2. A map of class folder.ids to sets of user.externalsIds
     */
    private function getClassStudentLists()
    {
        // Get Student Lists for each Class
        foreach ($this->foldersCache as $folder) {

            $this->folderToStudentListMap[$folder->getId()] = [];
            $classList                                      = $this->externalClass->getStudents($folder->getCodeString());

            // Each Student in Class
            if (is_array($classList)) {
                foreach ($classList as $student) {
                    $this->studentExternalIds[$student['synergy_id']]                       = $student['synergy_id'];
                    $this->folderToStudentListMap[$folder->getId()][$student['synergy_id']] = $student['synergy_id'];
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
        // No groups administered and no students in taught classes ?
        if (empty($this->foldersCache) && empty($this->studentExternalIds)) {
            return; // bug out
        }

        // Efficient but complex (until OR support goes in) predicate building
        $pb      = $this->database->buildPredicateBuilder('');
        $groupPb = $this->database->buildPredicateBuilder('');
        $classPb = $this->database->buildPredicateBuilder('');

        // Always ensure user is not deleted
        $pb->isNull('u.deleted_at');

        // Are we interested in group members ?
        if(!empty($this->foldersCache)){
            $groupSelect = "ua.sid AS 'g.id',";
            $groupJoin   = empty($this->studentExternalIds) ? '
                INNER JOIN userAccess ua ON u.id = ua.uid 
                INNER JOIN groups g      ON g.id = u.group_id
            ' : '
                LEFT JOIN userAccess ua ON u.id = ua.uid 
                LEFT JOIN groups g      ON g.id = u.group_id
            ';
            $groupPb->equals('ua.sid', array_keys($this->foldersCache));
            $groupPb->equals('ua.service', UserAccess::SERVICE_HOMEPAGE);
            $groupPb->equals('g.group_type', \Group::STUDENT_GROUP_TYPE);
        } else {
            $groupSelect = '';
            $groupJoin   = '';
        }

        // Are we interested in class students ?
        if(!empty($this->studentExternalIds)){
            $classPb->equals('u.synergy_id', $this->studentExternalIds);
        }

        $groupWhere = implode(' AND ', $groupPb->getWhere());
        $classWhere = implode(' AND ', $classPb->getWhere());

        // Are we getting students in Groups and Classes ?
        if(!empty($this->foldersCache) && !empty($this->studentExternalIds)){
            $orClause = "(($groupWhere) OR ($classWhere))";
        }
        // elseif just Group
        elseif (!empty($this->foldersCache) ) {
            $orClause = $groupWhere;
        }
        // else just Class
        else {
            $orClause = $classWhere;
        }

        $whereClause = "$orClause AND " . implode(' AND ',  $pb->getWhere());

        $query = "
                SELECT 
                    $groupSelect
                    u.id          AS 'u.id',
                    u.username    AS 'u.username',
                    u.synergy_id  AS 'u.synergy_id',
                    u.firstname   AS 'u.firstname',
                    u.lastname    AS 'u.lastname',
                    u.local_email AS 'u.local_email',
                    u.alt_email   AS 'u.alt_email'
                FROM user u
                $groupJoin 
                WHERE $whereClause
        ";
        $params = array_merge($pb->getParams(), $groupPb->getParams(), $classPb->getParams());

        foreach ($this->database->executeQueryIterator($query, $params) as $row) {
            $this->cacheUser((int)$row['u.id'], $row['u.username'], $row['u.synergy_id'], $row['u.firstname'], $row['u.lastname'], $row['u.local_email'], $row['u.alt_email']);

            // Was this user from a Group ?
            if (null !== $row['g.id']) {
                $groupId = (int)$row['g.id'];
                if (!is_array($this->folderToStudentListMap[$groupId])) {
                    $this->folderToStudentListMap[$groupId] = [];
                }
                $this->folderToStudentListMap[$groupId][$row['u.synergy_id']] = $row['u.synergy_id'];
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

            $participants = new GenericCollection(Participant::class);
            if (is_array($studentList)) {
                foreach ($studentList as $studentExternalId) {
                    $student = $this->studentExtCache[$studentExternalId];

                    // Does this student (found in external SIS) exist in Schoolbox?
                    if ($student instanceof User) {
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
        $pb = $this->database->buildPredicateBuilder('');
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
            WHERE " . implode(' AND ', $pb->getWhere()) . '
            GROUP BY q.assessment_id, qa.user_id, qa.attempt_id
        ';

        $quizResults = $this->database->executeQuery($query, $pb->getParams())->fetchAll();

        // Find Students who attempted Quizzes but are't in the current studentList
        $this->findExtraStudentResults($quizResults, 'qa.user_id', 'q.assessment_id');

        return $quizResults;
    }


    /**
     * Get any TaskResponses or TaskFeedbacks related to selected Tasks
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
        $pb = $this->database->buildPredicateBuilder('');
        $pb->isNull('t.deleted_at');
        $pb->equals('t.assessment_id', $this->taskAssessmentIds);
        $query = "
            SELECT 
                t.assessment_id         AS 't.assessment_id',
                tc.id                   AS 'tc.id',
                tc.student_id           AS 'tc.student_id',
                tc.completion_date      AS 'tc.completion_date',
                tc.completion_status    AS 'tc.completion_status'
            FROM task t
            JOIN task_completion tc ON tc.task_id = t.id
            JOIN (
              SELECT task_id, tc.student_id, max(tc.completion_date) as max_completion_date
              FROM task t
              JOIN task_completion tc ON tc.task_id = t.id              
              WHERE " . implode(' AND ', $pb->getWhere()) . '
              GROUP BY task_id, student_id
            ) tc2 ON tc.task_id = tc2.task_id AND tc.student_id = tc2.student_id AND tc.completion_date = tc2.max_completion_date
            WHERE ' . implode(' AND ', $pb->getWhere()) . '
            ORDER BY t.assessment_id';

        $taskResults = $this->database->executeQuery($query, $pb->getParams())->fetchAll();

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
        $pb = $this->database->buildPredicateBuilder('');
        $pb->equals('sb.deleted', 0);
        $pb->notNull('ss.submitted_at');
        $pb->equals('sb.assessment_id', $this->dueWorkAssessmentIds);
        $query     = "
            SELECT
                sb.assessment_id   AS 'sb.assessment_id',
                sb.mark_visibility AS 'sb.mark_visibility',
                ss.id              AS 'ss.id',
                ss.owner           AS 'ss.owner',
                ss.submitted_at    AS 'ss.submitted_at',
                ss.updated_at      AS 'ss.updated_at'
            FROM submission_box sb
            JOIN student_submission ss ON ss.box_id = sb.id
            WHERE " . implode(' AND ', $pb->getWhere()) . '
            ORDER BY sb.assessment_id';

        $dueWorkSubmissions = $this->database->executeQuery($query, $pb->getParams())->fetchAll();

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
        $pb = $this->database->buildPredicateBuilder('');
        $pb->equals('sb.deleted', 0);
        $pb->notNull('sr.published_at');
        $pb->equals('sb.assessment_id', $this->dueWorkAssessmentIds);
        $query = "
            SELECT
                sb.assessment_id                 AS 'sb.assessment_id',
                sr.id                            AS 'sr.id',
                sr.owner                         AS 'sr.owner',
                sr.student_submission_id         AS 'sr.student_submission_id',
                sr.updated_at                    AS 'sr.updated_at',
                sr.published_at                  AS 'sr.published_at'
            FROM submission_box sb
            JOIN submission_return sr ON sr.box_id = sb.id
            WHERE " . implode(' AND ', $pb->getWhere()) . '
            ORDER BY sb.assessment_id';

        $dueWorkFeedbacks = $this->database->executeQuery($query, $pb->getParams())->fetchAll();

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
            $pb = $this->database->buildPredicateBuilder('');
            $pb->equals('u.id', $this->assessmentExtraStudentIds);
            $query     = "
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

            foreach ($this->database->executeQueryIterator($query, $pb->getParams()) as $row) {
                $this->cacheUser($row['u.id'], $row['u.username'], $row['u.synergy_id'], $row['u.firstname'],
                    $row['u.lastname'], $row['u.local_email'], $row['u.alt_email']);
            }
        }
    }


    /**
     * Create QuizResponses and QuizFeedbacks from DB results
     *
     * @param string[][] $quizResults
     * @var string[]     $quizResults []
     * @var int          $quizResults []['q.assessment_id']
     * @var int          $quizResults []['qa.attempt_id']
     * @var int          $quizResults []['qa.user_id']
     * @var string       $quizResults []['qa.start']
     * @var string|null  $quizResults []['qa.finish']
     * @var string|null  $quizResults []['qa.reviewed_at']
     * @var int          $quizResults []['qa.first_section_id']
     *
     * @throws \DomainException
     */
    private function hydrateQuizResults(array $quizResults)
    {
        $countRows = count($quizResults);
        if (!count($quizResults)) {
            return;
        }
        $lastIdx = $countRows - 1;

        foreach ($quizResults as $rowIdx => $row) {
            $quiz     = $this->assessmentsCache[$row['q.assessment_id']];
            $userId   = (int)$row['qa.user_id'];
            $response = new QuizAttempt(); // TODO SA-2631
            $response->setId((int)$row['qa.attempt_id']);
            $response->setSubmittedBy($this->studentIntCache[$userId]);
            $response->setSubmittedOnBehalfOf($this->studentIntCache[$userId]);
            $finishDate = $this->getDateFromString($row['qa.finish']);
            $response->setSubmittedAt($finishDate);
            $response->setFirstSectionId((int)$row['qs.first_section_id']);
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
            if (null !== $row['qa.reviewed_at']) {
                $feedback = new QuizReview(); // TODO SA-2631
                $feedback->setId((int)$row['qa.attempt_id']);
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
            if ($rowIdx === $lastIdx || $quiz->getId() !== (int)$quizResults[$rowIdx + 1]['q.assessment_id']) {
                $this->checkAssessmentRelevance($quiz);
            }
        }
    }


    /**
     * Checks if the assessment requires marking or chasing, and discards if neither to save memory.
     *
     * @param IAssessable $assessment
     * @throws \DomainException
     */
    private function checkAssessmentRelevance(IAssessable $assessment)
    {
        // Does this assessment require marking or chasing ?
        if (!$this->isFeedbackRequired($assessment) && !$this->isChasingRequired($assessment)) {
            unset($this->assessmentsCache[$assessment->getId()]);
        }
    }


    /**
     * Returns whether the assessment has students who require marking
     *
     * @param IAssessable $assessment
     * @return bool
     * @throws \DomainException if $user is not a participant of the participation assessable.
     */
    private function isFeedbackRequired(IAssessable $assessment)
    {
        foreach ($assessment->getAllParticipants() as $participant) {
            if ($participant->isFeedbackRequired()) {
                return true;
            }
        }
        return false;
    }


    /**
     * Returns whether the assessment contains participants who require chasing
     *
     * @param IAssessable $assessment
     * @return bool
     */
    private function isChasingRequired(IAssessable $assessment)
    {
        if (!$assessment->getDueAt()->lt($this->toChaseLimit)){
            return false;
        }

        foreach ($assessment->getAllParticipants() as $participant){
            if($participant->isResponseRequired()){
                return true;
            }
        }
        return false;
    }


    /**
     * Create TaskResponses and TaskFeedbacks from DB results
     *
     * @param string[][] $taskResults
     * @var string       $taskResults []['t.assessment_id']
     * @var string       $taskResults []['tc.id']
     * @var string       $taskResults []['tc.student_id']
     * @var string       $taskResults []['tc.marking_user_id']
     * @var string       $taskResults []['tc.completion_date']
     * @var string       $taskResults []['tc.completion_status']
     *
     * @throws \DomainException
     */
    private function hydrateTaskResults(array $taskResults)
    {
        $countRows = count($taskResults);
        if (!count($taskResults)) {
            return;
        }
        $lastIdx = $countRows - 1;

        foreach ($taskResults as $rowIdx => $row) {
            $task          = $this->assessmentsCache[$row['t.assessment_id']];
            $userId        = (int)$row['tc.student_id'];
            $markingUserId = (int)$row['tc.marking_user_id'];

            $response = new TaskResponse(); // TODO SA-2631
            $response->setId((int)$row['tc.id']);
            $response->setSubmittedBy($this->getStubUser($markingUserId));
            $response->setSubmittedOnBehalfOf($this->studentIntCache[$userId]);
            $response->setAssessable($task);
            $completedAt = $this->getDateFromString($row['tc.completion_date']);
            $response->setCreatedAt($completedAt);
            $response->setUpdatedAt($completedAt);
            $response->setMarkedComplete(Completion::STATUS_INCOMPLETE !== (int)$row['tc.completion_status']);

            $task->getResponses()->setOffset($response->getId(), $response);
            $participants = $task->getAllParticipants();

            // Are we missing the Participant?
            if (!$participants[$userId]) {
                $participants[$userId] = new Participant($this->studentIntCache[$userId], $task);
            }
            $participants[$userId]->getAllResponses()->setOffset($response->getId(), $response);

            // For tasks, if 'reviewer' != 'reviewee', then that is Teacher Feedback
            if ($userId !== $markingUserId) {
                $feedback = (new TaskFeedback()) // TODO SA-2631
                    ->setResponse($response);

                $response->getAllFeedback()->setOffset($feedback->getId(), $feedback);
                $task->getFeedbacks()->setOffset($feedback->getId(), $feedback);
                $participants[$userId]->getAllFeedbacks()->setOffset($feedback->getId(), $feedback);
            }

            // Have we reached the last row for an Assessment ?
            if ($rowIdx === $lastIdx || $task->getId() !== (int)$taskResults[$rowIdx + 1]['t.assessment_id']) {
                $this->checkAssessmentRelevance($task);
            }
        }
    }


    /**
     * Create DueWorkResponses from DB results
     *
     * @param string[][] $dueWorkResponses
     * @var string[]     $dueWorkResponses []
     * @var int          $dueWorkResponses ['sb.assessment_id']
     * @var int          $dueWorkResponses ['sb.mark_visibility']
     * @var int          $dueWorkResponses ['ss.id']
     * @var int          $dueWorkResponses ['ss.owner']
     * @var int          $dueWorkResponses ['ss.submitted_at']
     * @var int          $dueWorkResponses ['ss.updated_at']
     */
    private function hydrateDueWorkResponses(array $dueWorkResponses)
    {
        foreach ($dueWorkResponses as $row) {

            $dueWork  = $this->assessmentsCache[$row['sb.assessment_id']];
            $userId   = $row['ss.owner'];
            $response = new DueWorkSubmission(); // TODO SA-2631
            $response->setId((int)$row['ss.id']);
            $response->setSubmittedBy($this->studentIntCache[$userId]);
            $response->setSubmittedOnBehalfOf($this->studentIntCache[$userId]);
            $response->setAssessable($dueWork);
            $response->setSubmittedAt($this->getDateFromString($row['ss.submitted_at']));
            $response->setUpdatedAt($this->getDateFromString($row['ss.updated_at']));

            $dueWork->getResponses()->setOffset($response->getId(), $response);

            // Add to cache so we can later link non-floating feedbacks to responses
            $this->dueWorkSubmissionCache[$response->getId()] = $response;

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
     * @var int    $dueWorkFeedbackResults['sb.assessment_id']
     * @var string $dueWorkFeedbackResults['sr.owner']
     * @var int    $dueWorkFeedbackResults['sr.id']
     * @var int    $dueWorkFeedbackResults['sr.student_submission_id']
     * @var int    $dueWorkFeedbackResults['sr.updated_at']
     * @var int    $dueWorkFeedbackResults['sr.published_at']
     * @var int    $dueWorkFeedbackResults['sb.assessment_id']
     * @throws \DomainException
     */
    private function hydrateDueWorkFeedbacks(array $dueWorkFeedbackResults)
    {
        $countRows = count($dueWorkFeedbackResults);
        if (!count($dueWorkFeedbackResults)) {
            return;
        }
        $lastIdx = $countRows - 1;

        foreach ($dueWorkFeedbackResults as $rowIdx => $row) {
            $dueWork  = $this->assessmentsCache[$row['sb.assessment_id']];
            $userId   = $row['sr.owner'];
            $feedback = new DueWorkFeedback(); // TODO SA-2631
            $feedback->setId((int)$row['sr.id']);
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

            // Does this feedback relate to a specific response ?
            $studentSubmissionId = (int)$row['sr.student_submission_id'];
            if($studentSubmissionId && $this->dueWorkSubmissionCache[$studentSubmissionId] instanceof DueWorkSubmission){
                $feedback->setResponse($this->dueWorkSubmissionCache[$studentSubmissionId]);
                $this->dueWorkSubmissionCache[$studentSubmissionId]->getAllFeedback()->add($feedback);
            }

            // Have we reached the last row for an Assessment ?
            if ($rowIdx === $lastIdx || $dueWork->getId() !== (int)$dueWorkFeedbackResults[$rowIdx + 1]['sb.assessment_id']) {
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
     * @param array  $results
     * @param string $userKey
     * @param string $assessmentKey
     */
    private function findExtraStudentResults($results, $userKey, $assessmentKey)
    {
        foreach ($results as $row) {
            $studentId    = (int)$row[$userKey];
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
     * @param int    $id
     * @param string $username
     * @param string $externalId
     * @param string $firstname
     * @param string $lastname
     * @param bool   $localEmail
     * @param string $altEmail
     */
    private function cacheUser($id, $username, $externalId, $firstname, $lastname, $localEmail, $altEmail)
    {
        $id         = (int)$id;
        $localEmail = (bool)$localEmail;

        $this->studentInternalIds[$id]         = $id;
        $this->studentExternalIds[$externalId] = $externalId;

        if (!$this->studentExtCache[$externalId] instanceof User) {
            $this->studentExtCache[$externalId] = (new User()) // TODO SA-2631
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
     * @param string      $format
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
     * Retrieves all class folders taught by user, and group folders administered by user
     */
    private function getClassesTaughtAndAdministered()
    {
        $getSubjects = true;
        $groupAccessLevel = UserAccess::ACCESS_WRITE;
        $folders = $this->folderMapper->findForUser($this->viewee, $getSubjects, $groupAccessLevel);

        foreach($folders as $folder){
            $this->foldersCache[$folder->getId()] = $folder;
        }
    }
}
