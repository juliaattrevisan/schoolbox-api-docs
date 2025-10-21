<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\DialogAgent;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\LockedChecker;
use Schoolbox\Learning\Authorizer\Rule\AssessmentNotDeleted;
use Schoolbox\Learning\Authorizer\Rule\IsInstructorOrHasHomepageAccess;
use Schoolbox\Learning\Authorizer\Rule\IsModifyUnLocked;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningDialogAgent';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $isSuper = $this->getAuthRule('isSuperuser');

        $canWrite = $this->buildAuthRuleHasInstructorOrHomepageAccess();

        $canModify = new Rule\Any(
            $isSuper,
            new Rule\All(
                $canWrite,
                new AssessmentNotDeleted(),
                new IsModifyUnLocked(new LockedChecker($this->getTermMapper()))
            )
        );

        $preloadAssessment = function ($params) {
            return $this->preloadAssessment($params);
        };

        $this->register('GET', '/{assessmentId:\d+}/configure', 'getUpdateForm', $canModify, $preloadAssessment);
        $this->register('POST', '/{assessmentId:\d+}/configure', 'postUpdate', $canModify, $preloadAssessment);
    }


    /**
     * TODO Technical Uplift Candidate
     *  We just duped this from Assessment\Module, due to this being a two-part configuration
     *  Also a bunch of related bits and bobs came with it, including all the preloaders and perms
     *
     * Prevent non-instructors from marking or modifying implementation
     * assessments, and users without homepage write access from modifying spec
     * assessments.
     *
     * @return Rule\IAuthorizationRule
     * @throws InvalidArgumentException
     */
    private function buildAuthRuleHasInstructorOrHomepageAccess(): Rule\IAuthorizationRule
    {
        return new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new IsInstructorOrHasHomepageAccess(
                    $this->getAccess(),
                    function (array $params) {
                        if (isset($params['assessment'])) {
                            $ctx = $params['assessment']->getContext();
                        } elseif (isset($params['participant'])) {
                            $ctx = $params['participant']->getAssessable()->getContext();
                        } elseif (isset($params['context'])) {
                            $ctx = $params['context'];
                        } else {
                            throw new NotFound('No assessment or context preloaded');
                        }
                        return $ctx;
                    },
                ),
            )
        );
    }


    /**
     * Convert route params into an assessment object
     *
     * @param array $params
     * @param User|null $student If provided, only load this student's participation
     * @return array
     * @throws ContainerExceptionInterface
     * @throws Deleted
     * @throws NotFoundExceptionInterface
     */
    protected function preloadAssessment(array $params, ?User $student = null): array
    {
        try {
            $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId'], $student);
        } catch (MapperException\Deleted $e) {
            throw new MapperException\Deleted('Sorry, the activity could not be updated. Please ensure the activity still exists and contact your support if the problem persists.');
        } catch (MapperException $e) {
            // Possibly thrown by base findByOne() mapper call, but masked by overriding @method annotation
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        return [
            'assessment' => $assessment
        ];
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        /** @var AssessmentMapperProxy $assessmentMapperProxy */
        $assessmentMapperProxy = $this->injector->get('assessmentDataSource');

        return new Controller(
            new \Schoolbox\Learning\Assessment\Breadcrumb\Factory(
                $this->getRouter()->getGenerator(),
                $this->getAuthorizer(),
                $this->getMapperFactory()->get(Access::class),
            ),
            $assessmentMapperProxy,
            $this->getMapperFactory()->get(\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Admin\DialogAgent\Scenario\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Admin\DialogAgent\Scenario\Field\Context\Mapper::class),
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
     * @return AssessmentMapperProxy
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function getAssessmentMapperProxy(): AssessmentMapperProxy
    {
        return $this->injector->get('assessmentDataSource');
    }


    /**
     * @return UserMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getUserMapper(): UserMapper
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }


    /**
     * @return ErrorMapper
     * @throws InvalidArgumentException
     */
    protected function getErrorMapper(): ErrorMapper
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }


    /**
     * @return Access
     * @throws InvalidArgumentException
     */
    protected function getAccess(): Access
    {
        return $this->getMapperFactory()->get(Access::class);
    }


    /**
     * @return \Schoolbox\Assessment\Term\Mapper
     * @throws \InvalidArgumentException If unable to find mapper class
     */
    private function getTermMapper(): \Schoolbox\Assessment\Term\Mapper
    {
        return $this->getMapperFactory()->get(\Schoolbox\Assessment\Term\Mapper::class);
    }
}
