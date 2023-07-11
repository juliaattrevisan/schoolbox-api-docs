<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

use DomainException;
use InvalidArgumentException;
use LogicException;
use UnexpectedValueException;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Application\Exception as ApplicationException;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role;
use Schoolbox\User;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learnosity';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $instructorPerms = $this->canViewQuiz();

        $preloadAssessment = function ($params) {
            return $this->preloadAssessment($params);
        };

        /* Modify Learnosity contents for a given schoolbox quiz id
         */
        $this->register('GET', '/{assessmentId}/content', 'getContent', $instructorPerms, $preloadAssessment);

        $this->register('GET', '/activityList', 'getActivityList', $instructorPerms, null);
        $this->register('GET', '/items', 'getItems', $instructorPerms, null);
        $this->register('GET', '/feedback', 'getFeedback', $instructorPerms, null);
        $this->register('GET', '/sessionList', 'getSessionListReport', $instructorPerms, null);
    }


    /**
     * @return Rule\LazyCallback
     */
    private function canViewQuiz(): Rule\LazyCallback
    {
        return new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF)
                )
            );
        });
    }


    /**
     * Convert route params into an assessment object
     *
     * @param array     $params
     * @param null|User $student If provided, only load this student's participation
     * @return array
     * @throws ApplicationException\Exception If assessment is not found
     * @throws DomainException If user has interaction but not participant record
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     * @throws Mapper\DefinitionException If a specified join mapper is not found
     * @throws UnexpectedValueException If unable to derive project participant collections
     */
    protected function preloadAssessment(array $params, User $student = null): array
    {
        try {
            $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId'], $student);
        } catch (MapperException\Deleted $e) {
            $assessment = $e->getDeletedObject();
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
        return new Controller();
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @return Mapper|ErrorMapper
     * @throws InvalidArgumentException
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }


    /**
     * @return object|AssessmentMapperProxy
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->injector->get('assessmentDataSource');
    }
}
