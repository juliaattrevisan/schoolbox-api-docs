<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Intellischool\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Intellischool\JwtFactory;
use Schoolbox\Url\Factory;

/**
 * Intellischool Dashboard Component
 */
class Controller extends Component_Dashboard_ControllerAbstract
{
    private JwtFactory $jwtFactory;

    /**
     * Loads the grid component into its set configuration.
     * @return bool
     */
    public function execute()
    {
        $key = (string)$this->getConfigLoader()->get(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY);
        $algo = (string)$this->getConfigLoader()->get(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY_SIGNING_ALGORITHM);
        $deploymentId = (string)$this->getConfigLoader()->get(ConfigMapper::NAME_INTELLISCHOOL_DEPLOYMENT_ID);
        $learnerUrl = (string)$this->getConfigLoader()->get(ConfigMapper::NAME_INTELLISCHOOL_LEARNER_DASHBOARD_BASE_URL);
        $cohortUrl = (string)$this->getConfigLoader()->get(ConfigMapper::NAME_INTELLISCHOOL_COHORT_DASHBOARD_BASE_URL);
        $jwtIssuer = (new Factory($this->getConfigLoader()))->buildFullyQualified()->toString();

        if ('' === $key || '' === $algo || '' === $deploymentId || '' === $learnerUrl || '' === $cohortUrl) {
            return false;
        }

        $this->jwtFactory = new JwtFactory(
            $key,
            $algo,
            $deploymentId,
            $learnerUrl,
            $cohortUrl,
            $jwtIssuer
        );
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        return $this->renderTwigTemplate(
            $templateHandle,
            '@intellischoolDashboard/component.twig',
            [
                'componentInstanceId'            => $this->getInstance()->getId(),
                'ltiEndpoint' => $this->jwtFactory::LTI_ENDPOINT,
                'jwt'         => $this->jwtFactory->buildForDashboardComponent($this->getUser()),
            ]
        );
    }
}
