<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
        $limit = Component_Config_Size::SIZE_ALL === $this->getConfig('size')
            ? self::HARD_LIMIT
            : (int)$this->getConfig('size');

        return $this->renderTwigTemplate($templateHandle, '@commsNewsHomepage/component.twig', [
            'componentInstanceId' => $this->getInstance()->getId(),
            'compactMode' => $this->getConfig('compactMode'),
            'limit' => $limit,
            'folderId' => $this->getSource()->getId(),
            'key' => $this->getUser()->getKey(),
            'referrer' => News\Statistics\Mapper::REFERRER_HOMEPAGE,
            'returnUrl' => News\Querystring::encode('/homepage/' . $this->getSource()->getId()),
            'readonly' => $this->getContextPermission() < UserAccess::ACCESS_WRITE
        ]);
    }
}
