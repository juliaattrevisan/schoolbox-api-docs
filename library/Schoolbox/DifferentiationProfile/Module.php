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
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\DifferentiationProfile\Mapper as DifferentiationProfileMapper;
use Schoolbox\DifferentiationProfile\Translator\ModelToArray;
use Schoolbox\DifferentiationProfile\AdjustmentCategories\Mapper as AdjustmentCategoriesMapper;
use Schoolbox\DifferentiationProfile\AdjustmentLevels\Mapper as AdjustmentLevelsMapper;
use Schoolbox\Admin\DifferentiationProfiles\Translator\ModelToArray as AdminConfigModelToArray;

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

        $this->register('GET', '/manage', 'manageDifferentiationProfiles', $getPerms);
        $this->register('GET', '/list', 'getDifferentiationProfileData', $getPerms);
        $this->register(
            'GET',
            '/linkedRecords/{studentId}',
            'getLinkedPastoralRecords',
            $getPerms,
        );
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
