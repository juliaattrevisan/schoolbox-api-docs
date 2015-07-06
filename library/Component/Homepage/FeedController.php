<?php
use Schoolbox\Feed\Feed;
use Schoolbox\Feed\StandardFeed;
use Schoolbox\User;

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_FeedController extends Component_Homepage_ControllerAbstract
{

    /**
     * Component name
     *
     * @var string
     */
    protected $componentName = 'Homepage Feed';

    /**
     * @var Feed
     */
    protected $feed;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute()
    {

        /** @var User $user */
        $user = $_SESSION['user'];

        $conf = \Schoolbox\Config\ConfigMapper::getInstance();

        $this->feed = new StandardFeed($conf, $this->getContext()->getId(), $user->getRole()->getType(), $this->getConfig('size'));
    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'feed')) {
            return false;
        }
        $this->parseConfig();

        $this->setVar('USER_TOKEN', $_SESSION['key']);

        $this->setBlock($templateHandle, 'event', 'event_block');

        if (!$this->feed->count()) {
            $this->setVar('event_block', '');
            return true;
        }

        $j = 0;
        foreach ($this->feed->renderAsFeedArray() as $n) {
            $date = new Alaress_Date_Format($n['time'], 'U');
            $this->setVar(
                array(
                    'EVENT_HREF'     => $n['href'],
                    'EVENT_ICON'     => $n['icon'],
                    'EVENT_BODY'     => $n['title'],
                    'EVENT_TIME'     => $date->getShort(),
                    'EVENT_LONGTIME' => $date->getLong(),
                    'EVENT_DARK'     => $j++ % 2 ? 'dark' : '',
                )
            );
            $this->parse('event_block', 'event', true);
        }

        return true;
    }
}
