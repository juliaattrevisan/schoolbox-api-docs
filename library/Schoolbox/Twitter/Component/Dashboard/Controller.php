<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Twitter\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;

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
        return $this->renderTwigTemplate(
            $templateHandle,
            '@twitter/component.twig',
            [
                'componentId'  => $this->getInstance()->getId(),
                'username'     => $this->getConfig('username'),
                'keywords'     => $this->getConfig('keywords'),
                'listSlug'     => $this->getConfig('listSlug'),
                'collectionId' => $this->getConfig('collectionId'),
                'limit'        => $this->getConfig('limit'),
            ]
        );
    }
}
