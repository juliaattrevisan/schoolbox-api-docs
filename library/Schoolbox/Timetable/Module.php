<?php

/**
 * Module definition for the timetable component.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Timetable;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Aura\Router\Router;
use Alaress\Twig\Loader\IChainLoader;
use External_Timetable;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role;
use Schoolbox\UserMapper;

class Module extends BaseModule
{

    protected $basename = 'timetable';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        /* Staff can view anyone's timetable calendar
         * Students can only view their own calendar
         * Parents can only view their own students' calendar
         */
        $getPerms = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\HasRole(Role::STAFF),
            new Rule\IsUserOrParentOfUser($this->getExternalUser(), $this->getUserMapper(), 'userId')
        );

        $preloadUser = function ($params) {
            return $this->preloadUser($params);
        };

        $this->register('GET', '/calendar/{date}/{userId}', 'getTimetableCalendar', $getPerms, $preloadUser);
        $this->register('GET', '/calendar/{userId}', 'getTimetableCalendarRedirect', $getPerms, $preloadUser);
    }


    /**
     * Preload a User instance
     *
     * @param array $params
     * @return array
     * @throws \InvalidArgumentException
     * @throws NotFound
     */
    private function preloadUser(array $params): array
    {
        try {
            $user = $this->getUserMapper()->findById($params['userId']);
        } catch (MapperException $e) {
            throw new NotFound();
        }

        return [
            'user' => $user,
        ];
    }


    /**
     * @return Mapper|UserMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(\Schoolbox\UserMapper::class);
    }


    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this
            ->addRoute($router, 'dateForTimetableDay', $basePath, '/dateForTimetableDay')
            ->addValues(array('format' => '.json'))
        ;
    }


    /**
     * Currently, any logged in user may access any part of the timetable module.
     *
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn());
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        /** @var External_Timetable $timetable */
        $timetable = $this->getExternalTimetable();
        return new Controller($timetable);
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
