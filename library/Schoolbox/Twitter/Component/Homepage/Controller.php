<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Twitter\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Twitter\Component\Delegate;

/**
 * Homepage component for Twitter feeds
 */
class Controller extends Component_Homepage_ControllerAbstract
{
    /**
     * @inheritDoc
     */
    public function execute()
    {
        return true;
    }


    /**
     * @inheritDoc
     */
    public function renderTemplate($templateHandle)
    {
        $delegate = new Delegate($this);

        // Don't show component to read-only users if it's empty
        if ($delegate->isEmptyState() && $this->getContentPermission() < UserAccess::ACCESS_WRITE) {
            return false;
        }

        return $this->renderTwigTemplate(
            $templateHandle,
            $delegate->determineTemplate(),
            $delegate->buildTemplateParams()
        );
    }
}
