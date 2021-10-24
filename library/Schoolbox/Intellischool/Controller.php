<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Intellischool;

use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\User;

/**
 * Controller for the Intellischool integration.
 */
class Controller extends Base
{
    /**
     * Index page for the Intellischool integration.
     *
     * @api GET /intellischool
     * @since 21.1.0
     */
    public function getIndex(): void
    {
        // Build the JWT, put it in the form
        $jwtFactory = new JwtFactory(
            $this->getConfigLoader()->get(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY),
            $this->getConfigLoader()->get(ConfigMapper::NAME_INTELLISCHOOL_JWT_PRIVATE_KEY_SIGNING_ALGORITHM),
            $this->getConfigLoader()->get(ConfigMapper::NAME_INTELLISCHOOL_DEPLOYMENT_ID),
            $this->getConfigLoader()->get(ConfigMapper::NAME_INTELLISCHOOL_LEARNER_DASHBOARD_BASE_URL),
            $this->getConfigLoader()->get(ConfigMapper::NAME_INTELLISCHOOL_COHORT_DASHBOARD_BASE_URL),
        );

        $this->response->content->set(
            $this->buildHtmlOutput(
                '@intellischool/index.twig',
                [
                    'idToken' => $jwtFactory->buildForLearnerProfile(
                        $this->getUser(),
                        (new User())->setExternalId('1014'),
                    )
                ],
            )
        );
    }
}
