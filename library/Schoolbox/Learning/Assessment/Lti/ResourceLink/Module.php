<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Lti\ResourceLink;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;
use Aura\Di\Exception\ServiceNotFound;
use Closure;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;
use Schoolbox\Learning\Authorizer\Rule\IsInstructor;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;

class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'learningLtiResourceLink';

    /**
     * @inheritdoc
     */
    protected function registerRoutes()
    {
        $permission = new Rule\Any(
            new Rule\IsSuperuser(),
            new IsInstructor(Access::getInstance()),
        );


        $this->register(
            self::METHOD_GET,
            '/launch/{assessmentId}',
            'getLaunchAssessment',
            new Rule\IsLoggedIn(),
            Closure::fromCallable([$this, 'preloadAssessment']),
        );

//        $this->register(
//            self::METHOD_GET,
//            '/launch/{assessmentId}/student/{studentId}',
//            'getLaunchAssessmentAsStudent',
//            new IsLoggedIn(),
//        );
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * Convert route params into an assessment object
     *
     * @param array $params
     * @return array
     * @throws ServiceNotFound
     */
    private function preloadAssessment(array $params): array
    {
        /** @var AssessmentMapperProxy $assessmentDataSource */
        $assessmentDataSource = $this->injector->get('assessmentDataSource');
        try {
            $assessment = $assessmentDataSource->findByIdForReview($params['assessmentId']);
        } catch (MapperException $e) {
            throw new NotFound();
//            // Possibly thrown by base findByOne() mapper call, but masked by overriding @method annotation
//            /** @var ErrorMapper $errorMapper */
//            $errorMapper = $this->getMapperFactory()->get(ErrorMapper::class);
//            throw $errorMapper->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        // Not an LTI assessment?
        if (!$assessment instanceof LtiAssessment) {
            throw new NotFound();
        }

        return [
            'assessment' => $assessment,
        ];
    }
}
