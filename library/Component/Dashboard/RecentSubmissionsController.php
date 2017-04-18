<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Assessment\Component\SubmissionBox as DueWork;
use Schoolbox\Learning\Quiz\Model as Quiz;
use \Schoolbox\GenericCollection;
use \Schoolbox\Collection;
use \Schoolbox\Learning\Assessment\IAssessable;
use \Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;

/**
 * Staff-only component.
 *
 * Displays the most recent N days of DueWork Submissions + Quiz Attempts for DueWork
 * and Quizzes that were created by current user.
 */
class Component_Dashboard_RecentSubmissionsController extends Component_Dashboard_ControllerAbstract
{
    // User has selected not to limit days of results, ie they have select 'ALL'
    const DAY_LIMIT_ALL = 0;

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
     * @throws ServiceNotFound
     * @throws RuntimeException
     */
    public function execute()
    {
        // don't try to parse data if we're not a teacher
        if (!$this->getUser()->getRole()->isStaff()) {
            return false;
        }

        $dayLimit = $this->getConfig('days');

        if (self::DAY_LIMIT_ALL === $dayLimit) {
            $cutoffDate = null;
        } else {
            $cutoffDate = Alaress_Date_Format::now();
            $cutoffDate->addDays(-1 * $dayLimit);
        }

        // Get most recent N days of DueWork Submissions
        /** @var \Schoolbox\Learning\Assessment\Response\SubSource\DueWork $studentSubmissionDataSource */
        $studentSubmissionDataSource = $this->getSitePage()->getDi()->get('submissionDataSource');
        $pb = $studentSubmissionDataSource->getPredicateBuilder();
        $pb->equals('b.owner', $this->getUser()->getId());
        if(self::DAY_LIMIT_ALL === $dayLimit){
            $pb->notNull('submitted_at');
        } else {
            $pb->greaterThan('submitted_at', $cutoffDate->getDatabase());
        }
        $studentSubmissions = $studentSubmissionDataSource->findByPredicates($pb);

        // Get most recent N days of Quiz Attempts
        /** @var \Schoolbox\Learning\Assessment\Response\SubSource\Quiz $quizAttemptDataSource */
        $quizAttemptDataSource = $this->getSitePage()->getDi()->get('attemptDataSource');
        $pb = $quizAttemptDataSource->getPredicateBuilder();
        $pb->equals('a.user_id', $this->getUser()->getId());
        $pb->equals('a.availability', Quiz::AVAILABILITY_ASSESS);
        if(self::DAY_LIMIT_ALL === $dayLimit) {
            $pb->notNull('finish');
        } else {
            $pb->greaterThan('finish', $cutoffDate->getDatabase());
        }
        $quizAttempts = $quizAttemptDataSource->findByPredicates($pb);

        /** @var GenericCollection|IResponse[] $responses */
        $responses = (new GenericCollection(IResponse::class))->merge($studentSubmissions)->merge($quizAttempts);

        // Group IResponses by IAssessable

        /** @var AssessmentMapperProxy $assessmentMapperProxy */
        $assessmentMapperProxy = $this->getSitePage()->getDi()->get('assessmentDataSource');

        $assessmentIds = [];
        $limit         = $this->getConfig('sizeNoAll');

        foreach($responses as $response){
            $assessmentId = $response->getAssessable()->getId();
            $assessmentIds[$assessmentId] = $assessmentId;
            if (count($assessmentIds) >= $limit) {
                break;
            }
        }

        /** @var IAssessable[]|GenericCollection $assessables */
        $assessables = $assessmentMapperProxy->findAllByIds($assessmentIds)->indexBy('id');

        foreach($responses as $response){
            $assessmentId = $response->getAssessable()->getId();
            if (isset($assessables[$assessmentId])) {
                $assessables[$assessmentId]->getResponses()->add($response);
            }
        }

        // Sort responses most recent first
        foreach($assessables as $assessable){
            $assessable->getResponses()->sortBy('submittedAt', 'DESC');
        }

        // Sort by most recent first, in most recent first
        $assessables->sortByCallback(function($assessmentA, $assessmentB){
            /** @var IAssessable $assessmentA */
            /** @var IAssessable $assessmentB */
            $dateA = $assessmentA->getResponses()[0]->getSubmittedAt();
            $dateB = $assessmentB->getResponses()[0]->getSubmittedAt();
            return $dateB->compare($dateA);
        });

        $this->assessments = $assessables;
        return $assessables->count() > 0;
    }

    /**
     * @param String $templateHandle
     * @return bool
     * @throws Exception
     */
    public function renderTemplate($templateHandle)
    {
        return $this->renderTwigTemplate(
            $templateHandle,
            '@learning/component/recentSubmissions.twig',
            [
                'assessments' => $this->assessments,
            ]
        );
    }
}
