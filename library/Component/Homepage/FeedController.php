<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Feed\Feed;
use Schoolbox\Feed\StandardFeed;
use Schoolbox\Permission\Access\UserAccess;

class Component_Homepage_FeedController extends Component_Homepage_ControllerAbstract
{
    /**
     * @var Feed
     */
    protected $feed;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if (!$this->isSourceRenderable() && !$this->hasContextWriteAccess()) {
            return false;
        }

        $this->feed = new StandardFeed(
            $this->getConfigLoader(),
            $this->getContext()->getId(),
            $this->getUser(),
            $this->getConfig('size')
        );
        return true;
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

        $this->setBlock($templateHandle, 'has_activity', 'has_activity_block');
        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');

        if (!$this->feed->count()) {
            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                return false;
            }

            $this->setVar('has_activity_block', '');
            $this->parse('empty_state_block', 'empty_state');
        } else {
            $this->setBlock('has_activity', 'event', 'event_block');

            $this->setVar('empty_state_block', '');

            foreach ($this->feed->renderAsFeedArray() as $n) {
                $date = new Alaress_Date_Format($n['time'], 'U');
                $this->setVar(
                    [
                        'EVENT_HREF'     => $n['href'],
                        'EVENT_ICON'     => $n['icon'],
                        'EVENT_BODY'     => $n['title'],
                        'EVENT_TIME'     => $date->getShort(),
                        'EVENT_LONGTIME' => $date->getLong(),
                    ]
                );
                $this->parse('event_block', 'event', true);
            }

            $this->parse('has_activity_block', 'has_activity');
        }

        return true;
    }
}
