<?php
namespace Schoolbox\Admin\FeatureTours;

use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;

class Controller extends Base
{
    /**
     * Load $templateName
     */
    public function render()
    {
        // If you Experimented earlier, this will be familiar

        $twigData = [
            'toursEnabled' => ConfigMapper::getInstance()->get('enable_feature_tours')
        ];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput("@featureTours/index.twig", $twigData)
        );
    }
}