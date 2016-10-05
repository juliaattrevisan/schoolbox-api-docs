<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment;

use Alaress\IDatabase;
use Carbon\Carbon;
use Jenssegers\Date\Date;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Collection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Group;
use Schoolbox\User;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Course\Specification as CourseSpecification;
use Schoolbox\Learning\Course\Implementation as CourseImplementation;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
use Schoolbox\Learning\Course\Unit\Implementation as UnitImplementation;
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
class Factory
{
    /** @var IDatabase */
    private $database;

    /** @var \External_UserInterface */
    private $externalUser;

    /** @var \External_ClassInterface */
    private $externalClass;

    /**
     * Indexed by assessment.id
     * @var IAssessable[] $assessmentsCache
     */
    private $assessmentsCache;

    /**
     * Indexed by course_specification.id
     * @var CourseSpecification[] $courseSpecsCache
     */
    private $courseSpecsCache;

    /**
     * Indexed by course_implementation.id
     * @var CourseImplementation[] $courseImplsCache
     */
    private $courseImplsCache;

    /**
     * Indexed by course_unit_specification.id
     * @var UnitSpecification[] $unitSpecsCache
     */
    private $unitSpecsCache;

    /**
     * Indexed by course_unit_implementation.id
     * @var UnitImplementation[] $unitImplsCache
     */
    private $unitImplsCache;

    /**
     * Each Folder requires an ID and Name. Indexed by folder.id
     * @var Collection|Folder[] $foldersCache
     */
    private $foldersCache;

    /**
     * Each Project requires an ID and Title. Indexed by assessment.id
     * @var Collection|Project[] $projectCache
     */
    private $projectCache;

    /**
     * Folder IDs of all Subjects either taught by this User or where this User has created Assessments
     * key = value = folder.id
     *
     * @var int[] $subjectIds
     */
    private $subjectIds;

    /**
     * Folder IDs of all Groups either administered by this User or where this User has created Assessments
     * key = value = folder.id
     *
     * @var int[] $groupIds
     */
    private $groupIds;

    // Used for retrieving Responses and Feedbacks
    /** @var int[] $quizAssessmentIds */
    private $quizAssessmentIds;

        /** @var int[] $dueWorkAssessmentIds */
    private $dueWorkAssessmentIds;

        /** @var int[] $taskAssessmentIds */
    private $taskAssessmentIds;

        /** @var int[] $projectAssessmentIds */
    private $projectAssessmentIds;

    /**
     * Sets of non-indexed, non-unique subjectCodes, each set indexed by folder.id
     * Need to make sets unique before use.
     *
     * @var string[][]|Collection[] $subjectCodes
     */
    private $subjectCodes;

    /**
     * Maps Subject/Group folder.id to sets of Student externalIds
     *
     * @var int[][] $folderToStudentListMap
     */
    private $folderToStudentListMap;

    /**
     * Set of all student.externalIds the specified User teaches, administers, or are in Groups or Subjects
     * where the User has created Assessments.
     * We need this so we know which Users to get SB details for
     * key = value = user.externalId
     *
     * @var int[] $studentExternalIds
     */
    private $studentExternalIds;

    /**
     * Set of all student.ids the specified User teaches, administers, or are in Groups or Subjects
     * where the User has created Assessments.
     * We need this so we know which Users to get SB details for
     * key = value = user.id
     *
     * @var int[] $studentInternalIds
     */
    private $studentInternalIds;

    /**
     * All known students.
     *
     * Indexed by user.externalId so we can find O(1) when assigning to Classes
     *
     * @var Collection|User[] $studentExtCache
     */
    private $studentExtCache;

    /**
     * All known students.
     *
     * Indexed by user.id so we can find O(1) when generating Participants
     *
     * @var Collection|User[] $studentIntCache
     */
    private $studentIntCache;

    /** @var \Schoolbox\Assessment\Term\Model  */
    private $term;

    /** @var \Alaress_Date_Format $tomorrow */
    private $tomorrow;

    public function __construct()
    {
        $this->database = \Database::getInstance();

        $this->assessmentsCache = new Collection();
        $this->courseSpecsCache = new Collection();
        $this->courseImplsCache = new Collection();
        $this->unitSpecsCache = new Collection();
        $this->unitImplsCache = new Collection();
        $this->foldersCache = new Collection();
        $this->projectCache = new Collection();
        $this->studentExtCache = new Collection();
        $this->studentIntCache = new Collection();
        $this->subjectIds = [];
        $this->groupIds = [];
        $this->quizAssessmentIds    = [];
        $this->dueWorkAssessmentIds = [];
        $this->taskAssessmentIds    = [];
        $this->projectAssessmentIds = [];
        $this->subjectCodes = new Collection();
        $this->folderToStudentListMap = [];
        $this->studentExternalIds = [];

        $this->term = TermMapper::getInstance()->findForToday();
        $this->term->getStart()->addYears(-1); // TODO remove this line. Just here to get more results

        $this->tomorrow = (new \Alaress_Date_Format(null, \Alaress_Date_Format::DATABASE_FORMAT))->addDays(1);
    }



    /**
     * Get Assessments for specified User that have participants that have submitted work
     * that requires marking.
     *
     * Assessments may live directly on folders, or in UnitImplementations that reside on a folder.
     *
     * If an assessment belongs to a unit, we need that units name, and its course's name.
     *
     * TODO Also get Quiz where assess.user_id = user.id
     * TODO Also get DueWork where submission_box.owner = user.id
     * TODO Also get Task where task_instance.user_id = user.id
     *
     * @param User $user
     * @return Collection|IAssessable[]
     */
    public function getPendingAssessments(User $user)
    {

$this->start = microtime(true);

        // Get Subjects taught by User

        // Do we already have the subject collection?
        if ($subjectsTaught = $user->getSubjectCollection()) {
            // Get SubjectIds
            foreach ($subjectsTaught as $row) {
                $this->subjectIds[intval($row['id'])] = intval($row['id']);
                $this->createFolder($row['id'], $row['name']);
                $this->saveFolderCode($row['id'], $row['code']);
            }
        } else {
            // Get Subjects Taught from External_DB
            $subjects = $this->getExternalUser()->getSubjectList($user->getExternalId(), $user->getId()); //var_dump($subjects);

            // Get SubjectCodes + SB FolderIds
            if(is_array($subjects) && !empty($subjects)) {
                $folderCodes = array_map(
                    function ($row) {
                        return trim($row['code']);
                    }, $subjects
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
                        f.id AS 'f.id',
                        f.name AS 'f.name',
                        fc.code AS 'fc.code'
                    FROM folder f
                    JOIN folder_code fc ON fc.folder_id = f.id
                    WHERE f.deleted_at IS NULL 
                    AND fc.deleted_at IS NULL
                    AND (fc.code IN (".implode(',',$wrappedRawCodes).") $prefixedCodeClause)
                ";

                $statement = $this->database->prepare($query);
                $statement->execute();
                //$this->printTable($statement->fetchAll(\PDO::FETCH_ASSOC)); die();
                while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                    $this->subjectIds[$row['f.id']] = intval($row['f.id']);
                    $this->createFolder($row['f.id'], $row['f.name']);
                    $this->saveFolderCode($row['f.id'], $row['fc.code']);
                }
            }
        }

        // Get Groups administered
        $query = "
            SELECT 
                f.id AS 'f.id',
                f.name AS 'f.name'
            FROM folder f
            JOIN userAccess ua ON ua.sid = f.id
            WHERE f.deleted_at IS NULL
            AND ua.uid = {$user->getId()}
            AND ua.type >= ".UserAccess::ACCESS_READ."
            AND ua.service = ".UserAccess::SERVICE_HOMEPAGE."
        ";
        //die($query);
        $statement = $this->database->prepare($query);
        $statement->execute();

        while($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $this->createFolder($row['f.id'], $row['f.name']);
            $this->groupIds[$row['f.id']] = intval($row['f.id']);
        }




        /* Get GroupIds and SubjectCodes where User has created Work so we can generated Participant Lists
         *
         * Also get:
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
        // Get Assessments for Groups and Subjects administered/taught by User
        $folderIds = array_unique(array_merge($this->subjectIds, $this->groupIds));

        $folderClause = count($folderIds) ? " OR f.id IN (".implode(',',$folderIds).") " : '';
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
                tk.id        AS 'tk.id',
                
                fc.code      AS 'fc.code',
                ug.cid       AS 'g.id'                
            FROM assessment a 
            LEFT JOIN assess qz         ON qz.assessment_id = a.id AND qz.deleted_at IS NULL
            LEFT JOIN submission_box dw ON dw.assessment_id = a.id AND dw.deleted = 0
            LEFT JOIN task tk           ON tk.assessment_id = a.id AND tk.deleted_at IS NULL
            
            JOIN assessment_folder af   ON af.assessment_id = a.id 
            JOIN folder f               ON f.id = af.folder_id AND f.deleted_at IS NULL
            
            LEFT JOIN folder_code fc    ON fc.folder_id = f.id AND fc.deleted_at IS NULL
            LEFT JOIN userGroups ug     ON ug.cid = f.id
            
            WHERE ((qz.user_id = {$user->getId()} OR dw.owner = {$user->getId()} OR tk.user_id = {$user->getId()}) $folderClause)
            AND (qz.assess_id IS NOT NULL OR dw.id IS NOT NULL OR tk.id IS NOT NULL)
            AND a.due_date >= STR_TO_DATE('".$this->term->getStart()->getDatabase()."', '%Y-%m-%d %H:%i:%s')
        ";

        $folderClause = count($folderIds) ? " OR pf.id IN (".implode(',',$folderIds).") " : '';
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
                tk.id        AS 'tk.id',
                
                fc.code      AS 'fc.code',
                ug.cid       AS 'g.id'                
            FROM assessment a 
            LEFT JOIN assess qz         ON qz.assessment_id = a.id AND qz.deleted_at IS NULL
            LEFT JOIN submission_box dw ON dw.assessment_id = a.id AND dw.deleted = 0
            LEFT JOIN task tk           ON tk.assessment_id = a.id AND tk.deleted_at IS NULL
            
            JOIN project_assessment pra ON pra.assessment_id = a.id
            JOIN project p              ON pra.project_id = p.id
            JOIN assessment pa          ON p.assessment_id = pa.id
            JOIN assessment_folder paf  ON paf.assessment_id = pa.id
            JOIN folder pf              ON pf.id = paf.folder_id AND pf.deleted_at IS NULL
            
            LEFT JOIN folder_code fc    ON fc.folder_id = pf.id AND fc.deleted_at IS NULL
            LEFT JOIN userGroups ug     ON ug.cid = pf.id
            
            WHERE ((qz.user_id = {$user->getId()} OR dw.owner = {$user->getId()} OR tk.user_id = {$user->getId()}) $folderClause)
            AND (qz.assess_id IS NOT NULL OR dw.id IS NOT NULL OR tk.id IS NOT NULL)
            AND a.due_date >= STR_TO_DATE('".$this->term->getStart()->getDatabase()."', '%Y-%m-%d %H:%i:%s')
        ";

        $folderClause = count($folderIds) ? " OR cif.id IN (".implode(',',$folderIds).") " : '';
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
                tk.id        AS 'tk.id',
                
                fc.code      AS 'fc.code',
                ug.cid       AS 'g.id'                
            FROM assessment a 
            LEFT JOIN assess qz                            ON qz.assessment_id = a.id AND qz.deleted_at IS NULL
            LEFT JOIN submission_box dw                    ON dw.assessment_id = a.id AND dw.deleted = 0
            LEFT JOIN task tk                              ON tk.assessment_id = a.id AND tk.deleted_at IS NULL
            
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
            
            WHERE ((qz.user_id = {$user->getId()} OR dw.owner = {$user->getId()} OR tk.user_id = {$user->getId()}) $folderClause)
            AND (qz.assess_id IS NOT NULL OR dw.id IS NOT NULL OR tk.id IS NOT NULL)
            AND a.due_date >= STR_TO_DATE('".$this->term->getStart()->getDatabase()."', '%Y-%m-%d %H:%i:%s')
        ";


        $folderClause = count($folderIds) ? " OR cif.id IN (".implode(',',$folderIds).") " : '';
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
                tk.id        AS 'tk.id',
                
                fc.code      AS 'fc.code',
                ug.cid       AS 'g.id'                
            FROM assessment a 
            LEFT JOIN assess qz                            ON qz.assessment_id = a.id AND qz.deleted_at IS NULL
            LEFT JOIN submission_box dw                    ON dw.assessment_id = a.id AND dw.deleted = 0
            LEFT JOIN task tk                              ON tk.assessment_id = a.id AND tk.deleted_at IS NULL
            
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
            
            WHERE ((qz.user_id = {$user->getId()} OR dw.owner = {$user->getId()} OR tk.user_id = {$user->getId()}) $folderClause)
            AND a.due_date >= STR_TO_DATE('".$this->term->getStart()->getDatabase()."', '%Y-%m-%d %H:%i:%s')
        ";

        //$statement = $this->database->prepare("SELECT * FROM ($query1 UNION $query2 UNION $query3 UNION $query4) AS temp ORDER BY `a.id`");
        $statement = $this->database->prepare("$query1 UNION $query2 UNION $query3 UNION $query4");

        $statement->execute();
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        //$this->printTable($rows); die();
        $this->parseAssessments($rows);
        //$this->printCrumbs($this->assessmentsCache);
        //die();




        // var_dump('time: '.(microtime(true)-$this->start));



        //var_dump($user->getSubjectCollection());die();


        // Add TaughtSubjects to SubjectsWithCreatedAssessments to get Participant Lists


        // Get Student Lists for each Subject
        foreach($this->subjectIds as $subjectId){

            $uniqueCodes = array_unique($this->subjectCodes[$subjectId]->getAll());

            $this->folderToStudentListMap[$subjectId] = [];
            $studentList = $this->getExternalClass()->getStudents(implode(',',$uniqueCodes));
            //var_dump("getting classLists($subjectId)... time: ".(microtime(true)-$this->start));

            // Each Student in Subject
            if(is_array($studentList)) {
                foreach ($studentList as $student) {
                    $this->studentExternalIds[$student['synergy_id']] = $student['synergy_id'];
                    $this->folderToStudentListMap[$subjectId][$student['synergy_id']] = $student['synergy_id'];
                }
            }
        }
//    var_dump($this->groupsAdministeredIds);
//    var_dump($this->groupsWithCreatedAssessmentsIds);
//        var_dump(array_unique(array_merge($this->groupsAdministeredIds,$this->groupsWithCreatedAssessmentsIds)));
//        die();

        //var_dump($this->folderToStudentListMap);


        // Get Students with matching externalIds, or in Groups
        if(!empty($this->groupIds) || !empty($this->studentExternalIds))
        {
            $query = '';
            if(!empty($this->groupIds)){
                $query .= "
                    SELECT 
                        1            AS 'case',
                        ua.sid       AS 'g.id',
                        u.id         AS 'u.id',
                        u.synergy_id AS 'u.synergy_id',
                        u.firstname  AS 'u.firstname',
                        u.lastname   AS 'u.lastname'
                    FROM user u
                    JOIN userAccess ua ON ua.uid = u.id
                    JOIN groups g ON u.group_id = g.id
                    WHERE ua.sid IN (".implode(',',$this->groupIds).")
                    AND ua.service = ".UserAccess::SERVICE_HOMEPAGE."
                    AND g.group_type = '".\Group::STUDENT_GROUP_TYPE."'
                ";
            }

            if(!empty($this->studentExternalIds)){
                if(!empty($query)){
                    $query .= ' UNION ';
                }

                $query .= "
                    SELECT
                        2            AS 'case',
                        NULL         AS 'g.id',
                        u.id         AS 'u.id',
                        u.synergy_id AS 'u.synergy_id',
                        u.firstname  AS 'u.firstname',
                        u.lastname   AS 'u.lastname'
                    FROM user u
                    WHERE u.synergy_id IN (" . implode(',', $this->studentExternalIds) . ")
                    AND u.deleted_at IS NULL
                ";
            }
            $query = "SELECT * FROM ($query) AS temp ORDER BY `u.id`";
            $statement = $this->database->prepare($query);
            $statement->execute();
            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
            //$this->printTable($rows);die();

            foreach($rows as $row){
                $this->createStudent($row['u.id'], $row['u.synergy_id'], $row['u.firstname'], $row['u.lastname']);

                // Was this user from a Group ?
                if(!is_null($row['g.id'])){
                    if(!is_array($this->folderToStudentListMap[$row['g.id']])){
                        $this->folderToStudentListMap[$row['g.id']] = [];
                    }
                    $this->folderToStudentListMap[$row['g.id']][$row['u.synergy_id']] = intval($row['u.synergy_id']);
                }
            }
        }

        //var_dump($this->folderToStudentListMap);die();

//var_dump('time: '.(microtime(true) - $this->start));


        // We have all Student ExternalIds, grouped by Subject/Group FolderId
        // We also have a cache of all Student User objects, indexed by Student.ExternalId
        // Lets turn them all into participants
        foreach($this->assessmentsCache as $assessment){
            $folderId = $assessment->getContext()->getFolder()->getId();

            $studentList = $this->folderToStudentListMap[$folderId];

            $participants = new Collection();
            if(is_array($studentList)){
                foreach($studentList as $studentExternalId){
                    $student = $this->studentExtCache[$studentExternalId];
                    $participants[$student->getId()] = new Participant($student);
                }
            }
            $assessment->setAllParticipants($participants);
        }

        // We now have Assessments, linked to their Contexts, and with their Participants.
        // Lets populate and link up the Responses and Feedbacks to the Assessments and Participants

        // Need to handle Responses and Feedbacks for Users who are not in the Group or Subject studentList.
        // - Run all DB Response/Feedback queries & store results in associative arrays
        // - iterate arrays to find missing students (if missing from assessment, but exists in student cache, we can skip)
        // - Get all missing students from DB, and add to studentCache
        // - Now process result arrays, inserting new Participants when required.

        /**
         * Students with Responses/Feedbacks but not in Group/Class studentList
         * key = val = user.id
         *
         * @var int[] $assessmentExtraStudentIds
         */
        $assessmentExtraStudentIds = [];

        //var_dump($this->quizAssessmentIds);die();

        if(count($this->quizAssessmentIds) && count($this->studentInternalIds)) {

            // Get Quiz Attempts for all selected students
            $query = "
            SELECT
                q.assessment_id AS 'q.assessment_id',
                qa.attempt_id   AS 'qa.attempt_id',
                qa.user_id      AS 'qa.user_id',
                qa.start        AS 'qa.start',
                qa.finish       AS 'qa.finish',
                qa.reviewed_at  AS 'qa.reviewed_at',
                min(qs.section_id)   AS 'qs.first_section_id'                                
            FROM assess q
            JOIN assess_attempt qa ON qa.assess_id = q.assess_id AND qa.deleted_at IS NULL
            JOIN assess_section qs ON qs.assess_id = q.assess_id AND qs.deleted_at IS NULL
            WHERE q.deleted_at IS NULL
            AND q.availability = 'assess'
            AND q.assessment_id IN (" . implode(',', $this->quizAssessmentIds) . ") 
            AND qa.user_id IN (" . implode(',', $this->studentInternalIds) . ")
            
            GROUP BY q.assessment_id, qa.attempt_id, qa.user_id
            
            ORDER BY qa.attempt_id
        ";

            //die($query);
            $statement = $this->database->prepare($query);
            $statement->execute();
            $quizResults = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $this->printTable($quizResults);
            //die();

            // Find Students who attempted Quizzes but weren't in the class
            foreach ($quizResults as $row) {
                $studentId = intval($row['qa.user_id']);
                $participants = $this->assessmentsCache[$row['q.assessment_id']]->getAllParticipants();
                // Are we missing the student and student does not exists in student cache?
                if (!$participants[$studentId] && !$this->studentIntCache[$studentId]) {
                    $assessmentExtraStudentIds[$studentId] = $studentId;
                }
            }
        } else {
            $quizResults = [];
        }


        //var_dump($this->assessmentsCache); die();



//var_dump('time: '.(microtime(true) - $this->start));

        // Get Task Completions for all selected students
        $query = "
            SELECT 
                t.assessment_id         AS 't.assessment_id',
                ti.requires_confirmation AS 'ti.requires_confirmation',
                tic.id                   AS 'tic.id',
                tic.student_id           AS 'tic.student_id',
                tic.marking_user_id      AS 'tic.marking_user_id',
                tic.completion_date      AS 'tic.completion_date'
            FROM task t
            JOIN task_instance ti ON ti.task_id = t.id AND ti.deleted_at IS NULL
            JOIN task_instance_completion tic ON tic.task_instance_id = ti.id
            WHERE t.deleted_at IS NULL
            AND t.assessment_id IN (" . implode(',', $this->taskAssessmentIds) . ") 
            AND tic.student_id IN (" . implode(',', $this->studentInternalIds) . ")
        ";

        //die($query);
        $statement = $this->database->prepare($query);
        $statement->execute();
        $taskResults = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $this->printTable($taskResults); //die();

        // Find Students who completed Tasks but weren't in the class
        foreach($taskResults as $row){
            $studentId = intval($row['tic.student_id']);
            $participants = $this->assessmentsCache[$row['t.assessment_id']]->getAllParticipants();
            // Are we missing the student and student does not exists in student cache?
            if(!$participants[$studentId] && !$this->studentIntCache[$studentId]){
                $assessmentExtraStudentIds[$studentId] = $studentId;
            }
        }





        //die();
//var_dump('time: '.(microtime(true) - $this->start));

        if(count($this->dueWorkAssessmentIds) && count($this->studentInternalIds)){
            // Get Due Work Submissions for all selected students
            $query = "
                SELECT
                    sb.assessment_id   AS 'sb.assessment_id',
                    sb.mark_visibility AS 'sb.mark_visibility',
                    ss.id              AS 'ss.id',
                    ss.owner           AS 'ss.owner',
                    ss.submitted       AS 'ss.submitted',
                    ss.marked          AS 'ss.marked'
                FROM submission_box sb
                JOIN student_submission ss ON ss.box_id = sb.id
                WHERE sb.deleted = 0
                AND ss.draft = 0
                AND sb.assessment_id IN (" . implode(',', $this->dueWorkAssessmentIds) . ") 
                AND ss.owner IN (" . implode(',', $this->studentInternalIds) . ")              
            ";
            //die($query);
            $statement = $this->database->prepare($query);
            $statement->execute();
            $dueWorkResponseResults = $statement->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $dueWorkResponseResults = [];
        }
        $this->printTable($dueWorkResponseResults);

        // Find Students who submitted DueWorkSubmissions but weren't in the class
        foreach($dueWorkResponseResults as $row){
            $studentId = intval($row['ss.owner']);
            $participants = $this->assessmentsCache[$row['sb.assessment_id']]->getAllParticipants();
            // Are we missing the student and student does not exists in student cache?
            if(!$participants[$studentId] && !$this->studentIntCache[$studentId]){
                $assessmentExtraStudentIds[$studentId] = $studentId;
            }
        }



        if(count($this->dueWorkAssessmentIds) && count($this->studentInternalIds)){
            // Get Due Work Returns for all selected students
            $query = "
                SELECT
                    sb.assessment_id                 AS 'sb.assessment_id',
                    sb.mark_visibility               AS 'sb.mark_visibility',
                    sr.id                            AS 'sr.id',
                    sr.marker                        AS 'sr.marker',
                    sr.owner                         AS 'sr.owner',
                    sr.returned                      AS 'sr.returned',
                    sr.is_confirmation_of_submission AS 'sr.is_confirmation_of_submission'
                FROM submission_box sb
                JOIN submission_return sr ON sr.box_id = sb.id 
                WHERE sb.deleted = 0
                AND sr.deleted_at IS NULL
                AND sb.assessment_id IN (" . implode(',', $this->dueWorkAssessmentIds) . ") 
                AND sr.owner IN (" . implode(',', $this->studentInternalIds) . ")             
            ";

            //die($query);
            $statement = $this->database->prepare($query);
            $statement->execute();
            $dueWorkFeedbackResults = $statement->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $dueWorkFeedbackResults = [];
        }

        $this->printTable($dueWorkFeedbackResults);

        // Find Students who received DueWork Feedback but weren't in the class
        foreach($dueWorkFeedbackResults as $row){
            $studentId = intval($row['sr.owner']);
            $participants = $this->assessmentsCache[$row['sb.assessment_id']]->getAllParticipants();
            // Are we missing the student and student does not exists in student cache?
            if(!$participants[$studentId] && !$this->studentIntCache[$studentId]){
                $assessmentExtraStudentIds[$studentId] = $studentId;
            }
        }


        //die();


        // Do we have Responses / Feedbacks for students that are not yet loaded?
        if(count($assessmentExtraStudentIds)){
            $query = "
                SELECT 
                    u.id         AS 'u.id',
                    u.synergy_id AS 'u.synergy_id',
                    u.firstname  AS 'u.firstname',
                    u.lastname   AS 'u.lastname'
                FROM user u
                WHERE u.id IN (".implode(',',$assessmentExtraStudentIds).")
            ";
            $statement = $this->database->prepare($query);
            $statement->execute();
            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
            echo '<br><h2>Extra Students:</h2>'; $this->printTable($rows);
            foreach($rows as $row){
                $this->createStudent($row['u.id'], $row['u.synergy_id'], $row['u.firstname'], $row['u.lastname']);
            }

        }

        // Process the results, creating extra Participants where required, pulling User from caches



        //$this->printTable($quizResults); //die();
        foreach($quizResults as $row){
            $quiz = $this->assessmentsCache[$row['q.assessment_id']];
            $userId = $row['qa.user_id'];
            $response = new QuizAttempt();
            $response->setId($row['qa.attempt_id']);
            $response->setSubmittedBy($this->studentIntCache[$userId]);
            $response->setSubmittedOnBehalfOf($this->studentIntCache[$userId]);
            $response->setFirstSectionId($row['qs.first_section_id']);
            $response->setAssessable($quiz);
            $updatedAt = $row['qa.finish'] ?: $row['qa.start'];
            $response->setUpdatedAt($this->getFastDateFromString($updatedAt));

            $quiz->getResponses()->add($response);
            $participants = $quiz->getAllParticipants();

            // Are we missing the Participant?
            if(!$participants[$userId]){
                echo "<span style='color:green;'>Missing Quiz Participant ".$userId." - Generating from cache...</span></br>";
                $participants[$userId] = new Participant($this->studentIntCache[$userId]);
            }
            $participants[$userId]->getAllResponses()->add($response);

            // In this context, if not 'reviewed', then we interpret this as NO feedback
            if(!is_null($row['qa.reviewed_at'])){
                $feedback = new QuizReview();
                $feedback->setId($row['qa.attempt_id']);
                $feedback->setPublishedAt($this->getFastDateFromString($row['qa.reviewed_at']));
                $feedback->setUpdatedAt($this->getFastDateFromString($row['qa.reviewed_at']));

                $feedback->setResponse($response);
                $feedback->setAssessable($quiz);

                $response->getAllFeedback()->add($feedback);
                $quiz->getFeedbacks()->add($feedback);
                $participants[$userId]->getAllFeedbacks()->add($feedback);

                //var_dump($feedback);die();
            }
        }

        foreach($taskResults as $row){
            $task = $this->assessmentsCache[$row['t.assessment_id']];
            $userId = $row['tic.student_id'];
            $response = new TaskCompletion();
            $response->setId($row['tic.id']);
            $response->setSubmittedBy($this->studentIntCache[$userId]);
            $response->setAssessable($task);
            $response->setUpdatedAt($this->getFastDateFromString($row['tic.completion_date']));

            $task->getResponses()->add($response);
            $participants = $task->getAllParticipants();


            // Are we missing the Participant?
            if(!$participants[$userId]){
                echo "<span style='color:green;'>Missing Task Participant ".$userId."- Generating from cache...</span></br>";
                $participants[$userId] = new Participant($this->studentIntCache[$userId]);
            }
            $participants[$userId]->getAllResponses()->add($response);


            // For tasks, if 'reviewer' != 'reviewee', then that is Teacher Feedback
            if($userId != $row['tic.marking_user_id']){
                $feedback = new TaskFeedback();
                $feedback->setId($row['tic.id']);
                $feedback->setPublishedAt($this->getFastDateFromString($row['tic.completion_date']));
                $feedback->setUpdatedAt($this->getFastDateFromString($row['tic.completion_date']));
                $feedback->setResponse($response);
                $feedback->setAssessable($task);

                $response->getAllFeedback()->add($feedback);
                $task->getFeedbacks()->add($feedback);
                $participants[$userId]->getAllFeedbacks()->add($feedback);
            }
        }

        foreach($dueWorkResponseResults as $row){
            $dueWork = $this->assessmentsCache[$row['sb.assessment_id']];
            $userId = $row['ss.owner'];
            $response = new DueWorkSubmission();
            $response->setId($row['ss.id']);
            $response->setSubmittedBy($this->studentIntCache[$userId]);
            $response->setAssessable($dueWork);
            $response->setUpdatedAt($this->getFastDateFromString($row['ss.submitted']));

            $dueWork->getResponses()->add($response);
            $participants = $dueWork->getAllParticipants();

            // Are we missing the Participant?
            if(!$participants[$userId]){
                echo "<span style='color:green;'>Missing DueWork Response Participant ".$userId."- Generating from cache...</span></br>";
                $participants[$userId] = new Participant($this->studentIntCache[$userId]);
            }
            $participants[$userId]->getAllResponses()->add($response);
        }

        foreach($dueWorkFeedbackResults as $row){
            $dueWork = $this->assessmentsCache[$row['sb.assessment_id']];
            $userId = $row['sr.owner'];
            $feedback = new DueWorkFeedback();
            $feedback->setId($row['sr.id']);
            $feedback->setUpdatedAt($this->getFastDateFromString($row['sr.returned']));
            $feedback->setPublishedAt($this->getFastDateFromString($row['sr.returned']));
            $feedback->setAssessable($dueWork);

            $dueWork->getFeedbacks()->add($feedback);
            $participants = $dueWork->getAllParticipants();

            // Are we missing the Participant?
            if(!$participants[$userId]){
                echo "<span style='color:green;'>Missing DueWork Feedback Participant ".$userId."- Generating from cache...</span></br>";
                $participants[$userId] = new Participant($this->studentIntCache[$userId]);
            }
            $participants[$userId]->getAllFeedbacks()->add($feedback);
        }

        // TODO - Hydrate Responses + Feedbacks and add to Assessments + Participants


        $this->printPlainTextPage();
        //die();


        return $this->assessmentsCache;
    }

    private function printPlainTextPage()
    {
        return;

        echo '<table>';
        echo '<tr>
            <th>Context</th>
            <th>Title</th>
            <th>Type</th>
            <th>DueDate</th>
            <th>Participants</th>
            <th>Responses</th>
            <th>Feedbacks</th>
            <th>%<br>Submitted</th>
            <th>%<br>Marked</th>
            <th>%<br>Marking<br>Required</th>
            <th>Needs Marking</th>
            <th>Needs Chasing</th>
        </tr>';
        foreach ($this->assessmentsCache as $assessment){
            $isLate = $assessment->getDueAt()->lt($this->tomorrow);
            echo '<tr>';
            $folderId = $assessment->getContext()->getFolder()->getId();
            echo "<td><a href='/homepage/$folderId'>$folderId</a></td>\n";
            echo "<td>{$assessment->getTitle()} ({$assessment->getId()})</td>\n";
            echo "<td>{$assessment->getTypeName()}</td>\n";
            echo "<td>{$assessment->getDueAt()->getUsingFormat(\Alaress_Date_Format::DATABASE_DATE_FORMAT)}</td>\n";
            echo "<td>";
            foreach($assessment->getAllParticipants() as $participant){
                echo $participant->getUser()->getFirstname()." ".$participant->getUser()->getLastname()." (".$participant->getUser()->getId().')<br>';
            }
            echo "</td>";
            echo "<td>";
            foreach($assessment->getAllParticipants() as $participant){
                echo ' R: '.count($participant->getAllResponses()).'<br>';
            }
            echo "</td>";
            echo "<td>";
            foreach($assessment->getAllParticipants() as $participant){
                echo ' F: '.count($participant->getAllFeedbacks()).'<br>';
            }
            echo "</td>";
            echo "<td>".round($assessment->getPercentageSubmitted(),2)." %</td>\n";
            echo "<td>".round($assessment->getPercentageMarked(),2)." %</td>\n";
            echo "<td>".round($assessment->getPercentageMarkingRequired(),2)." %</td>\n";
            echo "<td>";
            foreach($assessment->getAllParticipants() as $participant){
                if($participant->isMarkingRequired($assessment)){
                    echo $participant->getUser()->getFirstname()." ".$participant->getUser()->getLastname();
                }
                echo '<br>';
            }
            echo "</td>";
            echo "<td ".(!$isLate ? 'style="background-color:grey;"' : '').">";
            if($isLate) {
                foreach ($assessment->getAllParticipants() as $participant) {
                    if (!count($participant->getAllResponses()) && !count($participant->getAllFeedbacks())) {
                        echo $participant->getUser()->getFirstname() . " " . $participant->getUser()->getLastname();
                    }
                    echo '<br>';
                }
            }
            echo "</td>";
            echo '</tr>';
        }
        echo '</table>';
    }



    /**
     * @return \External_UserInterface
     */
    private function getExternalUser()
    {
        if(!$this->externalUser instanceof \External_UserInterface){
            $this->externalUser = new \External_User();
        }
        return $this->externalUser;
    }

    /**
     * @return \External_ClassInterface
     */
    private function getExternalClass()
    {
        if(!$this->externalClass instanceof \External_ClassInterface){
            $this->externalClass = new \External_Class();
        }
        return $this->externalClass;
    }

    private function printTable(array $rows)
    {
        return;

        echo '
        <style type="text/css" media="screen">
        
            table{
                border-collapse:collapse;
                border:1px solid #000000;
            }
            
            table td, table th{
                border:1px solid #000000;
            }
        </style>';

        echo "<table>\n";
        $first = true;
        foreach($rows as $row) {
            if($first){
                echo "<tr>\n";
                foreach(array_keys($row) as $key){  echo "<th>$key</th>\n"; }
                echo "<tr>\n";
            }

            echo "<tr>\n";
            foreach($row as $val){ echo "<td>$val</td>\n"; }
            echo "<tr>\n";
            $first = false;
        }
        echo "</table><br>\n";
    }


    private function createFolder($id, $name)
    {
        $id = intval($id);
        if(!$this->foldersCache[$id] instanceof Folder){
            $this->foldersCache[$id] = (new Folder())->setId($id)->setName($name);
        }
    }

    private function createProject($id, $title)
    {
        $id = intval($id);
        if(!$this->projectCache[$id] instanceof Project){
            $this->projectCache[$id] = (new Project())->setId($id)->setTitle($title);
        }
    }

    private function createCourseSpec($id)
    {
        $id = intval($id);
        if(!$this->courseSpecsCache[$id] instanceof CourseSpecification){
            $this->courseSpecsCache[$id] = (new CourseSpecification())->setId($id);
        }
    }

    private function createCourseImpl($id)
    {
        $id = intval($id);
        if(!$this->courseImplsCache[$id] instanceof CourseImplementation){
            $this->courseImplsCache[$id] = (new CourseImplementation())->setId($id);
        }
    }

    private function createUnitSpec($id)
    {
        $id = intval($id);
        if(!$this->unitSpecsCache[$id] instanceof UnitSpecification){
            $this->unitSpecsCache[$id] = (new UnitSpecification())->setId($id);
        }
    }

    private function createUnitImpl($id)
    {
        $id = intval($id);
        if(!$this->unitImplsCache[$id] instanceof UnitImplementation){
            $this->unitImplsCache[$id] = (new UnitImplementation())->setId($id);
        }
    }


    /**
     * @param Collection|IAssessable[] $assessmentsCache
     */
    private function printCrumbs(Collection $assessmentsCache)
    {
        $assessmentsCache->sortBy('id','asc');

        // Test navigation
        foreach($assessmentsCache as $assessment)
        {
            $context = $assessment->getContext();
            // Case 1
            if($context instanceof Folder) {
                $classId = $context->getFolder()->getId();
                $className = $context->getFolder()->getName();
                $course = '';
                $unit = '';
                $class = "<a href='/homepage/$classId'>$className</a>";
                $project = '';
            }
            // Case 2
            else if($context instanceof UnitImplementation){
                $courseId = $context->getCourse()->getSpecification()->getFolder()->getId();
                $courseName = $context->getCourse()->getSpecification()->getFolder()->getName();
                $unitId = $context->getSpecification()->getFolder()->getId();
                $unitName = $context->getSpecification()->getFolder()->getName();
                $classId = $context->getCourse()->getFolder()->getId();
                $className = $context->getCourse()->getFolder()->getName();

                $course = "<a href='/homepage/$courseId'>$courseName</a> &gt; ";
                $class = "<a href='/homepage/$classId'>$className</a> &gt; ";
                $unit = "<a href='/homepage/$unitId'>$unitName</a>";
                $project = '';
            }
            else if($context instanceof Project){
                $projectContext = $context->getContext();

                // Case 3 - Project on Folder
                if($projectContext instanceof Folder) {
                    $classId = $projectContext->getFolder()->getId();
                    $className = $projectContext->getFolder()->getName();
                    $projectName = $context->getTitle();
                    $course = '';
                    $class = "<a href='/homepage/$classId'>$className</a> &gt; ";
                    $unit = '';
                    $project = "<em>$projectName</em>";
                }
                // Case 4 - Project on UnitImplementation
                else if($projectContext instanceof UnitImplementation){
                    $courseId = $projectContext->getCourse()->getSpecification()->getFolder()->getId();
                    $courseName = $projectContext->getCourse()->getSpecification()->getFolder()->getName();
                    $unitId = $projectContext->getSpecification()->getFolder()->getId();
                    $unitName = $projectContext->getSpecification()->getFolder()->getName();
                    $classId = $projectContext->getCourse()->getFolder()->getId();
                    $className = $projectContext->getCourse()->getFolder()->getName();
                    $projectName = $context->getTitle();

                    $course = "<a href='/homepage/$courseId'>$courseName</a> &gt; ";
                    $class = "<a href='/homepage/$classId'>$className</a> &gt; ";
                    $unit = "<a href='/homepage/$unitId'>$unitName</a> &gt; ";
                    $project = "<em>$projectName</em>";
                } else {
                    throw new \LogicException('Unknown Project context: '.print_r($assessment,true));
                }
            } else {
                throw new \LogicException('Unknown Assessment context: '.print_r($assessment,true));
            }
            echo "{$assessment->getId()}: {$course}{$class}{$unit}{$project}: {$assessment->getTitle()}<br>";
        }
    }


    private function saveFolderCode($folderId, $code)
    {
        if(!$this->subjectCodes[$folderId] instanceOf Collection){
            $this->subjectCodes[$folderId] = new Collection();
        }
        $this->subjectCodes[$folderId]->add($code);
    }

    /**
     * @param array $rows
     */
    private function parseAssessments($rows)
    {
        foreach($rows as $row){

            // Hydrate Assessment
            if(!$this->assessmentsCache[$row['a.id']] instanceof IAssessable){

                // Create Quiz / DueWork / Task object
                // Add ID to type array for later retrieval of Response / Feedback
                if(!is_null($row['qz.id'])){
                    $this->quizAssessmentIds[$row['a.id']] = intval($row['a.id']);
                    $this->assessmentsCache[$row['a.id']] = new Quiz();
                    $this->assessmentsCache[$row['a.id']]->setQuizId($row['qz.id']);
                } else if(!is_null($row['dw.id'])){
                    $this->dueWorkAssessmentIds[$row['a.id']] = intval($row['a.id']);
                    $this->assessmentsCache[$row['a.id']] = new DueWork();
                    $this->assessmentsCache[$row['a.id']]->setDueWorkId($row['dw.id']);
                } else if(!is_null($row['tk.id'])){
                    $this->taskAssessmentIds[$row['a.id']] = intval($row['a.id']);
                    $this->assessmentsCache[$row['a.id']] = new Task();
                    $this->assessmentsCache[$row['a.id']]->setTaskId($row['tk.id']);
                } else {
                    throw new \LogicException('Assessment must be Quiz, DueWork or Task: '.print_r($row,true));
                }

                $assessment = $this->assessmentsCache[$row['a.id']];
                $assessment->setId($row['a.id']);
                $assessment->setTitle($row['a.title']);

                // It is VERY slow to create a new AlaressDateFormat from a DB formatted string.
                $assessment->setDueAt($this->getFastDateFromString($row['a.due_date']));



                switch($row['case']){
                    // Case 1 - Assessment resides directly on Folder
                    case 1:
                        $this->createFolder($row['f.id'], $row['f.name']);
                        $assessment->setContext($this->foldersCache[$row['f.id']]);
                        break;
                    // Case 2 - Assessment in Project that resides directly on Folder
                    case 2:
                        $this->createProject($row['p.id'], $row['p.title']);
                        $this->createFolder($row['f.id'], $row['f.name']);
                        $assessment->setContext($this->projectCache[$row['p.id']]);
                        $this->projectCache[$row['p.id']]->setContext($this->foldersCache[$row['f.id']]);
                        break;
                    case 3:
                        $this->createUnitImpl($row['ui.id']);
                        $this->createUnitSpec($row['us.id']);
                        $this->createCourseImpl($row['ci.id']);
                        $this->createCourseSpec($row['cs.id']);
                        $this->createFolder($row['usf.id'], $row['usf.name']);
                        $this->createFolder($row['f.id'], $row['f.name']);
                        $this->createFolder($row['csf.id'], $row['csf.name']);

                        $assessment->setContext($this->unitImplsCache[$row['ui.id']]);
                        $this->unitImplsCache[$row['ui.id']]->setSpecification($this->unitSpecsCache[$row['us.id']]);
                        $this->unitSpecsCache[$row['us.id']]->setFolder($this->foldersCache[$row['usf.id']]);
                        $this->unitImplsCache[$row['ui.id']]->setCourse($this->courseImplsCache[$row['ci.id']]);
                        $this->courseImplsCache[$row['ci.id']]->setFolder($this->foldersCache[$row['f.id']]);
                        $this->courseImplsCache[$row['ci.id']]->setSpecification($this->courseSpecsCache[$row['cs.id']]);
                        $this->courseSpecsCache[$row['cs.id']]->setFolder($this->foldersCache[$row['csf.id']]);
                        break;
                    case 4:
                        $this->createProject($row['p.id'], $row['p.title']);
                        $this->createUnitImpl($row['ui.id']);
                        $this->createUnitSpec($row['us.id']);
                        $this->createCourseImpl($row['ci.id']);
                        $this->createCourseSpec($row['cs.id']);
                        $this->createFolder($row['usf.id'], $row['usf.name']);
                        $this->createFolder($row['f.id'], $row['f.name']);
                        $this->createFolder($row['csf.id'], $row['csf.name']);

                        $assessment->setContext($this->projectCache[$row['p.id']]);
                        $this->projectCache[$row['p.id']]->setContext($this->unitImplsCache[$row['ui.id']]);
                        $this->unitImplsCache[$row['ui.id']]->setSpecification($this->unitSpecsCache[$row['us.id']]);
                        $this->unitSpecsCache[$row['us.id']]->setFolder($this->foldersCache[$row['usf.id']]);
                        $this->unitImplsCache[$row['ui.id']]->setCourse($this->courseImplsCache[$row['ci.id']]);
                        $this->courseImplsCache[$row['ci.id']]->setFolder($this->foldersCache[$row['f.id']]);
                        $this->courseImplsCache[$row['ci.id']]->setSpecification($this->courseSpecsCache[$row['cs.id']]);
                        $this->courseSpecsCache[$row['cs.id']]->setFolder($this->foldersCache[$row['csf.id']]);
                        break;
                    default:
                        throw new \LogicException('Cannot derive AssessmentContext: '.print_r($row,true));
                }
            }

            // Subject ClassCodes
            if(!is_null($row['fc.code'])){
                $folder = $this->assessmentsCache[$row['a.id']]->getContext()->getFolder();
                $folder->getCodes()->add(new FolderCode($row['fc.code']));
                $subjectFolderId = $folder->getId();
                $this->subjectIds[$subjectFolderId] = $subjectFolderId;
                $this->saveFolderCode($subjectFolderId, $row['fc.code']);

            }
            // Group IDs
            if(!is_null($row['g.id'])){
                $this->groupIds[$row['g.id']] = intval($row['g.id']);
            }
        }
    }

    private function createStudent($id, $externalId, $firstname, $lastname)
    {
        $id = intval($id);
        $externalId = intval($externalId);

        $this->studentInternalIds[$id] = $id;
        $this->studentExternalIds[$externalId] = $externalId;

        if(!$this->studentExtCache[$externalId] instanceof User){
            $this->studentExtCache[$externalId] = (new User())
                ->setId($id)
                ->setExternalId($externalId)
                ->setFirstname($firstname)
                ->setLastname($lastname);
        }

        if(!$this->studentIntCache[$id] instanceof User){
            $this->studentIntCache[$id] = $this->studentExtCache[$externalId];
        }
    }

    /**
     * For some reason this is many times faster than having Alaress_Date_Format parse the string directly
     * @param string|null $dateString
     * @param string $format
     * @return \Alaress_Date_Format
     */
    private function getFastDateFromString($dateString, $format = \Alaress_Date_Format::DATABASE_FORMAT)
    {

        if(empty($dateString)){
            return new \Alaress_Date_NullDate();
        }
        //return new \Alaress_Date_Format($dateString, $format);
        return new \Alaress_Date_Format(new Carbon($dateString), $format);
    }


}