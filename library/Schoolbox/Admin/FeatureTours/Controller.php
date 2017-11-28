<?php

namespace Schoolbox\Admin\FeatureTours;

use Alaress\Web\Controller\Base;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;

class Controller extends Base
{
    /**
     *  Renders the feature tours admin page
     *
     *  @api   GET /adminv2/feature-tours
     */

    public function render()
    {
        $twigData = [
            'toursEnabled' => ConfigMapper::getInstance()->get('enable_feature_tours'),
        ];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput("@featureTours/index.twig", $twigData)
        );
    }


    /**
     *  Updates the tours_enabled config
     *
     *  @api   POST /adminv2/feature-tours
     */

    public function updateConfig()
    {
        /**
         * @var Config $config
         */
        $config = ConfigMapper::getInstance()->findOneByColumn("name", "enable_feature_tours");

        if ($this->getRequest()->post['enabled']) {
            $config->setValue("1");
        } else {
            $config->setValue("0");
        }

        ConfigMapper::getInstance()->save($config);
        $this->getResponse()->redirect->afterPost($this->getRouteIfAuthorized('featureTours.render'));
    }
}