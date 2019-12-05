<?php

namespace Schoolbox\Admin\FeatureTours;

use Alaress\Web\Controller\Base;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Tour;

class Controller extends Base
{
    /**
     * @var Tour\Mapper
     */
    private $tourMapper;

    /**
     * Controller constructor.
     *
     * @param Tour\Mapper $tourMapper
     */
    public function __construct(Tour\Mapper $tourMapper)
    {
        $this->tourMapper = $tourMapper;
    }

    /**
     *  Renders the feature tours admin page
     *
     *  @api   GET /adminv2/feature-tours
     */
    public function render()
    {
        $twigData = [
            'toursEnabled' => $this->getConfigLoader()->get('tours_enabled'),
            'pageCurrent' => 'feature-tours',
            'tours' => $this->tourMapper->findAll()
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
        // Update global config
        /** @var Config $config */
        $config = ConfigMapper::getInstance()->findOneByColumn("name", "tours_enabled");

        if ($this->getRequest()->post['enabled']) {
            $config->setValue("1");
        } else {
            $config->setValue("0");
        }

        ConfigMapper::getInstance()->save($config);

        // Save active states for tours
        $uuids = [];
        if (isset($this->getRequest()->post['tour-enable'])) {
            foreach ($this->getRequest()->post['tour-enable'] as $tourID) {
                $uuids[] = $tourID;
            }
        }

        $this->tourMapper->updateActiveTours($uuids);

        // Redirect back to the same page
        $this->getResponse()->redirect->afterPost($this->getRouteIfAuthorized('featureTours.render'));
    }
}
