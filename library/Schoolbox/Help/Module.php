<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Help;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Twig_Loader_Chain;


class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'help';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes()
    {
        $nesa = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );

        $this->register('GET', '/nesa', 'getNesa', $nesa, null);
        $this->register('POST', '/nesa', 'postNesa', $nesa, null);
        $this->register('POST', '/nesa/csv', 'postNesaCsv', $nesa, null);
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/Nesa/templates');
    }

    /**
     * Override with any steps required for a controller and its routes
     *
     * You should use this method to initialize any mappers or other objects
     * which the controller needs, but are too heavy to initialize at module
     * init time.
     *
     * @param Container|ContainerInterface $injector The dependency injection container.
     * @throws InvalidArgumentException
     */
    protected function preInitControllerDependencies(ContainerInterface $injector)
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'courseSpecificationMapper' => $this->getMapperFactory()->getLazy(CourseSpecificationMapper::class),
            'rubricMapper' => $this->getMapperFactory()->getLazy(RubricMapper::class),
            'errorMapper' => $this->getMapperFactory()->getLazy(ErrorMapper::class)
        ];
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
     * @return Mapper|ErrorMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }
}
