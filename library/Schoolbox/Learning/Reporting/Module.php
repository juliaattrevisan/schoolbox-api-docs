<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Reporting;

use Alaress\Authorizer\Rule;
use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Markbook\FeedbackFolders;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningReporting';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* LMS-friendly Staff can see the form
         */
        $canGiveFeedbackForAssessment = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRolePermission(Role::PERMISSION_VIEW_STUDENT_GRADES, Role::STAFF),
            ),
        );

        /* Preloaders
         */
        $preloadAssessment = function ($params) {
            return $this->preloadAssessment($params);
        };

        /* Route Definitions
         */
        $assRoute  = '/{assessmentId:\d+}';

        /* Get the mega markbook payload for the reporting activity status page
         */
        $this->register('GET', "{$assRoute}/status", 'getStatus', $canGiveFeedbackForAssessment, $preloadAssessment);
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
        return new Controller(
            $this->getAssessmentMapperProxy(),
            $this->getMapperFactory()->get(\Schoolbox\Learning\DueWork\StateMapper::class),
            new ScrubAdapter(),
            $this->getFeedbackFolders(),
            $this->getMapperFactory()->get(\Schoolbox\Assessment\Term\Mapper::class),
        );
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
     * @return ErrorMapper
     * @throws InvalidArgumentException
     */
    protected function getErrorMapper(): ErrorMapper
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }

    /**
     * @return FeedbackFolders
     */
    protected function getFeedbackFolders(): FeedbackFolders
    {
        return new FeedbackFolders(
            $this->injector->get('databaseInternal'),
            $this->injector->get('externalClass'),
            $this->getMapperFactory()->get(UserMapper::class),
        );
    }
}
