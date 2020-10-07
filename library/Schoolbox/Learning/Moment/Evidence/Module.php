<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;
use Closure;
use Schoolbox\Role;
use Schoolbox\UserMapper;

/**
 * Module for the learning moment evidence API and forms.
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningMomentEvidence';


    protected function registerRoutes()
    {
        // Permissions:
        // Current user must have permission to post to ALL user IDs provided
        // (they must be a staff member, parent of or the user themselves)
        $viewCreateFormRule = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF),
            )
        );


        // Preloaders:
        // Assume userIds is a comma-separated list of users, kind of have to in
        // order to assume I can create them
        $viewCreateFormPreloader = Closure::fromCallable([$this, 'preloadUsers']);


        // Routes to register:
        $this->register(
            self::METHOD_GET,
            '/{userIds}/create',
            'getCreateForm',
            $viewCreateFormRule,
            $viewCreateFormPreloader
        );
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * Preload the requested students.
     *
     * @param array $params
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     */
    private function preloadUsers(array $params): array
    {
        return [
            'users' => $this->getMapperFactory()
                            ->get(UserMapper::class)
                            ->findByColumn('id', explode(',', $params['userIds'])),
        ];
    }
}
