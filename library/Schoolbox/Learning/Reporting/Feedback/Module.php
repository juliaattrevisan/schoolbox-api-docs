<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Reporting\Feedback;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
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
        if ($params['feedbackId']) {
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
            $this->getMapperFactory()->get(\Schoolbox\Assessment\Term\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Learning\DueWork\StateMapper::class),
            $this->injector->get('participantDataSource'),
            $this->injector->get('feedbackDataSource'),
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
     * @return Error\Mapper
     * @throws \InvalidArgumentException If mapper class not found
     */
    private function getErrorMapper()
    {
        return $this->getMapperFactory()->get(Error\Mapper::class);
    }
}
