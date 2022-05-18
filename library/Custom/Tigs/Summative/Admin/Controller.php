<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative\Admin;

use Alaress\Database\Quoter\Mysql\Quoter;
use Alaress\Module\Cache\Rebuilder;
use Alaress\Web\Controller\ConfigController;
use Custom\Tigs\Summative\Config;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\ConfigMapper;
use Alaress\Database\Predicate;

class Controller extends ConfigController
{

    /** @var Quoter */
    private $quoter;


    /**
     * Constructor
     *
     * @param ConfigMapper         $configMapper
     * @param FieldStrategyFactory $fieldStrategyFactory
     * @param Rebuilder            $rebuilder
     * @param Quoter               $quoter
     */
    public function __construct(
        ConfigMapper $configMapper,
        FieldStrategyFactory $fieldStrategyFactory,
        Rebuilder $rebuilder,
        Quoter $quoter
    ) {
        parent::__construct($configMapper, $fieldStrategyFactory, $rebuilder);

        $this->quoter = $quoter;
    }


    /**
     * Renders the module admin page
     *
     * @api   GET /modules/custom/tigs/summative/admin
     * @since 18.1.0
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getAdmin(): void
    {
        $configContext = $this->buildConfigContext(
            $this->buildConfigPredicate(),
            $this->getRouter()->generate('tigsSummativeAdmin.postAdmin'),
        );

        $data = ['configContext' => $configContext,];

        $this->getResponse()->content->set($this->buildHtmlOutput('@tigsSummativeAdmin/admin.twig', $data, "TIGS Summative Reporting Admin"));
    }


    /**
     * Updates admin items available on the module admin page
     *
     * @api   POST /modules/custom/tigs/summative/admin
     * @since 18.1.0
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     * @throws Predicate\InvalidColumnException
     */
    public function postAdmin(): void
    {
        $this->updateConfig($this->request->post->get(), $this->buildConfigPredicate());
        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('tigsSummativeAdmin.getAdmin'));
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
                    ->like(ConfigMapper::COL_NAME, $this->quoter->escapeForLike(Config::PREFIX_TIGS_CRP) . "%")
                    ->equals(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_CUSTOM);
    }
}
