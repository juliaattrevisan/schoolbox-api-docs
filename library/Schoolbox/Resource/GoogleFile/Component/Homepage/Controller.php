<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\GoogleFile\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Component_Instance;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\GoogleFile\Mapper;
use Schoolbox\User;
use SitePage;

class Controller extends Component_Homepage_ControllerAbstract
{

    /**
     * Has this component been properly configured?
     * Assume yes to begin with
     *
     * @var bool
     */
    private bool $hasError = false;

    private GenericCollection $googleFiles;

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
     * Decdie whether Google had all its parts provided
     *
     * @return bool
     */
    private function isGoogleEnabled(): bool
    {
        return $this->getConfigLoader()->get('googledrive_enabled')
            && $this->getConfigLoader()->get('googleapps_api_key')
            && $this->getConfigLoader()->get('googleapps_client_id');
    }


    /**
     * Loads the grid component into its set configuration.
     *
     * @return bool
     */
    public function execute()
    {
        /* If you're a WRITE access user, tell them their Google setup is misconfigured
         * Otherwise hide the component
         */
        if (!$this->hasWriteAccess()) {
            return false;
        }

        if (!$this->isGoogleEnabled()) {
            $this->hasError = true;
            return true;
        }

        $googleFileMapper = Mapper::getInstance();

        $this->googleFiles = $googleFileMapper->findByColumn('folder_id', $this->getContext()->getId());

        if ($this->googleFiles->count() === 0) {
            return $this->hasWriteAccess();
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
            'clientId'    => $this->getConfigLoader()->get('googleapps_client_id'),
            'folder'      => $this->getContext(),
            'googleFiles' => $this->googleFiles,
            'hasError'    => $this->hasError,
            'writeAccess' => $this->hasWriteAccess(),
        ];

        /* Decide whether to show Component Title using showComponentTitle
         * Inverted from "Hide headings from readonly users" via compactMode
         */
        $renderHeader = $this->getConfig('showComponentTitle') || $this->hasContextWriteAccess();

        return $this->renderTwigTemplate($templateHandle, $twigTemplate, $twigData, $renderHeader);
    }
}
