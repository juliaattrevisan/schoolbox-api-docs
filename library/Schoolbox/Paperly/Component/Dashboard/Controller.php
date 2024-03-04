<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Paperly\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;

class Controller extends Component_Dashboard_ControllerAbstract
{

    /**
     * Loads the grid component into its set configuration.
     * @return bool
     */
    public function execute()
    {
        return true;
    }

    /**
     * Output the data we collected in exec()
     *
     * @param  String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        return $this->renderTwigTemplate(
            $templateHandle,
            '@paperlyDashboard/component.twig',
            [
                'componentInstanceId' => $this->getInstance()->getId(),
            ]
        );
    }
}
