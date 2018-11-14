<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Admin;

use Alaress\Web\Controller\ConfigController;
use Schoolbox\Config\ConfigMapper;
use Alaress\Database\Predicate;

class Controller extends ConfigController
{
    /**
     * Renders the module admin page
     *
     * @api   GET /adminv2/custom/tigs/summative
     * @level 18.1.0
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getAdmin(): void
    {
        $configContext = $this->buildConfigContext(
            $this->buildConfigPredicate(),
            $this->getRouter()->generate('tigsSummativeAdmin.postAdmin')
        );

        $data = ['configContext' => $configContext,];

        $this->getResponse()->content->set($this->buildHtmlOutput('@tigsSummativeAdmin/admin.twig', $data, "TIGS Summative Reporting Admin"));
    }


    /**
     * Updates admin items available on the module admin page
     *
     * @api   POST /adminv2/custom/tigs/summative
     * @level 18.1.0
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
                    ->like(ConfigMapper::COL_NAME, ConfigMapper::PREFIX_TIGS_CRP . "%")
                    ->equals(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_CUSTOM);
    }
}
