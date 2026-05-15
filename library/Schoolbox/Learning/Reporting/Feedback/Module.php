<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Reporting\Feedback;

use Alaress\Authorizer\Failure\Forbidden;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Error;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\LockedChecker;
use Schoolbox\Learning\Authorizer\Rule\HasParticipant;
use Schoolbox\Learning\Authorizer\Rule\IsImplementation;
use Schoolbox\Learning\Authorizer\Rule\IsInteractionUnLocked;
use Schoolbox\Learning\Authorizer\Rule\IsSpecification;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningReportingFeedback';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* LMS-friendly Staff can see the form
         */
        $lmsStaff = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\HasRolePermission(Role::PERMISSION_VIEW_STUDENT_GRADES, Role::STAFF),
            new HasParticipant(),
        );

        // TODO: verify this check, it's derived from Due Work, but i'm not entirely sure if reporting has special considerations needed like $lmsStaff...
        $canGiveFeedbackForAssessment = new Rule\Any(
            new Rule\IsSuperuser(),
            // feedback route is accessible for both spec and impl assessments
            new Rule\All(
                $lmsStaff,
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
                        new IsInteractionUnLocked(new LockedChecker($this->getTermMapper()))
                    ),
                    // Specification assessments, you need to have write access to all homepages using the spec (but more complex than can be done here)
                    new IsSpecification(),
                    // offload the perm check for spec assessments to the controller... we should be hitting the filters
                ),
            )
        );

        $preloadAssessment = function (array $params) {
            return $this->preloadAssessment($params);
        };

        $preloadParticipant = function (array $params) {
            return $this->preloadParticipant($params);
        };

        $this->register('GET', '/{assessmentId}/feedback/view', 'getFirstParticipantFeedback', $lmsStaff, $preloadAssessment);
        $this->register('GET', '/{assessmentId}/{learnerId}/feedback{/feedbackId:\d+}', 'getParticularFeedback', $lmsStaff, $preloadParticipant);
        $this->register('POST', '/{assessmentId}/{learnerId}/feedback{/feedbackId:\d+}', 'postParticularFeedback', $lmsStaff, $preloadParticipant);
        $this->register('POST', '/{assessmentId}/{learnerId}/feedback/publish', 'postPublishFeedback', $lmsStaff, $preloadParticipant);
        $this->register('POST', '/{assessmentId}/feedbacks', 'postParticipantFeedbacks', $canGiveFeedbackForAssessment, $preloadAssessment);
    }


    /**
     * @param array $params
     * @return array [ assessment, participant, response ]
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \UnexpectedValueException
     */
    private function preloadParticipant(array $params)
    {
        if (isset($params['assessment']) && $params['assessment'] instanceof IAssessable) {
            $assessment = $params['assessment'];
        } else {
            try {
                $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_GENERIC_NOT_FOUND);
            }
        }

        $feedback = null;
        $learner  = null;

        /* If a feedbackId was provided, load that particular feedback up
         */
        if ($params['feedbackId'] ?? false) {
            // Feedback will already be loaded in $assessment; no need to load again.
            // We don't know the participant yet though, so find that.
            foreach ($assessment->getAllParticipants() as $p) {
                foreach ($p->getAllFeedbacks() as $f) {
                    if ($f->getId() == $params['feedbackId']) {
                        $feedback = $f;
                        $learner  = $p->getUser();
                        break 2;
                    }
                }
            }
            if (!isset($learner, $feedback)) {
                throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_GENERIC_NOT_FOUND);
            }
        } elseif (isset($params['learner']) && $params['learner'] instanceof User) {
            $learner = $params['learner'];
        } elseif ($params['learnerId']) {
            /* Otherwise spawn a floating feedback instead
             */
            try {
                $learner = \Schoolbox\UserMapper::getInstance()->findById($params['learnerId']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_GENERIC_NOT_FOUND);
            }
        }

        return [
            'assessment' => $assessment,
            'feedback'   => $feedback,
            'learner'    => $learner,
        ];
    }


    /**
     * @param array $params
     * @return IAssessable[]
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    private function preloadAssessment(array $params): array
    {
        if (isset($params['assessment']) && $params['assessment'] instanceof IAssessable) {
            $assessment = $params['assessment'];
        } else {
            try {
                $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_GENERIC_NOT_FOUND);
            }
        }
        return ['assessment' => $assessment];
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        $bakery = new BreadcrumbFactory(
            $this->getRouter()->getGenerator(),
            $this->getAuthorizer(),
            $this->getMapperFactory()->get(Access::class),
        );

        return new Controller(
            $this->getErrorMapper(),
            $bakery,
            $this->getMapperFactory()->get(\Schoolbox\Learning\Assessment\DataModel\Reporting\Mapper::class),
            $this->getTermMapper(),
            $this->getMapperFactory()->get(\Schoolbox\Learning\DueWork\StateMapper::class),
            $this->injector->get('participantDataSource'),
            $this->injector->get('feedbackDataSource'),
            $this->getMapperFactory()->get(\Schoolbox\Learning\Assessment\DataModel\Reporting\Feedback\Mapper::class),
            $this->injector->get('assessmentDataSource'),
            $this->getMapperFactory()->get(\Schoolbox\Learning\Assessment\Project\ResultCache\Mapper::class),
            $this->getAccess(),
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @return Mapper|UserMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }


    /**
     * @return AssessmentMapperProxy
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->injector->get('assessmentDataSource');
    }


    /**
     * @return Access
     * @throws \InvalidArgumentException
     */
    private function getAccess()
    {
        return $this->getMapperFactory()->get(Access::class);
    }


    /**
     * @return TermMapper
     * @throws \InvalidArgumentException If unable to find mapper class
     */
    private function getTermMapper()
    {
        return $this->getMapperFactory()->get(TermMapper::class);
    }


    /**
     * @return Error\Mapper
     * @throws \InvalidArgumentException If mapper class not found
     */
    private function getErrorMapper()
    {
        return $this->getMapperFactory()->get(Error\Mapper::class);
    }
}
