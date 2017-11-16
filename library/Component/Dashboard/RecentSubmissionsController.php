<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Assessment\Component\SubmissionBox as DueWork;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\GenericCollection;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Permission\Access\UserAccess;

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

    /** @var Filters\Assessment\IdFinder */
    private $idFinder;

    /** @var \Schoolbox\Learning\Assessment\Response\SubSource\Quiz $quizAttemptDataSource */
    private $attemptDataSource;

    /** @var \Schoolbox\Learning\Assessment\Response\SubSource\DueWork $studentSubmissionDataSource */
    private $submissionDataSource;

    /** @var AssessmentMapperProxy $assessmentMapperProxy */
    private $assessmentMapperProxy;

    /**
     * Sorted by most recent student response first
     *
     * @var Collection|DueWork[]|Quiz[]
     */
    private $assessments;


    public function __construct(Component_Instance $instance, \Schoolbox\User $user, SitePage $sitepage)
    {
        parent::__construct($instance, $user, $sitepage);

        /** @var Filter\Mapper $filterMapper */
        $filterMapper                = $this->getMapperFactory()->get(Filter\Mapper::class);
        $this->idFinder = Filters\Assessment\IdFinder::getInstance(
            $filterMapper,
            $this->getMapperFactory()->get(Assessment\Mapper::class)
        );
        $this->attemptDataSource     = $this->getSitePage()->getDi()->get('attemptDataSource');
        $this->submissionDataSource  = $this->getSitePage()->getDi()->get('submissionDataSource');
        $this->assessmentMapperProxy = $this->getSitePage()->getDi()->get('assessmentDataSource');
    }


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

        // Get IDs of all assessments:
        // * taught by user
        // * of type Quiz or DueWork
        // * not an AssessmentSpec
        $folders             = $this->getFolderMapper()->findForUser($this->getUser(), true, UserAccess::ACCESS_WRITE);
        $folderIds           = $folders->getColumnValues('id');
        $taughtAssessmentIds = $this->idFinder->findIdsByFilters([
            Filters\Assessment\AssessmentType\Definition::class           => [
                Filters\Assessment\AssessmentType\Definition::KEY_DUE_WORK => Filters\Assessment\AssessmentType\Definition::KEY_DUE_WORK,
                Filters\Assessment\AssessmentType\Definition::KEY_QUIZ     => Filters\Assessment\AssessmentType\Definition::KEY_QUIZ,
            ],
            Filters\Assessment\Folder\TeacherInvolved\Definition::class   => array_combine($folderIds, $folderIds),
            Filters\Assessment\NullableContext\UnitSpec\Definition::class => [],
        ]);

        // Short circuit if no assessments match filters
        if (!count($taughtAssessmentIds)) {
            $this->assessments = $this->assessmentMapperProxy->createCollection();
            return false;
        }

        // Get most recent N days of DueWork Submissions for the filtered assessments
        $pb = $this->submissionDataSource->getPredicateBuilder();
        $pb->equals('b.assessment_id', $taughtAssessmentIds);
        if (self::DAY_LIMIT_ALL === $dayLimit) {
            $pb->notNull('submitted_at');
        } else {
            $pb->greaterThan('submitted_at', $cutoffDate->getDatabase());
        }
        $studentSubmissions = $this->submissionDataSource->findByPredicates($pb);

        // Get most recent N days of Quiz Attempts for the filtered assessments
        $pb = $this->attemptDataSource->getPredicateBuilder();
        $pb->equals('a.assessment_id', $taughtAssessmentIds);
        $pb->equals('a.availability', Quiz::AVAILABILITY_ASSESS);
        if (self::DAY_LIMIT_ALL === $dayLimit) {
            $pb->notNull('finish');
        } else {
            $pb->greaterThan('finish', $cutoffDate->getDatabase());
        }
        $quizAttempts = $this->attemptDataSource->findByPredicates($pb);

        /** @var GenericCollection|IResponse[] $responses */
        $responses = (new GenericCollection(IResponse::class))
            ->merge($studentSubmissions)
            ->merge($quizAttempts)
            ->sortByCallback(function (IResponse $a, IResponse $b) {
                return $b->getSubmittedAt()->compare($a->getSubmittedAt());
            });

        // Group IResponses by IAssessable

        $assessmentIds = [];
        $limit         = $this->getConfig('sizeNoAll');

        foreach ($responses as $response) {
            $assessmentId                 = $response->getAssessable()->getId();
            $assessmentIds[$assessmentId] = $assessmentId;
            if (count($assessmentIds) >= $limit) {
                break;
            }
        }

        /** @var IAssessable[]|GenericCollection $assessables */
        $assessables = $this->assessmentMapperProxy->findAllByIds($assessmentIds)->indexBy('id');

        foreach ($responses as $response) {
            $assessmentId = $response->getAssessable()->getId();
            if (isset($assessables[$assessmentId])) {
                $assessables[$assessmentId]->getResponses()->add($response);
            }
        }

        // Sort by most recent first, in most recent first
        $assessables->sortByCallback(function ($assessmentA, $assessmentB) {
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
