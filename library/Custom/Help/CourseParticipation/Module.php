<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Help\CourseParticipation;

use Alaress\Authorizer\Rule\IsAlaressAdmin;
use Alaress;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Psr\Log\NullLogger;
use Schoolbox\Mail\CoreContext;
use Schoolbox\Mail\TwigEmailFactory;
use Schoolbox\Permission\SiteAccess;
use Schoolbox\MapperFactory;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\User\Password\MailService;
use Schoolbox\User\Password\Reset;
use Schoolbox\UserMapper;
use Twig_Error_Loader;
use Alaress\Twig\Loader\IChainLoader;


class Module extends Alaress\Module\Module
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
        $this->register('POST', '/apitest', 'postApiTest', $alaressOnly);
        $this->register('GET', '/{schoolCode:\w+}/token', 'refreshToken', $alaressOnly);
        $this->register('GET', '/{schoolCode:\w+}', 'getApiTestEnrolmentTable', $alaressOnly);

        $this->register('GET', '/{schoolCode:\w+}/courses', 'getFolderAccessBySchool');
        $this->register('POST', '/{schoolCode:\w+}/courses', 'postFolderAccessBySchool');
        $this->register('GET', '/{schoolCode:\w+}/users', 'getUsersBySchool');
        $this->register('POST', '/{schoolCode:\w+}/users', 'postUserRegistrations');
    }


    /**
     * @param ContainerInterface $injector
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(Controller::class)
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

        $injector->params[CoreContext::class] = [
            'conf' => $injector->lazyGet('config')
        ];

        $injector->params[TwigEmailFactory::class] = [
            'context' => $injector->lazyNew(CoreContext::class),
            'log'     => $injector->lazyNew(NullLogger::class)
        ];

        $injector->params[MailService::class] = [
            'config'      => $injector->lazyGet('config'),
            'resetMapper' => $mapperFactory->getLazy(Reset\Mapper::class),
            'userMapper'  => $mapperFactory->getLazy(UserMapper::class),
            'mailFactory' => $injector->lazyNew(TwigEmailFactory::class)
        ];

        $injector->params[Controller::class] = [
            'userAccessMapper' => $mapperFactory->getLazy(UserAccessMapper::class),
            'tokenMapper'      => $mapperFactory->getLazy(SiteAccess\Mapper::class),
            'folderMapper'     => $mapperFactory->getLazy(Folder\Mapper::class),
            'mailService'      => $injector->lazyNew(MailService::class),
        ];
    }


    /**
     * @param IChainLoader $templateLoader
     * @throws Twig_Error_Loader
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

}
