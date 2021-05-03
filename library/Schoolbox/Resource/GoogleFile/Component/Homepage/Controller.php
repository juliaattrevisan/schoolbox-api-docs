<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\GoogleFile\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Component_Instance;
use Schoolbox\User;
use SitePage;

class Controller extends Component_Homepage_ControllerAbstract
{
    /**
     * Injectable constructor
     *
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     */
    public function __construct(
        Component_Instance $instance,
        User $user,
        SitePage $sitepage
    ) {
        parent::__construct($instance, $user, $sitepage);
    }

    /**
     * Loads the grid component into its set configuration.
     *
     * @return bool
     */
    public function execute()
    {
            if (!$this->getConfigLoader()->get('googledrive_enabled')
                || !$this->getConfigLoader()->get('googleapps_api_key')
                || !$this->getConfigLoader()->get('googleapps_client_id')) {
                return false;
            };

        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if (!$this->hasContextWriteAccess()) {
            return false;
        }

        return true;
    }

    /**
     * @param String $templateHandle
     *
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        $twigTemplate = '@googleFile/component/googleFiles.twig';
        $twigData     = [
            'component'   => $this,
            'writeAccess' => $this->hasWriteAccess(),
        ];

        /* Decide whether to show Component Title using showComponentTitle
         * Inverted from "Hide headings from readonly users" via compactMode
         */
        $renderHeader = $this->getConfig('showComponentTitle') || $this->hasContextWriteAccess();

        return $this->renderTwigTemplate($templateHandle, $twigTemplate, $twigData, $renderHeader);
    }
}
