<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Twitter\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;
use Schoolbox\Twitter\Component\Delegate;

/**
 * Dashboard component for Twitter feeds
 */
class Controller extends Component_Dashboard_ControllerAbstract
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

        // Don't show component at all if it's empty
        if ($delegate->isEmptyState()) {
            return false;
        }

        return $this->renderTwigTemplate(
            $templateHandle,
            $delegate->determineTemplate(),
            $delegate->buildTemplateParams()
        );
    }
}
