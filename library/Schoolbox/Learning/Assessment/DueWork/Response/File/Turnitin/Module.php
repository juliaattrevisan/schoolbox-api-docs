<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin;

use Alaress\Module\Module as BaseModule;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\IMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Response\DataSource as ResponseMapperProxy;
use Schoolbox\Learning\Turnitin;
use Schoolbox\Mapper\Exception as MapperException;


/**
 * Turnitin Webhooks route through this controller
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'turnitinWebhook';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Anyone can post to the endpoint, for now
         *
         * ...but we should really be validating the Turnitin payloads before
         * blindly accepting them
         */
        $postPerms = null;

        $preloadAssessment = function (array $params) {
            return $this->preloadAssessment($params);
        };

        $this->register('POST', '/webhook', 'postWebhook', $postPerms);

        /* These are the individual controller methods that you can test in isolation
         */
//        $this->register('POST', '/upload-complete', 'postUploadComplete', $postPerms);
//        $this->register('POST', '/report-complete', 'postReportComplete', $postPerms);
//        $this->register('POST', '/report-updated', 'postReportUpdated', $postPerms);

        $this->register('GET', '/list/{assessmentId}', 'getList', $postPerms, $preloadAssessment);
    }


    /**
     * @param array $params
     * @return array [ assessment, response, turnitin ]
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \UnexpectedValueException
     */
    private function preloadAssessment(array $params): array
    {
        /* Die early when requested Assessment doesn't exist
         */
        try {
            $assessment = $this->getAssessmentMapperProxy()->findByIdForReview($params['assessmentId']);
        } catch (MapperException $e) {
            throw $this->getErrorMapper()->generateException(\Schoolbox\Error\Mapper::ERROR_GENERIC_NOT_FOUND);
        }

        return [
            'assessment' => $assessment,
        ];
    }


    /* Getters / Setters
     */

    /**
     * @return \Schoolbox\Error\Mapper|IMapper
     * @throws \InvalidArgumentException If mapper class not found
     */
    private function getErrorMapper()
    {
        return $this->getMapperFactory()->get(\Schoolbox\Error\Mapper::class);
    }


    /**
     * @return object|AssessmentMapperProxy
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->injector->get('assessmentDataSource');
    }


    /**
     * @return object|ResponseMapperProxy
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    protected function getResponseMapperProxy()
    {
        return $this->injector->get('responseDataSource');
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }


    /**
     * Override with any steps required for a controller and its routes
     *
     * You should use this method to initialize any mappers or other objects
     * which the controller needs, but are too heavy to initialize at module
     * init time.
     *
     * @param ContainerInterface $injector The dependency injection container.
     * @throws InvalidArgumentException
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'turnitinFileMapper' => $this->getMapperFactory()->getLazy(Turnitin\File\Mapper::class),
            'userMapper' => $this->getMapperFactory()->getLazy(\Schoolbox\UserMapper::class)
        ];
    }
}
