<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Twitter\Component\Homepage;

use Component_Homepage_ControllerAbstract;
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
        return $this->renderTwigTemplate(
            $templateHandle,
            Delegate::TEMPLATE,
            $delegate->buildTemplateParams()
        );
    }
}
