<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Intellischool\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Intellischool\JwtFactory;
use Schoolbox\Url\Factory;

/**
 * Intellischool Homepage Component
 */
class Controller extends Component_Homepage_ControllerAbstract
{
    private ?JwtFactory $jwtFactory = null;

    /**
     * Has this component been properly configured?
     * Assume yes to begin with
     *
     * @var bool
     */
    private bool $hasError = false;

    /**
     * Decide whether intellischool JWT actually has all its required parts
     *
     * @return bool
     */
    private function isIntellischoolEnabled(): bool
    {
        if (
            '' === (string)$this->getConfigLoader()->get(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY) ||
            '' === (string)$this->getConfigLoader()->get(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY_SIGNING_ALGORITHM) ||
            '' === (string)$this->getConfigLoader()->get(ConfigMapper::NAME_INTELLISCHOOL_DEPLOYMENT_ID) ||
            '' === (string)$this->getConfigLoader()->get(ConfigMapper::NAME_INTELLISCHOOL_LEARNER_DASHBOARD_BASE_URL) ||
            '' === (string)$this->getConfigLoader()->get(ConfigMapper::NAME_INTELLISCHOOL_COHORT_DASHBOARD_BASE_URL)
        ) {
            return false;
        } else {
            return true;
        }
    }
    /**
     * Loads the grid component into its set configuration.
     * @return bool
     */
    public function execute()
    {
        if (!$this->isIntellischoolEnabled()) {
            $this->hasError = true;
            return $this->hasWriteAccess();
        }

        $key = (string)$this->getConfigLoader()->get(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY);
        $algo = (string)$this->getConfigLoader()->get(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY_SIGNING_ALGORITHM);
        $deploymentId = (string)$this->getConfigLoader()->get(ConfigMapper::NAME_INTELLISCHOOL_DEPLOYMENT_ID);
        $learnerUrl = (string)$this->getConfigLoader()->get(ConfigMapper::NAME_INTELLISCHOOL_LEARNER_DASHBOARD_BASE_URL);
        $cohortUrl = (string)$this->getConfigLoader()->get(ConfigMapper::NAME_INTELLISCHOOL_COHORT_DASHBOARD_BASE_URL);
        $jwtIssuer = (new Factory($this->getConfigLoader()))->buildFullyQualified()->toString();

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
        $jwt = (null !== $this->jwtFactory) ? $this->jwtFactory->buildForDashboardComponent($this->getUser()) : null;
        $data = [
            'componentInstanceId'   => $this->getInstance()->getId(),
            'ltiEndpoint'           => JwtFactory::LTI_ENDPOINT,
            'hasError'              => $this->hasError,
            'jwt'                   => $jwt,
        ];
        /* Decide whether to show Component Title using showComponentTitle
         * Inverted from "Hide headings from readonly users" via compactMode
         */
        $renderHeader = $this->getConfig('showComponentTitle') || $this->hasContextWriteAccess();

        return $this->renderTwigTemplate(
            $templateHandle,
            '@intellischoolHomepage/component.twig',
            $data,
            $renderHeader
        );
    }
}
