<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Component_Instance;
use Schoolbox\Collection;
use Schoolbox\Comms\News;
use Schoolbox\News\Topic;
use Schoolbox\News\TopicMapper;
use Schoolbox\User;
use SitePage;
use Schoolbox\Permission\Access\UserAccess;

class Controller extends Component_Homepage_ControllerAbstract
{
    /**
     * Render the component's template
     *
     * @param string $templateHandle
     * @return bool
     * @throws \Exception
     */
    public function renderTemplate($templateHandle): bool
    {
        return $this->renderTwigTemplate($templateHandle, '@commsNewsHomepage/component.twig', [
            'componentInstanceId' => $this->getInstance()->getId(),
            'compactMode' => $this->getConfig('compactMode'),
            'limit' => (int)$this->getConfig('size'),
            'folderId' => $this->getSource()->getId(),
            'key' => $this->getUser()->getKey(),
            'referrer' => News\Statistics\Mapper::REFERRER_HOMEPAGE,
            'readonly' => $this->getContextPermission() < UserAccess::ACCESS_WRITE
        ]);
    }
}
