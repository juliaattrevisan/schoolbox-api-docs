<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Student;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Timetable_ColourMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningStudentCourses';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        /* People who should be able to see this page:
         * student themselves; their parents and all staff
         */
        $getPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF),
                new Rule\IsUserOrParentOfUser($this->getExternalUser(), $this->getUserMapper(), 'userId')
            )
        );

        $preloadUser = function ($params) {
            return $this->preloadUser($params);
        };

        $this->register('GET', '/{userId}', 'getCourses', $getPerms, $preloadUser);
    }


    /**
     * Preload a User instance
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws NotFound
     */
    private function preloadUser(array $params): array
    {
        try {
            $user = $this->getUserMapper()->findWithSubjects($params['userId']);
        } catch (MapperException) {
            throw new NotFound();
        }

        return [
            'user' => $user,
        ];
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(FolderMapper::class),
            $this->injector->get('databaseInternal'),
            $this->getMapperFactory()->get(Timetable_ColourMapper::class),
            $this->getMapperFactory()->get(Access::class)
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @return Mapper|UserMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }
}
