<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Quiz\Attempt;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Authorizer\Rule\IsInstructor;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Model as FolderModel;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningQuizAttempt';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Instructors only
         */
        $instructorPerms = $this->buildAuthRuleHasInstructorAccess();

        $preloadAttempt = function ($params) {
            return $this->preloadAttempt($params);
        };

        $this->register('GET', '/{learnosityReference}', 'getModify', $instructorPerms, $preloadAttempt);
        $this->register('POST', '/{learnosityReference}', 'postModify', $instructorPerms, $preloadAttempt);
    }


    /**
     * TODO: Literally copypasta'd: how do we spoc this?
     *  Probably think about dissolving the Learnosity/Controller and Learnosity/Module
     *  into these Quiz* variants, since we aren't considering seriously divorcing them
     *
     * Prevent non-instructors from marking or modifying assessments.
     *
     * This uses the `IsInstructor` validator, which is the new standard for
     * checking if a user has instructor permission over a folder.
     *
     * @return Rule\IAuthorizationRule
     * @throws InvalidArgumentException
     */
    private function buildAuthRuleHasInstructorAccess(): Rule\IAuthorizationRule
    {
        return new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new IsInstructor(
                    $this->getAccess(),
                    static function (array $params): FolderModel {
                        if (isset($params['assessment'])) {
                            $ctx = $params['assessment']->getContext()->getFolder();
                        } elseif (isset($params['participant'])) {
                            $ctx = $params['participant']->getAssessable()->getContext()->getFolder();
                        } elseif (isset($params['context'])) {
                            $ctx = $params['context']->getFolder();
                        } else {
                            throw new NotFound('No assessment or context preloaded');
                        }

                        return $ctx;
                    }
                )
            )
        );
    }


    /**
     * Preloads an attempt via its learnosity reference identifier
     *
     * @param array $params
     * @return array
     */
    protected function preloadAttempt(array $params): array
    {
        try {
            $attempt = $this->getAttemptMapper()->findOneByColumnList([
                'learnosity_reference' => $params['learnositySessionReference']
            ]);
        } catch (\Schoolbox\Mapper\Exception $e) {
            throw new NotFound();
        }

        return [
            'attempt' => $attempt
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
     * @return object|AssessmentMapperProxy
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->injector->get('assessmentDataSource');
    }


    /**
     * @return Mapper|\Schoolbox\Learning\Quiz\Attempt\Mapper
     * @throws InvalidArgumentException
     */
    protected function getAttemptMapper()
    {
        return $this->getMapperFactory()->get(\Schoolbox\Learning\Quiz\Attempt\Mapper::class);
    }


    /**
     * @return Access
     * @throws InvalidArgumentException
     */
    protected function getAccess()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getMapperFactory()->get(Access::class);
    }
}
