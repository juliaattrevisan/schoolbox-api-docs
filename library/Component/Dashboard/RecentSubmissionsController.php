<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\Assessment\Component\SubmissionBox as DueWork;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\Submission as StudentSubmission;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper as StudentSubmissionMapper;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Learning\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use \Schoolbox\GenericCollection;
use \Schoolbox\Collection;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Learning\Assessment\Quiz\Attempt\MapperProxy as QuizAttemptMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Submission\MapperProxy as DueWorkSubmissionMapperProxy;
use \Schoolbox\Learning\Assessment\IAssessable;
use \Schoolbox\Learning\Assessment\DueWork\Model as DueWorkAssessable;
use \Schoolbox\Learning\Assessment\Quiz\Model as QuizAssessable;

/**
 * Staff-only component.
 *
 * Displays the most recent N days of DueWork Submissions + Quiz Attempts for DueWork
 * and Quizzes that were created by current user.
 */
class Component_Dashboard_RecentSubmissionsController extends Component_Dashboard_ControllerAbstract
{
    /**
     * Sorted by most recent student response first
     *
     * @var Collection|DueWork[]|Quiz[]
     */
    private $assessments;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // don't try to parse data if we're not a teacher
        if (!$this->getUser()->getRole()->isStaff()) {
            return false;
        }

        $cutoffDate = Alaress_Date_Format::now();
        $cutoffDate->addDays(-1 * $this->getConfig('days'));

        // Get most recent N months of DueWork Submissions
        /** @var StudentSubmissionMapper $studentSubmissionMapper */
        $studentSubmissionMapper = $this->getMapperFactory()->get(StudentSubmissionMapper::class);
        $pb = $studentSubmissionMapper->createPredicateBuilder();
        $pb->equals('b.owner', $this->getUser()->getId());
        $pb->greaterThan('submitted_at', $cutoffDate->getDatabase());
        $studentSubmissions = $studentSubmissionMapper->findByPredicates($pb);

        // Get most recent N months of Quiz Attempts
        /** @var QuizAttemptMapper $quizAttemptMapper */
        $quizAttemptMapper = $this->getMapperFactory()->get(QuizAttemptMapper::class);
        $pb = $quizAttemptMapper->createPredicateBuilder();
        $pb->equals('a.user_id', $this->getUser()->getId());
        $pb->equals('a.availability', Quiz::AVAILABILITY_ASSESS);
        $pb->greaterThan('finish', $cutoffDate->getDatabase());
        $quizAttempts = $quizAttemptMapper->findByPredicates($pb);

        // Convert to IAssessables and IResponses
        $quizAttemptMapperProxy = new QuizAttemptMapperProxy($quizAttemptMapper);
        $submissionMapperProxy = new DueWorkSubmissionMapperProxy($studentSubmissionMapper);

        /** @var Folder[] Indexed by folder.id */
        $folderCache = [];
        /** @var IAssessable[] $assessables */
        $assessables = new GenericCollection(IAssessable::class);

        /** @var StudentSubmission[] $studentSubmissions */
        foreach($studentSubmissions as $submission){
            $assessableId = $submission->getSubmissionBox()->getAssessmentModel()->getId();
            if(!$assessables[$assessableId] instanceof IAssessable){
                $dueWork = $submission->getSubmissionBox();
                $assessable = (new DueWorkAssessable())
                    ->setId($dueWork->getAssessmentModel()->getId())
                    ->setTitle($dueWork->getName())
                    ->setContext($dueWork->getCategory());
                $assessables[$assessableId] = $assessable;
                $folderCache[$dueWork->getCategory()->getId()] = true;
            }
            $response = $submissionMapperProxy->transformModels(new Collection([$submission]))->first();
            $assessables[$assessableId]->getResponses()->add($response);
        }

        /** @var QuizAttempt[] $quizAttempts */
        foreach($quizAttempts as $attempt){
            $assessableId = $attempt->getQuiz()->getAssessmentModel()->getId();
            if(!$assessables[$assessableId] instanceof IAssessable){
                $quiz = $attempt->getQuiz();
                $assessable = (new QuizAssessable())
                    ->setId($quiz->getAssessmentModel()->getId())
                    ->setTitle($quiz->getTitle())
                    ->setContext($quiz->getCategory());
                $assessables[$assessableId] = $assessable;
                $folderCache[$quiz->getCategory()->getId()] = true;
            }
            $response = $quizAttemptMapperProxy->transformModels(new Collection([$attempt]))->first();
            $assessables[$assessableId]->getResponses()->add($response);
        }

        // Hydrate folders so we can print breadcrumbs
        foreach($assessables as $assessable){
            $folderId = $assessable->getContext()->getId();
            if(!$folderCache[$folderId] instanceof Folder){
                $folderCache[$folderId] = $this->getFolderMapper()->findCheaplyById($folderId, ['deleted' => true]);
            }
            $assessable->setContext($folderCache[$folderId]);


            // Sort responses most recent first
            $assessable->getResponses()->sortBy('submittedAt', 'DESC');
        }

        // Sort by most recent first, in most recent first
        $assessables->sortByCallback(function($assessmentA, $assessmentB){
            /** @var IAssessable $assessmentA */
            /** @var IAssessable $assessmentB */
            $dateA = $assessmentA->getResponses()[0]->getSubmittedAt();
            $dateB = $assessmentB->getResponses()[0]->getSubmittedAt();
            return $dateA->compare($dateB);
        });

        $this->assessments = $assessables;
        return $this->assessments->count();
    }

    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        return $this->renderTwigTemplate(
            $templateHandle,
            '@learning/component/recentSubmissions.twig',
            [
                'assessments'         => $this->assessments,
                'limit'               => $limit = $this->getConfig('size'),
            ]
        );
    }
}