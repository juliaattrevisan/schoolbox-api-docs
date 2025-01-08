<?php

namespace Schoolbox\Admin\FeatureTours;

use Alaress\Module\Cache\Rebuilder;
use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Tour;

class Controller extends Base
{
    /**
     * @var Tour\Mapper
     */
    private $tourMapper;

    private Rebuilder $rebuilder;

    /**
     * Controller constructor.
     *
     * @param Tour\Mapper $tourMapper
     * @param Rebuilder $rebuilder
     */
    public function __construct(Tour\Mapper $tourMapper, Rebuilder $rebuilder)
    {
        $this->tourMapper = $tourMapper;
        $this->rebuilder = $rebuilder;
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
            'pageCurrent' => $this->routeGenerator->generate('featureTours.render'),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('featureTours.render')),
            'tours' => $this->tourMapper->findAll(),
            'tourAudienceRoleNamesByUuid' => $this->tourMapper->getAudienceRoleNamesByUuid(true) // Note disabled tours need to be rendered.
        ];

        $this->response = $this->responseFromTemplate("@featureTours/index.twig", $twigData);
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

        if ($this->requestPost['enabled']) {
            $config->setValue("1");
        } else {
            $config->setValue("0");
        }

        ConfigMapper::getInstance()->save($config);

        // Save active states for tours
        $uuids = [];
        if (isset($this->requestPost['tour-enable'])) {
            foreach ($this->requestPost['tour-enable'] as $tourID) {
                $uuids[] = $tourID;
            }
        }

        $this->tourMapper->updateActiveTours($uuids);

        //rebuild the module cache
        $this->rebuilder->rebuild($this->getConfigLoader()->get(ConfigMapper::NAME_SITE_SCHOOL_CODE));

        // Redirect back to the same page
        $this->response = $this->redirectAfterPost($this->getRouteIfAuthorized('featureTours.render'));
    }
}
