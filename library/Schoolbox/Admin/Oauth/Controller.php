<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Oauth;

use Alaress\Database\Predicate;
use Alaress\Database\Quoter\Mysql\Quoter;
use Alaress\Web\Controller\ConfigController;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\ConfigMapper;

class Controller extends ConfigController
{

    /** @var Quoter */
    private $quoter;


    /**
     * @param ConfigMapper         $configMapper
     * @param FieldStrategyFactory $fieldStrategyFactory
     * @param Quoter               $quoter
     */
    public function __construct(ConfigMapper $configMapper, FieldStrategyFactory $fieldStrategyFactory, Quoter $quoter)
    {
        parent::__construct($configMapper, $fieldStrategyFactory);

        $this->quoter = $quoter;
    }


    /**
     * Renders the module admin page
     *
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @api   GET /adminv2/oauth
     * @since 190
     */
    public function getAdmin(): void
    {
        $configContext = $this->buildConfigContext(
            $this->buildConfigPredicate(),
            $this->getRouter()->generate('adminOauth.postAdmin'),
        );

        $data = [
            'pageCurrent' => $this->router->generate('adminOauth.postAdmin'),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminOauth.postAdmin')),
            'configContext' => $configContext,
        ];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@adminOauth/admin.twig',
                $data,
                "oAuth Provider Configuration"
            )
        );
    }


    /**
     * Updates admin items available on the module admin page
     *
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     * @throws Predicate\InvalidColumnException
     * @api   POST /adminv2/oauth
     * @since 190
     */
    public function postAdmin(): void
    {
        $this->updateConfig($this->request->post->get(), $this->buildConfigPredicate());
        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('adminOauth.getAdmin'));
    }


    /**
     * Returns a predicate builder for finding all custom config for this module
     *
     * @return \Alaress\Database\Predicate\Builder
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    private function buildConfigPredicate(): Predicate\Builder
    {
        return $this->getConfigMapper()
                    ->getPredicateBuilder()
                    ->like(
                        ConfigMapper::COL_NAME,
                        $this->quoter->escapeForLike(ConfigMapper::PREFIX_CLOUDWORK_OAUTH) . "%"
                    )
                    ->equals(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_OAUTH);
    }
}
