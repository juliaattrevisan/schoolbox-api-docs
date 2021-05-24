<?php

/**
 * Module
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Stk\NCEA;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule;
use Alaress\Database\Connector\IConnector;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use External_User;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'nceaReport';

    /**
     * Report is available for these year levels
     *
     * @var array
     */
    private $availableForYears = [11, 12, 13];

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws \Schoolbox\Application\Exception\NotFound
     * @throws \InvalidArgumentException
     */
    protected function registerRoutes()
    {
        /* Anyone can see the form
         */
        $getPerms = new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\All(
                    new Rule\All(
                        new Rule\Callback(
                            function ($params, User $user) {
                                try {
                                    /** @var \Schoolbox\User $user */
                                    $student = $this->getUserMapper()->findById($params['userId']);
                                    $externalUser = $this->getExternalUser()->getStudent($student->getExternalId());
                                } catch (MapperException $e) {
                                    throw new Unauthorized('User Not Found');
                                }

                                /** @var IConnector $externalDb */
                                $externalDb = $this->getInjector()->get('databaseExternal');

                                /* Permission to NCEA Report and its button, controlled by STK
                                 */
                                $userYear = $student->getYear() ? $student->getYear()->getName() : '';

                                $availability = (new Availability($externalDb))->hasAccess((int)$userYear);

                                if (!$availability) {
                                    throw new Unauthorized('Unavailable for this student\'s year level due to OLE flag');
                                }

                                /* Explicit IB Check: IB students not allowed
                                 */
                                if ($externalUser['StudentIBFlag']) {
                                    throw new Unauthorized('NCEA Reports not available to IB Students');
                                }
                            }
                        ),
                        /* This student must be in Year 11-13 inclusive
                         */
                        new Rule\IsYearLevel($this->availableForYears)
                    ),
                    /* The Students themselves, their parents, any staff/superuser can get button and report
                     */
                    new Rule\Any(
                        new Rule\IsUserOrParentOfUser($this->getExternalUser(), $this->getUserMapper(), 'userId'),
                        new Rule\HasRole(Role::STAFF),
                        new Rule\IsSuperuser()
                    )
                )
            );
        });

        $preloadUser = function ($params) {
            return $this->preloadUser($params);
        };

        $this->register('GET', '/report/{userId}/{year}', 'getReport', $getPerms, $preloadUser);
    }

    /**
     * Preload a User instance
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws NotFound
     * @throws \Alaress\Database\Exception
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    private function preloadUser(array $params): array
    {
        try {
            /** @var \Schoolbox\User $user */
            $user = $this->getUserMapper()->findById($params['userId']);
        } catch (MapperException $e) {
            throw new NotFound('User Not Found');
        }

        /* Only works for the current year
         */
        if ((int)$params['year'] !== (int)date('Y')) {
            throw new NotFound('Invalid year');
        }

        return [
            'user' => $user,
            'externalUser' => $this->getExternalUser()->getStudent($user->getExternalId()),
            'year' => (int)$params['year'],
        ];
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
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
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
}
