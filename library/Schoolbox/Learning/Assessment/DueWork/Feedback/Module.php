<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Feedback;

use Alaress\Authorizer\Failure\Forbidden;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\Feedback\DataSource as FeedbackDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantDataSource;
use Schoolbox\Learning\Assessment\Response\DataSource as ResponseDataSource;
use Schoolbox\Learning\Authorizer\Rule\IsInteractionUnLocked;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Role;

class Module extends BaseModule
{
    /** @var string $basename */
    protected $basename = 'learningDueWorkFeedback';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException If assessment's due date is null
     */
    protected function registerRoutes()
    {
        // Rules

        $canGiveFeedbackFromParticipant = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\All(
                new Rule\HasHomepageAccess(UserAccess::ACCESS_WRITE, function ($params) {
                    if ($params['assessment'] instanceof Assessment\DueWork\Model) {
                        return $params['assessment']->getBaseContext()->getFolder()->getId();
                    }
                    throw new Forbidden('User does not have homepage access');
                }, $this->getAccess()),
                new IsInteractionUnLocked(new Assessment\LockedChecker($this->getTermMapper()))
            ),
        );

        $canGiveFeedbackForAssessment = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\All(
            // User must have write access to the folder the assessment is in
                new Rule\HasHomepageAccess(UserAccess::ACCESS_WRITE, function ($params) {
                    /** @var IAssessable $assessable */
                    $assessable = $params['assessment'];
                    if ($assessable instanceof IAssessable) {
                        return $assessable->getContext()->getFolder()->getId();
                    }
                    throw new Forbidden('User does not have homepage access');
                }, $this->getAccess()),
                new IsInteractionUnLocked(new Assessment\LockedChecker($this->getTermMapper()))
            )
        );

        $canDeleteFeedback = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\All(
                new Rule\IsConfigOn('lms_permit_staff_feedback_delete', $this->getConfig()),
                new Rule\HasRole(Role::STAFF),
                new Rule\Callback(static function (array $params, User $user): void {
                    if (
                                            $params['feedback'] instanceof IFeedback
                                            && $params['feedback']->getReviewer()->getId() === $user->getId()
                    ) {
                        return;
                    }
                                        throw new Forbidden("Unable to delete feedback you did not create");
                })
            )
        );

        // Preloads

        /**
         * @param array $params
         * @return array
         */
        $preloadAssessable = function ($params) {
            return [
                'assessment' => $this->preloadAssessable($params['assessmentId']),
            ];
        };

        /**
         * @param array $params
         * @return array
         */
        $preloadParticipant = function ($params) {
            $student = $this->getUserDataSource()->findById($params['studentId']);
            return [
                'assessment' => $this->preloadAssessableWithStudentParticipation($params['assessmentId'], $student),
                'student'    => $student,
            ];
        };

        /**
         * @param array $params
         * @return array
         */
        $preloadFeedback = function ($params) {
            return [
                'feedback' => $this->preloadFeedback($params['assessmentId'], $params['feedbackId']),
            ];
        };

        /**
         * @param array $params
         * @return array
         */
        $preloadParticipantAndResponse = function ($params) use ($preloadParticipant) {
            $preloadedParticipant = $preloadParticipant($params);
            return $preloadedParticipant + [
                'response' => $this->preloadResponse($params['assessmentId'], $params['responseId']),
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

        // new markbook publish feedback
        $this->register(
            'POST',
            "/{$participantRoute}/feedback/publish",
            'postPublishFeedback',
            $canGiveFeedbackFromParticipant,
            $preloadParticipant
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
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('feedbackDataSource'),
            $this->injector->get('participantDataSource'),
            $this->injector->get('assessmentDataSource'),
            $this->injector->get('responseDataSource'),
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getMapperFactory()->get(RubricDataSource::class),
            $this->getMapperFactory()->get(ReturnedWorkMapper::class),
            $this->getMapperFactory()->get(Assessment\Project\ResultCache\Mapper::class),
            $this->injector->get('kamiConnector')
        );
    }

    /**
     * @param int $assessmentId
     *
     * @return IAssessable
     * @throws InvalidArgumentException
     * @throws MapperException\Deleted
     * @throws Mapper\DefinitionException
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \DomainException
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    protected function preloadAssessable($assessmentId)
    {
        return $this->getAssessmentDataSource()->findById($assessmentId);
    }

    /**
     * @param int  $assessmentId
     * @param User $student
     *
     * @return IAssessable
     * @throws InvalidArgumentException
     * @throws MapperException\Deleted
     * @throws Mapper\DefinitionException
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \DomainException
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    protected function preloadAssessableWithStudentParticipation($assessmentId, User $student)
    {
        $assessable = $this->preloadAssessable($assessmentId);
        return $this->getParticipantDataSource()->loadParticipationIntoAssessment($assessable, $student);
    }

    /**
     * @param int $assessmentId
     * @param int $responseId
     *
     * @return mixed|Assessment\IResponse
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws Mapper\DefinitionException
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \DomainException
     * @throws \LogicException
     * @throws \UnexpectedValueException
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
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws Mapper\DefinitionException
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \DomainException
     * @throws \LogicException
     * @throws \UnexpectedValueException
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
     * @throws InvalidArgumentException
     */
    private function getAccess()
    {
        return $this->getMapperFactory()->get(Access::class);
    }


    /**
     * @return Mapper|UserMapper
     * @throws InvalidArgumentException
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
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function getAssessmentDataSource()
    {
        return $this->injector->get('assessmentDataSource');
    }

    /**
     * @return object|ParticipantDataSource
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function getParticipantDataSource()
    {
        return $this->injector->get('participantDataSource');
    }

    /**
     * @return object|ResponseDataSource
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function getResponseDataSource()
    {
        return $this->injector->get('responseDataSource');
    }

    /**
     * @return object|FeedbackDataSource
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function getFeedbackDataSource()
    {
        return $this->injector->get('feedbackDataSource');
    }
}
