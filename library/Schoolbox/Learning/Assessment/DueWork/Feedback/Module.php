<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Feedback;

use Alaress\Authorizer\Failure\Forbidden;
use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Rule;
use Alaress\Authorizer\Rule\IAuthorizationRule;
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
use Schoolbox\Learning\Authorizer\Rule\IsImplementation;
use Schoolbox\Learning\Authorizer\Rule\IsInteractionUnLocked;
use Schoolbox\Learning\Authorizer\Rule\IsSpecification;
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
            // feedback route is accessible for both spec and impl assessments
            new Rule\Any(
                // Implementation assessment use the old logic, user must have write access homepage, and the assessment is not locked (due in an archived term)
                new Rule\All(
                    new IsImplementation(),
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
                ),
                // Specification assessments, you need to have write access to all homepages using the spec (but more complex than can be done here)
                new IsSpecification(),
                // offload the perm check for spec assessments to the controller... we should be hitting the filters
            )
        );

        $canDeleteFeedback = new Rule\Any(
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_LMS, UserAccess::ACCESS_FULL),
            ),
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
            $this->injector->get('kamiConnector'),
            $this->getMapperFactory()->get(TermMapper::class)
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

    /**
     * Only access with the following:
     * markbook is enabled
     * you are logged in
     * you are a staff member (who can view student grades) or superuser
     * if you have write access to all course implementations
     * have write access to the course folder
     *
     * route perm of `\Schoolbox\Assessment\Course\Markbook\Controller::getCanAccessPopOut`
     * @see \Schoolbox\Assessment\Course\Markbook\Controller::getCanAccessPopOut
     *
     * @return IAuthorizationRule
     */
    private function getCanAccessPopOut(): Rule\IAuthorizationRule
    {
        return new Rule\All(
            new Rule\Callback(function (): void {
                if (!$this->getConfig()->get('course_markbook_enable')) {
                    /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                    throw new InvalidPath('Course markbook module is disabled');
                }
            }),
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\HasRolePermission(Role::PERMISSION_VIEW_STUDENT_GRADES, Role::STAFF),
                new Rule\IsSuperuser()
            ),
            new Rule\Callback(function ($params, ?User $user): void {
                if (null === $user) {
                    /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                    throw new InvalidPath('You must be logged in to access this page');
                }
                /** @var IAssessable|null $assessment */
                $assessment = $params['assessment'] ?? null;
                // first check if the user has write access to the course
                $hasCourseWriteAccess = $this->getAccess()->getFolderAccess(
                    $user,
                    $assessment->getBaseContext()->getFolder()->getId(),
                ) >= UserAccess::ACCESS_WRITE;
                if (!$hasCourseWriteAccess) {
                    /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                    throw new InvalidPath('You do not have permission to access this course');
                }

                $courseSpec = null;
                // if we are a spec assessment we need to fetch the course spec
                // otherwise we need to get the spec from the assessment, then get the course spec
                if ($assessment->getBasecontext() instanceof  \Schoolbox\Learning\Course\DataModel\Specification\Unit\Model) {
                    $courseSpec = $assessment->getBasecontext()->getCourse();
                } elseif ($assessment->getBasecontext() instanceof  \Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model) {
                    $courseSpec = $assessment->getBasecontext()->getSpecification()->getCourse();
                }

                if (null === $courseSpec) {
                    // class level, assume they have access... (mostly on class markbook, and assessments created outside of a course)
                    return;
                }

                // then check if the user has write access to all the course implementations
                foreach ($courseSpec->getCourseImplementations() as $classImplementation) {
                    $hasAccess = $this->getAccess()->getFolderAccess(
                        $user,
                        $classImplementation->getFolder()->getId()
                    ) >= UserAccess::ACCESS_WRITE;
                    if (!$hasAccess) {
                        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                        throw new InvalidPath('You do not have permission to access this course implementation');
                    }
                }
            })
        );
    }
}
