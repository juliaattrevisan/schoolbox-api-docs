<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Help\CourseParticipation;

use Alaress\Authorizer\Rule\IsSchoolboxAdmin;
use Alaress;
use Alaress\Web\Controller\Base;
use Schoolbox\Permission\SiteAccess;
use Schoolbox\MapperFactory;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\User\Password\MailService;
use Schoolbox\User\Password\Reset;
use Schoolbox\UserMapper;
use Twig\Error\LoaderError;
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
        $schoolboxOnly = new IsSchoolboxAdmin();

        $this->register('GET', '/apitest', 'getApiTest', $schoolboxOnly);
        $this->register('POST', '/apitest', 'postApiTest', $schoolboxOnly);
        $this->register('GET', '/{schoolCode:\w+}/digest', 'getRefreshDigest', $schoolboxOnly);
        $this->register('GET', '/{schoolCode:\w+}', 'getApiTestEnrolmentTable', $schoolboxOnly);

        $this->register('GET', '/{schoolCode:\w+}/courses', 'getFolderAccessBySchool');
        $this->register('POST', '/{schoolCode:\w+}/courses', 'postFolderAccessBySchool');
        $this->register('GET', '/{schoolCode:\w+}/users', 'getUsersBySchool');
        $this->register('POST', '/{schoolCode:\w+}/users', 'postUserRegistrations');
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        /** @var MapperFactory $mapperFactory */
        $mapperFactory = $this->injector->get('mapperFactory');
        return new Controller(
            $mapperFactory->get(UserAccessMapper::class),
            $mapperFactory->get(SiteAccess\Mapper::class),
            $mapperFactory->get(Folder\Mapper::class),
            new MailService(
                $this->getConfig(),
                $mapperFactory->get(Reset\Mapper::class),
                $mapperFactory->get(UserMapper::class),
                $this->injector->get('mail.emailFactory'),
            )
        );
    }


    /**
     * @param IChainLoader $templateLoader
     * @throws LoaderError
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
