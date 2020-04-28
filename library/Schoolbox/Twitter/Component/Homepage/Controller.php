<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Twitter\Component\Homepage;

use Component_Homepage_ControllerAbstract;

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
        // Which type of view are we using?
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
