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
use Alaress\Web\Controller\Base;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Role;
use Schoolbox\Url\Factory;
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
        /** @var UserMapper $userMapper */
        $userMapper = $this->getMapperFactory()->get(UserMapper::class);

        // Various permissions
        $basePermission = new Rule\All(
            new Rule\IsConfigOn(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY, $this->getConfig()),
            new Rule\IsConfigOn(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY_SIGNING_ALGORITHM, $this->getConfig()),
            new Rule\IsConfigOn(ConfigMapper::NAME_INTELLISCHOOL_DEPLOYMENT_ID, $this->getConfig()),
            new Rule\IsConfigOn(ConfigMapper::NAME_INTELLISCHOOL_LEARNER_DASHBOARD_BASE_URL, $this->getConfig()),
            new Rule\IsConfigOn(ConfigMapper::NAME_INTELLISCHOOL_COHORT_DASHBOARD_BASE_URL, $this->getConfig()),
        );
        $staffPermission = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\HasRolePermission(Role::PERMISSION_VIEW_STUDENT_GRADES, Role::STAFF),
        );
        $parentPermission = new Rule\LazyCallback(function () use ($userMapper) {
            return new Rule\IsParentOfUser($this->getExternalUser(), $userMapper, 'studentId');
        });
        $studentPermission = new Rule\IsUser('studentId');

        // Various preloads
        $studentPreload = function ($params) use ($userMapper) {
            return [
                'student' => $userMapper->findById($params['studentId']),
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

        // Routes
        $this->register(
            'GET',
            "/student/{studentId}",
            'getStudentAnalytics',
            new Rule\All(
                $basePermission,
                new Rule\Any($staffPermission, $parentPermission, $studentPermission)
            ),
            $studentPreload,
        );
        $this->register(
            BaseModule::METHOD_GET,
            "/class/{folderId}",
            'getClassAnalytics',
            new Rule\All($basePermission, $staffPermission),
            $folderPreload,
        );
        $this->register(
            BaseModule::METHOD_GET,
            "/class/{folderId}/code/{classCode}",
            'getClassCodeAnalytics',
            new Rule\All($basePermission, $staffPermission),
            $classCodePreload,
        );
        //cohort profile
        $this->register(
            BaseModule::METHOD_GET,
            "/cohort",
            'getCohortProfile',
            new Rule\All($basePermission, $staffPermission),
        );
        //Task Adherence Dashboard
        $this->register(
            BaseModule::METHOD_GET,
            "/task",
            'getTaskDashboard',
            new Rule\All($basePermission, $staffPermission),
        );
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            new JwtFactory(
                $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY),
                $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY_SIGNING_ALGORITHM),
                $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_DEPLOYMENT_ID),
                $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_LEARNER_DASHBOARD_BASE_URL),
                $this->getConfig()->get(ConfigMapper::NAME_INTELLISCHOOL_COHORT_DASHBOARD_BASE_URL),
                (new Factory($this->getConfig()))->buildFullyQualified()->toString()
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
