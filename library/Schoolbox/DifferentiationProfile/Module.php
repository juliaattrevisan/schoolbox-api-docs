<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\DifferentiationProfile;

use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Rule;
use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use http\Exception\UnexpectedValueException;
use Schoolbox\Pastoral\Comment\Translator\ModelToArray as PastoralCommentTranslator;
use Schoolbox\Pastoral\ReportBuilder\Translator\ModelToArray as PastoralTranslator;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\DifferentiationProfile\Mapper as DifferentiationProfileMapper;
use Schoolbox\DifferentiationProfile\Translator\ModelToArray;
use Schoolbox\DifferentiationProfile\AdjustmentCategories\Mapper as AdjustmentCategoriesMapper;
use Schoolbox\DifferentiationProfile\AdjustmentLevels\Mapper as AdjustmentLevelsMapper;
use Schoolbox\Admin\DifferentiationProfiles\Translator\ModelToArray as AdminConfigModelToArray;
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
        /* Only logged in users with the differentiation profile service permission can view the form
         */
        $getPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\HasServicePermission(
                UserAccess::SERVICE_DIFFERENTIATION_PROFILE,
                UserAccess::ACCESS_WRITE
            )
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
            $getPerms,
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
