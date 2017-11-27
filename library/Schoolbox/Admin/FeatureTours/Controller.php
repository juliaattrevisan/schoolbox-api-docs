<?php
namespace Schoolbox\Admin\FeatureTours;

use Alaress\Web\Controller\Base;

class Controller extends Base
{
    /**
     * Load $templateName
     */
    public function render()
    {
        // If you Experimented earlier, this will be familiar

        $twigData = [];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput("@featureTours/index.twig", $twigData)
        );
    }
}