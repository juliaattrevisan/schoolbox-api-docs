<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\API\Help;

use Alaress\Authorizer\Rule\IsAlaressAdmin;
use Alaress\Module\Module;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Schoolbox\API\Help\Token;
use Schoolbox\MapperFactory;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Twig_Error_Loader;
use Twig_Loader_Chain;


class CourseParticipationModule extends Module
{
    /** @var string */
    protected $basename = 'helpCourseParticipation';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes()
    {
        $alaressOnly = new IsAlaressAdmin();

        $this->register('GET', '/apitest', 'getApiTest', $alaressOnly);
        $this->register('GET', '/{schoolCode:\w+}/courses', 'getFolderAccessBySchool');
    }


    /**
     * @param ContainerInterface $injector
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(CourseParticipationController::class)
        );
    }


    /**
     * Override with any steps required for a controller and its routes
     *
     * You should use this method to initialize any mappers or other objects
     * which the controller needs, but are too heavy to initialize at module
     * init time.
     *
     * @param Container|ContainerInterface $injector The dependency injection container.
     * @throws \InvalidArgumentException
     */
    protected function preInitControllerDependencies(ContainerInterface $injector)
    {
        /** @var MapperFactory $mapperFactory */
        $mapperFactory = $injector->get('mapperFactory');

        $injector->params[CourseParticipationController::class] = [
            'userAccessMapper' => $mapperFactory->getLazy(UserAccessMapper::class),
            'tokenMapper'      => $mapperFactory->getLazy(Token\Mapper::class),
            'folderMapper'     => $mapperFactory->getLazy(Folder\Mapper::class),
        ];
    }


    /**
     * @param Twig_Loader_Chain $templateLoader
     * @throws Twig_Error_Loader
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

}