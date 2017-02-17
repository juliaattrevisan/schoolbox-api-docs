<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Markbook;

use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Assessment\Course\Task\Completion as TaskCompletion;
use Schoolbox\Learning\Assessment\Task\Completion\Model as Completion;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Assessment\Term\Model as Term;
use Alaress\Csv\Writer AS CsvWriter;

class Controller extends Base
{
    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * @var AssessmentMapperProxy
     */
    protected $assessmentMapperProxy;

    /**
     * @var GradeGroup
     */
    protected $defaultGradeGroup;

    /**
     * @var CourseImplementationMapper
     */
    protected $courseImplementationMapper;

    /**
     * @var TermMapper
     */
    protected $termMapper;

    /**
     * Constructor
     *
     * @param ErrorMapper                $errorMapper
     * @param AssessmentMapperProxy      $assessmentMapperProxy
     * @param GradeGroup                 $defaultGradeGroup
     * @param CourseImplementationMapper $courseImplementationMapper
     * @param TermMapper                 $termMapper
     */
    public function __construct(
        ErrorMapper $errorMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        GradeGroup $defaultGradeGroup,
        CourseImplementationMapper $courseImplementationMapper,
        TermMapper $termMapper
    ) {
        $this->setErrorMapper($errorMapper);
        $this->setAssessmentMapperProxy($assessmentMapperProxy);
        $this->setDefaultGradeGroup($defaultGradeGroup);
        $this->setCourseImplementationMapper($courseImplementationMapper);
        $this->setTermMapper($termMapper);
    }

    /**
     * Displays a list of marks for students in the specified class
     *
     * @api   GET /learning/markbook/:classId
     * @level 165
     * @param Folder $class
     */
    public function getMarkbook(Folder $class)
    {
        $this->getResponse()->redirect->to(
            $this->getRouter()->generate(
                'learningMarkbook.getMarkbookForTerm',
                [
                    'classId'  => $class->getId(),
                    'termCode' => $this->getTermMapper()->findForToday()->getCode(),
                ]
            )
        );
    }

    /**
     * Displays a list of marks for students in the specified class for the given term
     *
     * @api   GET /learning/markbook/:classId/term/:termCode
     * @param Folder $class
     * @param Term   $term
     */
    public function getMarkbookForTerm(Folder $class, Term $term)
    {
        // Replaces /submission/listSubmission/{termSlug}/{folderId}
        //       eg /submission/listSubmission/{termSlug}/{folderId}
        // Replaces /submission/viewSubmission/{dueWorkId}
        //       eg /submission/viewSubmission/56

        // Does this class exist ?
        if (!$class->getId()) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        // Generate data: assessments and their Participants
        /** @var Collection|IAssessable[] $assessments */
        $assessments = $this->getAssessmentMapperProxy()->findAllByFolderIdForReview($class->getId(), null, ['term' => $term]);

        // Build a markbook
        $markbook = new Markbook($assessments, $this->getDefaultGradeGroup(), $this->getUser());

        // And what is required for a context selector
        $terms = $this->getTermMapper()->findAll(['order' => ['start' => 'desc']]);

        // Also show the courses/units that are on this homepage
        $courses = $this->getCourseImplementationMapper()->findByFolderId($class->getId());

        // And the previous and next term
        $previousTerm = null;
        $nextTerm     = null;
        foreach ($terms as $i => $termInList) {
            if ($termInList->getId() == $term->getId()) {
                if (isset($terms[$i + 1])) {
                    $previousTerm = $terms[$i + 1];
                }
                if (isset($terms[$i - 1])) {
                    $nextTerm = $terms[$i - 1];
                }
                break;
            }
        }

        // Can the viewee mark Assessments in for this class ?
        $writable = !$term->getArchived() &&
            Access::getInstance()->getFolderAccess($this->getUser(), [$class->getId()]) >= UserAccess::ACCESS_WRITE;

        // And output
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@learningMarkbook/markbook.twig',
                [
                    'class'        => $class,
                    'markbook'     => $markbook,
                    'terms'        => $terms,
                    'currentTerm'  => $term,
                    'previousTerm' => $previousTerm,
                    'nextTerm'     => $nextTerm,
                    'courses'      => $courses,
                    'writable'     => $writable,
                ]
            )->setTitle('Markbook for '.$class->getTitle())
        );
    }

    /**
     * Return the markbook in CSV format
     *
     * @api   GET /learning/markbook/:classId/term/:termCode/export
     * @param Folder $class
     * @param Term   $term
     */
    public function getMarkbookExport(Folder $class, Term $term)
    {
        // Replaces /submission/listSubmission/{termSlug}/{folderId}
        //       eg /submission/listSubmission/{termSlug}/{folderId}
        // Replaces /submission/viewSubmission/{dueWorkId}
        //       eg /submission/viewSubmission/56

        // Does this class exist ?
        if (!$class->getId()) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        // Generate data: assessments and their Participants
        /** @var Collection|IAssessable[] $assessments */
        $assessments = $this->getAssessmentMapperProxy()->findAllByFolderIdForReview($class->getId(), null, ['term' => $term]);

        // Build a markbook
        $markbook = new Markbook($assessments, $this->getDefaultGradeGroup(), $this->getUser());

        /** @var IAssessable $assessment */

        $export = [];

        $data['Student Id'] = 'Class Average';
        $data['Student First Name'] = '';
        $data['Student Last Name'] = '';
        $data['Student Average'] = $markbook->getMarkbookStatistics()['weightedMean'];

        // This is an ugly hack to get duplicate task names to appear as is the case in demo data
        // Just prepending spaces to the end of the assessment name

        $titlesFound = [];

        foreach ($markbook->getAssessmentsWithProjectContents() as $assessment) {

            $title = $assessment->getTitle() . "\r\n(" . $assessment->getTypeName() . ")";

            while (isset($titlesFound[$title])) {
                $title .= " ";
            }

            $titlesFound[$title] = true;
            $data[$title] = $markbook->getAssessmentStatistics()[$assessment->getId()]['mean'];
            $data[$title . " (Normalized)"] = '';
            $data[$title . " (Comment)"] = '';
        }

        $export[] = $data;

        foreach ($markbook->getStudents() as $student) {

            $data['Student Id'] = $student->getExternalId();
            $data['Student First Name'] = $student->getFirstname();
            $data['Student Last Name'] = $student->getLastname();
            $data['Student Average'] = $markbook->getStudentStatistics()[$student->getId()]['weightedMean'];

            $titlesFound = [];

            foreach ($markbook->getAssessmentsWithProjectContents() as $assessment) {

                $title = $assessment->getTitle() . "\r\n(" . $assessment->getTypeName() . ")";

                while (isset($titlesFound[$title])) {
                    $title .= " ";
                }

                $titlesFound[$title] = true;

                if ($assessment->isParticipant($student)) {

                    $participant = $assessment->getParticipant($student);
                    $mostRecentFeedback = $participant->findMostRecentFeedback();

                    if ($mostRecentFeedback != null) {
                        $comment = $mostRecentFeedback->getComment();
                    } else {
                        $comment = '';
                    }

                    if ($assessment instanceof Task) {

                        $result = '';

                        $mostRecentResponse = $participant->findMostRecentResponse();

                        if ($mostRecentResponse instanceof Completion) {
                            switch ($mostRecentResponse->getCompletionStatus()) {
                                case TaskCompletion::COMPLETION_STATUS_TEACHER_ASSESSED:
                                    $result = "Completed";
                                    break;
                                case TaskCompletion::COMPLETION_STATUS_SELF_ASSESSED:
                                    if ($assessment->isSelfAssessed()) {
                                        $result = "Self Assessed";
                                    } else {
                                        $result = "Teacher Assessed";
                                    }
                                    break;
                                case TaskCompletion::COMPLETION_STATUS_INCOMPLETE;
                                    $result = "Not Attempted";
                                    break;
                            }

                        }

                        $data[$title] = $result;
                        $data[$title . " (Normalized)"] = '';
                        $data[$title . " (Comment)"] = $comment;
                    } else {
                        $data[$title] = $assessment->getHumanReadableMark($student, true);
                        $data[$title . " (Normalized)"] = $assessment->getNormalisedMark($student, true);
                        $data[$title . " (Comment)"] = $comment;
                    }
                }
                else
                {
                    $data[$title] = '';
                    $data[$title . " (Normalized)"] = '';
                    $data[$title . " (Comment)"] = '';
                }

            }
            $export[] = $data;
        }


        //TODO This could probably be rewritten with a resource stream output object and using fputcsv to wrap up this code
        $file = tmpfile();
        $writer = new CsvWriter($file, $export);
        $writer->write();

        $filename = strtolower(str_replace(" ", "_", "markbookexport-" . $class->getFolder()->getNameWithCodes()));

        header('Content-Disposition: attachment;filename="' . $filename . '.csv";');
        header('Content-Type: application/csv; charset=UTF-8');
        rewind($file);
        echo stream_get_contents($file);
        fclose($file);
        exit;
    }

    /* Getters/Setters
     */

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param ErrorMapper $errorMapper
     * @return static
     */
    protected function setErrorMapper(ErrorMapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }

    /**
     * @return AssessmentMapperProxy
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->assessmentMapperProxy;
    }

    /**
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @return static
     */
    protected function setAssessmentMapperProxy(AssessmentMapperProxy $assessmentMapperProxy)
    {
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        return $this;
    }

    /**
     * @return GradeGroup
     */
    public function getDefaultGradeGroup()
    {
        return $this->defaultGradeGroup;
    }

    /**
     * @param GradeGroup $defaultGradeGroup
     * @return static
     */
    public function setDefaultGradeGroup(GradeGroup $defaultGradeGroup)
    {
        $this->defaultGradeGroup = $defaultGradeGroup;
        return $this;
    }

    /**
     * @return CourseImplementationMapper
     */
    public function getCourseImplementationMapper()
    {
        return $this->courseImplementationMapper;
    }

    /**
     * @param CourseImplementationMapper $courseImplementationMapper
     * @return static
     */
    public function setCourseImplementationMapper(CourseImplementationMapper $courseImplementationMapper)
    {
        $this->courseImplementationMapper = $courseImplementationMapper;
        return $this;
    }

    /**
     * @return TermMapper
     */
    public function getTermMapper()
    {
        return $this->termMapper;
    }

    /**
     * @param TermMapper $termMapper
     * @return static
     */
    public function setTermMapper(TermMapper $termMapper)
    {
        $this->termMapper = $termMapper;
        return $this;
    }
}
