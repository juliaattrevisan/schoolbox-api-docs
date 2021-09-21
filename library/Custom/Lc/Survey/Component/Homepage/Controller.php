<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Custom\Lc\Survey\Component\Homepage;

use Component_Homepage_ControllerAbstract;

/**
 */
class Controller extends Component_Homepage_ControllerAbstract
{
    /**
     * {@inheritDoc}
     *
     * @return bool
     */
    public function execute()
    {
        if (!$this->getUser()->getRole()->isStaff()) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        if (!$this->getUser()->getRole()->isStaff()) {
            return false;
        }

        // Render this mess out

        return $this->renderTwigTemplate($templateHandle, '@surveyHomepage/component.twig', [
            'folderId' => $this->getContext()->getId()
        ]);
    }
}
