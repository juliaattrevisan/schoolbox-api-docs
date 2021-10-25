<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Intellischool;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Role;
use Schoolbox\UserMapper;

/**
 * Module for the Intellischool integration.
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'intellischool';

    /**
     * @inheritdoc
     */
    public function registerRoutes()
    {
        $basePermission = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\HasRolePermission(Role::PERMISSION_VIEW_STUDENT_GRADES, Role::STAFF),
        );
        $studentPreload = function ($params) {
            return [
                'student' => $this->getMapperFactory()->get(UserMapper::class)->findById($params['studentId']),
            ];
        };
        $folderPreload = function ($params) {
            return [
                'folder' => $this->getMapperFactory()->get(FolderMapper::class)->findById($params['folderId']),
            ];
        };
        $classCodePreload = function ($params) use ($folderPreload) {
            $extraParams = $folderPreload($params);
            if (!$extraParams['folder'] instanceof FolderModel) {
                throw new NotFound();
            }
            foreach ($extraParams['folder']->getCodes() as $code) {
                if ($code->getCode() === $params['classCode']) {
                    return $extraParams;
                }
            }
            throw new NotFound();
        };
        $this->register(
            'GET',
            "/student/{studentId}",
            'getStudentDashboard',
            $basePermission,
            $studentPreload,
        );
        $this->register(
            BaseModule::METHOD_GET,
            "/class/{folderId}",
            'getBaseClassDashboard',
            $basePermission,
            $folderPreload,
        );
        $this->register(
            BaseModule::METHOD_GET,
            "/class/{folderId}/code/{classCode}",
            'getClassDashboard',
            $basePermission,
            $classCodePreload,
        );
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        /** @var Container $injector */
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'jwtFactory' => $injector->lazy(function () {
                        return new JwtFactory(
                            $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY),
                            $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY_SIGNING_ALGORITHM),
                            $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_DEPLOYMENT_ID),
                            $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_LEARNER_DASHBOARD_BASE_URL),
                            $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_COHORT_DASHBOARD_BASE_URL),
                        );
                    }),
                ]
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
