<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;
use Closure;
use Schoolbox\Role;
use Schoolbox\UserMapper;

/**
 * Module for badge requests.
 */
class Module extends BaseModule
{
    protected $basename = 'learningBadge';


    /**
     * @inheritdoc
     */
    public function registerRoutes(): void
    {
        $baseRule = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF),
            ),
        );

        $viewAwardedBadges = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF),
                new Rule\IsUserOrParentOfUser(
                    $this->getExternalUser(),
                    UserMapper::getInstance(),
                    'userId'
                )
            ),
        );

        $this->register(
            self::METHOD_GET,
            '',
            'getBadges',
            $baseRule
        );

        $this->register(
            self::METHOD_GET,
            '/{userId}/awarded',
            'getAwardedBadges',
            $viewAwardedBadges,
            Closure::fromCallable([$this, 'preloadUser']),
        );

        $this->register(
            self::METHOD_POST,
            '/{badgeId}/withdraw',
            'postWithdraw',
            $baseRule,
            Closure::fromCallable([$this, 'preloadBadge']),
        );

        $this->register(
            self::METHOD_GET,
            '/create',
            'getCreateForm',
            $baseRule
        );
        $this->register(
            self::METHOD_POST,
            '',
            'postBadge',
            $baseRule
        );
    }


    /**
     * Preload the requested student
     *
     * @param array $params
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     */
    private function preloadUser(array $params): array
    {
        return [
            'user' => $this->getMapperFactory()
                           ->get(UserMapper::class)
                           ->findById($params['userId']),
        ];
    }


    /**
     * Preload the requested badge
     *
     * @param array $params
     * @return array
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    private function preloadBadge(array $params): array
    {
        return [
            'badge' => $this->getMapperFactory()
                ->get(Mapper::class)
                ->findById($params['badgeId']),
        ];
    }


    /**
     * @return \External_UserInterface
     */
    protected function getExternalUser()
    {
        return $this->getInjector()->get('externalUser');
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'mapper' => $this->getMapperFactory()->getLazy(Mapper::class),
                    'awardMapper' => $this->getMapperFactory()->getLazy(Award\Mapper::class)
                ]
            )
        );
    }
}
