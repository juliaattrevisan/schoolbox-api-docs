<?php
/**
 * Module definition for pastoral actions
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Pastoral;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Pastoral\Authorizer\Rule\IsInAudience;
use Schoolbox\Pastoral\Authorizer\Rule\IsOwner;
use Schoolbox\Pastoral\Authorizer\Rule\IsParentAccessAllowed;
use Schoolbox\Pastoral\Authorizer\Rule\IsParentOfStudent;
use Schoolbox\Pastoral\Authorizer\Rule\IsPastoralModerator;
use Schoolbox\Pastoral\Authorizer\Rule\IsPastoralModeratorWrite;
use Schoolbox\Role;
use Twig_Loader_Chain;

class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'pastoral';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $routes = [
            'GET' => [
                '/report-builder' => 'getReportBuilder',
                '/report' => 'getReport',
                '/report-print' => 'getReportPrint',
                '/insert/{id}' => 'getInsert',
                '/update/{id}' => 'getUpdate',
                '/{studentId}/{id}' => 'getRecord',
                '/{id}' => 'getIndex',
            ],
            'POST' => [
                '/save' => 'postSave',
                '/comment' => 'postComment',
                '/comment-delete' => 'postCommentDelete',
                '/delete' => 'postDelete',
            ]
        ];

        foreach ($routes['GET'] as $path => $alias) {
            $this->addGetRoute($router, $alias, $basePath, $path);
        }

        foreach ($routes['POST'] as $path => $alias) {
            $this->addPostRoute($router, $alias, $basePath, $path);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $permissionViewRecord = new Rule\All(
        // Must be logged in
            new Rule\IsLoggedIn(),

            // And either...
            new Rule\Any
            (
            // Superusers and moderators get a free pass
                new Rule\Any
                (
                    new Rule\IsSuperuser(),
                    new IsPastoralModerator()
                ),

                // Otherwise...
                new Rule\Any(

                // Staff members that are in the audience
                    new Rule\All
                    (
                        new IsInAudience(),
                        new Rule\HasRole(Role::STAFF)
                    ),

                    // Or parents of the child in the audience (and parent access is allowed in Schoolbox config)
                    new Rule\All
                    (
                        new Rule\HasRole(Role::PARENT),
                        new IsParentAccessAllowed(),
                        new IsParentOfStudent(),
                        new IsInAudience()
                    )
                )
            )
        );

        $this->addRoutePermission($authorizer, 'getRecord', $permissionViewRecord);

        // Only staff can create reports, insert records, or post comments

        $permissionStaff = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any
            (
                new Rule\HasRole(Role::STAFF)
            )
        );

        $this->addRoutePermission($authorizer,
            'getReportBuilder|getReport|getReportPrint|getInsert|postComment|postCommentDelete', $permissionStaff);

        // Only staff, or parents of the student can view a students pastoral care record list page

        $permissionIndex = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any
            (
                new Rule\HasRole(Role::STAFF),
                new Rule\All
                (
                    new Rule\HasRole(Role::PARENT),
                    new IsParentOfStudent(),
                    new IsParentAccessAllowed()
                )
            )
        );

        $this->addRoutePermission($authorizer, 'getIndex', $permissionIndex);

        // Only staff that are superusers, pastoral moderators (with write perms) or the owner of a record can update it

        $permissionUpdate = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\All
            (
            // Must be a staff member
                new Rule\HasRole(Role::STAFF),

                // And either superuser, moderator or owner of record
                new Rule\Any
                (
                    new Rule\IsSuperuser(),
                    new IsPastoralModeratorWrite(),
                    new IsOwner()
                )
            )
        );

        $this->addRoutePermission($authorizer, 'getUpdate|postSave|postDelete', $permissionUpdate);
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:'.$this->basename, $injector->lazyNew(Controller::class));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/Templates');
    }
}
