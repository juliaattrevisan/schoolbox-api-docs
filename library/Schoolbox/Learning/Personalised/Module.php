<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Personalised;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Twig_Loader_Chain;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;

/**
 * Sub-module for a student's personalised learning view.
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningPersonalised';

    /**
     * @inheritdoc
     */
    public function registerRoutes()
    {
        // Permissions:
        // All users may view their own work
        // staff may view anyone's due work
        $access = new Rule\Any(
            new Rule\IsUser('studentId'),
            new Rule\HasRole(Role::STAFF)
        );

        $studentPreload = function ($params) {
            return [
                'student' => $this->getUserMapper()->findById($params['studentId']),
            ];
        };

        $this->register('GET', '', 'getPersonalised', new Rule\IsLoggedIn());
        $this->register(
            'GET',
            '/{studentId}',
            'getPersonalisedForStudent',
            $access,
            $studentPreload
        );
        $this->register(
            'GET',
            '/resources/{studentId}',
            'getResourcesForStudent',
            $access,
            $studentPreload
        );
        $this->register(
            'GET',
            '/calendar/{studentId}',
            'getCalendarForStudent',
            $access,
            $studentPreload
        );
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'folderMapper'         => $this->getMapperFactory()->getLazy(FolderMapper::class),
                    'assessmentDataSource' => $injector->lazyGet('assessmentDataSource'),
                ]
            )
        );
    }

    /* Getters / Setters
     */

    /**
     * @return UserMapper
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }
}
