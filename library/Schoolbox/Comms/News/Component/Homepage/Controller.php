<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Comms\News\Component\Homepage;

use Component_Config_Size;
use Component_Homepage_ControllerAbstract;
use Schoolbox\Comms\News;
use Schoolbox\Permission\Access\UserAccess;

class Controller extends Component_Homepage_ControllerAbstract
{
    // Max number of articles to show if 'size' config is set to 'ALL'
    private const HARD_LIMIT = 50;

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function renderTemplate($templateHandle): bool
    {
        $limit = Component_Config_Size::SIZE_ALL === (int)$this->getConfig('size')
            ? self::HARD_LIMIT
            : (int)$this->getConfig('size');

        return $this->renderTwigTemplate($templateHandle, '@commsNewsHomepage/component.twig', [
            'componentInstanceId' => $this->getInstance()->getId(),
            'compactMode' => $this->getConfig('compactMode'),
            'limit' => $limit,
            'folder' => $this->getSource(),
            'key' => $this->getUser()->getKey(),
            'referrer' => News\Statistics\Mapper::REFERRER_HOMEPAGE,
            'readonly' => $this->getContextPermission() < UserAccess::ACCESS_WRITE
        ]);
    }
}
