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


/**
 * Staff-only component.
 *
 * Displays the most recent N months of DueWork Submissions + Quiz Attempts for DueWork and Quizzes
 * that were created by current user.
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
        $cutoffDate->addMonths(-6); // Create a Config Item for this

        // Get most recent N months of DueWork Submissions
        $studentSubmissionMapper = $this->getMapperFactory()->get(StudentSubmissionMapper::class);
        $pb = $studentSubmissionMapper->createPredicateBuilder();
        $pb->equals('b.owner', $this->getUser()->getId());
        $pb->greaterThan('created_at', $cutoffDate->getDatabase());
        $studentSubmissions = $studentSubmissionMapper->findByPredicates($pb);


        // Get most recent N months of Quiz Attempts
        $quizAttemptMapper = $this->getMapperFactory()->get(QuizAttemptMapper::class);
        $pb = $quizAttemptMapper->createPredicateBuilder();
        $pb->equals('a.user_id', $this->getUser()->getId());
        $pb->greaterThan('start', $cutoffDate->getDatabase());
        $quizAttempts = $quizAttemptMapper->findByPredicates($pb);

        /** @var Folder[] Indexed by folder.id */
        $folderCache = [];

        // Group By DueWork.Id and Quiz.ID and convert to IAssessables

        /** @var Quiz[]|Collection $quizzes */
        $quizzes = new Collection();
        foreach($quizAttempts as $quizAttempt){
            $quizId = $quizAttempt->getQuiz()->getId();
            if(!$quizzes[$quizId] instanceof Quiz){
                $quizzes[$quizId] = $quizAttempt->getQuiz();
                $folderId = $quizzes[$quizId]->getCategory()->getId();
                if(!$folderCache[$folderId] instanceof Folder){
                    $folderCache[$folderId] = $this->getFolderMapper()->findCheaplyById($folderId);
                }
                $quizzes[$quizId]->setCategory($folderCache[$folderId]);
            }
            $quizzes[$quizId]->getAttempts()->add($quizAttempt);
        }

        // Sort quiz attempts most recent first
        foreach($quizzes as $quiz){
            $quiz->getAttempts()->sortBy('start', 'DESC');
        }

        /** @var DueWork[]|Collection $dueWorks */
        $dueWorks = new Collection();
        foreach($studentSubmissions as $submission){
            $boxId = $submission->getSubmissionBox()->getId();
            if(!$dueWorks[$boxId] instanceof DueWork){
                $dueWorks[$boxId] = $submission->getSubmissionBox();
                $folderId = $dueWorks[$boxId]->getCategory()->getId();
                if(!$folderCache[$folderId] instanceof Folder){
                    $folderCache[$folderId] = $this->getFolderMapper()->findCheaplyById($folderId);
                }
                $dueWorks[$boxId]->setCategory($folderCache[$folderId]);
            }
            $dueWorks[$boxId]->getSubmissions()->add($submission);
        }

        // Sort DueWork Submission most recent first
        foreach($dueWorks as $dueWork){
            $dueWork->getSubmissions()->sortBy('submittedAt', 'DESC');
        }

        $assessments = $dueWorks->merge($quizzes);

        $mostRecentFunction = function($assessment){
            if($assessment instanceof Quiz){
                /** @var QuizAttempt $mostRecentAttempt */
                return $assessment->getAttempts()[0]->getStart();
            } elseif($assessment instanceof DueWork){
                /** @var StudentSubmission $mostRecentSubmission */
                return $assessment->getSubmissions()[0]->getSubmittedAt();
            } else {
                throw new InvalidArgumentException('Unknown Assessment: '.get_class($assessment));
            }
        };

        // Sort by most recent response date
        $this->assessments = $assessments->sortByCallback(function($assessmentA, $assessmentB) use ($mostRecentFunction){
            /** @var Alaress_Date_Format $dateA */
            /** @var Alaress_Date_Format $dateB */
            $dateA = $mostRecentFunction($assessmentA);
            $dateB = $mostRecentFunction($assessmentB);
            return $dateA->compare($dateB);
        });
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