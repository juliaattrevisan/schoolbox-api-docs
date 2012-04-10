<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Component_Dashboard_HomepageNewsController extends Component_Dashboard_ControllerAbstract {

    private $feedList;
    private $limit = 20;

    public function execute () {
        $feedHandler = new Feed();
        $groupHandler = new Group();
        $userAccess = $groupHandler->getAllUserAccess($_SESSION['id']);

        if(is_array($userAccess) ){
            $categoryIDs = array();

            foreach($userAccess as $ua)
                $categoryIDs[] = $ua['sid'];

            $this->feedList = $feedHandler->getFeed($categoryIDs, $this->limit);

            return true;
        }
    }

    public function renderTemplate ($templateHandle) {
        if ( !$this->loadTemplate($templateHandle, 'homepageNews') ) {
            return false;
        }

        $this->setBlock($templateHandle, 'feed', 'feed_block');

        if (count($this->feedList) > 0) {
            $j = 0;
            foreach ($this->feedList as $feed) {
                $feedTime = new Alaress_Date_Relative($feed['date']);
                $feedTime->setPrefix('');

                $catName = empty($feed['cat_name']) ? '' : ' in <a href="/homepage/' . $feed['cat_id'] . '">' . $feed['cat_name'] . '</a>';

                $this->setVar(array(
                    'FEED_MESSAGE' => $feed['messageHTML'] . $catName,
                    'FEED_DATE' => $feedTime->getRelativeTime(),
                    'ON_OFF' => ++$j % 2 ? '' : 'dark'
                ));

                $this->parse('feed_block', 'feed', true);
            }
        }
        else {
            $this->setVar('feed_block', '');
        }

    }

    /**
     * @return Feed
     */
    public function getFeedHandler() {
        return $this->feedHandler;
    }
}