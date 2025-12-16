<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\DifferentiationProfile;

use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule;
use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use http\Exception\UnexpectedValueException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Pastoral\Comment\Translator\ModelToArray as PastoralCommentTranslator;
use Schoolbox\Pastoral\ReportBuilder\Translator\ModelToArray as PastoralTranslator;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\DifferentiationProfile\Mapper as DifferentiationProfileMapper;
use Schoolbox\DifferentiationProfile\Translator\ModelToArray;
use Schoolbox\DifferentiationProfile\AdjustmentCategories\Mapper as AdjustmentCategoriesMapper;
use Schoolbox\DifferentiationProfile\AdjustmentLevels\Mapper as AdjustmentLevelsMapper;
use Schoolbox\Admin\DifferentiationProfiles\Translator\ModelToArray as AdminConfigModelToArray;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'differentiationProfiles';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $staffWithFeatureEnabled = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\HasRole(Role::STAFF),
            new Rule\IsConfigOn(ConfigMapper::NAME_DIFFERENTIATION_PROFILE_ENABLED, $this->getConfig()),
        );

        /* Only staff with the differentiation profile service permission can view the form
         */
        $getPerms = new Rule\All(
            $staffWithFeatureEnabled,
            new Rule\HasServicePermission(
                UserAccess::SERVICE_DIFFERENTIATION,
                UserAccess::ACCESS_WRITE
            ),
        );

        $hasCampusPerms =  new Rule\HasServicePermission(
            UserAccess::SERVICE_DIFFERENTIATION,
            UserAccess::ACCESS_WRITE,
            fn (array $params) => (
                isset($params['student'])
                && $params['student'] instanceof User
                && null !== $params['student']->getCampusCollection()
            ) ?
                $params['student']->getCampusCollection() :
                $this->getMapperFactory()
                     ->get(UserMapper::class)
                     ->findById($params['studentId'])->getCampusCollection()
        );

        $updateProfilePerms = new Rule\All(
            $getPerms,
            $hasCampusPerms
        );

        /* Only staff can view differentiation profiles when the feature is enabled
         */
        $getProfilePerms = new Rule\All(
            $staffWithFeatureEnabled,
            new Rule\Callback(function (array $params): void {
                try {
                    $this->getMapperFactory()->get(DifferentiationProfileMapper::class)
                        ->findOneByColumn('student_id', $params['studentId']);
                } catch (\Exception $e) {
                    throw new Unauthorized('Differentiation profile does not exist.', ErrorMapper::ERROR_PAGE_NOT_FOUND);
                }
            }),
        );

        /* Only superusers can receive data from the form
         */
        $postPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );

        $this->register(self::METHOD_GET, '/manage', 'manageDifferentiationProfiles', $getPerms);
        $this->register(self::METHOD_GET, '/list', 'getDifferentiationProfileData', $getPerms);
        $this->register(
            self::METHOD_GET,
            '/linkedRecords/{studentId}',
            'getLinkedPastoralRecords',
            $getPerms,
        );
        $this->register(
            self::METHOD_POST,
            '/edit/{studentId}',
            'postDifferentiationProfileData',
            $updateProfilePerms,
            \Closure::fromCallable([$this, 'preloadStudent'])
        );

        $this->register(
            self::METHOD_GET,
            '/view/{studentId}',
            'viewDifferentiationProfileData',
            $getProfilePerms,
            \Closure::fromCallable([$this, 'preloadStudent'])
        );
    }

    private function preloadStudent(array $params): array
    {
        if (\array_key_exists('student', $params)) {
            $student = ($params['student']);
        } else {
            try {
                $student = $this->getMapperFactory()->get(UserMapper::class)->findById($params['studentId']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                throw new NotFound('Student not found.', 404, $e);
            }
        }
        return ['student' => $student];
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        $commentTranslator = new PastoralCommentTranslator(
            new \Schoolbox\User\Translator\Author\ModelToArray(),
            $this->injector->get('router.generator'),
            $this->injector->get('authorizer'),
            $this->injector->get('user'),
            new ScrubAdapter(),
        );

        return new Controller(
            $this->injector->get('l10n'),
            $this->getMapperFactory()->get(AdjustmentCategoriesMapper::class),
            $this->getMapperFactory()->get(AdjustmentLevelsMapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Admin\DifferentiationProfiles\Mapper::class),
            new AdminConfigModelToArray(
                $this->getRouter()->getGenerator(),
                $this->getAuthorizer(),
            ),
            $this->injector->get('filters.factoryFactory'),
            $this->getMapperFactory()->get(\Schoolbox\Filter\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Filter\Selection\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\DifferentiationProfile\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Pastoral\Mapper::class),
            new PastoralTranslator(
                new \Schoolbox\User\Translator\Author\ModelToArray(),
                $commentTranslator,
                $this->getRouter()->getGenerator(),
                $this->injector->get('authorizer'),
                $this->injector->get('user'),
                new ScrubAdapter(),
            ),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
