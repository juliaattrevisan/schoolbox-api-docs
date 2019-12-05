<?php
/**
 * Marketo callback for Schoolbox Help
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Custom\Help\Marketo\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;

class Controller extends Component_Dashboard_ControllerAbstract
{
    /**
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        return $this->getConfigLoader()->get('site_location') === 'help';
    }

    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        // Retrieve key and client ID from component config

        $marketoClientId = $this->getConfig('description');
        $marketoKey = $this->getConfig('componentname');

        // Find an email address for the user...
        //
        //  - Try using $user->getEmail()
        //  - Try using $user->getAltEmail()

        if (!empty($this->getUser()->getEmail())) {
            $email = $this->getUser()->getEmail();
        } elseif (!empty($this->getUser()->getAltEmail())) {
            $email = $this->getUser()->getAltEmail();
        } else {
            $email = '';
        }

        // Create hash

        $marketoHash = sha1($marketoKey . $this->getUser()->getEmail());

        // Render JS

        return $this->renderTwigTemplate(
            $templateHandle,
            '@marketoDashboard/component.twig',
            [
                'marketoClientId'   => $marketoClientId,
                'marketoHash'       => $marketoHash,
                'email'             => $email,
                'user'         => $this->getUser(),
            ],
            false,
            false,
            true
        );
    }
}
