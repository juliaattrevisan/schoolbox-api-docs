<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\All;
use Alaress\Authorizer\Rule\Any;
use Alaress\Authorizer\Rule\Callback;
use Alaress\Authorizer\Rule\HasRole;
use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Authorizer\Rule\IsSuperuser;
use Alaress\Authorizer\Rule\IsUserOrParentOfUser;
use Alaress\Authorizer\Rule\LazyCallback;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Alaress\Web\Router\PathMatcher;
use Aura\Router\Map;
use Calendar\Api\ModuleChecker;
use Schoolbox\CampusMapper;
use Schoolbox\Resource\Folder\Mapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'calendar';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(string $basePath)
    {
        // need this to navigate /event route
        $selectedDateRegex = '([0][1-9]|[12][0-9]|3[01])(-)(((0)[0-9])|((1)[0-2]))(-)\d{4}';

        $this->addGetRoute('getOwnCalendar', $basePath, "{/selectedDate}");
        $this->addGetRoute('getCalendar', $basePath, "/{selectedDate}/{userId}")->tokens([
            'selectedDate' => $selectedDateRegex,
            'userId'       => '\d+',
        ]);

        // Redirect old paths
        $this->addGetRoute('redirect', $basePath, "/{format}{/selectedDate,userId}")->tokens([
            'format'       => 'week|month|full|week-list|month-list',
            'selectedDate' => $selectedDateRegex,
            'userId'       => '\d+',
        ]);
        $this->addPostRoute('changeState', $basePath, '/changeState');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload(
            $matcher,
            '.*',
            function ($params) {
                if (array_key_exists('userId', $params)) {
                    $params['user'] = $this->getUserMapper()->findById($params['userId']);
                }
                if (
                    array_key_exists('selectedDate', $params) && $params['selectedDate'] !== ''
                    && !in_array($params['selectedDate'], ['week', 'month', 'full', 'week-list', 'month-list'])
                ) {
                    try {
                        $selectedDate = new \Alaress_Date_Format($params['selectedDate'], \Alaress_Date_Format::URL_FORMAT);
                        $params['selectedDate'] = $selectedDate->getDatabaseDate();
                    } catch (\Exception $e) {
                        $params['selectedDate'] = null;
                    }
                } else {
                    $params['selectedDate'] = null;
                }
                return $params;
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission($authorizer, 'getOwnCalendar', new IsLoggedIn());
        $this->addRoutePermission($authorizer, 'redirect', new IsLoggedIn());
        $this->addRoutePermission($authorizer, 'changeState', new IsLoggedIn());


        $this->addRoutePermission(
            $authorizer,
            'getCalendar',
            new LazyCallback(function () {
                return new All(
                    new IsLoggedIn(),
                    new Any(
                        new IsSuperuser(),
                        new Callback(
                            static function (array $params = [], User $viewer = null): void {
                                /* $viewee might end up being null if $params[user] is null
                                 * which can happen when you try to access a nonexistent user
                                 */
                                /** @var null|User $viewee */
                                $viewee = $params['user'] ?? null;

                                if (
                                    $viewee
                                    && $viewer->getRole()->isStaff()
                                    && (!$viewee->getRole()->isStudent() || $viewer->getRole()->getViewStudentCalendar())
                                ) {
                                    return;
                                }
                                throw new Unauthorized();
                            }
                        ),
                        new All(
                            new IsUserOrParentOfUser(
                                $this->getExternalUser(),
                                UserMapper::getInstance(),
                                'userId'
                            ),
                            new Any(
                                new HasRole(Role::PARENT),
                                new HasRole(Role::STUDENT)
                            )
                        )
                    )
                );
            })
        );
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(Mapper::class),
            $this->getMapperFactory()->get(CampusMapper::class),
            $this->getMapperFactory()->get(EventTypeMapper::class),
            $this->getMapperFactory()->get(\Calendar_Api_Config_Mapper::class),
            new ModuleChecker($this->getConfig()),
            $this->getMapperFactory()->get(EventMapper::class),
            $this->getMapperFactory()->get(UserMapper::class),
        );
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @return \Schoolbox\Mapper
     */
    private function getUserMapper(): \Schoolbox\Mapper
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }
}
