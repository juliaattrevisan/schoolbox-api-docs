<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Feedback;

use Alaress\Authorizer\Failure\Forbidden;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DueWork\Authorizer\Rule\CanSelfAssess;
use Schoolbox\Learning\Assessment\Feedback\DataSource as FeedbackDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantDataSource;
use Schoolbox\Learning\Assessment\Response\DataSource as ResponseDataSource;
use Schoolbox\Learning\Authorizer\Rule\IsInteractionUnLocked;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /** @var string $basename */
    protected $basename = 'learningDueWorkFeedback';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws Forbidden If user authenticated but lacks perms for request
     * @throws InvalidArgumentException If assessment's due date is null
     */
    protected function registerRoutes()
    {
        // Rules

        $canGiveFeedbackFromParticipant = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\All(
                new Rule\HasHomepageAccess(UserAccess::ACCESS_WRITE, function ($params) {
                    if ($params['participant'] instanceof Participant) {
                        return $params['participant']->getAssessable()->getContext()->getFolder()->getId();
                    }
                    throw new Forbidden('User does not have homepage access');
                }, $this->getAccess()),
                new IsInteractionUnLocked(new Assessment\LockedChecker($this->getTermMapper()))
            ),
            new CanSelfAssess()
        );

        $canGiveFeedbackForAssessment = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\All(
            // User must have write access to the folder the assessment is in
                new Rule\HasHomepageAccess(UserAccess::ACCESS_WRITE, function ($params) {
                    /** @var IAssessable $assessmentId */
                    $assessable = $params['assessment'];
                    if ($assessable instanceof IAssessable) {
                        return $assessable->getContext()->getFolder()->getId();
                    }
                    throw new Forbidden('User does not have homepage access');
                }, $this->getAccess()),
                new IsInteractionUnLocked(new Assessment\LockedChecker($this->getTermMapper()))
            )
        );

        $canDeleteFeedback = new Rule\IsSuperuser();

        // Preloads

        /**
         * @param $params
         *
         * @return array
         * @throws MapperException
         * @throws MapperException\Deleted
         * @throws \Aura\Di\Exception\ServiceNotFound
         */
        $preloadAssessable = function ($params) {
            return [
                'assessment' => $this->preloadAssessable($params['assessmentId']),
            ];
        };

        /**
         * @param $params
         *
         * @return array
         * @throws MapperException
         * @throws MapperException\Deleted
         * @throws \Aura\Di\Exception\ServiceNotFound
         */
        $preloadParticipant = function ($params) {
            return [
                'participant' => $this->preloadParticipant($params['assessmentId'], $params['studentId']),
            ];
        };

        /**
         * @param $params
         *
         * @return array
         * @throws MapperException
         * @throws MapperException\Deleted
         * @throws \Aura\Di\Exception\ServiceNotFound
         */
        $preloadFeedback = function ($params) {
            return [
                'feedback' => $this->preloadFeedback($params['assessmentId'], $params['feedbackId']),
            ];
        };

        /**
         * @param $params
         *
         * @return array
         * @throws MapperException
         * @throws MapperException\Deleted
         * @throws \Aura\Di\Exception\ServiceNotFound
         */
        $preloadParticipantAndResponse = function ($params) {
            return [
                'participant' => $this->preloadParticipant($params['assessmentId'], $params['studentId']),
                'response'    => $this->preloadResponse($params['assessmentId'], $params['responseId']),
            ];
        };

        $assessmentRoute  = 'assessments/{assessmentId:\d+}';
        $participantRoute = $assessmentRoute . '/{studentId:\d+}';
        $feedbackRoute    = 'feedback/{feedbackId:\d+}';

        $this->register(
            'POST',
            "/{$participantRoute}/feedback",
            'postParticipantFeedback',
            $canGiveFeedbackFromParticipant,
            $preloadParticipant
        );

        $this->register(
            'POST',
            "/{$participantRoute}{/responseId}/feedback",
            'postResponseFeedback',
            $canGiveFeedbackFromParticipant,
            $preloadParticipantAndResponse
        );

        $this->register(
            'DELETE',
            "/{$assessmentRoute}/$feedbackRoute",
            'deleteAssessmentFeedback',
            $canDeleteFeedback,
            $preloadFeedback
        );

        $this->register(
            'POST',
            "/{$assessmentRoute}/feedbacks",
            'postParticipantFeedbacks',
            $canGiveFeedbackForAssessment,
            $preloadAssessable
        );
    }

    /**
     * @inheritdoc
     *
     * @param IChainLoader $templateLoader
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * Override with any steps required for a controller and its routes
     * You should use this method to initialize any mappers or other objects
     * which the controller needs, but are too heavy to initialize at module
     * init time.
     *
     * @param Container|ContainerInterface $injector The dependency injection container.
     */
    protected function preInitControllerDependencies(ContainerInterface $injector)
    {
        $injector->params[Controller::class] = [
            'feedbackDataSource'    => $injector->lazyGet('feedbackDataSource'),
            'participantDataSource' => $injector->lazyGet('participantDataSource'),
            'assessmentDataSource'  => $injector->lazyGet('assessmentDataSource'),
            'userMapper'            => $this->getMapperFactory()->get(UserMapper::class),
            'responseDataSource'    => $injector->lazyGet('responseDataSource'),
            'rubricMapper'          => $this->getMapperFactory()->get(Rubric\RubricMapper::class),
            'returnedWorkMapper'    => $this->getMapperFactory()->get(ReturnedWorkMapper::class),
        ];
    }

    /**
     * @inheritdoc
     *
     * @param ContainerInterface $injector
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }

    /**
     * @param $assessmentId
     * @param $studentId
     *
     * @return Participant
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    protected function preloadParticipant($assessmentId, $studentId)
    {
        $assessment = $this->getAssessmentDataSource()->findById($assessmentId);
        $student    = $this->getUserDataSource()->findById($studentId);

        $assessment = $this->getParticipantDataSource()->loadParticipationIntoAssessment($assessment, $student);

        return $assessment->getParticipant($student);
    }

    /**
     * @param int $assessmentId
     *
     * @return IAssessable
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    protected function preloadAssessable($assessmentId)
    {
        return $this->getAssessmentDataSource()->findById($assessmentId);
    }

    /**
     * @param int $assessmentId
     * @param int $responseId
     *
     * @return mixed|Assessment\IResponse
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    protected function preloadResponse($assessmentId, $responseId)
    {
        $responses = $this->getResponseDataSource()->findForAssessment(
            $this->getAssessmentDataSource()->findById($assessmentId)
        );

        // TODO: Migrate this to DataSource

        foreach ($responses as $responseCurrent) {
            if ($responseCurrent->getId() == $responseId) {
                return $responseCurrent;
            }
        }

        throw new MapperException("Response '{$responseId}' not found for assessment '{$assessmentId}'.");
    }

    /**
     * @param int $assessmentId
     * @param int $feedbackId
     *
     * @return Assessment\IFeedback
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    protected function preloadFeedback($assessmentId, $feedbackId)
    {
        $feedbacks = $this->getFeedbackDataSource()->findForAssessment(
            $this->getAssessmentDataSource()->findById($assessmentId)
        );

        // TODO: Migrate this to DataSource

        foreach ($feedbacks as $feedbackCurrent) {
            if ($feedbackCurrent->getId() == $feedbackId) {
                return $feedbackCurrent;
            }
        }

        throw new MapperException("Feedback '{$feedbackId}' not found for assessment '{$assessmentId}'.");
    }


    /**
     * @return Access
     */
    private function getAccess()
    {
        return $this->getMapperFactory()->get(Access::class);
    }


    /**
     * @return Mapper|UserMapper
     */
    public function getUserDataSource()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return Mapper|TermMapper
     * @throws InvalidArgumentException If unable to find mapper class
     */
    private function getTermMapper()
    {
        return $this->getMapperFactory()->get(TermMapper::class);
    }

    /**
     * @return object|AssessmentDataSource
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getAssessmentDataSource()
    {
        return $this->getInjector()->get('assessmentDataSource');
    }

    /**
     * @return object|ParticipantDataSource
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getParticipantDataSource()
    {
        return $this->getInjector()->get('participantDataSource');
    }

    /**
     * @return object|ResponseDataSource
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getResponseDataSource()
    {
        return $this->getInjector()->get('responseDataSource');
    }

    /**
     * @return object|FeedbackDataSource
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getFeedbackDataSource()
    {
        return $this->getInjector()->get('feedbackDataSource');
    }
}
