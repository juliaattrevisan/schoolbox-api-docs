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
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Pastoral\Comment\Translator\ModelToArray as PastoralCommentTranslator;
use Schoolbox\Pastoral\Attachment\Translator\ModelToArray as PastoralAttachmentTranslator;
use Schoolbox\Pastoral\ReportBuilder\Translator\ModelToArray as PastoralTranslator;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\DifferentiationProfile\Mapper as DifferentiationProfileMapper;
use Schoolbox\DifferentiationProfile\Translator\ModelToArray;
use Schoolbox\DifferentiationProfile\AdjustmentCategories\Mapper as AdjustmentCategoriesMapper;
use Schoolbox\DifferentiationProfile\AdjustmentLevels\Mapper as AdjustmentLevelsMapper;
use Schoolbox\Admin\DifferentiationProfiles\Translator\ModelToArray as AdminConfigModelToArray;
use Schoolbox\Resource\Storage\Translator\ModelToArray as StorageTranslator;
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

        /* Checking if the user has WRITE service permission that matches the campus of the student being accessed
         */
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

        /* Only allow access if feature is enabled, user is staff, profile exists and is populated
         */
        $viewProfilePerms = new Rule\All(
            $staffWithFeatureEnabled,
            new Rule\Callback(function (array $params): void {
                try {
                    /** @var Model $profile */
                    $profile = $this->getMapperFactory()->get(DifferentiationProfileMapper::class)
                        ->findOneByColumn('student_id', $params['studentId']);
                } catch (\Exception $e) {
                    throw new Unauthorized('Differentiation profile does not exist.', ErrorMapper::ERROR_PAGE_NOT_FOUND);
                }
                /* Profile must be populated to be viewed
                 */
                if (!$profile->isPopulated()) {
                    throw new Unauthorized('Differentiation profile is not populated.', ErrorMapper::ERROR_PAGE_NOT_FOUND);
                }
                /* Profile must not be expired to be viewed
                 */
                if ($profile->isExpired()) {
                    throw new Unauthorized('Differentiation profile is expired.', ErrorMapper::ERROR_PAGE_NOT_FOUND);
                }
            }),
        );

        /* Only superusers can receive data from the form
         */
        $postPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser()
        );

        /* Preload student for routes that require it
         */
        $preloadStudent = fn(array $params) => $this->preloadStudent($params);

        $this->register(self::METHOD_GET, '/manage', 'manageDifferentiationProfiles', $getPerms);
        $this->register(self::METHOD_GET, '/list', 'getAllDifferentiationProfileData', $getPerms);
        $this->register(
            self::METHOD_GET,
            '/linkedRecords/{studentId}',
            'getLinkedPastoralRecords',
            $getPerms,
        );

        $studentPath = '/student/{studentId}';

        $this->register(
            self::METHOD_GET,
            "$studentPath/view",
            'viewDifferentiationProfileData',
            $viewProfilePerms,
            $preloadStudent,
        );

        $this->register(
            self::METHOD_GET,
            $studentPath,
            'getDifferentiationProfileData',
            $getPerms,
            $preloadStudent,
        );

        $this->register(
            self::METHOD_GET,
            "/list/export/csv",
            'exportDifferentiationProfilesToCsv',
            $getPerms,
        );

        $this->register(
            self::METHOD_POST,
            $studentPath,
            'createDifferentiationProfileData',
            $updateProfilePerms,
            $preloadStudent,
        );

        $this->register(
            self::METHOD_PUT,
            $studentPath,
            'putDifferentiationProfileData',
            $updateProfilePerms,
            $preloadStudent,
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

        $attachmentTranslator = new PastoralAttachmentTranslator(
            $this->injector->get('router.generator'),
            $this->injector->get('authorizer'),
            $this->injector->get('user'),
            new StorageTranslator(new PrefixGenerator($this->getConfig())),
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
                $attachmentTranslator,
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
