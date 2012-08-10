<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Homepage_FeedController extends Component_Homepage_ControllerAbstract {

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {

    }

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'feed')) {
            return false;
        }
        $this->parseConfig();

        //output feed
        $this->setBlock($templateHandle, 'feed', 'feed_block');

        $feedHandler = new Feed;

        $feedList = $feedHandler->getFeed($this->getContext('id'), $this->getConfig('size'));

        if (count($feedList) > 0) {
            $j = 0;
            foreach ($feedList as $feed) {
                $feedTime = new Alaress_Date_Relative($feed['date']);
                $feedTime->setPrefix('');

                $this->setVar(array(
                    'FEED_MESSAGE' => $feed['messageHTML'],
                    'FEED_DATE' => $feedTime->getRelativeTime(),
                    'ON_OFF' => ++$j % 2 ? '' : 'dark'
                ));

                $this->parse('feed_block', 'feed', true);
            }
        }
        else {
            $this->setVar('feed_block', '');
        }

        return true;
    }

}
