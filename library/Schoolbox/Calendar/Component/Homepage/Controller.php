<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Schoolbox\Permission\Access\UserAccess;

/**
 * Homepage calendar component.
 */
class Controller extends Component_Homepage_ControllerAbstract
{
    /**
     * @param String $templateHandle
     *
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        return $this->renderTwigTemplate(
            $templateHandle,
            '@calendar/component/homepage.twig',
            [
                'component'      => $this,
                'user'           => $this->getUser(),
                'folder'         => $this->getContext(),
                'canCreateEvent' => ($this->getContentPermission() >= UserAccess::ACCESS_WRITE),
            ],
            !$this->getConfig('compactMode')
        );
    }
}
